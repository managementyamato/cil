<?php
/**
 * 指定請求書作成ページ
 *
 * URLパラメータ:
 *   template_id : Drive上のxlsxテンプレのファイルID（必須）
 *   billing_id  : MF請求書ID（省略可、空フォーム）
 */
require_once '../api/auth.php';
require_once '../api/mf-api.php';
require_once '../api/google-drive.php';
require_once '../functions/custom-invoice-generator.php';

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$templateId = $_GET['template_id'] ?? '';
// billing_ids（カンマ区切り）または billing_id（単一、後方互換）
$billingIdsParam = $_GET['billing_ids'] ?? ($_GET['billing_id'] ?? '');
$billingIds = array_values(array_filter(array_map('trim', explode(',', $billingIdsParam))));

$templateInfo = null;
$branches = [];
$templateError = '';

if ($templateId !== '') {
    try {
        $drive = new GoogleDriveClient();
        $templateInfo = $drive->getFileInfo($templateId);
        $cacheDir = __DIR__ . '/../temp/custom-invoice-templates';
        if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);
        $cachePath = $cacheDir . '/' . $templateId . '.xlsx';
        if (!file_exists($cachePath) || (time() - filemtime($cachePath)) > 600) {
            $content = $drive->getFileContent($templateId);
            file_put_contents($cachePath, $content);
        }
        $branches = loadBranchesFromTemplate($cachePath);

        // テンプレート解析: 名前の定義 or 自動検出で必須項目が揃うか確認
        require_once __DIR__ . '/../vendor/autoload.php';
        $validateSpreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($cachePath);
        $vnames = [];
        foreach ($validateSpreadsheet->getDefinedNames() as $dn) $vnames[$dn->getName()] = true;
        // 自動検出も試す
        $vSheet = null;
        foreach ($validateSpreadsheet->getAllSheets() as $s) {
            if ($s->getTitle() === '_branches') continue;
            if ($s->getSheetState() !== PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN) {
                $vSheet = $s; break;
            }
        }
        if (!$vSheet) $vSheet = $validateSpreadsheet->getActiveSheet();
        $vAuto = autoDetectTemplateRanges($vSheet);
        $validateSpreadsheet->disconnectWorksheets();

        $hasItems = isset($vnames['items_table']) || isset($vAuto['items_table']);
        $hasBd = isset($vnames['billing_date']) || isset($vAuto['billing_date'])
            || ((isset($vnames['billing_date_year']) || isset($vAuto['billing_date_year']))
                && (isset($vnames['billing_date_month']) || isset($vAuto['billing_date_month']))
                && (isset($vnames['billing_date_day']) || isset($vAuto['billing_date_day'])));
        $missingNames = [];
        if (!$hasItems) $missingNames[] = '明細表（items_table）';
        if (!$hasBd) $missingNames[] = '請求日（billing_date）';
        if (!empty($missingNames)) {
            $templateError = 'このテンプレートから必須項目を検出できませんでした: '
                . implode(', ', $missingNames)
                . '。テンプレートに「請求日」ラベルや明細ヘッダ（品名/数量/単価/金額など）が含まれているか確認してください。';
        }
    } catch (Exception $e) {
        $templateError = 'テンプレート取得エラー: ' . $e->getMessage();
    }
} else {
    $templateError = 'テンプレートが指定されていません。指定請求書一覧から選択してください。';
}

// 複数MF請求書を並列取得
$mfInvoices = [];
$mfError = '';
$fetchErrors = [];
if (!empty($billingIds)) {
    try {
        if (!MFApiClient::isConfigured()) {
            throw new Exception('MFクラウド請求書APIが設定されていません');
        }
        $client = new MFApiClient();
        $batch = $client->getInvoiceDetailsBatch($billingIds);
        // 入力順を保持
        foreach ($billingIds as $bid) {
            $res = $batch[$bid] ?? null;
            if (!$res) {
                $fetchErrors[] = "ID $bid: レスポンスなし";
                continue;
            }
            if (!$res['success']) {
                $fetchErrors[] = "ID $bid: " . ($res['error'] ?? '不明なエラー');
                continue;
            }
            $detail = $res['data'] ?? [];
            $inv = $detail['billing'] ?? $detail['data'] ?? (isset($detail['id']) ? $detail : null);
            if ($inv) $mfInvoices[] = $inv;
            else $fetchErrors[] = "ID $bid: 見つかりません";
        }
        if (empty($mfInvoices)) {
            $mfError = 'MF請求書が取得できませんでした: ' . implode(' / ', $fetchErrors);
        } elseif (!empty($fetchErrors)) {
            $mfError = '一部の請求書取得に失敗: ' . implode(' / ', $fetchErrors);
        }
    } catch (Exception $e) {
        $mfError = $e->getMessage();
    }
}
// 代表値（先頭の請求書）
$mfInvoice = $mfInvoices[0] ?? null;

function normalizeMfDate(?string $s): string {
    if (!$s) return '';
    $s = str_replace('/', '-', $s);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : '';
}

function detectBranchFromTagsList(array $tagNames, array $branchList): string {
    foreach ($tagNames as $tag) {
        $tag = trim((string)$tag);
        if ($tag === '') continue;
        foreach ($branchList as $b) {
            if ($tag === $b['name']) return $b['name'];
            $short = preg_replace('/営業所$/u', '', $b['name']);
            if ($tag === $short) return $b['name'];
        }
    }
    return '';
}

// フォーム初期値: 複数MF請求書の billing_date から最新を採用
$billingDates = array_filter(array_map(fn($i) => normalizeMfDate($i['billing_date'] ?? ''), $mfInvoices));
$defaultBillingDate = !empty($billingDates) ? max($billingDates) : date('Y-m-d');

// 明細を全MF請求書から結合（納入日は各請求書の sales_date または billing_date を継承）
$initialItems = [];
foreach ($mfInvoices as $inv) {
    $invDate = normalizeMfDate($inv['sales_date'] ?? '') ?: normalizeMfDate($inv['billing_date'] ?? '') ?: $defaultBillingDate;
    foreach ($inv['items'] ?? [] as $item) {
        $excise = $item['excise'] ?? '';
        $qty = (float)($item['quantity'] ?? 1);
        $amount = (float)($item['price'] ?? 0);
        $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price']
                   : ($qty > 0 ? round($amount / $qty, 2) : 0);
        $initialItems[] = [
            'delivery_date' => normalizeMfDate($item['delivery_date'] ?? '') ?: $invDate,
            'name' => $item['name'] ?? '',
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'amount' => $amount,
            'note' => $item['detail'] ?? '',
            'order_no' => '',
            'reduced_tax' => in_array($excise, ['eight_percent', 'eight_percent_as_reduced_tax_rate'], true),
            'source_billing_number' => $inv['billing_number'] ?? '',
        ];
    }
}
$defaultDeliveryDate = $defaultBillingDate;

// 全選択請求書のタグから営業所自動判定（全て同じ営業所ならOK、違う場合は最初の検出値を使う）
$detectedBranches = [];
foreach ($mfInvoices as $inv) {
    $b = detectBranchFromTagsList($inv['tag_names'] ?? [], $branches);
    if ($b !== '') $detectedBranches[] = $b;
}
$uniqueBranches = array_unique($detectedBranches);
$detectedBranch = count($uniqueBranches) === 1 ? $uniqueBranches[0] : '';
$mixedBranchWarning = count($uniqueBranches) > 1;

require_once '../functions/header.php';
?>

<div class="container p-2">
    <h2>指定請求書作成<?= $templateInfo ? ' - ' . htmlspecialchars(pathinfo($templateInfo['name'] ?? '', PATHINFO_FILENAME)) : '' ?></h2>

    <?php if ($templateError): ?>
        <div class="p-1 mb-1 rounded bg-ffe6e6 text-c00"><?= htmlspecialchars($templateError) ?></div>
    <?php endif; ?>

    <?php if ($mfError): ?>
        <div class="p-1 mb-1 rounded bg-ffe6e6 text-c00"><?= htmlspecialchars($mfError) ?></div>
    <?php endif; ?>

    <?php if (!empty($mfInvoices)): ?>
        <div class="p-1 mb-1 rounded bg-e3f2fd text-sm">
            <?php if (count($mfInvoices) === 1): ?>
                MF請求書: <strong><?= htmlspecialchars($mfInvoices[0]['billing_number'] ?? '') ?></strong>
                / <?= htmlspecialchars($mfInvoices[0]['title'] ?? '') ?>
                / <?= htmlspecialchars($mfInvoices[0]['partner_name'] ?? '') ?>
            <?php else: ?>
                選択中のMF請求書: <strong><?= count($mfInvoices) ?>件</strong> / <?= htmlspecialchars($mfInvoices[0]['partner_name'] ?? '') ?>
                <ul style="margin:0.3rem 0 0 1.2rem; font-size:0.85rem;">
                    <?php foreach ($mfInvoices as $inv): ?>
                    <li><?= htmlspecialchars($inv['billing_number'] ?? '-') ?> / <?= htmlspecialchars($inv['title'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($mixedBranchWarning): ?>
        <div class="p-1 mb-1 rounded bg-fff3cd text-sm">
            選択された請求書のタグ営業所が一致しません（<?= htmlspecialchars(implode('・', $uniqueBranches)) ?>）。営業所を手動で選択してください。
        </div>
    <?php endif; ?>

    <?php if ($templateInfo): ?>
    <form id="custom-invoice-form">
        <input type="hidden" name="template_id" value="<?= htmlspecialchars($templateId) ?>">

        <?php if (!empty($branches)): ?>
        <div class="form-group">
            <label>営業所（納入部門名）<?php if ($detectedBranch): ?><span class="text-sm text-success ml-05">MFタグから自動判定</span><?php endif; ?></label>
            <select name="branch_name" class="form-input" required>
                <option value="">-- 選択してください --</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= htmlspecialchars($b['name']) ?>"
                            data-partner-code="<?= htmlspecialchars($b['partner_code']) ?>"
                            <?= $b['name'] === $detectedBranch ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label>請求日</label>
            <input type="date" name="billing_date" class="form-input" value="<?= htmlspecialchars($defaultBillingDate) ?>" required>
        </div>

        <h3 class="mt-2">明細行</h3>
        <p class="text-sm text-gray-666">数量/単価から金額は自動計算（手動上書き可）。8%対象は「軽減」にチェック。明細がテンプレ上限を超える場合は自動で分割・ZIP出力します。</p>

        <table id="items-table" class="table">
            <thead>
                <tr>
                    <th style="width: 120px">納入日</th>
                    <th>品名</th>
                    <th style="width: 60px">軽減</th>
                    <th style="width: 80px">数量</th>
                    <th style="width: 110px">単価</th>
                    <th style="width: 110px">金額</th>
                    <th>備考</th>
                    <th style="width: 120px">注文No.</th>
                    <th style="width: 40px"></th>
                </tr>
            </thead>
            <tbody id="items-body"></tbody>
        </table>
        <button type="button" id="add-row-btn" class="btn btn-secondary">行を追加</button>

        <div class="mt-2 p-1 rounded bg-f5f5f5">
            <button type="button" id="generate-xlsx" class="btn btn-primary">Excelダウンロード</button>
            <button type="button" id="generate-pdf" class="btn btn-primary ml-1">PDFダウンロード</button>
        </div>
    </form>

    <script<?= nonceAttr() ?>>
    const CSRF_TOKEN = '<?= generateCsrfToken() ?>';
    const INITIAL_ITEMS = <?= json_encode($initialItems, JSON_UNESCAPED_UNICODE) ?>;
    const DEFAULT_DELIVERY_DATE = <?= json_encode($defaultDeliveryDate) ?>;
    const TEMPLATE_ID = <?= json_encode($templateId) ?>;

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[c]));
    }

    function createRow(item) {
        item = item || {};
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="date" class="form-input item-delivery-date" value="${escapeHtml(item.delivery_date || DEFAULT_DELIVERY_DATE)}"></td>
            <td><input type="text" class="form-input item-name" value="${escapeHtml(item.name || '')}"></td>
            <td style="text-align:center"><input type="checkbox" class="item-reduced-tax" ${item.reduced_tax ? 'checked' : ''}></td>
            <td><input type="number" step="any" class="form-input item-quantity" value="${escapeHtml(item.quantity ?? 1)}"></td>
            <td><input type="number" step="1" class="form-input item-unit-price" value="${escapeHtml(item.unit_price ?? 0)}"></td>
            <td><input type="number" step="1" class="form-input item-amount" value="${escapeHtml(item.amount ?? 0)}"></td>
            <td><input type="text" class="form-input item-note" value="${escapeHtml(item.note || '')}"></td>
            <td><input type="text" class="form-input item-order-no" value="${escapeHtml(item.order_no || '')}"></td>
            <td><button type="button" class="btn btn-danger remove-row-btn">x</button></td>
        `;
        tr.querySelector('.remove-row-btn').addEventListener('click', () => tr.remove());
        const qty = tr.querySelector('.item-quantity');
        const unit = tr.querySelector('.item-unit-price');
        const amt = tr.querySelector('.item-amount');
        const recalc = () => {
            const q = parseFloat(qty.value) || 0;
            const u = parseFloat(unit.value) || 0;
            amt.value = Math.round(q * u);
        };
        qty.addEventListener('input', recalc);
        unit.addEventListener('input', recalc);
        return tr;
    }

    function collectItems() {
        const rows = document.querySelectorAll('#items-body tr');
        const items = [];
        rows.forEach(tr => {
            const name = tr.querySelector('.item-name').value.trim();
            if (!name) return;
            items.push({
                delivery_date: tr.querySelector('.item-delivery-date').value,
                name,
                reduced_tax: tr.querySelector('.item-reduced-tax').checked,
                quantity: parseFloat(tr.querySelector('.item-quantity').value) || 0,
                unit_price: parseFloat(tr.querySelector('.item-unit-price').value) || 0,
                amount: parseFloat(tr.querySelector('.item-amount').value) || 0,
                note: tr.querySelector('.item-note').value,
                order_no: tr.querySelector('.item-order-no').value,
            });
        });
        return items;
    }

    async function generate(format) {
        const form = document.getElementById('custom-invoice-form');
        const branchNameInput = form.elements['branch_name'];
        const branchName = branchNameInput ? branchNameInput.value : '';
        if (branchNameInput && branchNameInput.hasAttribute('required') && !branchName) {
            alert('営業所を選択してください');
            return;
        }
        const partnerCode = branchNameInput && branchNameInput.selectedOptions[0]
            ? (branchNameInput.selectedOptions[0].dataset.partnerCode || '') : '';

        const billingDate = form.elements['billing_date'].value;
        const items = collectItems();
        if (items.length === 0) {
            alert('明細行を1行以上入力してください');
            return;
        }

        const payload = {
            template_id: TEMPLATE_ID,
            branch_name: branchName,
            partner_code: partnerCode,
            billing_date: billingDate,
            items,
            format,
        };

        try {
            const response = await fetch('/api/custom-invoice-api.php?action=generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN,
                },
                body: JSON.stringify(payload),
            });
            if (!response.ok) {
                const text = await response.text();
                throw new Error(`HTTP ${response.status}: ${text}`);
            }
            const blob = await response.blob();
            const disposition = response.headers.get('Content-Disposition') || '';
            let filename = `custom_invoice.${format}`;
            const match = disposition.match(/filename\*=UTF-8''([^;]+)/);
            if (match) filename = decodeURIComponent(match[1]);
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = filename;
            document.body.appendChild(a); a.click(); a.remove();
            URL.revokeObjectURL(url);
        } catch (err) {
            alert('生成エラー: ' + err.message);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const body = document.getElementById('items-body');
        if (INITIAL_ITEMS.length > 0) {
            INITIAL_ITEMS.forEach(item => body.appendChild(createRow(item)));
        } else {
            body.appendChild(createRow());
        }
        document.getElementById('add-row-btn').addEventListener('click', () => {
            body.appendChild(createRow());
        });
        document.getElementById('generate-xlsx').addEventListener('click', () => generate('xlsx'));
        document.getElementById('generate-pdf').addEventListener('click', () => generate('pdf'));
    });

    function escapeRegexp(s) { return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
    </script>
    <?php endif; ?>
</div>

<?php require_once '../functions/footer.php'; ?>
