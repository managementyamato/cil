<?php
/**
 * CMS (HP更新) 設定ヘルパー
 * - GitHub Contents API による Markdown 編集の認証情報を保存
 * - PAT は encryption.php で暗号化して保存（enc: プレフィックス）
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/encryption.php';

define('CMS_CONFIG_FILE', dirname(__DIR__, 2) . '/config/cms-config.json');

/**
 * 既定値（未設定の場合のフォールバック）
 */
function cmsDefaultConfig() {
    return [
        'github_token' => '',
        'github_repo'  => '',        // 例: "YA-Naoto/ya-corporate-site"
        'github_branch'=> 'main',
        'content_dir'  => 'src/content/news',
        'categories'   => ['お知らせ', 'プレスリリース', '採用情報', 'メディア掲載'],
        'committer_name'  => 'Yamato CMS',
        'committer_email' => 'cms@yamato-mgt.com',
        'updated_at'   => null,
        'updated_by'   => null,
    ];
}

/**
 * 設定取得（PAT は復号した状態で返す）
 */
function getCmsConfig() {
    $default = cmsDefaultConfig();
    if (!file_exists(CMS_CONFIG_FILE)) return $default;

    $raw = @file_get_contents(CMS_CONFIG_FILE);
    if ($raw === false) return $default;

    $cfg = json_decode($raw, true);
    if (!is_array($cfg)) return $default;

    $merged = array_merge($default, $cfg);
    // PAT を復号
    if (!empty($merged['github_token'])) {
        try {
            $merged['github_token'] = decryptValue($merged['github_token']);
        } catch (Throwable $e) {
            $merged['github_token'] = '';
        }
    }
    return $merged;
}

/**
 * 設定保存（PAT は暗号化）
 * - PAT が空文字なら既存値を維持
 * - 失敗時は例外を投げる (呼び出し側でメッセージ提示)
 */
function saveCmsConfig($input) {
    $current = getCmsConfig();

    $token = isset($input['github_token']) ? trim((string)$input['github_token']) : '';
    if ($token === '') {
        // 既存維持: getCmsConfig() で復号した値が入っているので、再暗号化する必要あり
        $token = $current['github_token'] ?? '';
    }

    // PAT 暗号化 (失敗時は具体的なエラーを上位に伝える)
    $encryptedToken = '';
    if ($token !== '') {
        try {
            $encryptedToken = encryptValue($token);
        } catch (Throwable $e) {
            throw new Exception('PAT の暗号化に失敗しました: ' . $e->getMessage()
                . ' (config/encryption.key が存在し、Webサーバから読み取り可能かを確認してください)');
        }
    }

    $next = [
        'github_token'    => $encryptedToken,
        'github_repo'     => trim((string)($input['github_repo']     ?? $current['github_repo'])),
        'github_branch'   => trim((string)($input['github_branch']   ?? $current['github_branch'])) ?: 'main',
        'content_dir'     => trim((string)($input['content_dir']     ?? $current['content_dir'])) ?: 'src/content/news',
        'categories'      => is_array($input['categories'] ?? null) && !empty($input['categories'])
                                ? array_values(array_filter(array_map('trim', $input['categories']), fn($s) => $s !== ''))
                                : ($current['categories'] ?? cmsDefaultConfig()['categories']),
        'committer_name'  => trim((string)($input['committer_name']  ?? $current['committer_name'])) ?: 'Yamato CMS',
        'committer_email' => trim((string)($input['committer_email'] ?? $current['committer_email'])) ?: 'cms@yamato-mgt.com',
        'updated_at'      => date('Y-m-d H:i:s'),
        'updated_by'      => $_SESSION['user_email'] ?? 'unknown',
    ];

    $dir = dirname(CMS_CONFIG_FILE);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new Exception("config ディレクトリの作成に失敗: $dir");
        }
    }
    if (!is_writable($dir)) {
        throw new Exception("config ディレクトリに書き込み権限がありません: $dir (パーミッションを 0755 以上にしてください)");
    }

    $json = json_encode($next, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $tmp  = CMS_CONFIG_FILE . '.tmp';
    $r = @file_put_contents($tmp, $json, LOCK_EX);
    if ($r === false) {
        $err = error_get_last();
        throw new Exception('cms-config.json の書き込みに失敗: ' . ($err['message'] ?? 'unknown'));
    }
    if (!@rename($tmp, CMS_CONFIG_FILE)) {
        @unlink($tmp);
        $err = error_get_last();
        throw new Exception('cms-config.json のリネームに失敗: ' . ($err['message'] ?? 'unknown'));
    }
    return true;
}

/**
 * 設定が最低限揃っているか
 */
function cmsConfigIsReady($cfg = null) {
    $cfg = $cfg ?? getCmsConfig();
    return !empty($cfg['github_token'])
        && !empty($cfg['github_repo'])
        && strpos($cfg['github_repo'], '/') !== false
        && !empty($cfg['github_branch'])
        && !empty($cfg['content_dir']);
}
