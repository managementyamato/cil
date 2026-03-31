<?php
/**
 * Google Docs ドキュメント取得・パース API
 * 朝礼TODOの一括インポートに使用
 */
ob_start(); // 予期しない出力（警告など）をキャプチャ
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
ob_end_clean(); // キャプチャした出力を破棄

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => false,
    'allowedMethods' => ['GET'],
]);

// doc_id パラメータを受け取る（なければエラー）
$gdocId = isset($_GET['doc_id']) ? trim($_GET['doc_id']) : '';
if (empty($gdocId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $gdocId)) {
    errorResponse('ドキュメントIDが不正です', 400);
}
$exportUrl = 'https://docs.google.com/document/d/' . $gdocId . '/export?format=txt';

// ─── Google Docs をテキストとして取得 ────────────────────────────────

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $exportUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; YamatoGear/1.0)',
    CURLOPT_ENCODING       => '',
]);
$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if (!function_exists('curl_init')) {
    errorResponse('cURLが無効です。サーバー管理者に連絡してください。', 500);
}
if ($curlErr) {
    errorResponse('cURLエラー: ' . $curlErr, 502);
}
if ($httpCode !== 200) {
    errorResponse('HTTP ' . $httpCode . ' が返されました（Googleへの接続に失敗）', 502);
}
if (empty($raw)) {
    errorResponse('ドキュメントの内容が空です', 502);
}

// UTF-8変換・BOM除去
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');

// HTMLが返ってきた場合（認証リダイレクト等）
if (stripos(substr($raw, 0, 200), '<html') !== false || stripos(substr($raw, 0, 200), '<!DOCTYPE') !== false) {
    errorResponse('ドキュメントが非公開またはアクセス権がありません', 403);
}

// ─── テキストをTODO候補にパース ──────────────────────────────────────

$lines = preg_split('/\r\n|\r|\n/', $raw);

// 空行除去・trim
$nonEmptyLines = array();
foreach ($lines as $l) {
    $t = trim($l);
    if ($t !== '') $nonEmptyLines[] = $t;
}

$todos    = array();
$docTitle = '';

// ── ステップ1: 番号付き・箇条書きが存在するか確認 ──────────────────
$hasStructured = false;
foreach ($nonEmptyLines as $line) {
    if (preg_match('/^[\d１-９]+[\.．）\)]\s+.+/', $line) ||
        preg_match('/^[\*\-・●○◆■□▶]\s*.+/', $line)) {
        $hasStructured = true;
        break;
    }
}

// ── ステップ2: 構造化テキストとして解析 ────────────────────────────
if ($hasStructured) {

    // ドキュメントタイトル（最初の行が番号・箇条書きでなければタイトルとして使用）
    if (!empty($nonEmptyLines)) {
        $first = $nonEmptyLines[0];
        if (!preg_match('/^[\d１-９]+[\.．）\)]\s+/', $first) &&
            !preg_match('/^[\*\-・●○◆■□▶]\s*/', $first)) {
            $docTitle = mb_substr(trim($first), 0, 80);
            array_shift($nonEmptyLines);
        }
    }

    $currentTitle = null;
    $descLines    = array();

    foreach ($nonEmptyLines as $line) {

        // 番号付き項目
        if (preg_match('/^[\d１-９]+[\.．）\)]\s+(.+)/', $line, $m)) {
            // 前のTODOを確定
            if ($currentTitle !== null) {
                $desc = trim(implode(' ', $descLines));
                if (mb_strlen($desc) > 120) $desc = mb_substr($desc, 0, 120) . '…';
                $todos[] = array('title' => $currentTitle, 'description' => $desc);
            }
            $itemText = trim($m[1]);
            // "タイトル：説明" 形式を分離
            if (preg_match('/^(.+?)[：:]\s*(.+)$/', $itemText, $mm)) {
                $currentTitle = trim($mm[1]);
                $descLines    = array(trim($mm[2]));
            } else {
                $currentTitle = $itemText;
                $descLines    = array();
            }
            continue;
        }

        // 箇条書き項目
        if (preg_match('/^[\*\-・●○◆■□▶]\s*(.+)/', $line, $m)) {
            $text = trim($m[1]);
            // 担当・補足などは説明として追加
            if (preg_match('/^(担当|補足|分析|結論|目的|期日|備考|対象)[：:]/u', $text)) {
                if ($currentTitle !== null) $descLines[] = $text;
            } elseif ($currentTitle !== null) {
                if (mb_strlen($text) <= 40) {
                    $descLines[] = $text;
                } else {
                    // 独立したTODOとして確定
                    $desc = trim(implode(' ', $descLines));
                    if (mb_strlen($desc) > 120) $desc = mb_substr($desc, 0, 120) . '…';
                    $todos[] = array('title' => $currentTitle, 'description' => $desc);
                    $currentTitle = $text;
                    $descLines    = array();
                }
            } else {
                $todos[] = array('title' => $text, 'description' => '');
            }
            continue;
        }

        // 通常行: 説明の最初の1行のみ追加
        if ($currentTitle !== null && empty($descLines)) {
            $descLines[] = $line;
        }
    }

    // 最後のTODOを確定
    if ($currentTitle !== null) {
        $desc = trim(implode(' ', $descLines));
        if (mb_strlen($desc) > 120) $desc = mb_substr($desc, 0, 120) . '…';
        $todos[] = array('title' => $currentTitle, 'description' => $desc);
    }

// ── ステップ3: 非構造テキストは空行ブロック単位で分割 ────────────────
} else {

    $blocks = array();
    $buf    = array();
    foreach ($lines as $line) {
        $t = trim($line);
        if ($t === '') {
            if (!empty($buf)) { $blocks[] = $buf; $buf = array(); }
        } else {
            $buf[] = $t;
        }
    }
    if (!empty($buf)) $blocks[] = $buf;

    // 最初のブロックをタイトルとして使用（短い場合）
    if (!empty($blocks) && count($blocks[0]) <= 2) {
        $docTitle = implode(' ', $blocks[0]);
        $docTitle = mb_substr($docTitle, 0, 80);
        array_shift($blocks);
    }

    foreach ($blocks as $block) {
        if (empty($block)) continue;
        $title = preg_replace('/^[#\*\-・●○▶︎\s]+/', '', $block[0]);
        $title = trim($title);
        if (mb_strlen($title) < 2) continue;
        $desc = count($block) > 1 ? implode(' ', array_slice($block, 1)) : '';
        $desc = mb_substr(trim($desc), 0, 120);
        $todos[] = array('title' => $title, 'description' => $desc);
    }
}

// 短すぎるTODOを除去
$result = array();
foreach ($todos as $t) {
    if (mb_strlen(trim($t['title'])) >= 2) $result[] = $t;
}

successResponse(array(
    'doc_title' => $docTitle,
    'todos'     => $result,
    'count'     => count($result),
));
