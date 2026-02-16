<?php
/**
 * CSP違反レポートエンドポイント
 *
 * ブラウザがContent-Security-Policy違反を検出した際に、
 * このエンドポイントにレポートを送信する。
 *
 * 使用方法:
 * CSPヘッダーに report-uri /api/csp-report.php を含める
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/logger.php';

// Content-Type設定
header('Content-Type: application/json; charset=utf-8');

// CSP違反レポートを受信
$input = file_get_contents('php://input');

// 空のリクエストは無視
if (empty($input)) {
    http_response_code(204); // No Content
    exit;
}

$report = json_decode($input, true);

if ($report && isset($report['csp-report'])) {
    $violation = $report['csp-report'];

    // 違反の詳細をログに記録
    logWarning('CSP Violation', [
        'violated-directive' => $violation['violated-directive'] ?? '',
        'blocked-uri' => $violation['blocked-uri'] ?? '',
        'source-file' => $violation['source-file'] ?? '',
        'line-number' => $violation['line-number'] ?? 0,
        'column-number' => $violation['column-number'] ?? 0,
        'script-sample' => $violation['script-sample'] ?? '',
        'original-policy' => substr($violation['original-policy'] ?? '', 0, 200), // 長すぎる場合は切り詰め
        'disposition' => $violation['disposition'] ?? 'enforce',
        'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
        'user-agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
        'ip' => getClientIp(),
    ]);

    // 開発環境では詳細を返す
    if (defined('APP_DEBUG') && APP_DEBUG) {
        echo json_encode([
            'success' => true,
            'message' => 'CSP violation recorded',
            'violation' => $violation
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 本番環境では204 No Contentを返す（ブラウザへの情報漏洩を防ぐ）
http_response_code(204);
