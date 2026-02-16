<?php
require_once '../api/auth.php';
require_once '../functions/recurring-invoice.php';

// 管理者のみアクセス可能
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = '';
$invoiceList = [];
$csvError = '';

// CSVファイル読み込み
try {
    $invoiceList = loadRecurringInvoiceList();
} catch (Exception $e) {
    $csvError = $e->getMessage();
}

require_once '../functions/header.php';
?>

<link rel="stylesheet" href="/css/components.css">

<style<?= nonceAttr() ?>>
.recurring-invoice-container {
    max-width: 1000px;
    margin: 0 auto;
}

.info-box {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 4px;
}

.csv-table {
    width: 100%;
    border-collapse: collapse;
    margin: 1.5rem 0;
    background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden;
}

.csv-table th,
.csv-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid var(--gray-200);
}

.csv-table th {
    background: var(--gray-100);
    font-weight: 600;
    color: var(--gray-700);
}

.csv-table tbody tr:hover {
    background: var(--gray-50);
}

.btn-create {
    background: var(--primary);
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 600;
}

.btn-create:hover {
    background: var(--primary-dark);
}

.btn-create:disabled {
    background: var(--gray-400);
    cursor: not-allowed;
}

.error-box {
    background: #ffebee;
    border-left: 4px solid #c62828;
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 4px;
    color: #c62828;
}

.tag-rule-table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}

.tag-rule-table th,
.tag-rule-table td {
    padding: 0.5rem;
    text-align: left;
    border-bottom: 1px solid var(--gray-200);
}

.tag-rule-table th {
    background: var(--gray-50);
    font-weight: 600;
}

.tag-example {
    font-family: monospace;
    background: var(--gray-100);
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
}

.results-container {
    margin-top: 2rem;
}

.result-item {
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    border-radius: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.result-success {
    background: #e8f5e9;
    border-left: 4px solid #4caf50;
}

.result-error {
    background: #ffebee;
    border-left: 4px solid #f44336;
}

.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<div class="recurring-invoice-container">
    <h2>📅 定期請求書の作成</h2>

    <?php if ($csvError): ?>
        <div class="error-box">
            <strong>⚠️ エラー:</strong> <?= htmlspecialchars($csvError) ?>
            <p    class="mt-1 text-14">
                <code>config/recurring-invoices.csv</code> を作成してください。
            </p>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <h3     class="m-0-05">💡 使い方</h3>
        <ol      class="pl-15 my-05-m">
            <li><code>config/recurring-invoices.csv</code> にテンプレートとなるMF請求書IDを記載</li>
            <li>MFクラウド請求書で、各請求書に以下のタグを設定：
                <ul  class="mt-1">
                    <li>必須: <strong>「指定フォーマット」</strong> タグ（これがないとスキップされます）</li>
                    <li>任意: 「20日〆」「15日〆」「末〆」のいずれか（日付自動調整用）</li>
                </ul>
            </li>
            <li>「一括作成」ボタンをクリックすると、「指定フォーマット」タグが付いた請求書のみ作成されます</li>
        </ol>
    </div>

    <h3>🏷️ タグによる日付ルール</h3>

    <div        class="p-2 mb-2 rounded bg-info-border">
        <strong>⚠️ 重要:</strong> 請求書には必ず「<span class="tag-example">指定フォーマット</span>」タグを付けてください。このタグがない請求書はスキップされます。
    </div>

    <table class="tag-rule-table">
        <thead>
            <tr>
                <th>締め日タグ</th>
                <th>請求日</th>
                <th>支払期限</th>
                <th>例（実行月が2026年2月の場合）</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="tag-example">20日〆</span></td>
                <td>当月20日</td>
                <td>翌月末日</td>
                <td>請求日: 2026-02-20、支払期限: 2026-03-31</td>
            </tr>
            <tr>
                <td><span class="tag-example">15日〆</span></td>
                <td>当月15日</td>
                <td>翌月末日</td>
                <td>請求日: 2026-02-15、支払期限: 2026-03-31</td>
            </tr>
            <tr>
                <td><span class="tag-example">末〆</span></td>
                <td>当月末日</td>
                <td>翌月末日</td>
                <td>請求日: 2026-02-28、支払期限: 2026-03-31</td>
            </tr>
            <tr>
                <td><em>締め日タグなし</em></td>
                <td colspan="3">テンプレート請求書の日付をそのまま使用</td>
            </tr>
        </tbody>
    </table>

    <h3>📋 登録されている定期請求書（<?= count($invoiceList) ?>件）</h3>

    <?php if (!empty($invoiceList)): ?>
        <table class="csv-table" id="recurringInvoiceTable">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>MF請求書ID</th>
                    <th>備考</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoiceList as $index => $invoice): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><code><?= htmlspecialchars($invoice['mf_billing_id']) ?></code></td>
                        <td><?= htmlspecialchars($invoice['note']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div id="recurringInvoicePagination"></div>

        <div  class="d-flex gap-2 align-center mb-2">
            <label  class="font-bold">📅 作成対象月：</label>
            <select id="target-month"        class="p-1 rounded text-base" class="border-ccc">
                <?php
                // 過去3ヶ月と未来3ヶ月の選択肢を生成
                for ($i = -3; $i <= 3; $i++) {
                    $month = date('Y-m', strtotime("$i months"));
                    $monthLabel = date('Y年n月', strtotime("$i months"));
                    $selected = ($i === 0) ? 'selected' : ''; // 今月をデフォルト
                    echo "<option value=\"{$month}\" {$selected}>{$monthLabel}</option>";
                }
                ?>
            </select>
        </div>

        <div  class="d-flex gap-2 align-center">
            <button id="create-btn" class="btn-create">
                🚀 一括作成（<?= count($invoiceList) ?>件）
            </button>
            <span id="status-message"   class="text-gray-600"></span>
        </div>

        <div id="results-container"   class="results-container d-none">
            <h3>📊 作成結果</h3>
            <div id="results-list"></div>
        </div>

    <?php else: ?>
        <p   class="text-gray-600">CSVファイルに請求書IDが登録されていません。</p>
    <?php endif; ?>

</div>

<script<?= nonceAttr() ?>>
const csrfToken = '<?= generateCsrfToken() ?>';

// 一括作成ボタン
document.getElementById('create-btn')?.addEventListener('click', createRecurringInvoices);

async function createRecurringInvoices() {
    const btn = document.getElementById('create-btn');
    const statusMessage = document.getElementById('status-message');
    const resultsContainer = document.getElementById('results-container');
    const resultsList = document.getElementById('results-list');
    const targetMonth = document.getElementById('target-month').value;

    // ボタンを無効化
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span> 作成中...';
    statusMessage.textContent = `${targetMonth}の請求書を作成しています。しばらくお待ちください...`;
    resultsContainer.style.display = 'none';
    resultsList.innerHTML = '';

    try {
        const response = await fetch('/api/recurring-invoices-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                target_month: targetMonth
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success) {
            statusMessage.textContent = `✅ ${data.message}`;
            statusMessage.style.color = 'var(--success)';

            // 結果を表示
            if (data.results && data.results.length > 0) {
                resultsContainer.style.display = 'block';

                data.results.forEach(result => {
                    const resultItem = document.createElement('div');
                    resultItem.className = result.success ? 'result-item result-success' : 'result-item result-error';

                    const templateInfo = escapeHtml(result.note || result.template_id);

                    if (result.success) {
                        const closingInfo = result.closing_type ? `[${escapeHtml(result.closing_type)}] ` : '';
                        const message = `✓ ${templateInfo}: ${closingInfo}${escapeHtml(result.message)} (請求日: ${escapeHtml(result.billing_date)}, 支払期限: ${escapeHtml(result.due_date)}, 金額: ¥${Number(result.total_price).toLocaleString()})`;

                        const messageSpan = document.createElement('span');
                        messageSpan.textContent = message;
                        resultItem.appendChild(messageSpan);

                        // 印刷リンクを追加
                        const printLink = document.createElement('a');
                        printLink.href = `/pages/print-invoice.php?id=${escapeHtml(result.new_billing_id)}`;
                        printLink.target = '_blank';
                        printLink.textContent = '🖨️ 印刷';
                        printLink.style.marginLeft = '10px';
                        printLink.style.color = 'var(--primary)';
                        printLink.style.textDecoration = 'none';
                        printLink.style.fontWeight = 'bold';
                        resultItem.appendChild(printLink);
                    } else {
                        resultItem.textContent = `✗ ${templateInfo}: ${escapeHtml(result.message)}`;
                    }

                    resultsList.appendChild(resultItem);
                });
            }

        } else {
            statusMessage.textContent = `❌ エラー: ${escapeHtml(data.error)}`;
            statusMessage.style.color = 'var(--danger)';
        }

    } catch (error) {
        statusMessage.textContent = `❌ エラー: ${escapeHtml(error.message)}`;
        statusMessage.style.color = 'var(--danger)';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '🚀 一括作成（<?= count($invoiceList) ?>件）';
    }
}

// ページネーション初期化
document.addEventListener('DOMContentLoaded', function() {
    var table = document.getElementById('recurringInvoiceTable');
    if (table && table.querySelector('tbody tr')) {
        new Paginator({
            container: '#recurringInvoiceTable',
            itemSelector: 'tbody tr',
            perPage: 50,
            paginationTarget: '#recurringInvoicePagination'
        });
    }
});
</script>

<?php require_once '../functions/footer.php'; ?>
