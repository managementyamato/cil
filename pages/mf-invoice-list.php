<?php
/**
 * MF請求書一覧取得（デバッグ用）
 * 管理者のみアクセス可能
 */
require_once '../api/auth.php';
require_once '../api/mf-api.php';

// 管理者のみアクセス可能
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

require_once '../functions/header.php';

// 実行時間を延長（大量データ取得のため）
set_time_limit(120);

$invoices = [];
$error = '';
$allTagNames = [];
$showingAll = false;
$cacheInfo = null;

// 月選択（デフォルトは今月）
$selectedMonth = $_GET['month'] ?? date('Y-m');
$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';

try {
    if (!MFApiClient::isConfigured()) {
        throw new Exception('MFクラウド請求書APIが設定されていません');
    }

    $client = new MFApiClient();

    // 選択された月の請求書を取得（キャッシュ対応）
    $from = date('Y-m-01', strtotime($selectedMonth . '-01'));
    $to = date('Y-m-t', strtotime($selectedMonth . '-01'));

    // getAllInvoices メソッドを使用（キャッシュあり、リフレッシュ時は強制再取得）
    $allInvoices = $client->getAllInvoices($from, $to, $forceRefresh);

    // キャッシュ情報を取得（UI表示用）
    $cacheInfo = MFApiClient::getCacheInfo('invoices', ['from' => $from, 'to' => $to]);

    // デバッグ情報
    $debugInfo = [
        'invoice_count' => count($allInvoices),
        'date_range' => ['from' => $from, 'to' => $to]
    ];

    if (!empty($allInvoices)) {
        $debugInfo['first_invoice_keys'] = array_keys($allInvoices[0]);
        $debugInfo['first_invoice_has_tags'] = isset($allInvoices[0]['tags']);
        if (isset($allInvoices[0]['tags'])) {
            $debugInfo['first_invoice_tags'] = $allInvoices[0]['tags'];
        }
    }

    // デバッグ：全タグを収集
    // MF APIは tag_names という文字列配列でタグを返す
    $allTagNames = [];
    foreach ($allInvoices as $invoice) {
        $tagNames = $invoice['tag_names'] ?? [];
        foreach ($tagNames as $tagName) {
            if ($tagName && !in_array($tagName, $allTagNames)) {
                $allTagNames[] = $tagName;
            }
        }
    }

    // 「指定フォーマット」タグが付いている請求書のみをフィルタ
    $filteredInvoices = array_filter($allInvoices, function($invoice) {
        $tagNames = $invoice['tag_names'] ?? [];
        foreach ($tagNames as $tagName) {
            // 部分一致で検索（前後の空白も考慮）
            if (mb_strpos($tagName, '指定フォーマット') !== false) {
                return true;
            }
        }
        return false;
    });

    // フィルタ結果がない場合は、全請求書を表示（タグ追加用）
    if (empty($filteredInvoices)) {
        $invoices = $allInvoices;
        $showingAll = true;
    } else {
        $invoices = $filteredInvoices;
        $showingAll = false;
    }

    // 取引先ごとにグループ化
    $invoicesByPartner = [];
    foreach ($invoices as $invoice) {
        $partnerId = $invoice['partner_id'] ?? 'unknown';
        $partnerName = $invoice['partner_name'] ?? '（取引先不明）';

        if (!isset($invoicesByPartner[$partnerId])) {
            $invoicesByPartner[$partnerId] = [
                'partner_name' => $partnerName,
                'invoices' => []
            ];
        }

        $invoicesByPartner[$partnerId]['invoices'][] = $invoice;
    }

    // 取引先名でソート
    uasort($invoicesByPartner, function($a, $b) {
        return strcmp($a['partner_name'], $b['partner_name']);
    });

    // 各取引先内の請求書を請求日で降順ソート
    foreach ($invoicesByPartner as &$partnerData) {
        usort($partnerData['invoices'], function($a, $b) {
            return strcmp($b['billing_date'] ?? '', $a['billing_date'] ?? '');
        });
    }
    unset($partnerData);

} catch (Exception $e) {
    $error = $e->getMessage();
    // 本番環境でのデバッグ用
    if (isAdmin()) {
        $error .= "\n\nデバッグ情報:\n";
        $error .= "ファイル: " . $e->getFile() . "\n";
        $error .= "行: " . $e->getLine() . "\n";
        $error .= "スタックトレース:\n" . $e->getTraceAsString();
    }
}
?>

<link rel="stylesheet" href="/css/components.css">

<style<?= nonceAttr() ?>>
.invoice-list-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.partner-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding: 0.5rem;
    background: #f5f5f5;
    border-radius: 8px;
}

.partner-tab {
    padding: 0.75rem 1.5rem;
    background: white;
    border: 2px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
    white-space: nowrap;
}

.partner-tab:hover {
    background: #f0f0f0;
    border-color: var(--primary);
}

.partner-tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.partner-tab-count {
    font-size: 0.85rem;
    opacity: 0.7;
    margin-left: 0.5rem;
}

.partner-content {
    display: none;
}

.partner-content.active {
    display: block;
}

.invoice-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.invoice-table th,
.invoice-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.invoice-table th {
    background: #f5f5f5;
    font-weight: 600;
}

.invoice-table tr:hover {
    background: #f9f9f9;
}

.tag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.tag {
    background: #e3f2fd;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 0.85rem;
}

.tag.recurring {
    background: #c8e6c9;
    font-weight: bold;
}

.tag.closing {
    background: #fff9c4;
}

.tag.delivery {
    background: #e1f5fe;
    color: #01579b;
}

.tag.person {
    background: #f3e5f5;
    color: #4a148c;
}

.error-box {
    background: #ffebee;
    border-left: 4px solid #c62828;
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 4px;
    color: #c62828;
}

.copy-btn {
    padding: 4px 8px;
    font-size: 0.8rem;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.copy-btn:hover {
    background: var(--primary-dark);
}

.copy-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
}
</style>

<div class="invoice-list-container">
    <h2>📋 MF請求書一覧（指定請求書登録）</h2>
    <p       class="text-gray-666 mt-minus-10 mb-20">
        「指定フォーマット」タグが付いた請求書を作成予定リストに登録できます。登録後、「設定」→「定期請求書管理」から一括作成できます。
    </p>

    <?php if ($error): ?>
        <div class="error-box">
            <strong>⚠️ エラー:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div        class="p-2 mb-2 rounded bg-white border-ddd">
        <form method="GET"  class="d-flex gap-2 align-center flex-wrap">
            <label  class="font-bold">📅 対象月：</label>
            <select name="month"        class="p-1 rounded text-base" class="border-ccc">
                <?php
                // 過去12ヶ月と未来3ヶ月の選択肢を生成
                for ($i = -12; $i <= 3; $i++) {
                    $month = date('Y-m', strtotime("$i months"));
                    $monthLabel = date('Y年n月', strtotime("$i months"));
                    $selected = ($month === $selectedMonth) ? 'selected' : '';
                    echo "<option value=\"{$month}\" {$selected}>{$monthLabel}</option>";
                }
                ?>
            </select>
            <button type="submit"        class="btn btn-primary btn-pad-05-15">
                🔍 検索
            </button>
            <a href="?month=<?= htmlspecialchars($selectedMonth) ?>&refresh=1"
               class="btn bg-warning btn-pad-05-15"
               title="キャッシュを無視してMF APIから最新データを再取得します">
                🔄 最新データ取得
            </a>
            <?php if ($cacheInfo): ?>
                <span    class="text-sm ml-1 text-gray-666">
                    <?php if ($cacheInfo['expired']): ?>
                        ⚠️ キャッシュ期限切れ
                    <?php else: ?>
                        💾 <?= htmlspecialchars($cacheInfo['cached_at']) ?> 取得
                        （残り<?= floor($cacheInfo['remaining_seconds'] / 60) ?>分）
                    <?php endif; ?>
                </span>
            <?php elseif ($forceRefresh): ?>
                <span        class="text-sm ml-1 text-4caf50">
                    ✅ 最新データを取得しました
                </span>
            <?php endif; ?>
        </form>
    </div>

    <div        class="p-2 mb-2 rounded bg-e3f2fd">
        <strong>💡 使い方:</strong>
        <ul      class="pl-15 my-05-m">
            <li>「指定フォーマット」タグが付いている請求書を探す</li>
            <li>請求書IDをコピーして <code>config/recurring-invoices.csv</code> に追加</li>
            <li>締め日タグ（20日〆/15日〆/末〆）があれば、日付が自動調整されます</li>
        </ul>
    </div>

    <?php if (isset($debugInfo)): ?>
        <details        class="p-2 mb-2 rounded bg-ffe6e6">
            <summary        class="cursor-pointer font-bold text-c00">
                🐛 レスポンス構造デバッグ
            </summary>
            <pre        class="text-sm overflow-x-auto pre-white"><?= htmlspecialchars(json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        </details>
    <?php endif; ?>

    <details        class="p-2 mb-2 rounded bg-f5f5f5">
        <summary  class="cursor-pointer font-bold">
            🔍 デバッグ：検出された全タグ一覧（<?= count($allTagNames) ?>件）
        </summary>
        <div        class="mt-1 overflow-y-auto max-h-300">
            <?php if (empty($allTagNames)): ?>
                <p    class="text-999">タグが見つかりませんでした</p>
            <?php else: ?>
                <?php sort($allTagNames); ?>
                <?php foreach ($allTagNames as $tagName): ?>
                    <div       class="p-05 border-bottom-ddd">
                        <code     class="code-inline"><?= htmlspecialchars($tagName) ?></code>
                        <?php if (mb_strpos($tagName, '指定') !== false): ?>
                            <span        class="font-bold ml-10" class="text-danger">← 「指定」を含む</span>
                        <?php endif; ?>
                        <?php if (mb_strpos($tagName, 'フォーマット') !== false): ?>
                            <span        class="font-bold text-blue ml-10">← 「フォーマット」を含む</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </details>

    <?php if (!empty($invoices)): ?>
        <?php if ($showingAll): ?>
            <div        class="p-2 mb-2 rounded bg-info-border">
                <strong>⚠️ 「指定フォーマット」タグが付いた請求書が見つかりませんでした</strong><br>
                全請求書を表示しています。タグを追加する請求書を探してください。
            </div>
        <?php endif; ?>

        <div  class="mb-2">
            <strong>取引先数：<?= count($invoicesByPartner) ?>社</strong> /
            <strong>請求書数：<?= count($invoices) ?>件</strong>
        </div>

        <!-- 取引先タブ -->
        <div class="partner-tabs">
            <?php $isFirst = true; ?>
            <?php foreach ($invoicesByPartner as $partnerId => $partnerData): ?>
                <div class="partner-tab <?= $isFirst ? 'active' : '' ?>"
                     data-partner-id="<?= htmlspecialchars($partnerId) ?>">
                    🏢 <?= htmlspecialchars($partnerData['partner_name']) ?>
                    <span class="partner-tab-count">(<?= count($partnerData['invoices']) ?>件)</span>
                </div>
                <?php $isFirst = false; ?>
            <?php endforeach; ?>
        </div>

        <!-- 取引先ごとのコンテンツ -->
        <?php $isFirst = true; ?>
        <?php foreach ($invoicesByPartner as $partnerId => $partnerData): ?>
            <div id="partner-<?= htmlspecialchars($partnerId) ?>"
                 class="partner-content <?= $isFirst ? 'active' : '' ?>">

                <div        class="mb-2 p-2 bg-f9f9f9 rounded">
                    <h3        class="m-0 invoice-detail-title">
                        🏢 <?= htmlspecialchars($partnerData['partner_name']) ?>
                    </h3>
                    <p     class="text-gray-666 mt-1">
                        請求書：<?= count($partnerData['invoices']) ?>件
                    </p>
                </div>

                <table class="invoice-table" id="invoice-table-<?= htmlspecialchars($partnerId) ?>">
                    <thead>
                        <tr>
                            <th   class="w-80">請求書ID</th>
                            <th    class="w-120">請求書番号</th>
                            <th>件名</th>
                            <th>タグ</th>
                            <th   class="w-100">請求日</th>
                            <th   class="w-80">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partnerData['invoices'] as $invoice): ?>
                    <?php
                    $tagNames = $invoice['tag_names'] ?? [];
                    $hasRecurringTag = false;
                    $closingTag = null;
                    $displayTags = []; // 表示するタグのみ
                    $debugTagInfo = []; // デバッグ用：マッチしなかったタグ

                    foreach ($tagNames as $tagName) {
                        $matched = false;

                        // 指定フォーマット
                        if (strpos($tagName, '指定フォーマット') !== false) {
                            $hasRecurringTag = true;
                            $displayTags[] = ['name' => '指定フォーマット', 'type' => 'recurring'];
                            $matched = true;
                        }
                        // 締め日（末日〆、末〆の両方に対応）
                        elseif (preg_match('/(20日〆|15日〆|末日〆|末〆)/', $tagName, $matches)) {
                            $closingTag = $matches[1];
                            $displayTags[] = ['name' => $closingTag, 'type' => 'closing'];
                            $matched = true;
                        }
                        // 送付形式（メール、郵送、PDF、紙を含むタグ）
                        elseif (preg_match('/(メール|郵送|ＰＤＦ|PDF|紙)/', $tagName, $matches)) {
                            // 全角PDFを半角に正規化
                            $deliveryMethod = $matches[1];
                            if ($deliveryMethod === 'ＰＤＦ') {
                                $deliveryMethod = 'PDF';
                            }
                            $displayTags[] = ['name' => $deliveryMethod, 'type' => 'delivery'];
                            $matched = true;
                        }
                        // 担当者名（日本語の名前パターン）
                        elseif (preg_match('/^[ぁ-んァ-ヶー一-龠]{2,4}$/', $tagName)) {
                            $displayTags[] = ['name' => $tagName, 'type' => 'person'];
                            $matched = true;
                        }

                        // マッチしなかったタグを記録（デバッグ用）
                        if (!$matched) {
                            $debugTagInfo[] = $tagName;
                        }
                    }
                    ?>
                    <tr     class="<?= $hasRecurringTag ? 'bg-f1f8e9' : '' ?>">
                            <td>
                                <code><?= htmlspecialchars($invoice['id']) ?></code>
                            </td>
                            <td><?= htmlspecialchars($invoice['billing_number'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($invoice['title'] ?? '-') ?></td>
                            <td>
                                <div class="tag-list">
                                    <?php if (empty($displayTags)): ?>
                                        <span    class="text-999">（タグなし）</span>
                                    <?php else: ?>
                                        <?php foreach ($displayTags as $tag): ?>
                                            <?php
                                            $tagClass = '';
                                            $icon = '';
                                            switch ($tag['type']) {
                                                case 'recurring':
                                                    $tagClass = 'recurring';
                                                    $icon = '📄';
                                                    break;
                                                case 'closing':
                                                    $tagClass = 'closing';
                                                    $icon = '📅';
                                                    break;
                                                case 'delivery':
                                                    $tagClass = 'delivery';
                                                    $icon = '📧';
                                                    break;
                                                case 'person':
                                                    $tagClass = 'person';
                                                    $icon = '👤';
                                                    break;
                                            }
                                            ?>
                                            <span class="tag <?= $tagClass ?>">
                                                <?= $icon ?> <?= htmlspecialchars($tag['name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($debugTagInfo)): ?>
                                        <details        class="d-inline-block ml-10">
                                            <summary      class="cursor-pointer text-2xs text-999">
                                                🔍 未マッチ(<?= count($debugTagInfo) ?>)
                                            </summary>
                                            <div       class="text-2xs bg-fff3cd rounded p-05 mt-05">
                                                <?php foreach ($debugTagInfo as $unmatchedTag): ?>
                                                    <code        class="d-block p-025"><?= htmlspecialchars($unmatchedTag) ?></code>
                                                <?php endforeach; ?>
                                            </div>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($invoice['billing_date'] ?? '-') ?></td>
                            <td>
                                <button type="button" class="copy-btn copy-invoice-id-btn" data-invoice-id="<?= htmlspecialchars($invoice['id']) ?>">
                                    📋 Copy
                                </button>
                                <?php if ($hasRecurringTag): ?>
                                <button type="button"         class="copy-btn create-invoice-btn bg-success ml-05"
                                        data-template-id="<?= htmlspecialchars($invoice['id'], ENT_QUOTES) ?>"
                                        data-title="<?= htmlspecialchars($invoice['title'] ?? '', ENT_QUOTES) ?>">
                                    ➕ 登録
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="invoice-pagination-<?= htmlspecialchars($partnerId) ?>"></div>
            </div>
            <?php $isFirst = false; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <p   class="text-gray-666">請求書が見つかりませんでした。</p>
    <?php endif; ?>
</div>

<script<?= nonceAttr() ?>>
// イベントリスナー登録
document.addEventListener('DOMContentLoaded', function() {
    // 取引先タブ
    document.querySelectorAll('.partner-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const partnerId = this.getAttribute('data-partner-id');
            switchPartner(partnerId, this);
        });
    });

    // 請求書IDコピーボタン
    document.querySelectorAll('.copy-invoice-id-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const invoiceId = this.getAttribute('data-invoice-id');
            copyId(invoiceId);
        });
    });

    // 請求書作成ボタン
    document.querySelectorAll('.create-invoice-btn').forEach(btn => {
        btn.addEventListener('click', function(event) {
            const templateId = this.getAttribute('data-template-id');
            const title = this.getAttribute('data-title');
            createInvoice(templateId, title, event);
        });
    });
});

function switchPartner(partnerId, targetElement) {
    // 全てのタブとコンテンツから active クラスを削除
    document.querySelectorAll('.partner-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.partner-content').forEach(content => {
        content.classList.remove('active');
    });

    // クリックされたタブとコンテンツに active クラスを追加
    targetElement.classList.add('active');
    document.getElementById('partner-' + partnerId).classList.add('active');

    // ページネーションをリフレッシュ
    if (window._invoicePaginators && window._invoicePaginators[partnerId]) {
        window._invoicePaginators[partnerId].refresh();
    }
}

// ページネーション初期化（既存の処理）
document.addEventListener('DOMContentLoaded', function() {
    window._invoicePaginators = {};
    document.querySelectorAll('.invoice-table').forEach(function(table) {
        var partnerId = table.id.replace('invoice-table-', '');
        if (table.querySelector('tbody tr')) {
            window._invoicePaginators[partnerId] = new Paginator({
                container: table,
                itemSelector: 'tbody tr',
                perPage: 50,
                paginationTarget: '#invoice-pagination-' + partnerId,
                urlParamPrefix: null
            });
        }
    });
});

function copyId(id) {
    navigator.clipboard.writeText(id).then(() => {
        alert('請求書ID ' + id + ' をコピーしました！');
    }).catch(err => {
        prompt('請求書ID:', id);
    });
}

async function createInvoice(templateId, title, event) {
    // 対象月を取得（現在選択されている月）
    const selectedMonth = new URLSearchParams(window.location.search).get('month') || '<?= $selectedMonth ?>';

    if (!confirm(`「${title}」を作成予定リストに追加しますか？\n\n対象月: ${selectedMonth}\n※後で一括作成できます`)) {
        return;
    }

    const createBtn = event ? event.target : null;
    if (createBtn) {
        createBtn.disabled = true;
        createBtn.textContent = '登録中...';
    }

    try {
        const csrfToken = '<?= generateCsrfToken() ?>';
        const response = await fetch('/api/schedule-invoice-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                mf_template_id: templateId,
                target_month: selectedMonth
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();

        if (result.success) {
            const invoice = result.data;
            alert(`✅ 作成予定リストに追加しました！\n\n取引先: ${invoice.partner_name}\n請求日: ${invoice.billing_date || '未定'}\n支払期限: ${invoice.due_date || '未定'}\n\n「設定」→「定期請求書管理」から一括作成できます。`);

            if (createBtn) {
                createBtn.textContent = '✓ 登録済み';
                createBtn.style.background = '#9e9e9e';
            }
        } else {
            alert('❌ エラー: ' + (result.message || '不明なエラー'));
            if (createBtn) {
                createBtn.disabled = false;
                createBtn.textContent = '➕ 登録';
            }
        }
    } catch (error) {
        console.error('請求書登録エラー:', error);
        alert('❌ 通信エラー: ' + error.message);
        if (createBtn) {
            createBtn.disabled = false;
            createBtn.textContent = '➕ 登録';
        }
    }
}
</script>

<?php require_once '../functions/footer.php'; ?>
