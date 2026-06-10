<?php
/**
 * CSVインポート API
 *
 * 設定画面からCSVファイルをアップロードし、各エンティティにデータを取り込む。
 *
 * action=template   → エンティティのCSVテンプレート（ヘッダ行のみ）をダウンロード
 * action=preview    → CSVファイルをパースしてプレビュー（JSON返却）
 * action=import     → プレビュー後に確定インポート
 *
 * 管理者(admin)のみ使用可。
 */
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../functions/soft-delete.php';

// admin 限定
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '管理者権限が必要です']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ──────────────────────────────────────
// インポート可能エンティティ定義
// key      : DB テーブル名 (= getData/saveData のキー)
// label    : UI 表示名
// columns  : CSVカラム定義 (key => label)
//            key = DB カラム名, label = CSV ヘッダ / UI 表示名
// required : 必須カラム（CSV に含まれていなければエラー）
// ──────────────────────────────────────
$IMPORT_ENTITIES = [
    'projects' => [
        'label' => '案件',
        'columns' => [
            'name'            => '案件名',
            'customer_name'   => '顧客名',
            'sales_assignee'  => '営業担当',
            'dealer_name'     => '販売店名',
            'office_name'     => '営業所名',
            'maker'           => 'メーカー',
            'led_size'        => 'LEDサイズ',
            'lcd_size'        => 'LCDサイズ',
            'status'          => 'ステータス',
            'memo'            => '備考',
        ],
        'required' => ['name'],
    ],
    'troubles' => [
        'label' => 'トラブル対応',
        'columns' => [
            'project_name'  => '案件名',
            'title'         => 'タイトル',
            'description'   => '内容',
            'status'        => 'ステータス',
            'priority'      => '優先度',
            'responder'     => '対応者',
            'deadline'      => '期限',
        ],
        'required' => ['title'],
    ],
    'customers' => [
        'label' => '顧客',
        'columns' => [
            'companyName'     => '会社名',
            'contact'         => '担当者名',
            'phone'           => '電話番号',
            'email'           => 'メールアドレス',
            'address'         => '住所',
            'notes'           => '備考',
            'customer_code'   => '顧客コード',
            'customer_rank'   => 'ランク',
            'industry'        => '業種',
            'area'            => 'エリア',
            'account_status'  => 'ステータス',
            'account_type'    => '種別',
            'am_person'       => 'AM担当者',
            'hq_location'     => '本社所在地',
            'priority'        => '優先度',
        ],
        'required' => ['companyName'],
    ],
    'employees' => [
        'label' => '従業員',
        'columns' => [
            'name'       => '氏名',
            'email'      => 'メールアドレス',
            'department' => '部署',
            'role'       => '権限',
        ],
        'required' => ['name'],
    ],
    'leads' => [
        'label' => 'リード',
        'columns' => [
            'company_name'  => '会社名',
            'contact_name'  => '担当者名',
            'email'         => 'メールアドレス',
            'phone'         => '電話番号',
            'source'        => '流入経路',
            'status'        => 'ステータス',
            'notes'         => '備考',
            'assigned_to'   => '担当者',
        ],
        'required' => ['company_name'],
    ],
    'business_cards' => [
        'label' => '名刺',
        'columns' => [
            'company_name'  => '会社名',
            'person_name'   => '氏名',
            'department'    => '部署',
            'position'      => '役職',
            'email'         => 'メールアドレス',
            'phone'         => '電話番号',
            'mobile'        => '携帯番号',
            'address'       => '住所',
            'notes'         => '備考',
        ],
        'required' => ['person_name'],
    ],
    'contacts' => [
        'label' => '社内連絡先',
        'columns' => [
            'name'       => '氏名',
            'company'    => '会社名',
            'department' => '部署',
            'position'   => '役職',
            'phone'      => '電話番号',
            'mobile'     => '携帯番号',
            'email'      => 'メールアドレス',
            'address'    => '住所',
            'notes'      => '備考',
            'category'   => 'カテゴリ',
        ],
        'required' => ['name'],
    ],
    'partners' => [
        'label' => '協力会社',
        'columns' => [
            'name'    => '会社名',
            'contact' => '担当者名',
            'phone'   => '電話番号',
            'email'   => 'メールアドレス',
            'address' => '住所',
            'notes'   => '備考',
        ],
        'required' => ['name'],
    ],
    'manufacturers' => [
        'label' => 'メーカー',
        'columns' => [
            'name'    => 'メーカー名',
            'contact' => '担当者名',
            'phone'   => '電話番号',
            'email'   => 'メールアドレス',
            'notes'   => '備考',
        ],
        'required' => ['name'],
    ],
    'weekly_reports' => [
        'label' => '週報',
        'columns' => [
            'employee_name'  => '提出者',
            'report_date'    => '提出日',
            'week_start'     => '週開始日',
            'week_end'       => '週終了日',
            'achievements'   => '今週の成果',
            'issues'         => '課題・問題点',
            'next_plan'      => '来週の計画',
            'memo'           => '備考',
        ],
        'required' => ['employee_name', 'report_date'],
    ],
    'discount_approvals' => [
        'label' => '値引き申請',
        'columns' => [
            'applicant'       => '申請者',
            'project_name'    => '案件名',
            'customer_name'   => '顧客名',
            'product_name'    => '製品名',
            'original_price'  => '定価',
            'discount_price'  => '値引後価格',
            'discount_rate'   => '値引率',
            'reason'          => '値引理由',
            'status'          => 'ステータス',
        ],
        'required' => ['applicant', 'project_name'],
    ],
];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ──────────────────────────────────────
// テンプレートダウンロード
// ──────────────────────────────────────
if ($action === 'template') {
    $entity = $_GET['entity'] ?? '';
    if (!isset($IMPORT_ENTITIES[$entity])) {
        echo json_encode(['success' => false, 'error' => '不明なエンティティ: ' . $entity]);
        exit;
    }
    $def = $IMPORT_ENTITIES[$entity];
    $headers = array_values($def['columns']);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $entity . '_template.csv"');
    // BOM (Excel で UTF-8 を認識させるため)
    echo "\xEF\xBB\xBF";
    $fp = fopen('php://output', 'w');
    fputcsv($fp, $headers);
    fclose($fp);
    exit;
}

// ──────────────────────────────────────
// エンティティ一覧
// ──────────────────────────────────────
if ($action === 'entities') {
    $list = [];
    foreach ($IMPORT_ENTITIES as $key => $def) {
        $list[] = [
            'key'      => $key,
            'label'    => $def['label'],
            'columns'  => $def['columns'],
            'required' => $def['required'],
        ];
    }
    echo json_encode(['success' => true, 'entities' => $list], JSON_UNESCAPED_UNICODE);
    exit;
}

// ──────────────────────────────────────
// CSV パース共通
// ──────────────────────────────────────
function parseCsvUpload($entityDef) {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errMsg = 'ファイルのアップロードに失敗しました';
        if (isset($_FILES['csv_file'])) {
            $codes = [
                UPLOAD_ERR_INI_SIZE   => 'ファイルサイズが上限を超えています',
                UPLOAD_ERR_FORM_SIZE  => 'フォームのサイズ上限を超えています',
                UPLOAD_ERR_PARTIAL    => 'ファイルが途中までしかアップロードされていません',
                UPLOAD_ERR_NO_FILE    => 'ファイルが選択されていません',
            ];
            $errMsg = $codes[$_FILES['csv_file']['error']] ?? $errMsg;
        }
        return ['error' => $errMsg];
    }

    $tmpPath = $_FILES['csv_file']['tmp_name'];
    $raw = file_get_contents($tmpPath);
    if ($raw === false || strlen($raw) === 0) {
        return ['error' => 'ファイルが空です'];
    }

    // エンコーディング検出 & UTF-8 変換
    $enc = mb_detect_encoding($raw, ['UTF-8', 'SJIS-win', 'SJIS', 'EUC-JP', 'ASCII'], true);
    if ($enc && $enc !== 'UTF-8') {
        $raw = mb_convert_encoding($raw, 'UTF-8', $enc);
    }
    // BOM 除去
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        $raw = substr($raw, 3);
    }

    // 改行コード統一
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);

    // CSV パース
    $lines = [];
    $tmpFile = tmpfile();
    fwrite($tmpFile, $raw);
    fseek($tmpFile, 0);
    while (($row = fgetcsv($tmpFile)) !== false) {
        // 空行スキップ
        if (count($row) === 1 && ($row[0] === null || $row[0] === '')) continue;
        $lines[] = $row;
    }
    fclose($tmpFile);

    if (count($lines) < 2) {
        return ['error' => 'データが見つかりません（ヘッダ行 + 1行以上必要）'];
    }

    $csvHeaders = array_map('trim', $lines[0]);
    $dataRows   = array_slice($lines, 1);

    // CSV ヘッダ → DB カラムのマッピング
    // 日本語ラベル or DB カラム名のどちらでもマッチ
    $labelToKey = array_flip($entityDef['columns']); // label → key
    $columnMap  = []; // csvIndex => dbColumn
    $mappedLabels = [];
    foreach ($csvHeaders as $i => $header) {
        $h = trim($header);
        if (isset($entityDef['columns'][$h])) {
            // DB カラム名でマッチ
            $columnMap[$i] = $h;
            $mappedLabels[$i] = $entityDef['columns'][$h];
        } elseif (isset($labelToKey[$h])) {
            // 日本語ラベルでマッチ
            $columnMap[$i] = $labelToKey[$h];
            $mappedLabels[$i] = $h;
        }
        // マッチしないカラムは無視
    }

    if (empty($columnMap)) {
        $expected = implode(', ', array_values($entityDef['columns']));
        return ['error' => 'CSVのヘッダが一致しません。期待するヘッダ: ' . $expected];
    }

    // 必須カラムチェック
    $mappedDbColumns = array_values($columnMap);
    $missingRequired = [];
    foreach ($entityDef['required'] as $req) {
        if (!in_array($req, $mappedDbColumns, true)) {
            $missingRequired[] = $entityDef['columns'][$req] ?? $req;
        }
    }
    if (!empty($missingRequired)) {
        return ['error' => '必須カラムがCSVに含まれていません: ' . implode(', ', $missingRequired)];
    }

    // データ行をパース
    $rows = [];
    $errors = [];
    foreach ($dataRows as $rowIdx => $csvRow) {
        $record = [];
        foreach ($columnMap as $csvIdx => $dbCol) {
            $val = isset($csvRow[$csvIdx]) ? trim($csvRow[$csvIdx]) : '';
            $record[$dbCol] = $val;
        }

        // 必須フィールドバリデーション
        $rowErrors = [];
        foreach ($entityDef['required'] as $req) {
            if (empty($record[$req] ?? '')) {
                $label = $entityDef['columns'][$req] ?? $req;
                $rowErrors[] = $label . 'は必須です';
            }
        }

        $rows[] = [
            'data'   => $record,
            'errors' => $rowErrors,
            'row'    => $rowIdx + 2, // 1-indexed, +1 for header
        ];
        if (!empty($rowErrors)) {
            $errors[] = ($rowIdx + 2) . '行目: ' . implode(', ', $rowErrors);
        }
    }

    return [
        'headers'    => $csvHeaders,
        'columnMap'  => $columnMap,
        'mappedLabels' => $mappedLabels,
        'rows'       => $rows,
        'errors'     => $errors,
        'totalRows'  => count($rows),
        'errorCount' => count($errors),
        'validCount' => count($rows) - count($errors),
    ];
}

// ──────────────────────────────────────
// プレビュー
// ──────────────────────────────────────
if ($action === 'preview') {
    verifyCsrfToken();
    $entity = $_POST['entity'] ?? '';
    if (!isset($IMPORT_ENTITIES[$entity])) {
        echo json_encode(['success' => false, 'error' => '不明なエンティティ: ' . $entity]);
        exit;
    }
    $def = $IMPORT_ENTITIES[$entity];
    $result = parseCsvUpload($def);

    if (isset($result['error'])) {
        echo json_encode(['success' => false, 'error' => $result['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // プレビュー用に先頭20行だけ返す
    $previewRows = array_slice($result['rows'], 0, 20);

    echo json_encode([
        'success'    => true,
        'entity'     => $entity,
        'label'      => $def['label'],
        'columns'    => $def['columns'],
        'totalRows'  => $result['totalRows'],
        'validCount' => $result['validCount'],
        'errorCount' => $result['errorCount'],
        'errors'     => array_slice($result['errors'], 0, 10),
        'preview'    => $previewRows,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ──────────────────────────────────────
// インポート実行
// ──────────────────────────────────────
if ($action === 'import') {
    verifyCsrfToken();
    $entity = $_POST['entity'] ?? '';
    if (!isset($IMPORT_ENTITIES[$entity])) {
        echo json_encode(['success' => false, 'error' => '不明なエンティティ: ' . $entity]);
        exit;
    }
    $def = $IMPORT_ENTITIES[$entity];
    $result = parseCsvUpload($def);

    if (isset($result['error'])) {
        echo json_encode(['success' => false, 'error' => $result['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // エラー行を除いた有効行のみインポート
    $now = date('Y-m-d H:i:s');
    $imported = 0;
    $skipped  = 0;

    foreach ($result['rows'] as $row) {
        if (!empty($row['errors'])) {
            $skipped++;
            continue;
        }

        $record = $row['data'];
        $record['id'] = uniqid($entity . '_', true);
        $record['created_at'] = $now;
        $record['updated_at'] = $now;

        // saveEntityRow で 1 行ずつ安全に挿入
        try {
            saveEntityRow($entity, $record);
            $imported++;
        } catch (Throwable $e) {
            $skipped++;
        }
    }

    // キャッシュクリア (次の getData() で DB から再読み込み)
    getData(true);

    // 監査ログ
    if (function_exists('writeAuditLog')) {
        writeAuditLog('csv_import', [
            'entity'   => $entity,
            'label'    => $def['label'],
            'imported' => $imported,
            'skipped'  => $skipped,
            'total'    => $result['totalRows'],
        ]);
    }

    echo json_encode([
        'success'  => true,
        'imported' => $imported,
        'skipped'  => $skipped,
        'total'    => $result['totalRows'],
        'message'  => $def['label'] . ': ' . $imported . '件をインポートしました'
                    . ($skipped > 0 ? '（' . $skipped . '件スキップ）' : ''),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'error' => '不明なアクション: ' . $action], JSON_UNESCAPED_UNICODE);
