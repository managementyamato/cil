<?php
/**
 * 指定請求書 Excel/PDF 生成（名前の定義ベース）
 *
 * 入力: [
 *   'template_path' => '/path/to/template.xlsx',  // ローカルパス（Drive取得後のキャッシュパス等）
 *   'branch_name'   => '宮崎営業所',
 *   'partner_code'  => '1668202000',              // 省略時は_branchesシートから解決
 *   'billing_date'  => 'YYYY-MM-DD',
 *   'items' => [
 *     [
 *       'delivery_date' => 'YYYY-MM-DD',
 *       'name'          => '品名',
 *       'quantity'      => 1,
 *       'unit_price'    => 123000,
 *       'amount'        => 123000,
 *       'note'          => '',
 *       'order_no'      => '',
 *       'reduced_tax'   => false,
 *     ], ...
 *   ],
 *   'filename_prefix' => 'アクティオ指定請求書',   // 省略時はテンプレートファイル名から推測
 * ]
 *
 * 出力: 保存した .xlsx ファイルのパス
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

// items_table ヘッダ文字列 → フィールド名マッピング
const CUSTOM_INVOICE_HEADER_MAP = [
    '納入日' => 'delivery_date',
    '納品日' => 'delivery_date',
    '品名'   => 'name',
    '品目'   => 'name',
    '品名　名' => 'name',
    '軽減税率' => 'reduced_tax',
    '数量'   => 'quantity',
    '数　量' => 'quantity',
    '単価'   => 'unit_price',
    '金額'   => 'amount',
    '金　額' => 'amount',
    '備考'   => 'note',
    '備　考' => 'note',
    '注文No' => 'order_no',
    '注文No.' => 'order_no',
    '注文№' => 'order_no',
    '注文番号' => 'order_no',
];

/**
 * 指定請求書(Excel)を生成
 */
function generateCustomInvoiceXlsx(array $input, string $outputDir): string
{
    if (empty($input['template_path']) || !file_exists($input['template_path'])) {
        throw new RuntimeException('テンプレートファイルが見つかりません: ' . ($input['template_path'] ?? ''));
    }

    $spreadsheet = IOFactory::load($input['template_path']);

    // 書き込み対象シート(最初のシート、または_branches以外の最初のシート)
    $sheet = null;
    foreach ($spreadsheet->getAllSheets() as $s) {
        if ($s->getTitle() === '_branches') continue;
        if ($s->getSheetState() !== \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN) {
            $sheet = $s;
            break;
        }
    }
    if (!$sheet) $sheet = $spreadsheet->getActiveSheet();

    // 名前の定義を取得
    $definedNames = $spreadsheet->getDefinedNames();
    $definedNameMap = [];
    foreach ($definedNames as $dn) {
        $definedNameMap[$dn->getName()] = $dn;
    }

    // 名前の定義が不足している場合、自動検出でDefinedNameを生成・追加
    $autoDetected = autoDetectTemplateRanges($sheet);
    foreach ($autoDetected as $name => $rangeRef) {
        if (!isset($definedNameMap[$name]) && $rangeRef !== '') {
            $fullRef = "'" . str_replace("'", "''", $sheet->getTitle()) . "'!" . $rangeRef;
            $dn = \PhpOffice\PhpSpreadsheet\DefinedName::createInstance($name, $sheet, $fullRef);
            $spreadsheet->addDefinedName($dn);
            $definedNameMap[$name] = $dn;
        }
    }

    // 必須検証
    $missing = [];
    if (!isset($definedNameMap['items_table'])) $missing[] = 'items_table（明細表）';
    $hasBillingDate = isset($definedNameMap['billing_date'])
        || (isset($definedNameMap['billing_date_year']) && isset($definedNameMap['billing_date_month']) && isset($definedNameMap['billing_date_day']));
    if (!$hasBillingDate) $missing[] = 'billing_date（請求日）';
    if (!empty($missing)) {
        throw new RuntimeException(
            'テンプレートから必須項目を自動検出できませんでした: ' . implode(', ', $missing)
            . '。テンプレートに「請求日」ラベルや明細ヘッダ（品名/数量/単価/金額など）が含まれているか確認してください。'
            . '自動検出で対応できない場合は Excel の「名前の定義」で明示的に指定することもできます（docs/custom-invoice-template-guide.md 参照）。'
        );
    }

    // 営業所マスタ解決（指定されていない場合_branchesシートから探す）
    $branchName = $input['branch_name'] ?? '';
    $partnerCode = $input['partner_code'] ?? '';
    if ($branchName && !$partnerCode) {
        $resolved = resolvePartnerCodeFromBranches($spreadsheet, $branchName);
        if ($resolved !== null) $partnerCode = $resolved;
    }

    // 名前の定義（既に取得済み）から値を書き込む
    // 営業所名
    if (isset($definedNameMap['branch_name'])) {
        writeToNamedRange($spreadsheet, $definedNameMap['branch_name'], $branchName, 'text');
    }

    // 取引先コード
    if ($partnerCode !== '' && isset($definedNameMap['partner_code'])) {
        writeToNamedRange($spreadsheet, $definedNameMap['partner_code'], (string)$partnerCode, 'text');
    }

    // 請求日
    $billingDate = $input['billing_date'] ?? '';
    if ($billingDate) {
        $dt = DateTime::createFromFormat('Y-m-d', $billingDate);
        if ($dt) {
            // パターン1: 単セル billing_date
            if (isset($definedNameMap['billing_date'])) {
                $range = parseNamedRange($definedNameMap['billing_date']);
                $targetSheet = $spreadsheet->getSheetByName($range['sheet']);
                if ($targetSheet && count($range['cells']) === 1) {
                    $targetSheet->setCellValue($range['cells'][0], ExcelDate::PHPToExcel($dt));
                    // デフォルト日付フォーマット
                    $targetSheet->getStyle($range['cells'][0])
                        ->getNumberFormat()->setFormatCode('yyyy/mm/dd');
                } elseif ($targetSheet) {
                    // 複数セル：YYYYMMDDを1桁ずつ
                    writeDigitsToRange($targetSheet, $range['cells'], $dt->format('Ymd'));
                }
            }
            // パターン2: 年月日別々
            if (isset($definedNameMap['billing_date_year'])) {
                $r = parseNamedRange($definedNameMap['billing_date_year']);
                $s = $spreadsheet->getSheetByName($r['sheet']);
                if ($s) writeDigitsToRange($s, $r['cells'], $dt->format('Y'));
            }
            if (isset($definedNameMap['billing_date_month'])) {
                $r = parseNamedRange($definedNameMap['billing_date_month']);
                $s = $spreadsheet->getSheetByName($r['sheet']);
                if ($s) writeDigitsToRange($s, $r['cells'], $dt->format('m'));
            }
            if (isset($definedNameMap['billing_date_day'])) {
                $r = parseNamedRange($definedNameMap['billing_date_day']);
                $s = $spreadsheet->getSheetByName($r['sheet']);
                if ($s) writeDigitsToRange($s, $r['cells'], $dt->format('d'));
            }
        }
    }

    // 明細 items_table
    if (isset($definedNameMap['items_table'])) {
        writeItemsTable($spreadsheet, $definedNameMap['items_table'], $input['items'] ?? []);
    }

    // _branchesシートを削除（出力物には含めない）
    $branchesIdx = null;
    foreach ($spreadsheet->getAllSheets() as $idx => $s) {
        if ($s->getTitle() === '_branches') {
            $branchesIdx = $idx;
            break;
        }
    }
    if ($branchesIdx !== null) {
        $spreadsheet->removeSheetByIndex($branchesIdx);
    }

    // 出力
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }
    $prefix = $input['filename_prefix'] ?? '指定請求書';
    $filename = sprintf('%s_%s_%s.xlsx',
        $prefix,
        $branchName ?: 'no-branch',
        $billingDate ?: date('Ymd')
    );
    $filepath = $outputDir . '/' . $filename;

    $writer = new XlsxWriter($spreadsheet);
    $writer->save($filepath);

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    return $filepath;
}

/**
 * テンプレートのシートからラベル文字列で入力セルを自動検出し、
 * 名前の定義に相当する A1参照文字列のマップを返す。
 *
 * @return array [name => 'A1' or 'A1:B2' style range]
 */
function autoDetectTemplateRanges($sheet): array
{
    $result = [];
    $maxRow = min($sheet->getHighestRow(), 60);
    $maxColStr = $sheet->getHighestColumn();
    $maxCol = Coordinate::columnIndexFromString($maxColStr);
    if ($maxCol > 80) $maxCol = 80; // 過度に広いxlsxの保険

    // シート上の全ラベル取得（1..maxRow × 1..maxCol）
    // 結合セルは左上に値があるので、結合セルマップも作る
    $mergedTopLeftMap = []; // 座標 → 結合範囲の A1:B2
    foreach ($sheet->getMergeCells() as $mr) {
        [$tl] = explode(':', $mr);
        $mergedTopLeftMap[$tl] = $mr;
    }
    $cellInMergeNonTop = []; // 結合内で左上じゃないセル → true
    foreach ($sheet->getMergeCells() as $mr) {
        $allCells = Coordinate::extractAllCellReferencesInRange($mr);
        [$tl] = explode(':', $mr);
        foreach ($allCells as $c) if ($c !== $tl) $cellInMergeNonTop[$c] = true;
    }

    // === branch_name 検出: 「納入部門名」「部門名」「営業所名」「納入先」ラベルの右隣 ===
    $branchLabelCell = findCellWithText($sheet, $maxRow, $maxCol, '/(納入部門名|部門名|営業所名|納入先)/u');
    if ($branchLabelCell) {
        $target = findAdjacentInputCell($sheet, $branchLabelCell, 'right', $mergedTopLeftMap, $cellInMergeNonTop);
        if ($target) $result['branch_name'] = $target;
    }

    // === billing_date 検出: 「請求日」ラベルの右側 ===
    $billLabelCell = findCellWithText($sheet, $maxRow, $maxCol, '/請求日/u');
    if ($billLabelCell) {
        $dateCells = collectInputCellsInRow($sheet, $billLabelCell, $maxCol, $cellInMergeNonTop, 20);
        // 年月日ラベル混在パターンの検出
        $yearCells = [];
        $monthCells = [];
        $dayCells = [];
        $phase = 'year';
        foreach ($dateCells as $cell) {
            $v = trim((string)$sheet->getCell($cell)->getValue());
            if ($v === '年') { $phase = 'month'; continue; }
            if ($v === '月') { $phase = 'day'; continue; }
            if ($v === '日') break;
            // 数字プレースホルダ（既存のサンプル値）or 空のセル = 入力対象
            if ($phase === 'year') $yearCells[] = $cell;
            elseif ($phase === 'month') $monthCells[] = $cell;
            elseif ($phase === 'day') $dayCells[] = $cell;
        }
        if (!empty($yearCells) && !empty($monthCells) && !empty($dayCells)) {
            // 分割パターン
            $result['billing_date_year'] = rangesToContiguous($yearCells);
            $result['billing_date_month'] = rangesToContiguous($monthCells);
            $result['billing_date_day'] = rangesToContiguous($dayCells);
        } else {
            // 単セル（最初の入力セル、結合範囲ならその範囲全体）
            $first = $dateCells[0] ?? null;
            if ($first) {
                $result['billing_date'] = $mergedTopLeftMap[$first] ?? $first;
            }
        }
    }

    // === partner_code 検出: 「取引先コード」ラベル近傍の数字ストリップ ===
    $pcLabelCell = findCellWithText($sheet, $maxRow, $maxCol, '/(取引先コード|取引コード)/u');
    if ($pcLabelCell) {
        [$pcCol, $pcRow] = Coordinate::coordinateFromString($pcLabelCell);
        $labelRange = $mergedTopLeftMap[$pcLabelCell] ?? ($pcLabelCell . ':' . $pcLabelCell);
        [$rangeStart, $rangeEnd] = explode(':', $labelRange);
        [$labelEndCol, $labelEndRow] = Coordinate::coordinateFromString($rangeEnd);
        // ラベル範囲の周辺（±20列）に絞って探索（全行スキャンは誤検出元）
        $pcColIdx = Coordinate::columnIndexFromString($pcCol);
        $pcEndColIdx = Coordinate::columnIndexFromString($labelEndCol);
        $searchStartCol = max(1, $pcColIdx - 5);
        $searchEndCol = min($maxCol, $pcEndColIdx + 20);

        $candidateRows = [(int)$pcRow, (int)$labelEndRow + 1, (int)$labelEndRow + 2];
        $bestStrip = null;
        foreach (array_unique($candidateRows) as $tryRow) {
            $strip = findDigitStripInRow($sheet, $tryRow, $searchStartCol, $searchEndCol, $cellInMergeNonTop, 5);
            if ($strip && (!$bestStrip || count($strip) > count($bestStrip))) {
                $bestStrip = $strip;
            }
        }
        if ($bestStrip) {
            $result['partner_code'] = rangesToContiguous($bestStrip);
        } else {
            // フォールバック: ラベル右隣の単セル
            $right = findAdjacentInputCell($sheet, $pcLabelCell, 'right', $mergedTopLeftMap, $cellInMergeNonTop);
            if ($right) $result['partner_code'] = $right;
        }
    }

    // === items_table 検出: ヘッダ行を探す ===
    $tableInfo = detectItemsTable($sheet, $maxRow, $maxCol);
    if ($tableInfo) {
        $result['items_table'] = $tableInfo['range'];
    }

    return $result;
}

/**
 * シート内で指定正規表現にマッチするテキストを持つ最初のセルを返す
 */
function findCellWithText($sheet, int $maxRow, int $maxCol, string $pattern): ?string
{
    for ($r = 1; $r <= $maxRow; $r++) {
        for ($c = 1; $c <= $maxCol; $c++) {
            $cellRef = Coordinate::stringFromColumnIndex($c) . $r;
            $v = (string)$sheet->getCell($cellRef)->getValue();
            if ($v === '') continue;
            if (preg_match($pattern, $v)) return $cellRef;
        }
    }
    return null;
}

/**
 * ラベルセルの隣（right/below）で最初に現れる入力セル（空または値入りの結合セル）を返す
 * ラベル自身の行（top-alignedが普通）を起点にする
 */
function findAdjacentInputCell($sheet, string $labelCell, string $direction, array $mergedTopLeftMap, array $cellInMergeNonTop): ?string
{
    [$col, $row] = Coordinate::coordinateFromString($labelCell);
    // ラベル自体が結合セルの場合、結合範囲の右端列を起点にする（行はラベルの開始行を使う）
    $labelRange = $mergedTopLeftMap[$labelCell] ?? null;
    if ($labelRange) {
        [, $end] = explode(':', $labelRange);
        [$endCol,] = Coordinate::coordinateFromString($end);
    } else {
        $endCol = $col;
    }
    if ($direction === 'right') {
        $nextColIdx = Coordinate::columnIndexFromString($endCol) + 1;
        for ($i = 0; $i < 15; $i++) {
            $cellRef = Coordinate::stringFromColumnIndex($nextColIdx + $i) . $row;
            if (isset($cellInMergeNonTop[$cellRef])) continue;
            // 結合でも左上の単セルを返す（文字列はマージの左上に書けば自動で表示される）
            return $cellRef;
        }
    }
    return null;
}

/**
 * 指定行に「数字ストリップ」（各セルが空または1桁数字の連続範囲）があれば返す
 * ラベル近傍の取引先コード・登録番号などの分割入力セル検出用
 */
function findDigitStripInRow($sheet, int $row, int $startCol, int $endCol, array $cellInMergeNonTop, int $minLength = 5): ?array
{
    $bestStart = -1;
    $bestLen = 0;
    $curStart = -1;
    $curLen = 0;
    for ($c = $startCol; $c <= $endCol; $c++) {
        $ref = Coordinate::stringFromColumnIndex($c) . $row;
        if (isset($cellInMergeNonTop[$ref])) {
            // 結合の非トップはスキップするが、連続は続けないリセット
            if ($curLen > $bestLen) { $bestLen = $curLen; $bestStart = $curStart; }
            $curStart = -1; $curLen = 0;
            continue;
        }
        $v = trim((string)$sheet->getCell($ref)->getValue());
        $isDigitOrEmpty = $v === '' || preg_match('/^[0-9]$/', $v);
        if ($isDigitOrEmpty) {
            if ($curStart === -1) $curStart = $c;
            $curLen++;
        } else {
            if ($curLen > $bestLen) { $bestLen = $curLen; $bestStart = $curStart; }
            $curStart = -1; $curLen = 0;
        }
    }
    if ($curLen > $bestLen) { $bestLen = $curLen; $bestStart = $curStart; }
    if ($bestLen < $minLength) return null;

    $cells = [];
    for ($c = $bestStart; $c < $bestStart + $bestLen; $c++) {
        $cells[] = Coordinate::stringFromColumnIndex($c) . $row;
    }
    return $cells;
}

/**
 * 指定セル起点で同じ行の右側の入力セル（空or数字1文字or結合内非トップ）を収集
 */
function collectInputCellsInRow($sheet, string $startCell, int $maxCol, array $cellInMergeNonTop, int $limit = 15): array
{
    [$col, $row] = Coordinate::coordinateFromString($startCell);
    $startIdx = Coordinate::columnIndexFromString($col) + 1;
    $cells = [];
    $count = 0;
    for ($c = $startIdx; $c <= $maxCol && $count < $limit; $c++) {
        $ref = Coordinate::stringFromColumnIndex($c) . $row;
        if (isset($cellInMergeNonTop[$ref])) continue;
        $v = (string)$sheet->getCell($ref)->getValue();
        // 区切りラベル (年月日) は保持、空または数字プレースホルダも保持
        $cells[] = $ref;
        $count++;
    }
    return $cells;
}

/**
 * セル参照配列を連続範囲の A1:B2 形式に圧縮（非連続ならカンマ区切り）
 */
function rangesToContiguous(array $cells): string
{
    if (empty($cells)) return '';
    // 同じ行の場合のみ連続判定
    $rows = array_unique(array_map(fn($c) => Coordinate::coordinateFromString($c)[1], $cells));
    if (count($rows) === 1) {
        $cols = array_map(fn($c) => Coordinate::columnIndexFromString(Coordinate::coordinateFromString($c)[0]), $cells);
        sort($cols);
        // 連続しているかチェック
        $contiguous = true;
        for ($i = 1; $i < count($cols); $i++) {
            if ($cols[$i] !== $cols[$i-1] + 1) { $contiguous = false; break; }
        }
        if ($contiguous) {
            $row = reset($rows);
            return Coordinate::stringFromColumnIndex($cols[0]) . $row . ':' . Coordinate::stringFromColumnIndex(end($cols)) . $row;
        }
    }
    return implode(',', $cells);
}

/**
 * 明細表のヘッダ行と範囲を検出
 */
function detectItemsTable($sheet, int $maxRow, int $maxCol): ?array
{
    $headerKeywords = ['納入日', '納品日', '品名', '品目', '軽減税率', '数量', '単価', '金額', '備考', '注文'];
    $stopKeywords = ['合計', '小計', '税抜', '消費税', '振込先', '弊社使用'];

    $bestRow = 0;
    $bestMatches = [];
    for ($r = 1; $r <= $maxRow; $r++) {
        $matches = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $ref = Coordinate::stringFromColumnIndex($c) . $r;
            $v = trim((string)$sheet->getCell($ref)->getValue());
            if ($v === '') continue;
            $normV = preg_replace('/[\s\x{3000}]+/u', '', $v);
            foreach ($headerKeywords as $kw) {
                if (mb_strpos($normV, $kw) !== false) {
                    $matches[$c] = $ref; // 列番号キー
                    break;
                }
            }
        }
        if (count($matches) >= 3 && count($matches) > count($bestMatches)) {
            $bestRow = $r;
            $bestMatches = $matches;
        }
    }
    if ($bestRow === 0) return null;

    // カラム範囲
    $cols = array_keys($bestMatches);
    sort($cols);
    $firstCol = Coordinate::stringFromColumnIndex(reset($cols));
    // 最右列は、最後のヘッダセルが結合セルの場合、その結合範囲の右端まで広げる
    $lastColIdx = end($cols);
    $lastCell = Coordinate::stringFromColumnIndex($lastColIdx) . $bestRow;
    foreach ($sheet->getMergeCells() as $mr) {
        $cellsInRange = Coordinate::extractAllCellReferencesInRange($mr);
        if (in_array($lastCell, $cellsInRange, true)) {
            [, $mrEnd] = explode(':', $mr);
            [$mrEndCol,] = Coordinate::coordinateFromString($mrEnd);
            $mrEndIdx = Coordinate::columnIndexFromString($mrEndCol);
            if ($mrEndIdx > $lastColIdx) $lastColIdx = $mrEndIdx;
            break;
        }
    }
    $lastCol = Coordinate::stringFromColumnIndex($lastColIdx);

    // データ行終了を検出: ヘッダの次の行から下に向かって、合計等のキーワードが出現する手前まで
    $dataEndRow = min($bestRow + 30, $maxRow);
    for ($r = $bestRow + 1; $r <= min($bestRow + 30, $maxRow); $r++) {
        for ($c = 1; $c <= $maxCol; $c++) {
            $ref = Coordinate::stringFromColumnIndex($c) . $r;
            $v = trim((string)$sheet->getCell($ref)->getValue());
            if ($v === '') continue;
            foreach ($stopKeywords as $kw) {
                if (mb_strpos($v, $kw) !== false) {
                    $dataEndRow = $r - 1;
                    break 3;
                }
            }
        }
    }
    if ($dataEndRow <= $bestRow) $dataEndRow = $bestRow + 10;

    return [
        'range' => $firstCol . $bestRow . ':' . $lastCol . $dataEndRow,
        'header_row' => $bestRow,
        'data_start' => $bestRow + 1,
        'data_end' => $dataEndRow,
    ];
}

/**
 * 名前の定義からセル範囲情報を取得
 * 戻り値: ['sheet' => string, 'cells' => ['A1', 'A2', ...]]
 */
function parseNamedRange($definedName): array
{
    /** @var \PhpOffice\PhpSpreadsheet\DefinedName $definedName */
    $value = $definedName->getValue();
    // 例: 'シート名'!$A$1:$B$2  または  シート名!$A$1
    if (preg_match("/^'?([^'!]+)'?!(.+)$/u", $value, $m)) {
        $sheetName = $m[1];
        $rangeStr = str_replace('$', '', $m[2]);
    } else {
        // シート名なし - シート省略形式の場合
        $sheetName = $definedName->getWorksheet() ? $definedName->getWorksheet()->getTitle() : '';
        $rangeStr = str_replace('$', '', $value);
    }

    $cells = [];
    // 複数範囲（コンマ区切り）対応
    foreach (explode(',', $rangeStr) as $part) {
        $part = trim($part);
        if (strpos($part, ':') !== false) {
            // 範囲
            [$start, $end] = explode(':', $part);
            $cells = array_merge($cells, Coordinate::extractAllCellReferencesInRange("$start:$end"));
        } else {
            $cells[] = $part;
        }
    }
    return ['sheet' => $sheetName, 'cells' => $cells];
}

/**
 * 名前付き範囲に値を書き込む（単セルなら全体、複数セルなら1文字ずつ）
 */
function writeToNamedRange($spreadsheet, $definedName, string $value, string $type = 'text'): void
{
    $range = parseNamedRange($definedName);
    $sheet = $spreadsheet->getSheetByName($range['sheet']);
    if (!$sheet) return;
    $cells = $range['cells'];

    if (count($cells) === 1) {
        if ($type === 'number') {
            $sheet->setCellValue($cells[0], is_numeric($value) ? (float)$value : $value);
        } else {
            $sheet->setCellValueExplicit($cells[0], $value, DataType::TYPE_STRING);
        }
    } else {
        // 複数セル: 1文字ずつ
        writeDigitsToRange($sheet, $cells, $value);
    }
}

/**
 * 複数セルに1文字ずつ書き込む
 */
function writeDigitsToRange($sheet, array $cells, string $value): void
{
    $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
    $len = min(count($chars), count($cells));
    for ($i = 0; $i < $len; $i++) {
        $sheet->setCellValueExplicit($cells[$i], $chars[$i], DataType::TYPE_STRING);
    }
}

/**
 * _branches シートから営業所名→取引先コードを解決
 */
function resolvePartnerCodeFromBranches($spreadsheet, string $branchName): ?string
{
    $sheet = $spreadsheet->getSheetByName('_branches');
    if (!$sheet) return null;
    $highRow = $sheet->getHighestRow();
    for ($r = 2; $r <= $highRow; $r++) {
        $name = (string)$sheet->getCell("A$r")->getValue();
        if (trim($name) === trim($branchName)) {
            $code = (string)$sheet->getCell("B$r")->getValue();
            return $code !== '' ? $code : null;
        }
    }
    return null;
}

/**
 * items_table の最大明細行数（ヘッダ除く）を取得
 */
function getItemsTableCapacity(string $templatePath): int
{
    if (!file_exists($templatePath)) return 0;
    $spreadsheet = IOFactory::load($templatePath);
    $names = $spreadsheet->getDefinedNames();
    $map = [];
    foreach ($names as $n) $map[$n->getName()] = $n;
    if (!isset($map['items_table'])) {
        $spreadsheet->disconnectWorksheets();
        return 0;
    }
    $range = parseNamedRange($map['items_table']);
    $rows = [];
    foreach ($range['cells'] as $cell) {
        [, $row] = Coordinate::coordinateFromString($cell);
        $rows[(int)$row] = true;
    }
    $spreadsheet->disconnectWorksheets();
    // ヘッダ1行を除いた行数
    return max(count($rows) - 1, 0);
}

/**
 * _branches シートから営業所リスト（[[name, code], ...]）を取得
 */
function loadBranchesFromTemplate(string $templatePath): array
{
    if (!file_exists($templatePath)) return [];
    try {
        $reader = IOFactory::createReaderForFile($templatePath);
        $reader->setLoadSheetsOnly(['_branches']);
        $spreadsheet = $reader->load($templatePath);
    } catch (Throwable $e) {
        return []; // _branchesシートがないテンプレは空配列
    }
    $sheet = $spreadsheet->getSheetByName('_branches');
    if (!$sheet) { $spreadsheet->disconnectWorksheets(); return []; }

    $branches = [];
    $highRow = $sheet->getHighestRow();
    for ($r = 2; $r <= $highRow; $r++) {
        $name = trim((string)$sheet->getCell("A$r")->getValue());
        $code = trim((string)$sheet->getCell("B$r")->getValue());
        if ($name !== '') {
            $branches[] = ['name' => $name, 'partner_code' => $code];
        }
    }
    $spreadsheet->disconnectWorksheets();
    return $branches;
}

/**
 * items_table に明細行を書き込む
 */
function writeItemsTable($spreadsheet, $definedName, array $items): void
{
    $range = parseNamedRange($definedName);
    $sheet = $spreadsheet->getSheetByName($range['sheet']);
    if (!$sheet || empty($range['cells'])) return;

    // 範囲の行範囲を特定
    $rows = [];
    $cols = [];
    foreach ($range['cells'] as $cell) {
        [$col, $row] = Coordinate::coordinateFromString($cell);
        $rows[] = (int)$row;
        $cols[] = Coordinate::columnIndexFromString($col);
    }
    $rows = array_unique($rows);
    sort($rows);
    $cols = array_unique($cols);
    sort($cols);

    if (count($rows) < 2) {
        throw new RuntimeException('items_tableはヘッダ行とデータ行の2行以上必要です');
    }
    $headerRow = $rows[0];
    $dataStartRow = $rows[1];
    $dataEndRow = end($rows);
    $maxRows = $dataEndRow - $dataStartRow + 1;

    if (count($items) > $maxRows) {
        throw new RuntimeException("明細行の上限（{$maxRows}行）を超えています。入力: " . count($items) . '行');
    }

    // ヘッダ行から列→フィールドマップを作成（各結合セルの左上のみ処理）
    $mergedCellsMap = []; // 非左上の結合セル座標 → true
    foreach ($sheet->getMergeCells() as $mergeRange) {
        $allCells = Coordinate::extractAllCellReferencesInRange($mergeRange);
        [$topLeft] = explode(':', $mergeRange);
        foreach ($allCells as $c) {
            if ($c !== $topLeft) $mergedCellsMap[$c] = true;
        }
    }

    $fieldToCol = [];
    foreach ($cols as $colIdx) {
        $colLetter = Coordinate::stringFromColumnIndex($colIdx);
        $targetCell = $colLetter . $headerRow;
        // 結合セルの左上以外はスキップ（結合の重複処理を避ける）
        if (isset($mergedCellsMap[$targetCell])) continue;

        $headerValue = trim((string)$sheet->getCell($targetCell)->getValue());
        if ($headerValue === '') continue;

        // 正規化（ASCII/全角空白両方を除去）
        $normalized = preg_replace('/[\s\x{3000}]+/u', '', $headerValue);
        foreach (CUSTOM_INVOICE_HEADER_MAP as $pattern => $field) {
            $patternNorm = preg_replace('/[\s\x{3000}]+/u', '', $pattern);
            if ($normalized === $patternNorm || mb_strpos($normalized, $patternNorm) !== false) {
                // 既に割り当て済みなら上書きしない（最初のマッチ優先）
                if (!isset($fieldToCol[$field])) {
                    $fieldToCol[$field] = $colLetter;
                }
                break;
            }
        }
    }

    // データ書き込み
    foreach ($items as $i => $item) {
        $row = $dataStartRow + $i;

        if (isset($fieldToCol['delivery_date']) && !empty($item['delivery_date'])) {
            $dt = DateTime::createFromFormat('Y-m-d', $item['delivery_date']);
            $cell = $fieldToCol['delivery_date'] . $row;
            if ($dt) {
                $sheet->setCellValue($cell, ExcelDate::PHPToExcel($dt));
                $format = $sheet->getStyle($cell)->getNumberFormat()->getFormatCode();
                if ($format === 'General' || $format === '') {
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('m"月"d"日"');
                }
            } else {
                $sheet->setCellValue($cell, $item['delivery_date']);
            }
        }
        if (isset($fieldToCol['name'])) {
            $sheet->setCellValue($fieldToCol['name'] . $row, (string)($item['name'] ?? ''));
        }
        if (isset($fieldToCol['reduced_tax']) && !empty($item['reduced_tax'])) {
            $sheet->setCellValue($fieldToCol['reduced_tax'] . $row, '※');
        }
        if (isset($fieldToCol['quantity']) && isset($item['quantity']) && $item['quantity'] !== '') {
            $sheet->setCellValue($fieldToCol['quantity'] . $row, (float)$item['quantity']);
        }
        if (isset($fieldToCol['unit_price']) && isset($item['unit_price']) && $item['unit_price'] !== '') {
            $sheet->setCellValue($fieldToCol['unit_price'] . $row, (float)$item['unit_price']);
        }
        if (isset($fieldToCol['amount']) && isset($item['amount']) && $item['amount'] !== '') {
            $sheet->setCellValue($fieldToCol['amount'] . $row, (float)$item['amount']);
        }
        if (isset($fieldToCol['note'])) {
            $sheet->setCellValue($fieldToCol['note'] . $row, (string)($item['note'] ?? ''));
        }
        if (isset($fieldToCol['order_no'])) {
            $sheet->setCellValue($fieldToCol['order_no'] . $row, (string)($item['order_no'] ?? ''));
        }
    }
}

/**
 * xlsx → PDF 変換（LibreOffice の soffice コマンドが必要）
 */
function convertXlsxToPdf(string $xlsxPath, string $outputDir): string
{
    $sofficeCmd = findSofficeCommand();
    if (!$sofficeCmd) {
        throw new RuntimeException('LibreOffice (soffice) がインストールされていません。サーバー管理者に連絡してください。');
    }
    if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);
    $escapedXlsx = escapeshellarg($xlsxPath);
    $escapedOut = escapeshellarg($outputDir);
    $cmd = "$sofficeCmd --headless --nologo --nofirststartwizard --convert-to pdf --outdir $escapedOut $escapedXlsx 2>&1";
    exec($cmd, $output, $retval);
    if ($retval !== 0) {
        throw new RuntimeException('PDF変換に失敗: ' . implode("\n", $output));
    }
    $pdfPath = $outputDir . '/' . pathinfo($xlsxPath, PATHINFO_FILENAME) . '.pdf';
    if (!file_exists($pdfPath)) {
        throw new RuntimeException('PDFファイルが生成されませんでした: ' . $pdfPath);
    }
    return $pdfPath;
}

function findSofficeCommand(): ?string
{
    $candidates = [
        'soffice',
        'libreoffice',
        'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        '/usr/bin/soffice',
        '/usr/bin/libreoffice',
    ];
    foreach ($candidates as $cmd) {
        $check = stripos(PHP_OS, 'WIN') === 0
            ? "where \"$cmd\" 2>NUL"
            : "command -v " . escapeshellarg($cmd) . " 2>/dev/null";
        if (file_exists($cmd)) {
            return '"' . $cmd . '"';
        }
        $result = @shell_exec($check);
        if ($result && trim($result)) {
            return escapeshellarg($cmd);
        }
    }
    return null;
}
