<?php
require_once '../api/auth.php';
require_once '../api/mf-api.php';
require_once '../functions/mf-invoice-sync.php';

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

        $invoices = $client->getAllInvoices($from, $to, true);

        $debugInfo['parsed_invoices'] = $invoices;
        $debugInfo['invoice_count'] = count($invoices);

        // サンプルデータを保存（最初の3件）
        if (!empty($invoices)) {
            $debugInfo['sample_invoices'] = array_slice($invoices, 0, 3);
        }

        // デバッグ情報をファイルに保存
        $debugFile = dirname(__DIR__) . '/mf-sync-debug.json';
        file_put_contents($debugFile, json_encode($debugInfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // 共通関数で同期実行
        $syncResult = syncMfInvoices($data, $invoices, $from, $to);
        $data = $syncResult['data'];

        saveData($data);

        // 自動同期の場合はリダイレクトしない
        if (!$shouldAutoSync) {
            header('Location: finance.php?synced=' . count($invoices) . '&new=' . $syncResult['new'] . '&updated=' . $syncResult['updated'] . '&deleted=' . $syncResult['deleted']);
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

<style<?= nonceAttr() ?>>
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
    background: linear-gradient(135deg, #555 0%, #333 100%);
    color: white;
    border: none;
}

.kpi-card.success {
    background: linear-gradient(135deg, #666 0%, #444 100%);
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
    color: #555;
}

.kpi-change.down {
    color: #c62828;
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
    background: linear-gradient(180deg, #666 0%, #444 100%);
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

/* 当月売上サマリー */
.current-month-summary {
    background: white;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.summary-main {
    display: flex;
    align-items: baseline;
    gap: 1rem;
}

.summary-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #6b7280;
}

.summary-amount {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
}

.summary-count {
    font-size: 0.875rem;
    color: #9ca3af;
}

.summary-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    color: #374151;
    font-size: 0.8125rem;
    cursor: pointer;
    transition: background 0.2s;
}

.summary-toggle:hover {
    background: #e5e7eb;
}

.summary-toggle svg {
    transition: transform 0.2s;
}

.summary-toggle.open svg {
    transform: rotate(180deg);
}

/* 過去月履歴 */
.monthly-history {
    background: #f9fafb;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    border: 1px solid #e5e7eb;
}

.monthly-history .stats-row {
    margin-bottom: 0;
}

.monthly-history .stat-card {
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.monthly-history .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.monthly-history .stat-card.selected {
    border: 2px solid var(--primary);
    background: #eff6ff;
}

/* タブ切り替え */
.view-tabs {
    display: flex;
    gap: 0.25rem;
    background: #f3f4f6;
    border-radius: 10px;
    padding: 5px;
    margin-bottom: 1.5rem;
}

.view-tab {
    flex: 1;
    padding: 0.625rem 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 8px;
    font-size: 0.8rem;
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
    color: #333;
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
    background: #e8f6f3;
    color: #117a65;
    border: 1px solid #a3d9cc;
}

.tag.assignee {
    background: #f0f0f0;
    color: #555;
}

.tag.default {
    background: #f3f4f6;
    color: #4b5563;
}

/* 同期・フィルタエリア */
.filter-bar {
    background: white;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.filter-form {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
    min-width: 0;
}

.filter-select {
    padding: 0.5rem 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 0.8125rem;
    background: white;
    color: #374151;
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s;
    flex-shrink: 0;
}

.filter-select:focus {
    outline: none;
    border-color: #666;
    box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.1);
}

.filter-input {
    padding: 0.5rem 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 0.8125rem;
    background: white;
    flex: 1;
    min-width: 150px;
    max-width: 280px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.filter-input:focus {
    outline: none;
    border-color: #666;
    box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.1);
}

.filter-input::placeholder {
    color: #9ca3af;
}

.action-buttons {
    display: flex;
    gap: 0.375rem;
    flex-shrink: 0;
}

.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    padding: 0.5rem 0.625rem;
}

/* スマホ対応 */
@media (max-width: 768px) {
    .filter-bar {
        flex-wrap: wrap;
    }
    .filter-form {
        flex-wrap: wrap;
        width: 100%;
    }
    .filter-input {
        width: 100%;
        max-width: none;
    }
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
    transition: transform 0.2s, box-shadow 0.2s;
}

.summary-card.clickable,
.summary-card[onclick] {
    cursor: pointer;
}

.summary-card.clickable:hover,
.summary-card[onclick]:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    border-color: #666;
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
    color: #333;
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
    background: linear-gradient(135deg, #f5f5f5 0%, #eee 100%);
    border-left: 4px solid #555;
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
    border-top: 2px solid #555;
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
    background: #f0f0f0;
    color: #333;
    border: 1px solid #ccc;
}

.alert-error {
    background: #fee2e2;
    color: #c62828;
    border: 1px solid #fca5a5;
}

/* 同期設定カード */
.sync-card {
    background: linear-gradient(135deg, #f5f5f5 0%, #eee 100%);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid #ccc;
}

.sync-form {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.sync-label {
    font-weight: 500;
    color: #333;
}

.sync-info {
    color: #555;
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
        MFから<?= intval($_GET['synced']) ?>件の請求書を同期しました
        <?php if (isset($_GET['new'])): ?>
            （新規: <?= intval($_GET['new']) ?>件、更新: <?= intval($_GET['updated'] ?? 0) ?>件<?php if (isset($_GET['deleted']) && intval($_GET['deleted']) > 0): ?>、削除: <?= intval($_GET['deleted']) ?>件<?php endif; ?>）
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <?php if ($_GET['error'] === 'mf_not_configured'): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            MF APIの設定が完了していません。<a href="mf-settings.php"      class="text-inherit text-underline">設定ページ</a>から設定してください。
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

        // タグ検索フィルタ
        // カンマ区切り → OR検索（例: 小黒,西井 → 小黒または西井）
        // スペース区切り → AND検索（例: 小黒 PJ001 → 小黒かつPJ001）
        $tagMatch = true;
        if (!empty($searchTag)) {
            // カンマでOR条件に分割
            $orGroups = preg_split('/[,、]+/', trim($searchTag));
            $tagMatch = false; // OR条件なので、1つでも合えばtrue

            foreach ($orGroups as $orGroup) {
                $orGroup = trim($orGroup);
                if (empty($orGroup)) continue;

                // スペースでAND条件に分割
                $searchKeywords = preg_split('/\s+/', $orGroup);
                $groupMatch = true;

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

                    // 1つでもキーワードが見つからなければ、このANDグループは不一致
                    if (!$keywordMatch) {
                        $groupMatch = false;
                        break;
                    }
                }

                // 1つでもORグループが一致すればOK
                if ($groupMatch) {
                    $tagMatch = true;
                    break;
                }
            }
        }

        // フィルタが一致した場合のみ追加
        if ($yearMonthMatch && $tagMatch) {
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
        $projectPnL[$customer] = ['total' => 0, 'count' => 0];
    }
    $amount = floatval($inv['total_amount'] ?? 0);
    $projectPnL[$customer]['total'] += $amount;
    $projectPnL[$customer]['count']++;
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
        <button type="button" class="btn btn-primary" id="openSyncModalBtn">MFから同期</button>
        <?php endif; ?>
    </div>
</div>

<!-- 当月売上サマリー -->
<?php
$displayMonth = $selectedYearMonth ?: date('Y-m');
$displayMonthData = $monthlyComparison[$displayMonth] ?? ['sales' => $totalAmount, 'count' => $invoiceCount];
$displayMonthLabel = date('Y年n月', strtotime($displayMonth . '-01'));
$isCurrentMonth = $displayMonth === date('Y-m');
?>
<div class="current-month-summary">
    <div class="summary-main">
        <div class="summary-label"><?= $displayMonthLabel ?><?= $isCurrentMonth ? ' (今月)' : '' ?></div>
        <div class="summary-amount">¥<?= number_format($displayMonthData['sales']) ?></div>
        <div class="summary-count"><?= $displayMonthData['count'] ?>件</div>
    </div>
    <button type="button" class="summary-toggle" id="toggleMonthlyHistoryBtn">
        <span>過去の売上</span>
        <svg id="toggle-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </button>
</div>

<!-- 過去月の売上（折りたたみ） -->
<div id="monthly-history"   class="monthly-history d-none">
    <div class="stats-row">
        <?php foreach ($monthlyComparison as $month => $mc):
            $label = date('n月', strtotime($month . '-01'));
            $isCurrent = $month === date('Y-m');
            $isSelected = $month === $selectedYearMonth;
        ?>
        <a href="finance.php?year_month=<?= urlencode($month) ?>&view=<?= htmlspecialchars($viewMode) ?>"
           class="stat-card <?= $isSelected ? 'selected' : '' ?> text-no-underline" <?= $isCurrent ? 'style="border:2px solid var(--primary);"' : '' ?>>
            <div class="stat-label"><?= htmlspecialchars($label) ?><?= $isCurrent ? ' (今月)' : '' ?></div>
            <div class="stat-number">&yen;<?= number_format($mc['sales']) ?></div>
            <div    class="text-xs text-gray-500"><?= $mc['count'] ?>件</div>
        </a>
        <?php endforeach; ?>
    </div>
</div>


<!-- フィルタバー -->
<div class="filter-bar">
    <form method="GET" action="" class="filter-form">
        <select name="year_month" class="filter-select">
            <option value="">全期間</option>
            <?php foreach ($availableYearMonths as $ym): ?>
                <option value="<?= htmlspecialchars($ym) ?>" <?= $selectedYearMonth === $ym ? 'selected' : '' ?>>
                    <?= date('Y年n月', strtotime($ym . '-01')) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input
            type="text"
            name="search_tag"
            value="<?= htmlspecialchars($searchTag) ?>"
            placeholder="PJ番号、担当者、請求書番号（カンマでOR検索）"
            class="filter-input"
        >
        <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">
        <button type="submit" class="btn btn-primary">検索</button>
        <?php if ($selectedYearMonth || $searchTag): ?>
            <a href="finance.php?view=<?= htmlspecialchars($viewMode) ?>" class="btn btn-secondary">クリア</a>
        <?php endif; ?>
    </form>
    <div class="action-buttons">
        <?php if (MFApiClient::isConfigured() && isset($data['mf_invoices']) && !empty($data['mf_invoices'])): ?>
            <a href="mf-mapping.php" class="btn btn-secondary btn-icon" title="請求書マッピング">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            </a>
            <a href="mf-monthly.php" class="btn btn-success btn-icon" title="月別集計">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </a>
        <?php endif; ?>
        <?php if (!empty($filteredInvoices)): ?>
            <a href="download-invoices-csv.php?year_month=<?= urlencode($selectedYearMonth) ?>&search_tag=<?= urlencode($searchTag) ?>"
               class="btn btn-secondary btn-icon" title="CSVダウンロード">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ビュー切り替えタブ -->
<div class="view-tabs">
    <button class="view-tab <?= $viewMode === 'table' ? 'active' : '' ?>" data-view="table">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
        テーブル
    </button>
    <button class="view-tab <?= $viewMode === 'card' ? 'active' : '' ?>" data-view="card">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        カード
    </button>
    <button class="view-tab <?= $viewMode === 'customer' ? 'active' : '' ?>" data-view="customer">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        顧客別
    </button>
    <button class="view-tab <?= $viewMode === 'assignee' ? 'active' : '' ?>" data-view="assignee">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        担当者別
    </button>
</div>

<!-- テーブル表示 -->
<div id="view-table" class="tab-content <?= $viewMode === 'table' ? 'active' : '' ?>">
    <div class="card">
        <div   class="card-body p-0">
            <?php if (empty($filteredInvoices)): ?>
                <p      class="text-center text-gray-600 p-3rem">
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
                                <th  class="text-right">金額</th>
                                <th  class="text-right">税抜</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredInvoices as $invoice): ?>
                                <tr data-invoice-id="<?= htmlspecialchars($invoice['id'], ENT_QUOTES) ?>" class="cursor-pointer">
                                    <td>
                                        <?php if (!empty($invoice['project_id'])): ?>
                                            <span class="tag project"><?= htmlspecialchars($invoice['project_id']) ?></span>
                                        <?php else: ?>
                                            <span  class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($invoice['partner_name'] ?? '-') ?></td>
                                    <td>
                                        <?php if (!empty($invoice['assignee'])):
                                            $assigneeColor = getAssigneeColor($invoice['assignee']);
                                        ?>
                                            <span         class="tag" style="background: <?= $assigneeColor['bg'] ?>; color: <?= $assigneeColor['text'] ?>;"><?= htmlspecialchars($invoice['assignee']) ?></span>
                                        <?php else: ?>
                                            <span  class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($invoice['id'])): ?>
                                            <a href="https://invoice.moneyforward.com/billings/<?= htmlspecialchars($invoice['id']) ?>" target="_blank" rel="noopener noreferrer" class="invoice-link text-3b8 font-semibold">
                                                <?= htmlspecialchars($invoice['billing_number']) ?>
                                            </a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($invoice['billing_number']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($invoice['title']) ?></td>
                                    <td><?= htmlspecialchars($invoice['sales_date']) ?></td>
                                    <td   class="amount-cell text-right">¥<?= number_format($invoice['total_amount']) ?></td>
                                    <td  class="text-right">¥<?= number_format($invoice['subtotal']) ?></td>
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
        <p      class="text-center text-gray-600 p-3rem">
            請求書がありません。
        </p>
    <?php else: ?>
        <div class="invoice-cards">
            <?php foreach ($filteredInvoices as $invoice): ?>
                <div class="invoice-card" data-invoice-id="<?= htmlspecialchars($invoice['id'], ENT_QUOTES) ?>">
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
                        <?php if (!empty($invoice['assignee'])):
                            $cardAssigneeColor = getAssigneeColor($invoice['assignee']);
                        ?>
                            <span         class="tag" style="background: <?= $cardAssigneeColor['bg'] ?>; color: <?= $cardAssigneeColor['text'] ?>;"><?= htmlspecialchars($invoice['assignee']) ?></span>
                        <?php endif; ?>
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
            <div class="summary-card" data-customer-name="<?= htmlspecialchars($name, ENT_QUOTES) ?>" class="cursor-pointer">
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
        <?php foreach ($filteredAssigneeTotals as $name => $data):
            $summaryAssigneeColor = getAssigneeColor($name);
        ?>
            <div class="summary-card clickable" data-assignee-name="<?= htmlspecialchars($name, ENT_QUOTES) ?>">
                <div class="summary-card-header">
                    <div class="summary-card-name">
                        <?php if ($name !== '未設定'): ?>
                            <span         class="tag text-09" style="background: <?= $summaryAssigneeColor['bg'] ?>; color: <?= $summaryAssigneeColor['text'] ?>;"><?= htmlspecialchars($name) ?></span>
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
<div   class="card mt-3">
    <div class="card-header"><h3  class="m-0">顧客別売上集計（上位10社）</h3></div>
    <div class="card-body">
        <?php if (empty($projectPnL)): ?>
            <p    class="text-center p-2 text-gray-500">請求データがありません</p>
        <?php else: ?>
            <table     class="table text-14">
                <thead>
                    <tr>
                        <th>顧客名</th>
                        <th  class="text-right">請求件数</th>
                        <th  class="text-right">合計金額</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projectPnL as $customer => $pnl): ?>
                    <tr>
                        <td><?= htmlspecialchars($customer) ?></td>
                        <td  class="text-right"><?= $pnl['count'] ?>件</td>
                        <td    class="text-right font-semibold">&yen;<?= number_format($pnl['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($invoiceLeaks)): ?>
<div         class="card mt-3 card-border-danger">
    <div class="card-header">
        <h3        class="m-0 text-ef4">請求漏れの可能性がある案件（<?= count($invoiceLeaks) ?>件）</h3>
    </div>
    <div class="card-body">
        <p    class="text-sm mb-2 text-gray-600">ステータスが「設置済」「完了」の案件で、対応する請求が見つからないものです。</p>
        <table     class="table text-14">
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
                    <td><span        class="rounded text-xs status-badge-blue"><?= htmlspecialchars($leak['status'] ?? '') ?></span></td>
                    <td><a href="master.php?search_pj=<?= urlencode($leak['id'] ?? '') ?>" class="link-blue-sm">案件確認</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 請求書詳細モーダル -->
<div id="invoiceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalInvoiceTitle"></h3>
            <button type="button" class="modal-close" id="closeInvoiceModalBtn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<!-- MF同期モーダル -->
<div id="syncModal" class="modal">
    <div     class="modal-content max-w-500">
        <div class="modal-header">
            <h3>🔄 MFから請求書を同期</h3>
            <button type="button" class="modal-close" id="closeSyncModalBtn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div  class="mb-3">
                <label  class="d-block mb-1 font-medium">同期する期間を選択</label>
                <div  class="d-flex gap-1 align-center">
                    <input type="month" id="syncMonth" class="form-input form-flex-container" value="<?= htmlspecialchars(date('Y-m')) ?>">
                    <button type="button" id="allPeriodBtn"   class="btn btn-secondary whitespace-nowrap">全期間</button>
                </div>
                <div id="allPeriodInfo"        class="d-none mt-1 p-1 text-14 text-924 bg-yellow-info">
                    ⚠️ 過去3年分の全請求書を同期します（時間がかかる場合があります）
                </div>
            </div>
            <div  class="d-flex gap-1 flex-wrap mb-3">
                <?php for ($i = 0; $i < 6; $i++):
                    $m = date('Y-m', strtotime("-{$i} month"));
                    $label = date('Y年n月', strtotime("-{$i} month"));
                ?>
                <button type="button" class="btn btn-secondary month-btn month-btn-size" data-month="<?= $m ?>"><?= $label ?></button>
                <?php endfor; ?>
            </div>
            <div id="syncResult"  class="mb-2 p-2 rounded-lg d-none"></div>
            <div  class="d-flex gap-1 justify-between align-center">
                <?php if (isAdmin()): ?>
                <button type="button" id="clearBtn"         class="btn btn-outline text-red border-red-600">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    データクリア
                </button>
                <?php else: ?>
                <div></div>
                <?php endif; ?>
                <div  class="d-flex gap-1">
                    <button type="button" class="btn btn-secondary" id="cancelSyncBtn">キャンセル</button>
                    <button type="button" id="syncBtn" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                        同期開始
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script<?= nonceAttr() ?>>
// XSS対策：HTMLエスケープ関数
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ビュー切り替え
function switchView(view) {
    const url = new URL(window.location.href);
    url.searchParams.set('view', view);
    window.location.href = url.toString();
}

// 過去月履歴の表示切替
function toggleMonthlyHistory() {
    const history = document.getElementById('monthly-history');
    const toggle = document.querySelector('.summary-toggle');
    if (history.style.display === 'none') {
        history.style.display = 'block';
        toggle.classList.add('open');
    } else {
        history.style.display = 'none';
        toggle.classList.remove('open');
    }
}

// イベントリスナー登録
document.addEventListener('DOMContentLoaded', function() {
    // ビュー切り替えタブ
    document.querySelectorAll('.view-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const view = this.dataset.view;
            switchView(view);
        });
    });

    // 過去の売上トグル
    const toggleBtn = document.getElementById('toggleMonthlyHistoryBtn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleMonthlyHistory);
    }

    // MF同期モーダル開く
    const openSyncBtn = document.getElementById('openSyncModalBtn');
    if (openSyncBtn) {
        openSyncBtn.addEventListener('click', openSyncModal);
    }

    // 請求書詳細モーダル閉じる
    const closeInvoiceBtn = document.getElementById('closeInvoiceModalBtn');
    if (closeInvoiceBtn) {
        closeInvoiceBtn.addEventListener('click', closeInvoiceModal);
    }

    // MF同期モーダル閉じる
    const closeSyncBtn = document.getElementById('closeSyncModalBtn');
    if (closeSyncBtn) {
        closeSyncBtn.addEventListener('click', closeSyncModal);
    }

    const cancelSyncBtn = document.getElementById('cancelSyncBtn');
    if (cancelSyncBtn) {
        cancelSyncBtn.addEventListener('click', closeSyncModal);
    }

    // 全期間ボタン
    const allPeriodBtn = document.getElementById('allPeriodBtn');
    if (allPeriodBtn) {
        allPeriodBtn.addEventListener('click', toggleAllPeriod);
    }

    // 月選択ボタン
    document.querySelectorAll('.month-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const month = this.dataset.month;
            selectMonth(month);
        });
    });

    // 同期開始ボタン
    const syncBtn = document.getElementById('syncBtn');
    if (syncBtn) {
        syncBtn.addEventListener('click', syncNow);
    }

    // データクリアボタン
    const clearBtn = document.getElementById('clearBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearMfInvoices);
    }

    // テーブル行クリック（請求書詳細表示）
    document.querySelectorAll('tr[data-invoice-id]').forEach(row => {
        row.addEventListener('click', function() {
            const invoiceId = this.dataset.invoiceId;
            showSingleInvoice(invoiceId);
        });
    });

    // カード表示の請求書クリック
    document.querySelectorAll('.invoice-card[data-invoice-id]').forEach(card => {
        card.addEventListener('click', function() {
            const invoiceId = this.dataset.invoiceId;
            showSingleInvoice(invoiceId);
        });
    });

    // 顧客別カードクリック
    document.querySelectorAll('.summary-card[data-customer-name]').forEach(card => {
        card.addEventListener('click', function() {
            const customerName = this.dataset.customerName;
            showCustomerInvoices(customerName);
        });
    });

    // 担当者別カードクリック
    document.querySelectorAll('.summary-card[data-assignee-name]').forEach(card => {
        card.addEventListener('click', function() {
            const assigneeName = this.dataset.assigneeName;
            showAssigneeInvoices(assigneeName);
        });
    });
});

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
        html += '<div  class="d-flex justify-between align-center mb-1">';
        html += '<div   class="font-semibold">' + escapeHtml(invoice.title || '-') + '</div>';
        html += '<div        class="font-bold" class="text-1d4">¥' + parseFloat(invoice.total_amount || 0).toLocaleString() + '</div>';
        html += '</div>';
        html += '<div    class="text-gray-500 text-14">';
        html += '売上日: ' + escapeHtml(invoice.sales_date || '-') + ' | ';
        html += '請求番号: ' + escapeHtml(invoice.billing_number || '-');
        html += '</div>';
        html += '</div>';
    });

    document.getElementById('modalBody').innerHTML = html;
    document.getElementById('invoiceModal').classList.add('show');
}

function showAssigneeInvoices(assigneeName) {
    // テーブルビューに切り替えて担当者名で検索
    const params = new URLSearchParams();
    params.set('view', 'table');
    params.set('search_tag', assigneeName);
    if (currentYearMonth) {
        params.set('year_month', currentYearMonth);
    }
    window.location.href = 'finance.php?' + params.toString();
}

function showSingleInvoice(invoiceId) {
    const invoice = allInvoices.find(inv => inv.id === invoiceId);
    if (!invoice) return;

    document.getElementById('modalInvoiceTitle').textContent = '請求書詳細';

    let html = '<div class="invoice-detail-item">';
    html += '<div  class="d-flex justify-between align-start mb-2">';
    html += '<div>';
    html += '<div       class="invoice-detail-title">' + escapeHtml(invoice.partner_name || '-') + '</div>';
    html += '<div  class="text-gray-500">' + escapeHtml(invoice.title || '-') + '</div>';
    html += '</div>';
    html += '<div        class="invoice-detail-amount">¥' + parseFloat(invoice.total_amount || 0).toLocaleString() + '</div>';
    html += '</div>';

    html += '<div class="summary-box">';
    html += '<div class="summary-row"><span>小計（税抜き）:</span><span>¥' + parseFloat(invoice.subtotal || 0).toLocaleString() + '</span></div>';
    html += '<div class="summary-row"><span>消費税:</span><span>¥' + parseFloat(invoice.tax || 0).toLocaleString() + '</span></div>';
    html += '<div class="summary-row total"><span>合計金額:</span><span>¥' + parseFloat(invoice.total_amount || 0).toLocaleString() + '</span></div>';
    html += '</div>';

    html += '<div        class="gap-2 grid text-14 invoice-detail-grid">';
    html += '<div><strong>請求番号:</strong> ';
    if (invoice.id) {
        html += '<a href="https://invoice.moneyforward.com/billings/' + escapeHtml(invoice.id) + '" target="_blank"     class="text-3b8">' + escapeHtml(invoice.billing_number || '-') + '</a>';
    } else {
        html += escapeHtml(invoice.billing_number || '-');
    }
    html += '</div>';
    html += '<div><strong>売上日:</strong> ' + escapeHtml(invoice.sales_date || '-') + '</div>';
    html += '<div><strong>請求日:</strong> ' + escapeHtml(invoice.billing_date || '-') + '</div>';
    html += '<div><strong>支払期限:</strong> ' + escapeHtml(invoice.due_date || '-') + '</div>';
    html += '</div>';

    if (invoice.project_id || invoice.assignee) {
        html += '<div  class="mt-2">';
        if (invoice.project_id) {
            html += '<span   class="tag project mr-1">' + escapeHtml(invoice.project_id) + '</span>';
        }
        if (invoice.assignee) {
            const assigneeColor = getAssigneeColor(invoice.assignee);
            html += '<span        class="d-inline-block rounded text-xs font-medium tag-xs" style="background: ' + assigneeColor.bg + '; color: ' + assigneeColor.text + ';">' + escapeHtml(invoice.assignee) + '</span>';
        }
        html += '</div>';
    }

    // MFで開くボタン
    if (invoice.id) {
        html += '<div        class="invoice-detail-divider">';
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

// 担当者名からユニークな色を取得（PHPと同じロジック）
function getAssigneeColor(name) {
    if (!name || name === '-') {
        return { bg: '#f0f0f0', text: '#666' };
    }
    const colors = [
        { bg: '#e8eaf6', text: '#3949ab' },  // インディゴ
        { bg: '#e0f2f1', text: '#00897b' },  // ティール
        { bg: '#ede7f6', text: '#5e35b1' },  // 紫
        { bg: '#fce4ec', text: '#c62828' },  // 赤
        { bg: '#e8f5e9', text: '#2e7d32' },  // 緑
        { bg: '#fff3e0', text: '#e65100' },  // オレンジ
        { bg: '#e3f2fd', text: '#1565c0' },  // 青
        { bg: '#fce4ec', text: '#ad1457' },  // ピンク
        { bg: '#eceff1', text: '#546e7a' },  // ブルーグレー
        { bg: '#efebe9', text: '#5d4037' },  // ブラウン
    ];
    // crc32の簡易実装
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = ((hash << 5) - hash) + name.charCodeAt(i);
        hash = hash & hash;
    }
    const index = Math.abs(hash) % colors.length;
    return colors[index];
}

window.onclick = function(event) {
    const invoiceModal = document.getElementById('invoiceModal');
    const syncModal = document.getElementById('syncModal');
    if (event.target === invoiceModal) {
        closeInvoiceModal();
    }
    if (event.target === syncModal) {
        closeSyncModal();
    }
}

// MF同期モーダル
const csrfToken = '<?= generateCsrfToken() ?>';

function openSyncModal() {
    document.getElementById('syncModal').classList.add('show');
    document.getElementById('syncResult').style.display = 'none';
}

function closeSyncModal() {
    document.getElementById('syncModal').classList.remove('show');
}

// 全期間モードのフラグ
let isAllPeriodMode = false;

function toggleAllPeriod() {
    const btn = document.getElementById('allPeriodBtn');
    const monthInput = document.getElementById('syncMonth');
    const info = document.getElementById('allPeriodInfo');
    const monthBtns = document.querySelectorAll('.month-btn');

    isAllPeriodMode = !isAllPeriodMode;

    if (isAllPeriodMode) {
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-primary');
        btn.textContent = '✓ 全期間';
        monthInput.disabled = true;
        monthInput.style.opacity = '0.5';
        info.style.display = 'block';
        monthBtns.forEach(b => b.disabled = true);
    } else {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-secondary');
        btn.textContent = '全期間';
        monthInput.disabled = false;
        monthInput.style.opacity = '1';
        info.style.display = 'none';
        monthBtns.forEach(b => b.disabled = false);
    }
}

function selectMonth(month) {
    document.getElementById('syncMonth').value = month;
    // 全期間モードを解除
    if (isAllPeriodMode) {
        toggleAllPeriod();
    }
}

async function syncNow() {
    const month = isAllPeriodMode ? 'all' : document.getElementById('syncMonth').value;
    if (!month) {
        alert('同期する月を選択してください');
        return;
    }

    const btn = document.getElementById('syncBtn');
    const result = document.getElementById('syncResult');

    btn.disabled = true;
    const loadingMsg = isAllPeriodMode ? '全期間同期中...' : '同期中...';
    btn.innerHTML = '<span        class="align-center gap-05" class="d-inline-flex">' + loadingMsg + '</span>';
    result.style.display = 'block';
    result.style.background = '#f3f4f6';
    result.style.color = '#6b7280';
    result.textContent = isAllPeriodMode
        ? '全期間の請求書を同期中です。しばらくお待ちください（数分かかる場合があります）...'
        : '同期中です。しばらくお待ちください...';

    try {
        const response = await fetch('/api/sync-invoices.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: 'target_month=' + encodeURIComponent(month)
        });

        const data = await response.json();

        if (data.success) {
            result.style.background = '#dcfce7';
            result.style.color = '#166534';
            result.innerHTML = '<strong>✓ ' + escapeHtml(data.message) + '</strong>';
            if (data.period) {
                result.innerHTML += '<br><small>期間: ' + escapeHtml(data.period.from) + ' 〜 ' + escapeHtml(data.period.to) + '</small>';
            }
            // 3秒後にページをリロード
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            result.style.background = '#fee2e2';
            result.style.color = '#dc2626';
            result.textContent = '❌ エラー: ' + (data.error || '同期に失敗しました');
        }
    } catch (e) {
        result.style.background = '#fee2e2';
        result.style.color = '#dc2626';
        result.textContent = '❌ エラー: ' + e.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>同期開始';
    }
}

// MF請求書データをクリア
async function clearMfInvoices() {
    if (!confirm('MF請求書データをすべてクリアしますか？\n\nクリア後、必要な月を再同期してください。')) {
        return;
    }

    const btn = document.getElementById('clearBtn');
    const result = document.getElementById('syncResult');

    btn.disabled = true;
    btn.textContent = 'クリア中...';

    result.style.display = 'block';
    result.style.background = '#fef3c7';
    result.style.color = '#92400e';
    result.textContent = '⏳ データをクリアしています...';

    try {
        const response = await fetch('/api/clear-mf-invoices.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= generateCsrfToken() ?>'
            }
        });

        const data = await response.json();

        if (data.success) {
            result.style.background = '#dcfce7';
            result.style.color = '#166534';
            result.textContent = '✅ ' + data.message;
            // 3秒後にページをリロード
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            result.style.background = '#fee2e2';
            result.style.color = '#dc2626';
            result.textContent = '❌ エラー: ' + (data.error || 'クリアに失敗しました');
        }
    } catch (e) {
        result.style.background = '#fee2e2';
        result.style.color = '#dc2626';
        result.textContent = '❌ エラー: ' + e.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>データクリア';
    }
}
</script>

</div><!-- /.page-container -->

<script<?= nonceAttr() ?>>
// 請求書リンクのクリック時にイベント伝播を停止（行全体のクリックイベントを防ぐ）
document.querySelectorAll('.invoice-link').forEach(link => {
    link.addEventListener('click', function(e) {
        e.stopPropagation();
    });
});
</script>

<?php require_once '../functions/footer.php'; ?>
