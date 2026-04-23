<?php
/**
 * 指定請求書 生成API
 *
 * GET  ?action=list_templates              - Driveフォルダ内のxlsxテンプレ一覧を返す
 * GET  ?action=init&billing_id=xxx         - MF請求書データ+テンプレ情報を返す
 * POST ?action=save_folder                 - Driveフォルダ設定を保存
 * POST ?action=generate                    - Excel生成してバイナリダウンロード
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/custom-invoice-generator.php';
require_once __DIR__ . '/mf-api.php';
require_once __DIR__ . '/google-drive.php';

ini_set('memory_limit', '1024M');
set_time_limit(120);

const CUSTOM_INVOICE_XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
const CUSTOM_INVOICE_CACHE_DIR_NAME = 'custom-invoice-templates';
const CUSTOM_INVOICE_CACHE_TTL = 600; // 10分

$action = $_GET['action'] ?? '';

// ==== list_templates: Driveフォルダ内のテンプレ一覧 ====
if ($action === 'list_templates') {
    initApi(['requireAuth' => true, 'requireCsrf' => false, 'allowedMethods' => ['GET']]);
    if (!isAdmin()) errorResponse('管理者権限が必要です', 403);

    try {
        $drive = new GoogleDriveClient();
        $folder = $drive->getCustomInvoiceFolder();
        if (!$folder) {
            successResponse(['folder' => null, 'templates' => []]);
        }
        // 全ファイル取得してmime/拡張子の両方でxlsx判定
        $files = $drive->listFilesInFolder($folder['id'], null);
        $templates = [];
        foreach ($files as $f) {
            $name = $f['name'] ?? '';
            $mime = $f['mimeType'] ?? '';
            $isXlsx = $mime === CUSTOM_INVOICE_XLSX_MIME
                  || strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'xlsx';
            if (!$isXlsx) continue;
            $templates[] = [
                'id' => $f['id'],
                'name' => $name,
                'partner_key' => pathinfo($name, PATHINFO_FILENAME),
                'modified_time' => $f['modifiedTime'] ?? '',
            ];
        }
        successResponse(['folder' => $folder, 'templates' => $templates]);
    } catch (Throwable $e) {
        error_log('[custom-invoice-api:list_templates] ' . $e->getMessage());
        errorResponse($e->getMessage(), 500);
    }
    exit;
}

// ==== save_folder: Driveフォルダ設定を保存 ====
if ($action === 'save_folder') {
    initApi(['requireAuth' => true, 'requireCsrf' => true, 'allowedMethods' => ['POST']]);
    if (!isAdmin()) errorResponse('管理者権限が必要です', 403);
    try {
        $folderId = trim($_POST['folder_id'] ?? '');
        $folderName = trim($_POST['folder_name'] ?? '');
        if ($folderId === '') errorResponse('フォルダIDは必須です', 400);
        $drive = new GoogleDriveClient();
        $drive->saveCustomInvoiceFolder($folderId, $folderName);
        successResponse(['folder_id' => $folderId, 'folder_name' => $folderName], '保存しました');
    } catch (Throwable $e) {
        error_log('[custom-invoice-api:save_folder] ' . $e->getMessage());
        errorResponse($e->getMessage(), 500);
    }
    exit;
}

// ==== init: MF請求書 + 営業所リスト ====
if ($action === 'init') {
    initApi(['requireAuth' => true, 'requireCsrf' => false, 'allowedMethods' => ['GET']]);
    if (!isAdmin()) errorResponse('管理者権限が必要です', 403);

    $billingId = $_GET['billing_id'] ?? '';
    $templateId = $_GET['template_id'] ?? '';
    try {
        $mfInvoice = null;
        if ($billingId !== '') {
            if (!MFApiClient::isConfigured()) {
                errorResponse('MFクラウド請求書APIが設定されていません', 500);
            }
            $client = new MFApiClient();
            $detail = $client->getInvoiceDetail($billingId);
            $mfInvoice = $detail['billing'] ?? $detail['data'] ?? (isset($detail['id']) ? $detail : null);
            if (!$mfInvoice) {
                errorResponse('指定されたMF請求書が見つかりません', 404);
            }
        }
        // テンプレートから営業所一覧を取得
        $branches = [];
        if ($templateId !== '') {
            $tpl = fetchCustomInvoiceTemplate($templateId);
            $branches = loadBranchesFromTemplate($tpl['path']);
        }
        successResponse([
            'mf_invoice' => $mfInvoice ? normalizeMfInvoiceForForm($mfInvoice) : null,
            'branches' => $branches,
        ]);
    } catch (Throwable $e) {
        error_log('[custom-invoice-api:init] ' . $e->getMessage());
        errorResponse($e->getMessage(), 500);
    }
    exit;
}

// ==== generate: Excel生成 ====
if ($action === 'generate') {
    initApi(['requireAuth' => true, 'requireCsrf' => true, 'allowedMethods' => ['POST']]);
    if (!isAdmin()) errorResponse('管理者権限が必要です', 403);

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) errorResponse('リクエストボディが不正です', 400);

        $format = $input['format'] ?? 'xlsx';
        if (!in_array($format, ['xlsx', 'pdf'], true)) {
            errorResponse('formatはxlsxまたはpdfのみ対応', 400);
        }
        $templateId = $input['template_id'] ?? '';
        if ($templateId === '') errorResponse('template_idは必須です', 400);

        $tpl = fetchCustomInvoiceTemplate($templateId);
        $templatePath = $tpl['path'];
        $input['template_path'] = $templatePath;
        if (empty($input['filename_prefix'])) {
            $input['filename_prefix'] = $tpl['base_name']; // 例: アクティオ
        }

        $outputDir = __DIR__ . '/../temp/custom-invoices';
        $items = $input['items'] ?? [];

        // テンプレの明細容量を取得
        $capacity = getItemsTableCapacity($templatePath);
        if ($capacity <= 0) {
            // items_tableが定義されていない場合は単一ファイル生成にフォールバック
            $capacity = max(count($items), 1);
        }

        // 上限内なら単一ファイル、超える場合は分割
        if (count($items) <= $capacity) {
            $xlsxPath = generateCustomInvoiceXlsx($input, $outputDir);
            if ($format === 'pdf') {
                $pdfPath = convertXlsxToPdf($xlsxPath, $outputDir);
                streamDownload($pdfPath, 'application/pdf');
            } else {
                streamDownload($xlsxPath, CUSTOM_INVOICE_XLSX_MIME);
            }
        } else {
            // 分割処理
            $chunks = array_chunk($items, $capacity);
            $totalParts = count($chunks);
            $files = [];
            foreach ($chunks as $i => $chunk) {
                $partNum = $i + 1;
                $partInput = $input;
                $partInput['items'] = $chunk;
                $partInput['filename_prefix'] = sprintf('%s_%d_of_%d', $input['filename_prefix'], $partNum, $totalParts);
                $xlsxPath = generateCustomInvoiceXlsx($partInput, $outputDir);
                if ($format === 'pdf') {
                    $files[] = convertXlsxToPdf($xlsxPath, $outputDir);
                    @unlink($xlsxPath);
                } else {
                    $files[] = $xlsxPath;
                }
            }
            // ZIP化
            $zipName = sprintf('%s_%s_%s_%d分割.zip',
                $input['filename_prefix'],
                $input['branch_name'] ?? 'no-branch',
                $input['billing_date'] ?? date('Ymd'),
                $totalParts
            );
            $zipPath = $outputDir . '/' . $zipName;
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('ZIPファイルを作成できませんでした');
            }
            foreach ($files as $f) {
                $zip->addFile($f, basename($f));
            }
            $zip->close();
            foreach ($files as $f) @unlink($f);
            streamDownload($zipPath, 'application/zip');
        }
    } catch (Throwable $e) {
        error_log('[custom-invoice-api:generate] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        errorResponse($e->getMessage(), 500);
    }
    exit;
}

errorResponse('未知のaction', 400);

// ============================================================
// ヘルパー関数
// ============================================================

/**
 * Driveからテンプレートをダウンロードしてローカルキャッシュに保存し、
 * ['path' => string, 'base_name' => string] を返す
 */
function fetchCustomInvoiceTemplate(string $templateId): array
{
    $cacheDir = __DIR__ . '/../temp/' . CUSTOM_INVOICE_CACHE_DIR_NAME;
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);
    $cachePath = $cacheDir . '/' . $templateId . '.xlsx';
    $metaPath = $cacheDir . '/' . $templateId . '.meta.json';

    if (file_exists($cachePath) && file_exists($metaPath)
        && (time() - filemtime($cachePath)) < CUSTOM_INVOICE_CACHE_TTL) {
        $meta = json_decode(@file_get_contents($metaPath), true) ?: [];
        return ['path' => $cachePath, 'base_name' => $meta['base_name'] ?? pathinfo($templateId, PATHINFO_FILENAME)];
    }

    $drive = new GoogleDriveClient();
    $info = $drive->getFileInfo($templateId);
    $baseName = pathinfo($info['name'] ?? ($templateId . '.xlsx'), PATHINFO_FILENAME);

    $content = $drive->getFileContent($templateId);
    if ($content === false || $content === '') {
        throw new RuntimeException('Driveからテンプレートを取得できませんでした');
    }
    file_put_contents($cachePath, $content);
    file_put_contents($metaPath, json_encode(['base_name' => $baseName], JSON_UNESCAPED_UNICODE));
    return ['path' => $cachePath, 'base_name' => $baseName];
}

function normalizeMfInvoiceForForm(array $mfInvoice): array
{
    $fallbackDate = mfDateToIso($mfInvoice['sales_date'] ?? $mfInvoice['billing_date'] ?? '');
    $items = [];
    foreach ($mfInvoice['items'] ?? [] as $item) {
        $excise = $item['excise'] ?? '';
        $qty = (float)($item['quantity'] ?? 1);
        $amount = (float)($item['price'] ?? 0);
        $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price']
                   : ($qty > 0 ? round($amount / $qty, 2) : 0);
        $items[] = [
            'delivery_date' => mfDateToIso($item['delivery_date'] ?? '') ?: $fallbackDate,
            'name' => $item['name'] ?? '',
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'amount' => $amount,
            'note' => $item['detail'] ?? '',
            'order_no' => '',
            'reduced_tax' => in_array($excise, ['eight_percent', 'eight_percent_as_reduced_tax_rate'], true),
        ];
    }
    return [
        'id' => $mfInvoice['id'] ?? '',
        'billing_number' => $mfInvoice['billing_number'] ?? '',
        'title' => $mfInvoice['title'] ?? '',
        'partner_name' => $mfInvoice['partner_name'] ?? '',
        'billing_date' => mfDateToIso($mfInvoice['billing_date'] ?? ''),
        'sales_date' => mfDateToIso($mfInvoice['sales_date'] ?? ''),
        'tag_names' => $mfInvoice['tag_names'] ?? [],
        'items' => $items,
    ];
}

function mfDateToIso(?string $s): string
{
    if (!$s) return '';
    $s = str_replace('/', '-', $s);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : '';
}

function streamDownload(string $path, string $mimeType): void
{
    $filename = basename($path);
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($path);
    @unlink($path);
    exit;
}
