<?php
require_once '../config/config.php';
require_once '../api/mf-api.php';

// 編集権限チェック
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

// データ読み込み
$data = getData();

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// 同期対象月の設定を保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sync_month'])) {
    $syncType = trim($_POST['sync_target_month'] ?? '');

    // 全期間の場合は "all"、特定月の場合は月の値を使用
    if ($syncType === 'all') {
        $targetMonth = 'all';
    } else {
        $targetMonth = trim($_POST['sync_target_month_value'] ?? '');
    }

    // 全期間または年月の形式をチェック (YYYY-MM or "all")
    if ($targetMonth === 'all' || preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
        $configFile = __DIR__ . '/../config/mf-sync-config.json';
        $config = [
            'target_month' => $targetMonth,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        header('Location: finance.php?sync_month_saved=1');
        exit;
    }
}

// 自動同期設定
// デフォルトは無効（手動同期のみ）
$shouldAutoSync = false;
$autoSyncEnabled = false; // 自動同期を有効にする場合はtrueに変更

if ($autoSyncEnabled) {
    // 前回の同期から1時間以上経過していたら自動同期
    $lastSyncTime = isset($data['mf_sync_timestamp']) ? strtotime($data['mf_sync_timestamp']) : 0;
    $currentTime = time();
    $oneHourInSeconds = 3600;

    if (MFApiClient::isConfigured() && ($currentTime - $lastSyncTime) >= $oneHourInSeconds) {
        $shouldAutoSync = true;
    }
}

// MFから同期（請求書データを保存）
if (($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_from_mf'])) || $shouldAutoSync) {
    if (!MFApiClient::isConfigured()) {
        header('Location: finance.php?error=mf_not_configured');
        exit;
    }

    try {
        $client = new MFApiClient();

        // 同期設定を読み込み
        $syncConfigFile = __DIR__ . '/../config/mf-sync-config.json';
        $targetMonth = date('Y-m'); // デフォルト: 今月
        if (file_exists($syncConfigFile)) {
            $syncConfig = json_decode(file_get_contents($syncConfigFile), true);
            $targetMonth = $syncConfig['target_month'] ?? date('Y-m');
        }

        // デバッグ情報を収集
        $debugInfo = array(
            'sync_time' => date('Y-m-d H:i:s'),
            'target_month' => $targetMonth,
            'request_params' => array(),
            'raw_response' => null,
            'parsed_invoices' => null,
            'errors' => array()
        );

        // 開始日と終了日を計算
        if ($targetMonth === 'all') {
            // 全期間の場合: 過去3年分を取得
            $from = date('Y-m-01', strtotime('-3 years'));
            $to = date('Y-m-d'); // 今日まで
            $debugInfo['request_params'] = array('from' => $from, 'to' => $to, 'note' => '全期間の請求書を取得（過去3年分）');
        } else {
            // 指定月の場合
            $from = date('Y-m-01', strtotime($targetMonth . '-01'));
            $to = date('Y-m-t', strtotime($targetMonth . '-01'));
            $debugInfo['request_params'] = array('from' => $from, 'to' => $to, 'note' => date('Y年n月', strtotime($targetMonth . '-01')) . 'の請求書を取得');
        }

        $invoices = $client->getAllInvoices($from, $to);

        $debugInfo['parsed_invoices'] = $invoices;
        $debugInfo['invoice_count'] = count($invoices);

        // サンプルデータを保存（最初の3件）
        if (!empty($invoices)) {
            $debugInfo['sample_invoices'] = array_slice($invoices, 0, 3);
        }

        // デバッグ情報をファイルに保存
        $debugFile = dirname(__DIR__) . '/mf-sync-debug.json';
        file_put_contents($debugFile, json_encode($debugInfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // 請求書データをmf_invoices配列に保存
        if (!isset($data['mf_invoices'])) {
            $data['mf_invoices'] = array();
        }

        // 既存のIDマップを作成（重複チェック用）
        $existingIds = array();
        foreach ($data['mf_invoices'] as $existingInvoice) {
            $existingIds[$existingInvoice['id']] = true;
        }

        $newCount = 0;
        $skipCount = 0;

        foreach ($invoices as $invoice) {
            $invoiceId = $invoice['id'] ?? '';

            // 重複チェック：既存のIDと一致する場合はスキップ
            if (isset($existingIds[$invoiceId])) {
                $skipCount++;
                continue;
            }

            // タグからPJ番号と担当者名を抽出
            $tags = $invoice['tag_names'] ?? array();
            $projectId = '';
            $assignee = '';

            $closingDate = '';
            foreach ($tags as $tag) {
                // PJ番号を抽出（P + 数字）
                if (preg_match('/^P\d+$/i', $tag)) {
                    $projectId = $tag;
                }
                // 〆日を抽出（例: 20日〆, 末日〆）
                if (preg_match('/(末日|[\d]+日)〆/', $tag, $matches)) {
                    $closingDate = $matches[1] . '〆';
                }
                // 担当者名を抽出（日本語の人名を想定）
                // 2文字の日本語で、会社名や部署名、一般名詞を除外
                if (mb_strlen($tag) === 2 &&
                    preg_match('/^[ぁ-んァ-ヶー一-龯]+$/', $tag) &&
                    !preg_match('/(株式|有限|合同|本社|支店|営業|部|課|係|室|〆|メール|販売|レンタル|建設|工事|開発|総務|経理|人事|企画|管理|その他|郵送|派遣|修理|交換|水没|末締)/', $tag)) {
                    $assignee = $tag;
                }
            }

            // 金額詳細を取得
            // MoneyForward APIのフィールド名:
            // - subtotal_price: 小計（税抜き）
            // - excise_price: 消費税
            // - total_price: 合計金額（税込み）
            $subtotal = floatval($invoice['subtotal_price'] ?? 0);
            $tax = floatval($invoice['excise_price'] ?? 0);
            $total = floatval($invoice['total_price'] ?? 0);

            $data['mf_invoices'][] = array(
                'id' => $invoiceId,
                'billing_number' => $invoice['billing_number'] ?? '',
                'title' => $invoice['title'] ?? '',
                'partner_name' => $invoice['partner_name'] ?? '',
                'billing_date' => $invoice['billing_date'] ?? '',
                'due_date' => $invoice['due_date'] ?? '',
                'sales_date' => $invoice['sales_date'] ?? '',
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total_amount' => $total,
                'payment_status' => $invoice['payment_status'] ?? '未設定',
                'posting_status' => $invoice['posting_status'] ?? '未郵送',
                'memo' => $invoice['memo'] ?? '',
                'note' => $invoice['note'] ?? '',
                'tag_names' => $tags,
                'project_id' => $projectId,
                'assignee' => $assignee,
                'closing_date' => $closingDate,
                'pdf_url' => $invoice['pdf_url'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
                'synced_at' => date('Y-m-d H:i:s')
            );
            $newCount++;
        }

        // 同期時刻を記録
        $data['mf_sync_timestamp'] = date('Y-m-d H:i:s');

        saveData($data);

        // 自動同期の場合はリダイレクトしない
        if (!$shouldAutoSync) {
            header('Location: finance.php?synced=' . count($invoices) . '&new=' . $newCount . '&skip=' . $skipCount);
            exit;
        }
    } catch (Exception $e) {
        // 自動同期の場合はエラーをログに記録してページ表示を継続
        if ($shouldAutoSync) {
            error_log('MF自動同期エラー: ' . $e->getMessage());
        } else {
            header('Location: finance.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}


require_once '../functions/header.php';
?>

<style>
/* ダッシュボードKPIカード */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 1200px) {
    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .kpi-grid {
        grid-template-columns: 1fr;
    }
}

.kpi-card {
    background: white;
    padding: 1.25rem;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    transition: transform 0.2s, box-shadow 0.2s;
}

.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.kpi-card.primary {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    border: none;
}

.kpi-card.success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
}

.kpi-label {
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.8;
    margin-bottom: 0.5rem;
}

.kpi-card.primary .kpi-label,
.kpi-card.success .kpi-label {
    opacity: 0.9;
}

.kpi-value {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1.2;
}

.kpi-change {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    margin-top: 0.5rem;
}

.kpi-change.up {
    color: #10b981;
}

.kpi-change.down {
    color: #ef4444;
}

.kpi-card.primary .kpi-change,
.kpi-card.success .kpi-change {
    color: rgba(255,255,255,0.9);
}

/* グラフエリア */
.chart-container {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.chart-title {
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
}

.chart-wrapper {
    height: 250px;
    position: relative;
}

/* シンプルな棒グラフ */
.bar-chart {
    display: flex;
    align-items: flex-end;
    gap: 0.5rem;
    height: 200px;
    padding: 1rem 0;
    border-bottom: 2px solid #e5e7eb;
}

.bar-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.bar {
    width: 100%;
    max-width: 60px;
    background: linear-gradient(180deg, #3b82f6 0%, #1d4ed8 100%);
    border-radius: 4px 4px 0 0;
    transition: height 0.3s ease;
    min-height: 4px;
}

.bar-label {
    font-size: 0.7rem;
    color: #6b7280;
    white-space: nowrap;
}

.bar-value {
    font-size: 0.7rem;
    font-weight: 600;
    color: #1f2937;
}

/* タブ切り替え */
.view-tabs {
    display: flex;
    gap: 0;
    background: #f3f4f6;
    border-radius: 8px;
    padding: 4px;
    margin-bottom: 1.5rem;
}

.view-tab {
    flex: 1;
    padding: 0.75rem 1rem;
    text-align: center;
    cursor: pointer;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    color: #6b7280;
    transition: all 0.2s;
    border: none;
    background: none;
}

.view-tab:hover {
    color: #1f2937;
}

.view-tab.active {
    background: white;
    color: #1f2937;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* カード表示 */
.invoice-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
}

.invoice-card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}

.invoice-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.invoice-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.invoice-card-customer {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.95rem;
}

.invoice-card-amount {
    font-weight: 700;
    color: #1d4ed8;
    font-size: 1.1rem;
}

.invoice-card-title {
    color: #6b7280;
    font-size: 0.875rem;
    margin-bottom: 0.75rem;
    line-height: 1.4;
}

.invoice-card-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: #9ca3af;
}

.invoice-card-tags {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
    margin-top: 0.75rem;
}

/* テーブル改善 */
.data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.data-table th {
    background: #f9fafb;
    padding: 0.75rem 1rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e5e7eb;
    position: sticky;
    top: 0;
    white-space: nowrap;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
    font-size: 0.875rem;
    vertical-align: middle;
    white-space: nowrap;
}

.data-table tbody tr {
    transition: background 0.15s;
}

.data-table tbody tr:hover {
    background: #f9fafb;
}

.customer-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.customer-avatar {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
}

.customer-name {
    font-weight: 500;
    color: #1f2937;
}

.amount-cell {
    font-weight: 600;
    color: #1f2937;
}

/* タグスタイル改善 */
.tag {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 500;
}

.tag.project {
    background: #dcfce7;
    color: #166534;
}

.tag.assignee {
    background: #fef3c7;
    color: #92400e;
}

.tag.default {
    background: #f3f4f6;
    color: #4b5563;
}

/* 送付・入金ステータスタグ */
.tag.sent {
    background: #dbeafe;
    color: #1e40af;
}

.tag.unsent {
    background: #fee2e2;
    color: #991b1b;
}

.tag.paid {
    background: #dcfce7;
    color: #166534;
}

.tag.unpaid {
    background: #fef3c7;
    color: #92400e;
}

/* 同期・フィルタエリア */
.filter-bar {
    background: white;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.filter-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #4b5563;
}

.filter-select {
    padding: 0.5rem 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.875rem;
    background: white;
    min-width: 150px;
}

.filter-input {
    padding: 0.5rem 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.875rem;
    min-width: 200px;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

/* 顧客別・担当者別集計 */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}

.summary-card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.summary-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.summary-card-name {
    font-weight: 600;
    color: #1f2937;
}

.summary-card-total {
    font-weight: 700;
    color: #1d4ed8;
    font-size: 1.25rem;
}

.summary-card-count {
    font-size: 0.75rem;
    color: #6b7280;
}

/* モーダル改善 */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: #fff;
    margin: 1rem;
    padding: 0;
    border-radius: 16px;
    width: 90%;
    max-width: 800px;
    max-height: 85vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
}

.modal-header {
    padding: 1.5rem;
    background: white;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    color: #1f2937;
}

.modal-close {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    transition: background 0.2s;
}

.modal-close:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.modal-body {
    padding: 1.5rem;
    overflow-y: auto;
}

.invoice-detail-item {
    padding: 1rem;
    background: #f9fafb;
    border-radius: 8px;
    margin-bottom: 0.75rem;
}

.summary-box {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-left: 4px solid #3b82f6;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.summary-box .summary-row {
    display: flex;
    justify-content: space-between;
    padding: 0.25rem 0;
}

.summary-box .summary-row.total {
    font-weight: bold;
    font-size: 1.125rem;
    padding-top: 0.5rem;
    border-top: 2px solid #3b82f6;
    margin-top: 0.5rem;
}

/* アラートスタイル改善 */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

/* 同期設定カード */
.sync-card {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid #93c5fd;
}

.sync-form {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.sync-label {
    font-weight: 500;
    color: #1e40af;
}

.sync-info {
    color: #1e40af;
    font-size: 0.875rem;
}
</style>

<?php if (isset($_GET['sync_month_saved'])): ?>
    <div class="alert alert-success">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        同期対象月の設定を保存しました。
    </div>
<?php endif; ?>


<?php if (isset($_GET['synced'])): ?>
    <div class="alert alert-success">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        MFから<?= intval($_GET['synced']) ?>件の請求書を取得しました
        <?php if (isset($_GET['new'])): ?>
            （新規: <?= intval($_GET['new']) ?>件、スキップ: <?= intval($_GET['skip'] ?? 0) ?>件）
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <?php if ($_GET['error'] === 'mf_not_configured'): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            MF APIの設定が完了していません。<a href="mf-settings.php" style="color: inherit; text-decoration: underline;">設定ページ</a>から設定してください。
        </div>
    <?php else: ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            エラー: <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
// フィルタの取得（GETパラメータから）
$selectedYearMonth = isset($_GET['year_month']) ? $_GET['year_month'] : '';
$searchTag = isset($_GET['search_tag']) ? trim($_GET['search_tag']) : '';
$viewMode = isset($_GET['view']) ? $_GET['view'] : 'table';
$filterPosting = isset($_GET['posting']) ? $_GET['posting'] : '';
$filterPayment = isset($_GET['payment']) ? $_GET['payment'] : '';

// 全請求書から年月のリストを生成
$availableYearMonths = array();
$monthlyTotals = array();
$customerTotals = array();
$assigneeTotals = array();

if (isset($data['mf_invoices']) && !empty($data['mf_invoices'])) {
    foreach ($data['mf_invoices'] as $invoice) {
        // 年月を抽出
        $salesDate = $invoice['sales_date'] ?? '';
        if ($salesDate && preg_match('/^(\d{4})[-\/](\d{2})/', $salesDate, $matches)) {
            $yearMonth = $matches[1] . '-' . $matches[2];
            if (!in_array($yearMonth, $availableYearMonths)) {
                $availableYearMonths[] = $yearMonth;
            }

            // 月別集計
            if (!isset($monthlyTotals[$yearMonth])) {
                $monthlyTotals[$yearMonth] = 0;
            }
            $monthlyTotals[$yearMonth] += floatval($invoice['total_amount'] ?? 0);
        }

        // 顧客別集計
        $customerName = $invoice['partner_name'] ?? '不明';
        if (!isset($customerTotals[$customerName])) {
            $customerTotals[$customerName] = array('total' => 0, 'count' => 0);
        }
        $customerTotals[$customerName]['total'] += floatval($invoice['total_amount'] ?? 0);
        $customerTotals[$customerName]['count']++;

        // 担当者別集計
        $assignee = $invoice['assignee'] ?? '未設定';
        if (!isset($assigneeTotals[$assignee])) {
            $assigneeTotals[$assignee] = array('total' => 0, 'count' => 0);
        }
        $assigneeTotals[$assignee]['total'] += floatval($invoice['total_amount'] ?? 0);
        $assigneeTotals[$assignee]['count']++;
    }
}

// 降順ソート（新しい月が上に）
rsort($availableYearMonths);
krsort($monthlyTotals);
arsort($customerTotals);
arsort($assigneeTotals);

// デフォルトは最新月
if (empty($selectedYearMonth) && !empty($availableYearMonths)) {
    $selectedYearMonth = $availableYearMonths[0];
}

// フィルタされた請求書データを取得
$filteredInvoices = array();
$totalAmount = 0;
$totalTax = 0;

if (isset($data['mf_invoices']) && !empty($data['mf_invoices'])) {
    foreach ($data['mf_invoices'] as $invoice) {
        $salesDate = $invoice['sales_date'] ?? '';

        // 年月フィルタ
        $yearMonthMatch = true;
        if ($selectedYearMonth && $salesDate) {
            $normalizedDate = str_replace('/', '-', $salesDate);
            $yearMonthMatch = (strpos($normalizedDate, $selectedYearMonth) === 0);
        }

        // タグ検索フィルタ（スペース区切りで複数タグ検索対応）
        $tagMatch = true;
        if (!empty($searchTag)) {
            // スペースで区切って複数のキーワードに分割
            $searchKeywords = preg_split('/\s+/', trim($searchTag));
            $tagMatch = true;

            // 全てのキーワードが一致する必要がある（AND検索）
            foreach ($searchKeywords as $keyword) {
                if (empty($keyword)) continue;

                $keywordMatch = false;
                $tags = $invoice['tag_names'] ?? array();

                // タグ名で検索
                foreach ($tags as $tag) {
                    if (mb_stripos($tag, $keyword) !== false) {
                        $keywordMatch = true;
                        break;
                    }
                }

                // PJ番号、担当者名、請求書番号でも検索
                if (!$keywordMatch) {
                    if (!empty($invoice['project_id']) && mb_stripos($invoice['project_id'], $keyword) !== false) {
                        $keywordMatch = true;
                    }
                }
                if (!$keywordMatch) {
                    if (!empty($invoice['assignee']) && mb_stripos($invoice['assignee'], $keyword) !== false) {
                        $keywordMatch = true;
                    }
                }
                if (!$keywordMatch) {
                    if (!empty($invoice['invoice_number']) && mb_stripos($invoice['invoice_number'], $keyword) !== false) {
                        $keywordMatch = true;
                    }
                }

                // 1つでもキーワードが見つからなければ、この請求書は除外
                if (!$keywordMatch) {
                    $tagMatch = false;
                    break;
                }
            }
        }

        // 送付ステータスフィルタ
        $postingMatch = true;
        if (!empty($filterPosting)) {
            $postingStatus = $invoice['posting_status'] ?? '';
            $isSent = in_array($postingStatus, ['sent', 'posted', '郵送済み', 'メール送信済み', '送付済み']);
            if ($filterPosting === 'sent' && !$isSent) {
                $postingMatch = false;
            } elseif ($filterPosting === 'unsent' && $isSent) {
                $postingMatch = false;
            }
        }

        // 入金ステータスフィルタ
        $paymentMatch = true;
        if (!empty($filterPayment)) {
            $paymentStatus = $invoice['payment_status'] ?? '';
            $isPaid = in_array($paymentStatus, ['paid', '入金済み', '入金済']);
            if ($filterPayment === 'paid' && !$isPaid) {
                $paymentMatch = false;
            } elseif ($filterPayment === 'unpaid' && $isPaid) {
                $paymentMatch = false;
            }
        }

        // フィルタが一致した場合のみ追加
        if ($yearMonthMatch && $tagMatch && $postingMatch && $paymentMatch) {
            $filteredInvoices[] = $invoice;
            $totalAmount += floatval($invoice['total_amount'] ?? 0);
            $totalTax += floatval($invoice['tax'] ?? 0);
        }
    }
}

$totalSubtotal = $totalAmount - $totalTax;
$invoiceCount = count($filteredInvoices);

// 請求書番号の降順でソート（最新が上）
usort($filteredInvoices, function($a, $b) {
    return strcmp($b['billing_number'] ?? '', $a['billing_number'] ?? '');
});

// 前月比計算
$prevMonth = date('Y-m', strtotime($selectedYearMonth . '-01 -1 month'));
$prevMonthTotal = $monthlyTotals[$prevMonth] ?? 0;
$currentMonthTotal = $monthlyTotals[$selectedYearMonth] ?? $totalAmount;
$monthChange = $prevMonthTotal > 0 ? (($currentMonthTotal - $prevMonthTotal) / $prevMonthTotal) * 100 : 0;

// 未送付・未入金のカウント
$unsentCount = 0;
$unsentAmount = 0;
$unpaidCount = 0;
$unpaidAmount = 0;

foreach ($filteredInvoices as $invoice) {
    $postingStatus = $invoice['posting_status'] ?? '';
    $isSent = in_array($postingStatus, ['sent', 'posted', '郵送済み', 'メール送信済み', '送付済み']);
    if (!$isSent) {
        $unsentCount++;
        $unsentAmount += floatval($invoice['total_amount'] ?? 0);
    }

    $paymentStatus = $invoice['payment_status'] ?? '';
    $isPaid = in_array($paymentStatus, ['paid', '入金済み', '入金済']);
    if (!$isPaid) {
        $unpaidCount++;
        $unpaidAmount += floatval($invoice['total_amount'] ?? 0);
    }
}

// 現在の同期対象月設定を読み込み
$syncConfigFile = __DIR__ . '/../config/mf-sync-config.json';
$syncTargetMonth = date('Y-m'); // デフォルト: 今月
if (file_exists($syncConfigFile)) {
    $syncConfig = json_decode(file_get_contents($syncConfigFile), true);
    $syncTargetMonth = $syncConfig['target_month'] ?? date('Y-m');
}

// 案件別損益集計
$projectPnL = [];
foreach ($data['mf_invoices'] ?? [] as $inv) {
    $customer = $inv['partner_name'] ?? $inv['customer_name'] ?? '不明';
    if (!isset($projectPnL[$customer])) {
        $projectPnL[$customer] = ['total' => 0, 'count' => 0, 'paid' => 0, 'unpaid' => 0];
    }
    $amount = floatval($inv['total_amount'] ?? 0);
    $projectPnL[$customer]['total'] += $amount;
    $projectPnL[$customer]['count']++;
    if (($inv['status'] ?? '') === '入金済み' || ($inv['payment_status'] ?? '') === 'paid') {
        $projectPnL[$customer]['paid'] += $amount;
    } else {
        $projectPnL[$customer]['unpaid'] += $amount;
    }
}
// Sort by total descending
uasort($projectPnL, function($a, $b) {
    return $b['total'] <=> $a['total'];
});
$projectPnL = array_slice($projectPnL, 0, 10, true);

// 請求漏れチェック: 完了/設置済の案件で請求がないもの
$invoiceLeaks = [];
$invoicedCustomers = [];
foreach ($data['mf_invoices'] ?? [] as $inv) {
    $partner = $inv['partner_name'] ?? $inv['customer_name'] ?? '';
    if (!empty($partner)) {
        $invoicedCustomers[$partner] = true;
    }
}
foreach ($data['projects'] ?? [] as $pj) {
    $status = $pj['status'] ?? '';
    if (!in_array($status, ['設置済', '完了'])) continue;
    $customer = $pj['customer_name'] ?? '';
    // Check if any invoice exists for this project
    $hasInvoice = false;
    foreach ($data['mf_invoices'] ?? [] as $inv) {
        $invCustomer = $inv['partner_name'] ?? $inv['customer_name'] ?? '';
        $invSubject = $inv['title'] ?? $inv['subject'] ?? '';
        // Match by customer name or project ID in subject
        if ($invCustomer === $customer || stripos($invSubject, $pj['id'] ?? '') !== false) {
            $hasInvoice = true;
            break;
        }
    }
    if (!$hasInvoice) {
        $invoiceLeaks[] = $pj;
    }
}

// 月次売上比較
$monthlyComparison = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $monthlyComparison[$m] = ['sales' => 0, 'count' => 0];
}
foreach ($data['mf_invoices'] ?? [] as $inv) {
    $salesDate = $inv['sales_date'] ?? '';
    if ($salesDate) {
        $m = date('Y-m', strtotime(str_replace('/', '-', $salesDate)));
        if (isset($monthlyComparison[$m])) {
            $monthlyComparison[$m]['sales'] += floatval($inv['total_amount'] ?? 0);
            $monthlyComparison[$m]['count']++;
        }
    }
}
?>

<div class="page-container">

<div class="page-header">
    <h2>売上管理</h2>
    <div class="page-header-actions">
        <?php if (MFApiClient::isConfigured()): ?>
        <form method="POST" style="display: inline;">
            <?= csrfTokenField() ?>
            <button type="submit" name="sync_from_mf" class="btn btn-primary">MFから同期</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- 月次売上比較 -->
<div class="stats-row">
    <?php foreach ($monthlyComparison as $month => $mc):
        $label = date('n月', strtotime($month . '-01'));
        $isCurrentMonth = $month === date('Y-m');
    ?>
    <div class="stat-card" style="<?= $isCurrentMonth ? 'border:2px solid var(--primary);' : '' ?>">
        <div class="stat-label"><?= htmlspecialchars($label) ?><?= $isCurrentMonth ? ' (今月)' : '' ?></div>
        <div class="stat-number">&yen;<?= number_format($mc['sales']) ?></div>
        <div style="font-size:0.75rem; color:var(--gray-500);"><?= $mc['count'] ?>件</div>
    </div>
    <?php endforeach; ?>
</div>

<!-- 未送付・未入金アラート -->
<?php if ($unsentCount > 0 || $unpaidCount > 0): ?>
<div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
    <?php if ($unsentCount > 0): ?>
    <a href="finance.php?year_month=<?= urlencode($selectedYearMonth) ?>&posting=unsent&view=<?= htmlspecialchars($viewMode) ?>"
       class="alert" style="flex: 1; min-width: 250px; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; text-decoration: none; margin-bottom: 0;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
        </svg>
        <div>
            <strong>未送付: <?= $unsentCount ?>件</strong>
            <span style="margin-left: 0.5rem; font-size: 0.875rem;">¥<?= number_format($unsentAmount) ?></span>
        </div>
    </a>
    <?php endif; ?>
    <?php if ($unpaidCount > 0): ?>
    <a href="finance.php?year_month=<?= urlencode($selectedYearMonth) ?>&payment=unpaid&view=<?= htmlspecialchars($viewMode) ?>"
       class="alert" style="flex: 1; min-width: 250px; background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; text-decoration: none; margin-bottom: 0;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <div>
            <strong>未入金: <?= $unpaidCount ?>件</strong>
            <span style="margin-left: 0.5rem; font-size: 0.875rem;">¥<?= number_format($unpaidAmount) ?></span>
        </div>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- KPIダッシュボード -->
<div class="kpi-grid">
    <div class="kpi-card primary">
        <div class="kpi-label">売上合計（税込）</div>
        <div class="kpi-value">¥<?= number_format($totalAmount) ?></div>
        <?php if ($monthChange != 0): ?>
        <div class="kpi-change <?= $monthChange >= 0 ? 'up' : 'down' ?>">
            <?= $monthChange >= 0 ? '↑' : '↓' ?> <?= abs(round($monthChange, 1)) ?>% 前月比
        </div>
        <?php endif; ?>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">売上合計（税抜）</div>
        <div class="kpi-value">¥<?= number_format($totalSubtotal) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">請求書数</div>
        <div class="kpi-value"><?= number_format($invoiceCount) ?></div>
    </div>
    <div class="kpi-card success">
        <div class="kpi-label">平均単価</div>
        <div class="kpi-value">¥<?= $invoiceCount > 0 ? number_format(round($totalAmount / $invoiceCount)) : 0 ?></div>
    </div>
</div>

<!-- 月別売上グラフ -->
<?php if (!empty($monthlyTotals)): ?>
<div class="chart-container">
    <div class="chart-header">
        <div class="chart-title">月別売上推移（税込）</div>
    </div>
    <div class="chart-wrapper">
        <?php
        $chartMonths = array_slice($monthlyTotals, 0, 6, true);
        $chartMonths = array_reverse($chartMonths, true);
        $maxValue = max($chartMonths) ?: 1;
        ?>
        <div class="bar-chart">
            <?php foreach ($chartMonths as $month => $value): ?>
            <div class="bar-item">
                <div class="bar-value">¥<?= number_format(round($value / 10000)) ?>万</div>
                <div class="bar" style="height: <?= ($value / $maxValue) * 180 ?>px;"></div>
                <div class="bar-label"><?= date('n月', strtotime($month . '-01')) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 同期設定 -->
<?php if (MFApiClient::isConfigured()): ?>
<div class="sync-card">
    <form method="POST" action="" class="sync-form">
        <?= csrfTokenField() ?>
        <span class="sync-label">同期対象:</span>
        <select
            id="sync_target_month"
            name="sync_target_month"
            class="filter-select"
            onchange="toggleMonthInput(this)"
        >
            <option value="all" <?= $syncTargetMonth === 'all' ? 'selected' : '' ?>>全期間（過去3年分）</option>
            <option value="custom" <?= $syncTargetMonth !== 'all' ? 'selected' : '' ?>>特定の月を指定</option>
        </select>
        <input
            type="month"
            id="sync_target_month_input"
            name="sync_target_month_value"
            value="<?= $syncTargetMonth !== 'all' ? htmlspecialchars($syncTargetMonth) : date('Y-m') ?>"
            class="filter-input"
            style="<?= $syncTargetMonth === 'all' ? 'display: none;' : '' ?>"
        >
        <button type="submit" name="save_sync_month" class="btn btn-primary">設定保存</button>
        <span id="sync_info" class="sync-info">
            <?php if ($syncTargetMonth === 'all'): ?>
                過去3年分の請求書を同期
            <?php else: ?>
                <?= date('Y年n月', strtotime($syncTargetMonth . '-01')) ?>の請求書を同期
            <?php endif; ?>
        </span>
    </form>
</div>
<?php endif; ?>

<!-- フィルタバー -->
<div class="filter-bar">
    <form method="GET" action="" class="filter-group">
        <div class="filter-item">
            <label class="filter-label">表示月:</label>
            <select name="year_month" class="filter-select">
                <option value="">全期間</option>
                <?php foreach ($availableYearMonths as $ym): ?>
                    <option value="<?= htmlspecialchars($ym) ?>" <?= $selectedYearMonth === $ym ? 'selected' : '' ?>>
                        <?= date('Y年n月', strtotime($ym . '-01')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label class="filter-label">検索:</label>
            <input
                type="text"
                name="search_tag"
                value="<?= htmlspecialchars($searchTag) ?>"
                placeholder="PJ番号、担当者、請求書番号..."
                class="filter-input"
            >
        </div>
        <div class="filter-item">
            <label class="filter-label">送付:</label>
            <select name="posting" class="filter-select" style="min-width: 100px;">
                <option value="">全て</option>
                <option value="sent" <?= $filterPosting === 'sent' ? 'selected' : '' ?>>送付済</option>
                <option value="unsent" <?= $filterPosting === 'unsent' ? 'selected' : '' ?>>未送付</option>
            </select>
        </div>
        <div class="filter-item">
            <label class="filter-label">入金:</label>
            <select name="payment" class="filter-select" style="min-width: 100px;">
                <option value="">全て</option>
                <option value="paid" <?= $filterPayment === 'paid' ? 'selected' : '' ?>>入金済</option>
                <option value="unpaid" <?= $filterPayment === 'unpaid' ? 'selected' : '' ?>>未入金</option>
            </select>
        </div>
        <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">
        <button type="submit" class="btn btn-primary">検索</button>
        <?php if ($selectedYearMonth || $searchTag || $filterPosting || $filterPayment): ?>
            <a href="finance.php?view=<?= htmlspecialchars($viewMode) ?>" class="btn btn-secondary">クリア</a>
        <?php endif; ?>
    </form>

    <div class="action-buttons">
        <?php if (MFApiClient::isConfigured()): ?>
            <form method="POST" action="" style="margin: 0;">
                <?= csrfTokenField() ?>
                <input type="hidden" name="year_month" value="<?= htmlspecialchars($selectedYearMonth) ?>">
                <input type="hidden" name="search_tag" value="<?= htmlspecialchars($searchTag) ?>">
                <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">
                <button type="submit" name="sync_from_mf" class="btn btn-primary btn-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    MF同期
                </button>
            </form>
            <?php if (isset($data['mf_invoices']) && !empty($data['mf_invoices'])): ?>
                <a href="mf-monthly.php" class="btn btn-success btn-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    月別集計
                </a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($filteredInvoices)): ?>
            <a href="download-invoices-csv.php?year_month=<?= urlencode($selectedYearMonth) ?>&search_tag=<?= urlencode($searchTag) ?>"
               class="btn btn-secondary btn-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                CSV
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ビュー切り替えタブ -->
<div class="view-tabs">
    <button class="view-tab <?= $viewMode === 'table' ? 'active' : '' ?>" onclick="switchView('table')">
        テーブル表示
    </button>
    <button class="view-tab <?= $viewMode === 'card' ? 'active' : '' ?>" onclick="switchView('card')">
        カード表示
    </button>
    <button class="view-tab <?= $viewMode === 'customer' ? 'active' : '' ?>" onclick="switchView('customer')">
        顧客別
    </button>
    <button class="view-tab <?= $viewMode === 'assignee' ? 'active' : '' ?>" onclick="switchView('assignee')">
        担当者別
    </button>
</div>

<!-- テーブル表示 -->
<div id="view-table" class="tab-content <?= $viewMode === 'table' ? 'active' : '' ?>">
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <?php if (empty($filteredInvoices)): ?>
                <p style="color: var(--gray-600); text-align: center; padding: 3rem;">
                    請求書がありません。MFから同期してください。
                </p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>PJ</th>
                                <th>顧客</th>
                                <th>担当</th>
                                <th>請求書番号</th>
                                <th>案件名</th>
                                <th>売上日</th>
                                <th style="text-align: right;">金額</th>
                                <th style="text-align: right;">税抜</th>
                                <th>送付</th>
                                <th>入金</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredInvoices as $invoice): ?>
                                <tr onclick="showSingleInvoice('<?= htmlspecialchars($invoice['id'], ENT_QUOTES) ?>')" style="cursor: pointer;">
                                    <td>
                                        <?php if (!empty($invoice['project_id'])): ?>
                                            <span class="tag project"><?= htmlspecialchars($invoice['project_id']) ?></span>
                                        <?php else: ?>
                                            <span style="color: #9ca3af;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="customer-cell">
                                            <div class="customer-avatar">
                                                <?= mb_substr($invoice['partner_name'] ?? '?', 0, 1) ?>
                                            </div>
                                            <span class="customer-name"><?= htmlspecialchars($invoice['partner_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($invoice['assignee'])): ?>
                                            <span class="tag assignee"><?= htmlspecialchars($invoice['assignee']) ?></span>
                                        <?php else: ?>
                                            <span style="color: #9ca3af;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($invoice['id'])): ?>
                                            <a href="https://invoice.moneyforward.com/billings/<?= htmlspecialchars($invoice['id']) ?>" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation();" style="color: #3b82f6; font-weight: 500;">
                                                <?= htmlspecialchars($invoice['billing_number']) ?>
                                            </a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($invoice['billing_number']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($invoice['title']) ?></td>
                                    <td><?= htmlspecialchars($invoice['sales_date']) ?></td>
                                    <td class="amount-cell" style="text-align: right;">¥<?= number_format($invoice['total_amount']) ?></td>
                                    <td style="text-align: right;">¥<?= number_format($invoice['subtotal']) ?></td>
                                    <td>
                                        <?php
                                        $postingStatus = $invoice['posting_status'] ?? '';
                                        $isSent = in_array($postingStatus, ['sent', 'posted', '郵送済み', 'メール送信済み', '送付済み']);
                                        ?>
                                        <span class="tag <?= $isSent ? 'sent' : 'unsent' ?>">
                                            <?= $isSent ? '送付済' : '未送付' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $paymentStatus = $invoice['payment_status'] ?? '';
                                        $isPaid = in_array($paymentStatus, ['paid', '入金済み', '入金済']);
                                        ?>
                                        <span class="tag <?= $isPaid ? 'paid' : 'unpaid' ?>">
                                            <?= $isPaid ? '入金済' : '未入金' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- カード表示 -->
<div id="view-card" class="tab-content <?= $viewMode === 'card' ? 'active' : '' ?>">
    <?php if (empty($filteredInvoices)): ?>
        <p style="color: var(--gray-600); text-align: center; padding: 3rem;">
            請求書がありません。
        </p>
    <?php else: ?>
        <div class="invoice-cards">
            <?php foreach ($filteredInvoices as $invoice): ?>
                <div class="invoice-card" onclick="showSingleInvoice('<?= htmlspecialchars($invoice['id'], ENT_QUOTES) ?>')">
                    <div class="invoice-card-header">
                        <div class="invoice-card-customer"><?= htmlspecialchars($invoice['partner_name']) ?></div>
                        <div class="invoice-card-amount">¥<?= number_format($invoice['total_amount']) ?></div>
                    </div>
                    <div class="invoice-card-title"><?= htmlspecialchars($invoice['title']) ?></div>
                    <div class="invoice-card-meta">
                        <span><?= htmlspecialchars($invoice['sales_date']) ?></span>
                        <span><?= htmlspecialchars($invoice['billing_number']) ?></span>
                    </div>
                    <div class="invoice-card-tags">
                        <?php if (!empty($invoice['project_id'])): ?>
                            <span class="tag project"><?= htmlspecialchars($invoice['project_id']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($invoice['assignee'])): ?>
                            <span class="tag assignee"><?= htmlspecialchars($invoice['assignee']) ?></span>
                        <?php endif; ?>
                        <?php
                        $postingStatus = $invoice['posting_status'] ?? '';
                        $isSent = in_array($postingStatus, ['sent', 'posted', '郵送済み', 'メール送信済み', '送付済み']);
                        $paymentStatus = $invoice['payment_status'] ?? '';
                        $isPaid = in_array($paymentStatus, ['paid', '入金済み', '入金済']);
                        ?>
                        <span class="tag <?= $isSent ? 'sent' : 'unsent' ?>"><?= $isSent ? '送付済' : '未送付' ?></span>
                        <span class="tag <?= $isPaid ? 'paid' : 'unpaid' ?>"><?= $isPaid ? '入金済' : '未入金' ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 顧客別表示 -->
<div id="view-customer" class="tab-content <?= $viewMode === 'customer' ? 'active' : '' ?>">
    <?php
    // 選択月の顧客別集計
    $filteredCustomerTotals = array();
    foreach ($filteredInvoices as $invoice) {
        $customerName = $invoice['partner_name'] ?? '不明';
        if (!isset($filteredCustomerTotals[$customerName])) {
            $filteredCustomerTotals[$customerName] = array('total' => 0, 'count' => 0);
        }
        $filteredCustomerTotals[$customerName]['total'] += floatval($invoice['total_amount'] ?? 0);
        $filteredCustomerTotals[$customerName]['count']++;
    }
    uasort($filteredCustomerTotals, function($a, $b) {
        return $b['total'] - $a['total'];
    });
    ?>
    <div class="summary-grid">
        <?php foreach ($filteredCustomerTotals as $name => $data): ?>
            <div class="summary-card" onclick="showCustomerInvoices('<?= htmlspecialchars($name, ENT_QUOTES) ?>')" style="cursor: pointer;">
                <div class="summary-card-header">
                    <div class="summary-card-name"><?= htmlspecialchars($name) ?></div>
                    <div class="summary-card-total">¥<?= number_format($data['total']) ?></div>
                </div>
                <div class="summary-card-count"><?= $data['count'] ?>件の請求書</div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 担当者別表示 -->
<div id="view-assignee" class="tab-content <?= $viewMode === 'assignee' ? 'active' : '' ?>">
    <?php
    // 選択月の担当者別集計
    $filteredAssigneeTotals = array();
    foreach ($filteredInvoices as $invoice) {
        $assignee = $invoice['assignee'] ?: '未設定';
        if (!isset($filteredAssigneeTotals[$assignee])) {
            $filteredAssigneeTotals[$assignee] = array('total' => 0, 'count' => 0);
        }
        $filteredAssigneeTotals[$assignee]['total'] += floatval($invoice['total_amount'] ?? 0);
        $filteredAssigneeTotals[$assignee]['count']++;
    }
    uasort($filteredAssigneeTotals, function($a, $b) {
        return $b['total'] - $a['total'];
    });
    ?>
    <div class="summary-grid">
        <?php foreach ($filteredAssigneeTotals as $name => $data): ?>
            <div class="summary-card">
                <div class="summary-card-header">
                    <div class="summary-card-name">
                        <?php if ($name !== '未設定'): ?>
                            <span class="tag assignee" style="font-size: 0.9rem;"><?= htmlspecialchars($name) ?></span>
                        <?php else: ?>
                            <?= htmlspecialchars($name) ?>
                        <?php endif; ?>
                    </div>
                    <div class="summary-card-total">¥<?= number_format($data['total']) ?></div>
                </div>
                <div class="summary-card-count"><?= $data['count'] ?>件の請求書</div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 顧客別集計 -->
<div class="card" style="margin-top:1.5rem;">
    <div class="card-header"><h3 style="margin:0;">顧客別売上集計（上位10社）</h3></div>
    <div class="card-body">
        <?php if (empty($projectPnL)): ?>
            <p style="color:var(--gray-500); text-align:center; padding:1rem;">請求データがありません</p>
        <?php else: ?>
            <table class="table" style="font-size:0.875rem;">
                <thead>
                    <tr>
                        <th>顧客名</th>
                        <th style="text-align:right;">請求件数</th>
                        <th style="text-align:right;">合計金額</th>
                        <th style="text-align:right;">入金済</th>
                        <th style="text-align:right;">未入金</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projectPnL as $customer => $pnl): ?>
                    <tr>
                        <td><?= htmlspecialchars($customer) ?></td>
                        <td style="text-align:right;"><?= $pnl['count'] ?>件</td>
                        <td style="text-align:right; font-weight:600;">&yen;<?= number_format($pnl['total']) ?></td>
                        <td style="text-align:right; color:#10b981;">&yen;<?= number_format($pnl['paid']) ?></td>
                        <td style="text-align:right; color:<?= $pnl['unpaid'] > 0 ? '#ef4444' : '#6b7280' ?>;">&yen;<?= number_format($pnl['unpaid']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($invoiceLeaks)): ?>
<div class="card" style="margin-top:1.5rem; border-left:4px solid #ef4444;">
    <div class="card-header">
        <h3 style="margin:0; color:#ef4444;">請求漏れの可能性がある案件（<?= count($invoiceLeaks) ?>件）</h3>
    </div>
    <div class="card-body">
        <p style="font-size:0.85rem; color:var(--gray-600); margin-bottom:1rem;">ステータスが「設置済」「完了」の案件で、対応する請求が見つからないものです。</p>
        <table class="table" style="font-size:0.875rem;">
            <thead>
                <tr>
                    <th>P番号</th>
                    <th>現場名</th>
                    <th>顧客名</th>
                    <th>ステータス</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($invoiceLeaks, 0, 20) as $leak): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($leak['id'] ?? '') ?></strong></td>
                    <td><?= htmlspecialchars($leak['name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($leak['customer_name'] ?? '') ?></td>
                    <td><span style="background:#dbeafe; color:#1e40af; padding:0.2rem 0.5rem; border-radius:4px; font-size:0.75rem;"><?= htmlspecialchars($leak['status'] ?? '') ?></span></td>
                    <td><a href="master.php?search_pj=<?= urlencode($leak['id'] ?? '') ?>" style="color:#3b82f6; font-size:0.85rem;">案件確認</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- モーダル -->
<div id="invoiceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalInvoiceTitle"></h3>
            <span class="modal-close" onclick="closeInvoiceModal()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </span>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<script>
// ビュー切り替え
function switchView(view) {
    const url = new URL(window.location.href);
    url.searchParams.set('view', view);
    window.location.href = url.toString();
}

// 同期対象月切り替え
function toggleMonthInput(select) {
    const monthInput = document.getElementById('sync_target_month_input');
    const syncInfo = document.getElementById('sync_info');

    if (select.value === 'all') {
        monthInput.style.display = 'none';
        syncInfo.textContent = '過去3年分の請求書を同期';
    } else {
        monthInput.style.display = 'block';
        const month = monthInput.value;
        if (month) {
            const date = new Date(month + '-01');
            const year = date.getFullYear();
            const monthNum = date.getMonth() + 1;
            syncInfo.textContent = `${year}年${monthNum}月の請求書を同期`;
        }
    }
}

// 全請求書データ
const allInvoices = <?= json_encode($data['mf_invoices'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
const currentYearMonth = <?= json_encode($selectedYearMonth) ?>;

function showCustomerInvoices(partnerName) {
    let customerInvoices = allInvoices.filter(inv => inv.partner_name === partnerName);

    if (currentYearMonth) {
        customerInvoices = customerInvoices.filter(inv => {
            const salesDate = inv.sales_date || '';
            const normalizedDate = salesDate.replace(/\//g, '-');
            return normalizedDate.indexOf(currentYearMonth) === 0;
        });
    }

    if (customerInvoices.length === 0) return;

    let totalAmount = 0, totalSubtotal = 0, totalTax = 0;
    customerInvoices.forEach(invoice => {
        totalAmount += parseFloat(invoice.total_amount || 0);
        totalSubtotal += parseFloat(invoice.subtotal || 0);
        totalTax += parseFloat(invoice.tax || 0);
    });

    let titleText = partnerName + ' の請求書一覧';
    if (currentYearMonth) {
        const yearMonth = new Date(currentYearMonth + '-01');
        titleText = partnerName + ' の請求書（' + yearMonth.getFullYear() + '年' + (yearMonth.getMonth() + 1) + '月）';
    }
    document.getElementById('modalInvoiceTitle').textContent = titleText;

    let html = '<div class="summary-box">';
    html += '<div class="summary-row"><span>請求書数:</span><span>' + customerInvoices.length + '件</span></div>';
    html += '<div class="summary-row"><span>小計（税抜き）:</span><span>¥' + totalSubtotal.toLocaleString() + '</span></div>';
    html += '<div class="summary-row"><span>消費税:</span><span>¥' + totalTax.toLocaleString() + '</span></div>';
    html += '<div class="summary-row total"><span>合計金額:</span><span>¥' + totalAmount.toLocaleString() + '</span></div>';
    html += '</div>';

    customerInvoices.forEach(invoice => {
        html += '<div class="invoice-detail-item">';
        html += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">';
        html += '<div style="font-weight: 600;">' + escapeHtml(invoice.title || '-') + '</div>';
        html += '<div style="font-weight: 700; color: #1d4ed8;">¥' + parseFloat(invoice.total_amount || 0).toLocaleString() + '</div>';
        html += '</div>';
        html += '<div style="font-size: 0.875rem; color: #6b7280;">';
        html += '売上日: ' + escapeHtml(invoice.sales_date || '-') + ' | ';
        html += '請求番号: ' + escapeHtml(invoice.billing_number || '-');
        html += '</div>';
        html += '</div>';
    });

    document.getElementById('modalBody').innerHTML = html;
    document.getElementById('invoiceModal').classList.add('show');
}

function showSingleInvoice(invoiceId) {
    const invoice = allInvoices.find(inv => inv.id === invoiceId);
    if (!invoice) return;

    document.getElementById('modalInvoiceTitle').textContent = '請求書詳細';

    let html = '<div class="invoice-detail-item">';
    html += '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">';
    html += '<div>';
    html += '<div style="font-weight: 600; font-size: 1.1rem; margin-bottom: 0.25rem;">' + escapeHtml(invoice.partner_name || '-') + '</div>';
    html += '<div style="color: #6b7280;">' + escapeHtml(invoice.title || '-') + '</div>';
    html += '</div>';
    html += '<div style="font-weight: 700; color: #1d4ed8; font-size: 1.25rem;">¥' + parseFloat(invoice.total_amount || 0).toLocaleString() + '</div>';
    html += '</div>';

    html += '<div class="summary-box">';
    html += '<div class="summary-row"><span>小計（税抜き）:</span><span>¥' + parseFloat(invoice.subtotal || 0).toLocaleString() + '</span></div>';
    html += '<div class="summary-row"><span>消費税:</span><span>¥' + parseFloat(invoice.tax || 0).toLocaleString() + '</span></div>';
    html += '<div class="summary-row total"><span>合計金額:</span><span>¥' + parseFloat(invoice.total_amount || 0).toLocaleString() + '</span></div>';
    html += '</div>';

    html += '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; font-size: 0.875rem;">';
    html += '<div><strong>請求番号:</strong> ';
    if (invoice.id) {
        html += '<a href="https://invoice.moneyforward.com/billings/' + escapeHtml(invoice.id) + '" target="_blank" style="color: #3b82f6;">' + escapeHtml(invoice.billing_number || '-') + '</a>';
    } else {
        html += escapeHtml(invoice.billing_number || '-');
    }
    html += '</div>';
    html += '<div><strong>売上日:</strong> ' + escapeHtml(invoice.sales_date || '-') + '</div>';
    html += '<div><strong>請求日:</strong> ' + escapeHtml(invoice.billing_date || '-') + '</div>';
    html += '<div><strong>支払期限:</strong> ' + escapeHtml(invoice.due_date || '-') + '</div>';
    html += '</div>';

    // ステータス表示
    const postingStatus = invoice.posting_status || '';
    const isSent = ['sent', 'posted', '郵送済み', 'メール送信済み', '送付済み'].includes(postingStatus);
    const paymentStatus = invoice.payment_status || '';
    const isPaid = ['paid', '入金済み', '入金済'].includes(paymentStatus);

    html += '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; font-size: 0.875rem; margin-top: 1rem;">';
    html += '<div><strong>送付状況:</strong> <span class="tag ' + (isSent ? 'sent' : 'unsent') + '">' + (isSent ? '送付済' : '未送付') + '</span></div>';
    html += '<div><strong>入金状況:</strong> <span class="tag ' + (isPaid ? 'paid' : 'unpaid') + '">' + (isPaid ? '入金済' : '未入金') + '</span></div>';
    html += '</div>';

    if (invoice.project_id || invoice.assignee) {
        html += '<div style="margin-top: 1rem;">';
        if (invoice.project_id) {
            html += '<span class="tag project" style="margin-right: 0.5rem;">' + escapeHtml(invoice.project_id) + '</span>';
        }
        if (invoice.assignee) {
            html += '<span class="tag assignee">' + escapeHtml(invoice.assignee) + '</span>';
        }
        html += '</div>';
    }

    // MFで開くボタン
    if (invoice.id) {
        html += '<div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">';
        html += '<a href="https://invoice.moneyforward.com/billings/' + escapeHtml(invoice.id) + '" target="_blank" class="btn btn-secondary btn-icon">';
        html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
        html += 'MFで開く';
        html += '</a>';
        html += '</div>';
    }

    html += '</div>';

    document.getElementById('modalBody').innerHTML = html;
    document.getElementById('invoiceModal').classList.add('show');
}

function closeInvoiceModal() {
    document.getElementById('invoiceModal').classList.remove('show');
}

function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

window.onclick = function(event) {
    const modal = document.getElementById('invoiceModal');
    if (event.target === modal) {
        closeInvoiceModal();
    }
}

// 月入力の変更時
document.addEventListener('DOMContentLoaded', function() {
    const monthInput = document.getElementById('sync_target_month_input');
    if (monthInput) {
        monthInput.addEventListener('change', function() {
            const select = document.getElementById('sync_target_month');
            if (select.value === 'custom') {
                const date = new Date(this.value + '-01');
                document.getElementById('sync_info').textContent = `${date.getFullYear()}年${date.getMonth() + 1}月の請求書を同期`;
            }
        });
    }
});
</script>

</div><!-- /.page-container -->

<?php require_once '../functions/footer.php'; ?>
