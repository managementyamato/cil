<?php
/**
 * 指定請求書作成API
 * テンプレート請求書から新しい請求書を作成する
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/recurring-invoice.php';
require_once __DIR__ . '/mf-api.php';

// API初期化（認証・CSRF検証・レート制限）
initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'rateLimit' => 30,  // 1分間に30回まで
    'allowedMethods' => ['POST']
]);

// admin権限チェック
if (!isAdmin()) {
    errorResponse('管理者権限が必要です', 403);
}

try {
    // リクエストボディを取得
    $input = getJsonInput();

    // パラメータ検証
    requireParams($input, ['template_id']);

    $templateId = sanitizeInput($input['template_id'], 'string');
    $targetMonth = isset($input['target_month']) ? sanitizeInput($input['target_month'], 'string') : null;

    // 対象月のバリデーション（Y-m形式）
    if ($targetMonth && !preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
        errorResponse('対象月の形式が不正です（Y-m形式で指定してください）', 400);
    }

    // MF APIクライアントを初期化
    $mfClient = new MFApiClient();

    // 請求書作成
    logInfo('指定請求書作成開始', [
        'template_id' => $templateId,
        'target_month' => $targetMonth,
        'user' => $_SESSION['user_email'] ?? 'unknown'
    ]);

    // デバッグ：テンプレート請求書の存在確認
    try {
        $templateDetail = $mfClient->getInvoiceDetail($templateId);
        logInfo('テンプレート請求書取得成功', [
            'template_id' => $templateId,
            'has_billing' => isset($templateDetail['billing']),
            'response_keys' => array_keys($templateDetail)
        ]);
    } catch (Exception $e) {
        logError('テンプレート請求書取得失敗', [
            'template_id' => $templateId,
            'error' => $e->getMessage()
        ]);
    }

    $result = createInvoiceFromTemplate($mfClient, $templateId, null, $targetMonth);

    if ($result['success']) {
        // 監査ログに記録
        writeAuditLog(
            'invoice_create',
            $result['new_billing_id'],
            "指定請求書を作成（テンプレート: {$templateId}）",
            [
                'template_id' => $templateId,
                'new_billing_id' => $result['new_billing_id'],
                'target_month' => $targetMonth,
                'billing_date' => $result['billing_date'] ?? null,
                'due_date' => $result['due_date'] ?? null
            ]
        );

        logInfo('指定請求書作成成功', [
            'new_billing_id' => $result['new_billing_id'],
            'template_id' => $templateId
        ]);

        successResponse([
            'new_billing_id' => $result['new_billing_id'],
            'billing_date' => $result['billing_date'] ?? null,
            'due_date' => $result['due_date'] ?? null
        ], '請求書を作成しました');
    } else {
        logWarning('指定請求書作成失敗', [
            'template_id' => $templateId,
            'error' => $result['message']
        ]);

        // より詳細なエラーメッセージ
        $errorMessage = $result['message'];
        if (strpos($errorMessage, 'テンプレート請求書が見つかりません') !== false) {
            $errorMessage .= "\n\nヒント: MF請求書一覧で表示されているIDが正しいか確認してください。一覧APIと詳細APIでIDの形式が異なる可能性があります。";
        }

        errorResponse($errorMessage, 400);
    }

} catch (Exception $e) {
    logException($e, '指定請求書作成API');
    errorResponse('予期しないエラーが発生しました', 500);
}
