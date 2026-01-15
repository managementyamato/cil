<?php
require_once 'config.php';
require_once 'mf-api.php';

// 編集権限チェック
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

// データ読み込み
$data = getData();

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
        $syncConfigFile = __DIR__ . '/mf-sync-config.json';
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

        // 指定月の開始日と終了日を計算
        $from = date('Y-m-01', strtotime($targetMonth . '-01'));
        $to = date('Y-m-t', strtotime($targetMonth . '-01'));
        $invoices = $client->getAllInvoices($from, $to);
        $debugInfo['request_params'] = array('from' => $from, 'to' => $to, 'note' => date('Y年n月', strtotime($targetMonth . '-01')) . 'の請求書を取得');

        $debugInfo['parsed_invoices'] = $invoices;
        $debugInfo['invoice_count'] = count($invoices);

        // サンプルデータを保存（最初の3件）
        if (!empty($invoices)) {
            $debugInfo['sample_invoices'] = array_slice($invoices, 0, 3);
        }

        // デバッグ情報をファイルに保存
        $debugFile = __DIR__ . '/mf-sync-debug.json';
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
            // MoneyForward APIは一覧取得時にsubtotalとtaxを返す
            $subtotal = floatval($invoice['subtotal'] ?? 0);
            $tax = floatval($invoice['tax'] ?? 0);
            $total = floatval($invoice['total_amount'] ?? 0);

            // もしsubtotalが0で、total_amountがある場合は、明細から計算を試みる
            if ($subtotal === 0.0 && $total > 0) {
                if (isset($invoice['items']) && is_array($invoice['items'])) {
                    foreach ($invoice['items'] as $item) {
                        $subtotal += floatval($item['price'] ?? 0) * floatval($item['quantity'] ?? 0);
                    }
                    $tax = $total - $subtotal;
                }
            }

            // それでもsubtotalが0の場合は、消費税10%として逆算
            if ($subtotal === 0.0 && $total > 0) {
                $subtotal = round($total / 1.1);
                $tax = $total - $subtotal;
            }

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


require_once 'header.php';
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
    max-width: 600px;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-label {
    color: var(--gray-600);
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: bold;
}

.stat-value.positive {
    color: #10b981;
}

.stat-value.negative {
    color: #ef4444;
}

</style>

<?php if (isset($_GET['synced'])): ?>
    <div class="alert alert-success">
        MFから<?= intval($_GET['synced']) ?>件の請求書を取得しました
        <?php if (isset($_GET['new'])): ?>
            （新規: <?= intval($_GET['new']) ?>件、スキップ: <?= intval($_GET['skip'] ?? 0) ?>件）
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <?php if ($_GET['error'] === 'mf_not_configured'): ?>
        <div class="alert alert-error">MF APIの設定が完了していません。<a href="mf-settings.php" style="color: inherit; text-decoration: underline;">設定ページ</a>から設定してください。</div>
    <?php else: ?>
        <div class="alert alert-error">エラー: <?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php
// フィルタの取得（GETパラメータから）
$selectedYearMonth = isset($_GET['year_month']) ? $_GET['year_month'] : '';

// 全請求書から年月のリストを生成
$availableYearMonths = array();

if (isset($data['mf_invoices']) && !empty($data['mf_invoices'])) {
    foreach ($data['mf_invoices'] as $invoice) {
        // 年月を抽出
        $salesDate = $invoice['sales_date'] ?? '';
        if ($salesDate && preg_match('/^(\d{4})[-\/](\d{2})/', $salesDate, $matches)) {
            $yearMonth = $matches[1] . '-' . $matches[2];
            if (!in_array($yearMonth, $availableYearMonths)) {
                $availableYearMonths[] = $yearMonth;
            }
        }
    }
}

// 降順ソート（新しい月が上に）
rsort($availableYearMonths);

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

        // フィルタが一致した場合のみ追加
        if ($yearMonthMatch) {
            $filteredInvoices[] = $invoice;
            $totalAmount += floatval($invoice['total_amount'] ?? 0);
            $totalTax += floatval($invoice['tax'] ?? 0);
        }
    }
}

$totalSubtotal = $totalAmount - $totalTax;
?>

<!-- 年月選択フォーム -->
<div style="margin-bottom: 1.5rem;">
    <form method="GET" action="" style="display: flex; gap: 0.5rem; align-items: center;">
        <label for="year_month" style="font-weight: 500; color: var(--gray-700);">表示月:</label>
        <select name="year_month" id="year_month" class="form-input" style="width: auto; min-width: 150px;" onchange="this.form.submit()">
            <option value="">全期間</option>
            <?php foreach ($availableYearMonths as $ym): ?>
                <option value="<?= htmlspecialchars($ym) ?>" <?= $selectedYearMonth === $ym ? 'selected' : '' ?>>
                    <?= date('Y年n月', strtotime($ym . '-01')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">
            <?= $selectedYearMonth ? date('Y年n月', strtotime($selectedYearMonth . '-01')) . ' 総売上' : '総売上（全期間）' ?>
        </div>
        <div class="stat-value">¥<?= number_format($totalAmount) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">
            <?= $selectedYearMonth ? date('Y年n月', strtotime($selectedYearMonth . '-01')) . ' 税抜き' : '税抜き（全期間）' ?>
        </div>
        <div class="stat-value">¥<?= number_format($totalSubtotal) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">
            請求書一覧
            <?php if ($selectedYearMonth): ?>
                <span style="font-size: 1rem; font-weight: normal; color: var(--gray-600);">
                    (<?= date('Y年n月', strtotime($selectedYearMonth . '-01')) ?>)
                </span>
            <?php endif; ?>
        </h2>
        <div style="display: flex; gap: 0.5rem;">
            <?php if (MFApiClient::isConfigured()): ?>
                <form method="POST" action="" style="margin: 0;">
                    <button type="submit" name="sync_from_mf" class="btn btn-primary" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                        MFから同期
                    </button>
                </form>
                <?php if (isset($data['mf_invoices']) && !empty($data['mf_invoices'])): ?>
                    <a href="mf-monthly.php" class="btn btn-success" style="font-size: 0.875rem; padding: 0.5rem 1rem; text-decoration: none;">
                        月別集計 (<?= count($data['mf_invoices']) ?>件)
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($filteredInvoices)): ?>
            <p style="color: var(--gray-600); text-align: center; padding: 2rem;">
                <?= $selectedYearMonth ? date('Y年n月', strtotime($selectedYearMonth . '-01')) . 'の請求書がありません。' : '請求書がありません。MFから同期してください。' ?>
            </p>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>P番号</th>
                            <th>顧客名</th>
                            <th>担当者</th>
                            <th>請求書番号</th>
                            <th>案件名</th>
                            <th>売上日</th>
                            <th>合計金額</th>
                            <th>税抜き</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredInvoices as $invoice): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($invoice['project_id'])): ?>
                                        <span style="background: #fef3c7; color: #92400e; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; display: inline-block;">
                                            <?= htmlspecialchars($invoice['project_id']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--gray-400);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($invoice['partner_name']) ?></td>
                                <td>
                                    <?php if (!empty($invoice['assignee'])): ?>
                                        <span style="background: #dbeafe; color: #1e40af; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; display: inline-block;">
                                            <?= htmlspecialchars($invoice['assignee']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--gray-400);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($invoice['id'])): ?>
                                        <a href="https://invoice.moneyforward.com/billings/<?= htmlspecialchars($invoice['id']) ?>"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           style="color: #3b82f6; text-decoration: none; font-weight: 500;">
                                            <?= htmlspecialchars($invoice['billing_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($invoice['billing_number']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($invoice['title']) ?></td>
                                <td><?= htmlspecialchars($invoice['sales_date']) ?></td>
                                <td style="font-weight: 600;">¥<?= number_format($invoice['total_amount']) ?></td>
                                <td>¥<?= number_format($invoice['subtotal']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>


<?php require_once 'footer.php'; ?>
