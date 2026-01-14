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

        // デバッグ情報を収集
        $debugInfo = array(
            'sync_time' => date('Y-m-d H:i:s'),
            'request_params' => array(),
            'raw_response' => null,
            'parsed_invoices' => null,
            'errors' => array()
        );

        // パラメータなしで全ページ取得を試す
        try {
            $invoices = $client->getAllInvoices(null, null);
            $debugInfo['request_params'] = array('from' => null, 'to' => null, 'note' => '日付フィルタなしで全件取得');
        } catch (Exception $e1) {
            $debugInfo['errors'][] = 'パラメータなし取得エラー: ' . $e1->getMessage();

            // 過去3ヶ月分のデータを全ページ取得
            $from = date('Y-m-d', strtotime('-3 months'));
            $to = date('Y-m-d');
            $invoices = $client->getAllInvoices($from, $to);
            $debugInfo['request_params'] = array('from' => $from, 'to' => $to, 'note' => '過去3ヶ月分で取得');
        }

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

            foreach ($tags as $tag) {
                // PJ番号を抽出（P + 数字）
                if (preg_match('/^P\d+$/i', $tag)) {
                    $projectId = $tag;
                }
                // 担当者名を抽出（日本語の人名を想定）
                // 会社名や長い文字列を除外
                if (mb_strlen($tag) <= 4 &&
                    preg_match('/^[ぁ-んァ-ヶー一-龯]+$/', $tag) &&
                    !preg_match('/(株式会社|有限会社|合同会社|〆|メール|販売|レンタル)/', $tag)) {
                    $assignee = $tag;
                }
            }

            // 金額詳細を計算
            $subtotal = 0;
            $tax = 0;
            $total = 0;

            if (isset($invoice['items']) && is_array($invoice['items'])) {
                foreach ($invoice['items'] as $item) {
                    $subtotal += floatval($item['price'] ?? 0) * floatval($item['quantity'] ?? 0);
                }
            }

            // 合計金額（total_amountがあればそれを使用）
            if (isset($invoice['total_amount'])) {
                $total = floatval($invoice['total_amount']);
                // 消費税を逆算（小計から計算できない場合）
                if ($subtotal > 0) {
                    $tax = $total - $subtotal;
                }
            } else {
                // total_amountがない場合は小計のみ
                $total = $subtotal;
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
// 12月分の請求書データを集計
$decemberTotal = 0;
$decemberTotalTax = 0;
$decemberInvoices = array();

if (isset($data['mf_invoices']) && !empty($data['mf_invoices'])) {
    foreach ($data['mf_invoices'] as $invoice) {
        // 売上日が12月のものを抽出
        $salesDate = $invoice['sales_date'] ?? '';
        if (preg_match('/^\d{4}-12-/', $salesDate)) {
            $decemberInvoices[] = $invoice;
            $decemberTotal += floatval($invoice['total_amount'] ?? 0);
            $decemberTotalTax += floatval($invoice['tax'] ?? 0);
        }
    }
}

$decemberSubtotal = $decemberTotal - $decemberTotalTax;
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">12月 総売上</div>
        <div class="stat-value">¥<?= number_format($decemberTotal) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">12月 税抜き</div>
        <div class="stat-value">¥<?= number_format($decemberSubtotal) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">12月分 請求書一覧</h2>
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
        <?php if (empty($decemberInvoices)): ?>
            <p style="color: var(--gray-600); text-align: center; padding: 2rem;">
                12月分の請求書がありません。MFから同期してください。
            </p>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>顧客名</th>
                            <th>請求書番号</th>
                            <th>案件名</th>
                            <th>担当者</th>
                            <th>売上日</th>
                            <th>合計金額</th>
                            <th>税抜き</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($decemberInvoices as $invoice): ?>
                            <tr>
                                <td><?= htmlspecialchars($invoice['partner_name']) ?></td>
                                <td><?= htmlspecialchars($invoice['billing_number']) ?></td>
                                <td><?= htmlspecialchars($invoice['title']) ?></td>
                                <td>
                                    <?php if (!empty($invoice['assignee'])): ?>
                                        <span class="badge" style="background: #dbeafe; color: #1e40af; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">
                                            <?= htmlspecialchars($invoice['assignee']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--gray-400);">-</span>
                                    <?php endif; ?>
                                </td>
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
