<?php
/**
 * GitHub Contents API クライアント（CMS用）
 * - 認証: fine-grained PAT (Contents: Read & Write 権限)
 * - 単一ファイルの CRUD のみ使用
 *   - GET    /repos/{owner}/{repo}/contents/{path}?ref={branch}
 *   - PUT    /repos/{owner}/{repo}/contents/{path}   (create/update, with sha)
 *   - DELETE /repos/{owner}/{repo}/contents/{path}   (with sha)
 */

require_once __DIR__ . '/cms-config.php';

define('GITHUB_API_BASE', 'https://api.github.com');
define('GITHUB_API_VERSION', '2022-11-28');

/**
 * Markdown のフロントマター + 本文をパース (CMS共通)
 * - frontmatter: ---で囲まれた key: value 行を連想配列で抽出
 * - body: --- の下の本文 (トリム済み)
 */
if (!function_exists('cmsParseMd')) {
    function cmsParseMd($content) {
        if (preg_match('/^---\r?\n(.*?)\r?\n---\r?\n?(.*)/s', $content, $m)) {
            $fm = [];
            foreach (explode("\n", trim($m[1])) as $line) {
                $pos = strpos($line, ':');
                if ($pos !== false) {
                    $k = trim(substr($line, 0, $pos));
                    $v = trim(substr($line, $pos + 1));
                    $fm[$k] = preg_replace('/^"(.*)"$/', '$1', $v);
                }
            }
            return ['frontmatter' => $fm, 'body' => trim($m[2])];
        }
        return ['frontmatter' => [], 'body' => trim($content)];
    }
}

/**
 * フロントマター連想配列 + 本文 → Markdown 文字列 (CMS共通)
 */
if (!function_exists('cmsStringifyMd')) {
    function cmsStringifyMd($fm, $body) {
        $out = "---\n";
        foreach ($fm as $k => $v) {
            $v = str_replace(["\r\n", "\r", "\n", '"'], [' ', ' ', ' ', '\\"'], (string)$v);
            $out .= "$k: \"$v\"\n";
        }
        return $out . "---\n\n" . rtrim((string)$body) . "\n";
    }
}

/**
 * GitHub API を叩く共通関数
 * @return array ['code' => int, 'body' => mixed, 'raw' => string, 'error' => string|null]
 */
function githubApiCall($method, $path, $payload = null, $token = null, $extraHeaders = []) {
    if ($token === null) {
        $cfg = getCmsConfig();
        $token = $cfg['github_token'] ?? '';
    }
    if ($token === '') {
        return ['code' => 0, 'body' => null, 'raw' => '', 'error' => 'GitHub PAT 未設定です。設定 > HP更新 設定 で登録してください'];
    }

    $url = GITHUB_API_BASE . $path;
    $ch  = curl_init($url);

    $headers = array_merge([
        'Accept: application/vnd.github+json',
        'Authorization: Bearer ' . $token,
        'X-GitHub-Api-Version: ' . GITHUB_API_VERSION,
        'User-Agent: yamato-mgt-cms',
    ], $extraHeaders);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['code' => 0, 'body' => null, 'raw' => '', 'error' => 'cURL エラー: ' . $err];
    }

    $body = json_decode($raw, true);
    $error = null;
    if ($code >= 400) {
        $msg = is_array($body) && isset($body['message']) ? $body['message'] : ('HTTP ' . $code);
        $error = $msg;
    }
    return ['code' => $code, 'body' => $body, 'raw' => $raw, 'error' => $error];
}

/**
 * 接続テスト（リポジトリ存在 & トークン権限を検証）
 * @return array ['ok' => bool, 'message' => string, 'detail' => array|null]
 */
function githubTestConnection($token, $repo, $branch) {
    if (!$token || !$repo || strpos($repo, '/') === false) {
        return ['ok' => false, 'message' => 'PAT・リポジトリ名(owner/repo)・ブランチを入力してください', 'detail' => null];
    }
    // ブランチを取得して、リポジトリ+ブランチ+読み権限をまとめて検証
    $r = githubApiCall('GET', "/repos/" . $repo . "/branches/" . rawurlencode($branch), null, $token);
    if ($r['code'] === 200) {
        return ['ok' => true, 'message' => '接続OK: ' . $repo . '@' . $branch, 'detail' => null];
    }
    if ($r['code'] === 401) return ['ok' => false, 'message' => 'PAT が無効か期限切れです', 'detail' => null];
    if ($r['code'] === 403) return ['ok' => false, 'message' => 'PAT の権限不足です（Contents: Read & Write が必要）', 'detail' => null];
    if ($r['code'] === 404) return ['ok' => false, 'message' => 'リポジトリ or ブランチが見つかりません: ' . $repo . '@' . $branch, 'detail' => null];
    return ['ok' => false, 'message' => 'エラー: ' . ($r['error'] ?? ('HTTP ' . $r['code'])), 'detail' => null];
}

/**
 * GraphQL で指定ディレクトリ配下の全 .md ファイルの「メタ + 本文」を 1リクエストで取得
 *
 * REST Contents API では list + 個別 get の N+1 になるが、GraphQL なら一発。
 * 100ファイル × 2KB ≒ 200KB 程度のレスポンスでも数秒で返る。
 *
 * @return array ['ok' => bool, 'items' => [{name, sha, text}, ...], 'error' => string|null]
 */
function githubListContentsWithBodies($owner, $repo, $dir, $branch) {
    $expr = $branch . ':' . ltrim($dir, '/');
    $query = 'query($o:String!,$r:String!,$e:String!){
      repository(owner:$o,name:$r){
        object(expression:$e){
          ... on Tree {
            entries {
              name
              oid
              type
              object {
                ... on Blob { text byteSize isBinary }
              }
            }
          }
        }
      }
    }';
    $payload = ['query' => $query, 'variables' => ['o' => $owner, 'r' => $repo, 'e' => $expr]];

    // GraphQL は /graphql POST 固定。既存の githubApiCall はパス指定なので使い回せる。
    $r = githubApiCall('POST', '/graphql', $payload);
    if ($r['code'] !== 200) {
        return ['ok' => false, 'items' => [], 'error' => $r['error'] ?? ('HTTP ' . $r['code'])];
    }
    // GraphQL は HTTP 200 でも body['errors'] にエラーを入れることがある
    if (!empty($r['body']['errors'])) {
        $msg = $r['body']['errors'][0]['message'] ?? 'GraphQL error';
        return ['ok' => false, 'items' => [], 'error' => $msg];
    }

    $entries = $r['body']['data']['repository']['object']['entries'] ?? [];
    $items = [];
    foreach ($entries as $e) {
        if (($e['type'] ?? '') !== 'blob') continue;
        if (!preg_match('/\.md$/', $e['name'] ?? '')) continue;
        if (!empty($e['object']['isBinary'])) continue;
        $items[] = [
            'name' => $e['name'],
            'sha'  => $e['oid'] ?? '',
            'text' => $e['object']['text'] ?? '',
        ];
    }
    return ['ok' => true, 'items' => $items, 'error' => null];
}

/**
 * 指定ディレクトリ配下のファイル一覧を取得
 * @return array ['ok' => bool, 'items' => array, 'error' => string|null]
 *   items: [{name, path, sha, size, ...}, ...]
 */
function githubListContents($owner, $repo, $dir, $branch) {
    $path = '/repos/' . $owner . '/' . $repo . '/contents/' . ltrim($dir, '/') . '?ref=' . rawurlencode($branch);
    $r = githubApiCall('GET', $path);
    if ($r['code'] === 200 && is_array($r['body'])) {
        return ['ok' => true, 'items' => $r['body'], 'error' => null];
    }
    if ($r['code'] === 404) {
        return ['ok' => true, 'items' => [], 'error' => null]; // 空フォルダ扱い
    }
    return ['ok' => false, 'items' => [], 'error' => $r['error'] ?? ('HTTP ' . $r['code'])];
}

/**
 * 単一ファイルの内容と SHA を取得
 * @return array ['ok' => bool, 'content' => string, 'sha' => string, 'error' => string|null]
 */
function githubGetFile($owner, $repo, $path, $branch) {
    $apiPath = '/repos/' . $owner . '/' . $repo . '/contents/' . ltrim($path, '/') . '?ref=' . rawurlencode($branch);
    $r = githubApiCall('GET', $apiPath);
    if ($r['code'] === 200 && is_array($r['body']) && isset($r['body']['content'])) {
        $content = base64_decode(str_replace("\n", '', $r['body']['content']));
        return ['ok' => true, 'content' => $content, 'sha' => $r['body']['sha'] ?? '', 'error' => null];
    }
    if ($r['code'] === 404) {
        return ['ok' => false, 'content' => '', 'sha' => '', 'error' => 'ファイルが見つかりません'];
    }
    return ['ok' => false, 'content' => '', 'sha' => '', 'error' => $r['error'] ?? ('HTTP ' . $r['code'])];
}

/**
 * ファイルを作成または更新（PUT contents）
 * - 新規作成: $sha は空
 * - 更新: 既存ファイルの $sha が必須（コンフリクト検知用）
 *
 * @return array ['ok' => bool, 'sha' => string, 'commit' => array|null, 'error' => string|null]
 */
function githubPutFile($owner, $repo, $path, $branch, $content, $message, $sha = '', $committer = null) {
    $payload = [
        'message' => $message,
        'content' => base64_encode($content),
        'branch'  => $branch,
    ];
    if ($sha !== '') $payload['sha'] = $sha;
    if (is_array($committer) && !empty($committer['name']) && !empty($committer['email'])) {
        $payload['committer'] = ['name' => $committer['name'], 'email' => $committer['email']];
    }

    $apiPath = '/repos/' . $owner . '/' . $repo . '/contents/' . ltrim($path, '/');
    $r = githubApiCall('PUT', $apiPath, $payload);

    if (($r['code'] === 200 || $r['code'] === 201) && is_array($r['body'])) {
        return [
            'ok'     => true,
            'sha'    => $r['body']['content']['sha'] ?? '',
            'commit' => $r['body']['commit'] ?? null,
            'error'  => null,
        ];
    }
    return ['ok' => false, 'sha' => '', 'commit' => null, 'error' => $r['error'] ?? ('HTTP ' . $r['code'])];
}

// ====================================================================
// 一覧キャッシュ (cache/cms-news-list-<md5>.json)
// ====================================================================
// 設計:
// - list アクション結果を TTL 5分で保存
// - create/update/delete 成功時に invalidate
// - キーは owner|repo|branch|dir の md5 でリポジトリ・ブランチ別に分離
// - 値は frontmatter のみ (title/date/category/id/sha)。本文は持たない
// ====================================================================

define('CMS_LIST_CACHE_TTL', 300); // 5分

function cmsListCacheFile($owner, $repo, $branch, $dir) {
    $key = md5("{$owner}|{$repo}|{$branch}|{$dir}");
    return dirname(__DIR__, 2) . "/cache/cms-news-list-{$key}.json";
}

function cmsListCacheLoad($owner, $repo, $branch, $dir, $ttl = CMS_LIST_CACHE_TTL) {
    $f = cmsListCacheFile($owner, $repo, $branch, $dir);
    if (!file_exists($f)) return null;
    if (time() - filemtime($f) > $ttl) return null;
    $j = json_decode(@file_get_contents($f), true);
    return is_array($j) ? $j : null;
}

function cmsListCacheSave($owner, $repo, $branch, $dir, $data) {
    $f = cmsListCacheFile($owner, $repo, $branch, $dir);
    $cacheDir = dirname($f);
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    @file_put_contents($f, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function cmsListCacheInvalidate($owner, $repo, $branch, $dir) {
    @unlink(cmsListCacheFile($owner, $repo, $branch, $dir));
}

/**
 * ファイルを削除（DELETE contents）
 * @return array ['ok' => bool, 'commit' => array|null, 'error' => string|null]
 */
function githubDeleteFile($owner, $repo, $path, $branch, $message, $sha, $committer = null) {
    $payload = [
        'message' => $message,
        'sha'     => $sha,
        'branch'  => $branch,
    ];
    if (is_array($committer) && !empty($committer['name']) && !empty($committer['email'])) {
        $payload['committer'] = ['name' => $committer['name'], 'email' => $committer['email']];
    }

    $apiPath = '/repos/' . $owner . '/' . $repo . '/contents/' . ltrim($path, '/');
    $r = githubApiCall('DELETE', $apiPath, $payload);

    if ($r['code'] === 200 && is_array($r['body'])) {
        return ['ok' => true, 'commit' => $r['body']['commit'] ?? null, 'error' => null];
    }
    return ['ok' => false, 'commit' => null, 'error' => $r['error'] ?? ('HTTP ' . $r['code'])];
}
