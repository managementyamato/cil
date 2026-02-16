<?php
/**
 * 作成予定請求書登録API
 * テンプレート請求書情報を自社システムに登録し、後でまとめてMFに作成できるようにする
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/recurring-invoice.php';
require_once __DIR__ . '/mf-api.php';

// API初期化（認証・CSRF検証・レート制限）
initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'rateLimit' => 60,
    'allowedMethods' => ['POST', 'GET', 'DELETE']
]);

// admin権限チェック
if (!isAdmin()) {
    errorResponse('管理者権限が必要です', 403);
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // GET: 作成予定請求書一覧を取得
    if ($method === 'GET') {
        $data = getData();
        $scheduledInvoices = $data['scheduled_invoices'] ?? [];

        // 削除済みを除外
        $scheduledInvoices = array_filter($scheduledInvoices, function($inv) {
            return !isset($inv['deleted']) || !$inv['deleted'];
        });

        // 対象月でフィルタ（オプション）
        if (isset($_GET['target_month'])) {
            $targetMonth = sanitizeInput($_GET['target_month'], 'string');
            $scheduledInvoices = array_filter($scheduledInvoices, function($inv) use ($targetMonth) {
                return ($inv['target_month'] ?? '') === $targetMonth;
            });
        }

        // ステータスでフィルタ（オプション）
        if (isset($_GET['status'])) {
            $status = sanitizeInput($_GET['status'], 'string');
            $scheduledInvoices = array_filter($scheduledInvoices, function($inv) use ($status) {
                return ($inv['status'] ?? 'pending') === $status;
            });
        }

        successResponse(array_values($scheduledInvoices), '作成予定請求書を取得しました');
    }

    // POST: 作成予定請求書を登録
    if ($method === 'POST') {
        $input = getJsonInput();
        requireParams($input, ['mf_template_id', 'target_month']);

        $mfTemplateId = sanitizeInput($input['mf_template_id'], 'string');
        $targetMonth = sanitizeInput($input['target_month'], 'string');

        // 対象月のバリデーション（Y-m形式）
        if (!preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
            errorResponse('対象月の形式が不正です（Y-m形式で指定してください）', 400);
        }

        // MF APIクライアントを初期化
        $mfClient = new MFApiClient();

        // テンプレート請求書情報を取得（一覧から）
        $allInvoices = $mfClient->getInvoices();
        $templateInvoice = null;

        foreach ($allInvoices as $invoice) {
            if ($invoice['id'] === $mfTemplateId) {
                $templateInvoice = $invoice;
                break;
            }
        }

        if (!$templateInvoice) {
            errorResponse('テンプレート請求書が見つかりません', 404);
        }

        // タグから日付を計算
        $tagNames = $templateInvoice['tag_names'] ?? [];
        $dates = calculateDatesFromTags($tagNames, $targetMonth);

        // 作成予定請求書データを構築
        $scheduledInvoice = [
            'id' => uniqid('sched_'),
            'mf_template_id' => $mfTemplateId,
            'partner_name' => $templateInvoice['partner_name'] ?? '',
            'partner_code' => $templateInvoice['partner_code'] ?? '',
            'title' => $templateInvoice['title'] ?? '',
            'target_month' => $targetMonth,
            'billing_date' => $dates['billing_date'] ?? null,
            'due_date' => $dates['due_date'] ?? null,
            'closing_type' => $dates['closing_type'] ?? null,
            'status' => 'pending',
            'created_by' => $_SESSION['user_email'] ?? 'unknown',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // データベースに保存
        $data = getData();
        if (!isset($data['scheduled_invoices'])) {
            $data['scheduled_invoices'] = [];
        }

        $data['scheduled_invoices'][] = $scheduledInvoice;
        saveData($data);

        // 監査ログに記録
        auditCreate(
            'scheduled_invoices',
            $scheduledInvoice['id'],
            '作成予定請求書を登録',
            $scheduledInvoice
        );

        logInfo('作成予定請求書登録成功', [
            'id' => $scheduledInvoice['id'],
            'template_id' => $mfTemplateId,
            'target_month' => $targetMonth
        ]);

        successResponse($scheduledInvoice, '作成予定請求書を登録しました');
    }

    // DELETE: 作成予定請求書を削除
    if ($method === 'DELETE') {
        $input = getJsonInput();
        requireParams($input, ['id']);

        $id = sanitizeInput($input['id'], 'string');

        $data = getData();
        $scheduledInvoices = $data['scheduled_invoices'] ?? [];

        $found = false;
        foreach ($scheduledInvoices as &$invoice) {
            if ($invoice['id'] === $id) {
                $invoice['deleted'] = true;
                $invoice['deleted_at'] = date('Y-m-d H:i:s');
                $invoice['deleted_by'] = $_SESSION['user_email'] ?? 'unknown';
                $found = true;
                break;
            }
        }
        unset($invoice);

        if (!$found) {
            errorResponse('作成予定請求書が見つかりません', 404);
        }

        $data['scheduled_invoices'] = $scheduledInvoices;
        saveData($data);

        // 監査ログに記録
        auditDelete('scheduled_invoices', $id, '作成予定請求書を削除', ['id' => $id]);

        logInfo('作成予定請求書削除成功', ['id' => $id]);

        successResponse(['id' => $id], '作成予定請求書を削除しました');
    }

} catch (Exception $e) {
    logException($e, '作成予定請求書API');
    errorResponse('予期しないエラーが発生しました', 500);
}
