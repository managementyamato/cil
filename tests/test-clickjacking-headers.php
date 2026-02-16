<?php
/**
 * クリックジャッキング対策ヘッダーのテスト
 *
 * 使用方法: php tests/test-clickjacking-headers.php
 */

require_once __DIR__ . '/../config/config.php';

echo "=== クリックジャッキング対策ヘッダーのテスト ===\n\n";

// テスト1: 通常のページ（CSP有効）
echo "テスト1: 通常のページ（CSP有効）\n";
echo "-------------------------------------\n";
ob_start();
setSecurityHeaders();
$headers = xdebug_get_headers();
ob_end_clean();

$hasXFrameOptions = false;
$hasFrameAncestors = false;

foreach ($headers as $header) {
    if (stripos($header, 'X-Frame-Options:') === 0) {
        echo "✓ " . $header . "\n";
        $hasXFrameOptions = true;
    }
    if (stripos($header, 'Content-Security-Policy:') === 0) {
        if (strpos($header, 'frame-ancestors') !== false) {
            echo "✓ CSP: frame-ancestors が含まれています\n";
            $hasFrameAncestors = true;
        } else {
            echo "✗ CSP: frame-ancestors が含まれていません\n";
        }
    }
}

if (!$hasXFrameOptions) {
    echo "✗ X-Frame-Options ヘッダーが設定されていません\n";
}
if (!$hasFrameAncestors) {
    echo "✗ CSP frame-ancestors が設定されていません\n";
}

echo "\n";

// テスト2: APIエンドポイント（CSP無効、frame有効）
echo "テスト2: APIエンドポイント（CSP無効、frame有効）\n";
echo "-------------------------------------\n";
header_remove(); // ヘッダーをリセット
ob_start();
setSecurityHeaders(['csp' => false, 'frame' => true]);
$headers = xdebug_get_headers();
ob_end_clean();

$hasXFrameOptions = false;
$hasFrameAncestors = false;

foreach ($headers as $header) {
    if (stripos($header, 'X-Frame-Options:') === 0) {
        echo "✓ " . $header . "\n";
        $hasXFrameOptions = true;
    }
    if (stripos($header, 'Content-Security-Policy:') === 0) {
        echo "✓ " . $header . "\n";
        if (strpos($header, 'frame-ancestors') !== false) {
            $hasFrameAncestors = true;
        }
    }
}

if (!$hasXFrameOptions) {
    echo "✗ X-Frame-Options ヘッダーが設定されていません\n";
}
if (!$hasFrameAncestors) {
    echo "✗ CSP frame-ancestors が設定されていません\n";
}

echo "\n";

// 判定
if ($hasXFrameOptions || $hasFrameAncestors) {
    echo "結果: ✓ クリックジャッキング対策が設定されています\n";
    exit(0);
} else {
    echo "結果: ✗ クリックジャッキング対策が不十分です\n";
    exit(1);
}
