<?php
/**
 * 指定請求書一覧
 *
 * Google Driveの「指定請求書テンプレート」フォルダに保管された .xlsx ファイルをテンプレとして使用する。
 * ファイル名（拡張子除く）がMF請求書の partner_name と部分一致するものを自動マッチング。
 */
require_once '../api/auth.php';
require_once '../api/mf-api.php';
require_once '../api/google-drive.php';

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

require_once '../functions/header.php';
set_time_limit(120);

$selectedMonth = $_GET['month'] ?? date('Y-m');
$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';

$driveFolder = null;
$templates = [];
$driveError = '';
$driveDebug = null;
try {
    $drive = new GoogleDriveClient();
    $driveFolder = $drive->getCustomInvoiceFolder();
    if ($driveFolder) {
        // まずフォルダ内の全ファイルを取得して、xlsx を判定
        $allFiles = $drive->listFilesInFolder($driveFolder['id'], null);
        $driveDebug = [
            'total_files' => count($allFiles),
            'items' => array_map(fn($f) => [
                'name' => $f['name'] ?? '',
                'mimeType' => $f['mimeType'] ?? '',
            ], $allFiles),
        ];
        foreach ($allFiles as $f) {
            $name = $f['name'] ?? '';
            $mime = $f['mimeType'] ?? '';
            // xlsxかどうかを mime または拡張子で判定
            $isXlsx = ($mime === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                  || (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'xlsx');
            if (!$isXlsx) continue;
            $templates[] = [
                'id' => $f['id'],
                'name' => $name,
                'partner_key' => pathinfo($name, PATHINFO_FILENAME),
            ];
        }
    }
} catch (Exception $e) {
    $driveError = 'Driveテンプレ取得エラー: ' . $e->getMessage();
}

$invoices = [];
$cacheInfo = null;
$error = '';

// 取引先名・ファイル名から「株式会社」「請求書」等の共通語を除去して比較用のコア名を作る
function normalizePartnerCoreName(string $s): string {
    $n = preg_replace('/\s+/u', '', $s);
    $n = preg_replace('/^(株式会社|有限会社|合同会社|合資会社|一般社団法人|医療法人|\(株\)|\(有\)|㈱|㈲)/u', '', $n);
    $n = preg_replace('/(株式会社|有限会社|合同会社|合資会社|一般社団法人|医療法人|\(株\)|\(有\)|㈱|㈲)$/u', '', $n);
    $n = preg_replace('/(指定請求書|指定フォーマット|請求書|テンプレート|template|様式)$/iu', '', $n);
    return $n;
}

function templateMatches(string $partnerName, string $filenameBase): bool {
    $p = normalizePartnerCoreName($partnerName);
    $f = normalizePartnerCoreName($filenameBase);
    if ($p === '' || $f === '') return false;
    if ($p === $f) return true;
    if (mb_strpos($p, $f) !== false) return true;
    if (mb_strpos($f, $p) !== false) return true;
    return false;
}

try {
    if (!MFApiClient::isConfigured()) {
        throw new Exception('MFクラウド請求書APIが設定されていません');
    }
    $client = new MFApiClient();
    $from = date('Y-m-01', strtotime($selectedMonth . '-01'));
    $to = date('Y-m-t', strtotime($selectedMonth . '-01'));
    $allInvoices = $client->getAllInvoices($from, $to, $forceRefresh);
    $cacheInfo = MFApiClient::getCacheInfo('invoices', ['from' => $from, 'to' => $to]);

    $invoices = array_values(array_filter($allInvoices, function ($inv) use ($templates) {
        $partnerName = (string)($inv['partner_name'] ?? '');
        if ($partnerName === '') return false;
        foreach ($templates as $tpl) {
            if (templateMatches($partnerName, $tpl['partner_key'])) return true;
        }
        return false;
    }));

    usort($invoices, fn($a, $b) => strcmp($b['billing_date'] ?? '', $a['billing_date'] ?? ''));
} catch (Exception $e) {
    $error = $e->getMessage();
}

// 取引先別グループ化
$byPartner = [];
foreach ($invoices as $inv) {
    $name = $inv['partner_name'] ?? '不明';
    $byPartner[$name][] = $inv;
}

function resolveTemplateForInvoice(string $partnerName, array $templates): ?array {
    foreach ($templates as $tpl) {
        if (templateMatches($partnerName, $tpl['partner_key'])) return $tpl;
    }
    return null;
}
?>

<style<?= nonceAttr() ?>>
.cil-filter { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
.cil-table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
.cil-table th, .cil-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #e0e0e0; text-align: left; }
.cil-table th { background: #f5f5f5; font-weight: 600; font-size: 0.85rem; }
.cil-table tbody tr:hover { background: #fafafa; }
.cil-tag { display: inline-block; padding: 1px 6px; background: #e0e0e0; color: #555; border-radius: 3px; font-size: 0.7rem; margin-right: 2px; }
.cil-create-btn { display: inline-block; padding: 4px 10px; background: #3f51b5; color: #fff; border-radius: 4px; text-decoration: none; font-size: 0.85rem; }
.cil-create-btn:hover { background: #303f9f; }
.cil-amount { text-align: right; font-family: monospace; }
.cil-partner-heading { margin-top: 1.5rem; margin-bottom: 0.5rem; padding: 0.4rem 0.75rem; background: #f0f4ff; border-left: 4px solid #3f51b5; font-size: 1rem; }
.cil-info { padding: 0.6rem 0.9rem; background: #e8f0fe; border-radius: 4px; margin-bottom: 0.8rem; font-size: 0.85rem; }
.cil-warn { padding: 0.6rem 0.9rem; background: #fff3cd; border-radius: 4px; margin-bottom: 0.8rem; font-size: 0.9rem; }
.cil-err { padding: 0.6rem 0.9rem; background: #ffe6e6; border-radius: 4px; margin-bottom: 0.8rem; color: #c00; }
.cil-bulk-toolbar { display: flex; gap: 0.5rem; align-items: center; padding: 0.5rem 0.75rem; background: #fafafa; border-radius: 4px; }
</style>

<div class="container p-2">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
        <h2 style="margin:0;">指定請求書一覧</h2>
        <a href="/pages/custom-invoice-manual.php" target="_blank" style="text-decoration:none; padding:6px 12px; background:#607d8b; color:#fff; border-radius:4px; font-size:0.85rem;">マニュアル（新規テンプレ追加方法）</a>
    </div>

    <?php if (!$driveFolder): ?>
        <div class="cil-warn">
            テンプレート保管フォルダが未設定です。
            <a href="/pages/settings.php?tab=google_drive_folders">設定 → Google Drive保存先</a> から「指定請求書テンプレート」フォルダを登録してください。
        </div>
    <?php elseif (empty($templates)): ?>
        <div class="cil-warn">
            テンプレートフォルダ「<?= htmlspecialchars($driveFolder['name'] ?: $driveFolder['id']) ?>」にxlsxファイルがありません。
            取引先ごとのテンプレ xlsx を同フォルダにアップロードしてください。
            <?php if ($driveDebug): ?>
            <details style="margin-top:0.5rem;">
                <summary style="cursor:pointer">フォルダ内の検出ファイル（デバッグ情報）</summary>
                <div style="background:#fff; padding:0.5rem; border-radius:4px; margin-top:0.3rem; font-size:0.85rem;">
                    <p>フォルダID: <code><?= htmlspecialchars($driveFolder['id']) ?></code></p>
                    <p>検出ファイル数: <?= $driveDebug['total_files'] ?></p>
                    <?php if (!empty($driveDebug['items'])): ?>
                    <ul style="margin:0.3rem 0 0 1rem;">
                        <?php foreach ($driveDebug['items'] as $it): ?>
                        <li><?= htmlspecialchars($it['name']) ?> <span style="color:#888">(<?= htmlspecialchars($it['mimeType']) ?>)</span></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p>（ファイルが1件も取得できていません。Driveのフォルダ権限、またはOAuthスコープの問題の可能性があります）</p>
                    <?php endif; ?>
                </div>
            </details>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="cil-info">
            テンプレ保管先: <strong><?= htmlspecialchars($driveFolder['name'] ?: $driveFolder['id']) ?></strong>
            / 登録テンプレ: <strong><?= count($templates) ?></strong>件
            (<?= htmlspecialchars(implode('、', array_column($templates, 'partner_key'))) ?>)
        </div>
    <?php endif; ?>

    <?php if ($driveError): ?><div class="cil-err"><?= htmlspecialchars($driveError) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="cil-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="GET" class="cil-filter mb-2 p-1 rounded bg-f5f5f5">
        <label>対象月:
            <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" class="form-input" style="width: auto;">
        </label>
        <button type="submit" class="btn btn-primary">表示</button>
        <button type="submit" name="refresh" value="1" class="btn btn-secondary">最新データ取得</button>
        <?php if ($cacheInfo): ?>
        <span class="text-sm text-gray-666">
            <?php if ($cacheInfo['expired']): ?>キャッシュ期限切れ<?php else: ?><?= htmlspecialchars($cacheInfo['cached_at']) ?> 取得（残り<?= floor($cacheInfo['remaining_seconds'] / 60) ?>分）<?php endif; ?>
        </span>
        <?php endif; ?>
    </form>

    <?php if (empty($invoices) && !$error && !empty($templates)): ?>
        <p class="text-gray-666">対象月に該当する請求書はありません。</p>
    <?php elseif (!empty($invoices)): ?>
        <p class="text-sm mb-1">取引先: <strong><?= count($byPartner) ?></strong>社 / 請求書: <strong><?= count($invoices) ?></strong>件</p>

        <?php foreach ($byPartner as $partnerName => $partnerInvoices):
            $matchedTpl = resolveTemplateForInvoice($partnerName, $templates);
            $partnerSlug = md5($partnerName);
        ?>
        <h3 class="cil-partner-heading"><?= htmlspecialchars($partnerName) ?> (<?= count($partnerInvoices) ?>件) <span class="text-sm text-gray-666">テンプレ: <?= htmlspecialchars($matchedTpl['name'] ?? '') ?></span></h3>
        <?php if ($matchedTpl): ?>
        <div class="cil-bulk-toolbar mb-1" data-partner-slug="<?= htmlspecialchars($partnerSlug) ?>" data-template-id="<?= htmlspecialchars($matchedTpl['id']) ?>">
            <button type="button" class="btn btn-secondary cil-select-all-btn" data-partner-slug="<?= htmlspecialchars($partnerSlug) ?>">全選択</button>
            <button type="button" class="btn btn-secondary cil-clear-all-btn" data-partner-slug="<?= htmlspecialchars($partnerSlug) ?>">全解除</button>
            <button type="button" class="btn btn-primary cil-bulk-create-btn"
                    data-partner-slug="<?= htmlspecialchars($partnerSlug) ?>"
                    data-template-id="<?= htmlspecialchars($matchedTpl['id']) ?>"
                    disabled>選択した請求書を纏めて作成 (<span class="cil-count">0</span>件)</button>
        </div>
        <?php endif; ?>
        <table class="cil-table">
            <thead>
                <tr>
                    <?php if ($matchedTpl): ?><th style="width:32px"></th><?php endif; ?>
                    <th style="width:80px">請求書#</th>
                    <th>件名</th>
                    <th style="width:220px">タグ</th>
                    <th style="width:110px">請求日</th>
                    <th style="width:120px" class="cil-amount">金額(税込)</th>
                    <th style="width:100px">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($partnerInvoices as $inv):
                    $tags = $inv['tag_names'] ?? [];
                ?>
                <tr>
                    <?php if ($matchedTpl): ?>
                    <td style="text-align:center;">
                        <input type="checkbox" class="cil-row-check"
                               data-partner-slug="<?= htmlspecialchars($partnerSlug) ?>"
                               value="<?= htmlspecialchars($inv['id']) ?>">
                    </td>
                    <?php endif; ?>
                    <td style="font-family:monospace; font-size:0.85rem;"><?= htmlspecialchars($inv['billing_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($inv['title'] ?? '-') ?></td>
                    <td>
                        <?php foreach (array_slice($tags, 0, 4) as $tag): ?>
                            <span class="cil-tag"><?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td><?= htmlspecialchars($inv['billing_date'] ?? '-') ?></td>
                    <td class="cil-amount">&yen;<?= number_format((float)($inv['total_price'] ?? 0)) ?></td>
                    <td>
                        <?php if ($matchedTpl): ?>
                        <a href="/pages/custom-invoice-create.php?billing_ids=<?= htmlspecialchars($inv['id']) ?>&template_id=<?= htmlspecialchars($matchedTpl['id']) ?>"
                           target="_blank"
                           class="cil-create-btn">作成</a>
                        <?php else: ?>
                        <span class="text-gray-666 text-sm">テンプレなし</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script<?= nonceAttr() ?>>
(function() {
    function updateBulkButton(slug) {
        const checks = document.querySelectorAll('.cil-row-check[data-partner-slug="' + slug + '"]:checked');
        const btn = document.querySelector('.cil-bulk-create-btn[data-partner-slug="' + slug + '"]');
        if (!btn) return;
        const count = checks.length;
        btn.querySelector('.cil-count').textContent = count;
        btn.disabled = count < 1;
    }

    document.querySelectorAll('.cil-row-check').forEach(cb => {
        cb.addEventListener('change', () => updateBulkButton(cb.dataset.partnerSlug));
    });

    document.querySelectorAll('.cil-select-all-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.dataset.partnerSlug;
            document.querySelectorAll('.cil-row-check[data-partner-slug="' + slug + '"]').forEach(cb => cb.checked = true);
            updateBulkButton(slug);
        });
    });
    document.querySelectorAll('.cil-clear-all-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.dataset.partnerSlug;
            document.querySelectorAll('.cil-row-check[data-partner-slug="' + slug + '"]').forEach(cb => cb.checked = false);
            updateBulkButton(slug);
        });
    });

    document.querySelectorAll('.cil-bulk-create-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.dataset.partnerSlug;
            const templateId = btn.dataset.templateId;
            const ids = Array.from(document.querySelectorAll('.cil-row-check[data-partner-slug="' + slug + '"]:checked')).map(cb => cb.value);
            if (ids.length === 0) return;
            const url = '/pages/custom-invoice-create.php?template_id=' + encodeURIComponent(templateId)
                + '&billing_ids=' + encodeURIComponent(ids.join(','));
            window.open(url, '_blank');
        });
    });
})();
</script>

<?php require_once '../functions/footer.php'; ?>
