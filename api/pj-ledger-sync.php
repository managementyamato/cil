<?php
/**
 * PJ管理台帳 Google Sheets同期API
 * スプレッドシートからデータを取得して pj-ledger.json に保存
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/pj-ledger-data.php';

// PJ管理台帳スプレッドシートID
const PJ_LEDGER_SPREADSHEET_ID = '1fDQC5c7sEyyUu308IPHZDVNBCjL7CFk1vPDlO40mrzM';
const PJ_LEDGER_SHEET_NAME = 'PJ管理台帳';
const PJ_LEDGER_HEADER_ROW = 24;
const PJ_LEDGER_DATA_START_ROW = 25;

// ─── GET: 同期状態確認 ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    initApi([
        'requireAuth'    => true,
        'requireCsrf'    => false,
        'allowedMethods' => ['GET'],
    ]);

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'status':
            $pjData = getPjLedgerData();
            $lastSync = $pjData['last_sync'] ?? null;
            $count = count(filterPjDeleted($pjData['projects'] ?? []));
            successResponse([
                'last_sync' => $lastSync,
                'project_count' => $count,
            ]);
            break;

        default:
            errorResponse('不正なアクションです', 400);
    }
    exit;
}

// ─── POST: 同期実行 ──────────────────────────────────
initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'allowedMethods' => ['POST'],
]);

if (!canEdit()) {
    errorResponse('編集権限がありません', 403);
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'sync':
        try {
            $result = syncFromGoogleSheets();
            successResponse($result, '同期が完了しました');
        } catch (Exception $e) {
            errorResponse('同期エラー: ' . $e->getMessage(), 500);
        }
        break;

    default:
        errorResponse('不正なアクションです', 400);
}

// ─── 同期処理本体 ─────────────────────────────────────

function syncFromGoogleSheets() {
    $accessToken = getGoogleAccessToken();

    // ヘッダー行 + データ範囲を一括取得（A24:EM で全範囲）
    $sheetData = fetchSheetData($accessToken, PJ_LEDGER_SHEET_NAME . '!A24:EM');

    if (empty($sheetData)) {
        throw new Exception('スプレッドシートからデータを取得できませんでした');
    }

    // 1行目がヘッダー（行24）
    $headers = $sheetData[0] ?? [];
    $dataRows = array_slice($sheetData, 1); // 行25以降

    // カラムマッピング（列インデックス → フィールド名）
    $colMap = buildColumnMap($headers);

    $pjData = getPjLedgerData();
    $existingProjects = $pjData['projects'] ?? [];
    $now = date('Y-m-d H:i:s');

    // 既存データをPJ番号でインデックス化
    $existingByPjNumber = [];
    foreach ($existingProjects as $idx => $p) {
        $pjNum = $p['pj_number'] ?? '';
        if ($pjNum !== '') {
            $existingByPjNumber[$pjNum] = $idx;
        }
    }

    $created = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($dataRows as $rowIdx => $row) {
        // No列（A列 = index 0）が空なら終わり
        $no = getCell($row, 0);
        if ($no === '' || !is_numeric($no)) {
            // PJ番号もチェック
            $pjNum = getCell($row, $colMap['pj_number'] ?? 1);
            if ($pjNum === '') {
                $skipped++;
                continue;
            }
        }

        $project = mapRowToProject($row, $colMap, (int)$no);

        // 案件名もPJ番号も空なら完全にスキップ
        if (empty($project['pj_number']) && empty($project['project_name'])) {
            $skipped++;
            continue;
        }

        $pjNum = $project['pj_number'];

        if (isset($existingByPjNumber[$pjNum])) {
            // 既存: 更新（削除済みでなければ）
            $idx = $existingByPjNumber[$pjNum];
            if (empty($existingProjects[$idx]['deleted_at'])) {
                $project['id'] = $existingProjects[$idx]['id'];
                $project['created_by'] = $existingProjects[$idx]['created_by'] ?? 'sheets_sync';
                $project['created_at'] = $existingProjects[$idx]['created_at'] ?? $now;
                $project['updated_at'] = $now;
                $existingProjects[$idx] = $project;
                $updated++;
            } else {
                $skipped++;
            }
        } else {
            // 新規
            $project['id'] = uniqid('pj_');
            $project['created_by'] = 'sheets_sync';
            $project['created_at'] = $now;
            $project['updated_at'] = $now;
            $existingProjects[] = $project;
            $created++;
        }
    }

    // 月間純利データの同期
    $monthlyProfits = syncMonthlyProfits($sheetData, $colMap, $existingProjects, $pjData['monthly_profits'] ?? []);

    $pjData['projects'] = $existingProjects;
    $pjData['monthly_profits'] = $monthlyProfits;
    $pjData['last_sync'] = $now;

    savePjLedgerData($pjData);

    return [
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'total'   => count(filterPjDeleted($existingProjects)),
    ];
}

/**
 * ヘッダー行からカラムマッピングを構築
 */
function buildColumnMap($headers) {
    // 固定位置ベースのマッピング（スプレッドシートのカラム位置に対応）
    // A=0, B=1, ..., Z=25, AA=26, AB=27, ...
    return [
        'no'                    => 0,   // A: No
        'pj_number'             => 1,   // B: PJ番号
        'sales_dept'            => 2,   // C: 営業部
        'ya_person'             => 3,   // D: YA担当
        'space'                 => 4,   // E: スペース
        'invoice_number'        => 5,   // F: 請求者番号
        // G: フィルタ列（スキップ）
        'project_name'          => 7,   // H: 案件名
        'dealer'                => 8,   // I: ディーラー
        'branch_name'           => 9,   // J: 営業所名
        'contact_email'         => 10,  // K: 連絡先メールアドレス
        'type'                  => 11,  // L: 種別
        'manufacturer'          => 12,  // M: メーカー
        'indoor_outdoor'        => 13,  // N: 屋内/屋外
        'pitch'                 => 14,  // O: ピッチ
        'horizontal_panels'     => 15,  // P: 横枚数
        'vertical_panels'       => 16,  // Q: 縦枚数
        'total_panels'          => 17,  // R: 合計枚数
        'led_size'              => 18,  // S: LEDサイズ
        'mic1'                  => 19,  // T: マイク1
        'mic2'                  => 20,  // U: マイク2
        'orientation'           => 21,  // V: 縦横
        'color'                 => 22,  // W: 色
        'lcd_size'              => 23,  // X: LCDサイズ
        'cms_player'            => 24,  // Y: CMS/プレイヤー
        'router'                => 25,  // Z: ルーター
        'construction_date'     => 26,  // AA: 施工日
        'end_date'              => 27,  // AB: 終了予定日
        'warranty_end_date'     => 28,  // AC: 保証終了日
        'rental_days'           => 29,  // AD: レンタル稼働日数
        'sales_working_days'    => 30,  // AE: 販売稼働日数
        'status'                => 31,  // AF: 施工前使用中終了
        'period_months'         => 32,  // AG: 期間月計算
        'total_sales_estimate'  => 33,  // AH: 売上合計予想
        'actual_invoice_amount' => 34,  // AI: 実際の請求金額
        'deviation_rate'        => 35,  // AJ: 売上予想と実際の請求の乖離
        'initial_cost'          => 36,  // AK: 月額費用を除く初期費用
        'discount_amount'       => 37,  // AL: 割引金額
        'monthly_rental_sales'  => 38,  // AM: レンタル月額売上
        'additional_sales'      => 39,  // AN: 追加売上（追加部材）
        'additional_material_cost' => 40, // AO: 追加部材原価
        'support_material_cost' => 41,  // AP: 対応部材原価
        'expenses'              => 42,  // AQ: 諸経費
        'profit'                => 43,  // AR: 利益
        'profit_rate'           => 44,  // AS: 利益率
        'shipping_cost'         => 45,  // AT: 輸送費原価
        'new_install_material_cost' => 46, // AU: 新規設置時部材原価
        'monthly_material_cost' => 47,  // AV: 月間部材原価
        'support_cost'          => 48,  // AW: 対応費原価
        'tech_cost_ratio_estimate' => 49, // AX: 技術関連費用割合（売上合計予想）
        'tech_cost_ratio_actual'   => 50, // AY: 技術関連費用割合（実際請求金額）
        'used_panel_count'      => 51,  // AZ: 使用パネル合計
        // BA-BJ: 空列
        'remarks'               => 62,  // BK: 備考1
        // BL-BM: 空列
        'monthly_profit'        => 65,  // BN: 月間純利
    ];
}

/**
 * 月間純利の月別カラム位置（DR=121 ~ EL=141 が 2024/4 ~ 2025/12）
 */
function getMonthlyProfitColumns() {
    // 行24のヘッダーに 24/4, 24/5, ... 25/12 と入っている
    // DR=121 から始まる
    $months = [];
    $colStart = 121; // DR列

    // 2024/4 ～ 2024/12（9ヶ月）
    for ($m = 4; $m <= 12; $m++) {
        $months["2024/$m"] = $colStart++;
    }
    // 2025/1 ～ 2025/12（12ヶ月）
    for ($m = 1; $m <= 12; $m++) {
        $months["2025/$m"] = $colStart++;
    }

    return $months;
}

/**
 * 行データをプロジェクトオブジェクトにマッピング
 */
function mapRowToProject($row, $colMap, $no) {
    $project = ['no' => $no];

    // 文字列フィールド
    $strFields = [
        'pj_number', 'sales_dept', 'ya_person', 'space', 'invoice_number',
        'project_name', 'dealer', 'branch_name', 'contact_email',
        'type', 'manufacturer', 'indoor_outdoor', 'pitch',
        'led_size', 'mic1', 'mic2', 'orientation', 'color',
        'lcd_size', 'cms_player', 'router',
        'construction_date', 'end_date', 'warranty_end_date',
        'status', 'deviation_rate', 'profit_rate',
        'tech_cost_ratio_estimate', 'tech_cost_ratio_actual',
        'remarks',
    ];

    foreach ($strFields as $field) {
        if (isset($colMap[$field])) {
            $project[$field] = getCell($row, $colMap[$field]);
        } else {
            $project[$field] = '';
        }
    }

    // 数値フィールド（¥やカンマを除去）
    $numFields = [
        'horizontal_panels', 'vertical_panels', 'total_panels',
        'rental_days', 'sales_working_days', 'period_months',
        'total_sales_estimate', 'actual_invoice_amount',
        'initial_cost', 'discount_amount', 'monthly_rental_sales',
        'additional_sales', 'additional_material_cost', 'support_material_cost',
        'expenses', 'profit', 'shipping_cost',
        'new_install_material_cost', 'monthly_material_cost', 'support_cost',
        'used_panel_count',
    ];

    foreach ($numFields as $field) {
        if (isset($colMap[$field])) {
            $project[$field] = parseCurrency(getCell($row, $colMap[$field]));
        } else {
            $project[$field] = 0;
        }
    }

    // 日付フィールドの正規化
    foreach (['construction_date', 'end_date', 'warranty_end_date'] as $dateField) {
        $project[$dateField] = normalizeDate($project[$dateField]);
    }

    return $project;
}

/**
 * 月間純利データの同期
 */
function syncMonthlyProfits($sheetData, $colMap, $projects, $existingProfits) {
    $monthCols = getMonthlyProfitColumns();
    $dataRows = array_slice($sheetData, 1);
    $now = date('Y-m-d H:i:s');

    // PJ番号 → project_id のマップ
    $pjNumToId = [];
    foreach ($projects as $p) {
        if (!empty($p['pj_number']) && !empty($p['id']) && empty($p['deleted_at'])) {
            $pjNumToId[$p['pj_number']] = $p['id'];
        }
    }

    // 既存データをキーでインデックス化
    $existingIdx = [];
    foreach ($existingProfits as $idx => $mp) {
        $key = ($mp['project_id'] ?? '') . '|' . ($mp['month'] ?? '');
        $existingIdx[$key] = $idx;
    }

    foreach ($dataRows as $row) {
        $pjNum = getCell($row, $colMap['pj_number'] ?? 1);
        if (empty($pjNum) || !isset($pjNumToId[$pjNum])) continue;

        $projectId = $pjNumToId[$pjNum];

        foreach ($monthCols as $month => $colIdx) {
            $val = getCell($row, $colIdx);
            if ($val === '') continue;

            $amount = parseCurrency($val);
            $key = $projectId . '|' . $month;

            if (isset($existingIdx[$key])) {
                $existingProfits[$existingIdx[$key]]['amount'] = $amount;
                $existingProfits[$existingIdx[$key]]['updated_at'] = $now;
            } else {
                $existingProfits[] = [
                    'id'         => uniqid('mp_'),
                    'project_id' => $projectId,
                    'month'      => $month,
                    'amount'     => $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
    }

    return $existingProfits;
}

// ─── ユーティリティ関数 ──────────────────────────────

function getCell($row, $index) {
    return trim($row[$index] ?? '');
}

function parseCurrency($value) {
    if ($value === '' || $value === '-') return 0;
    // ¥, カンマ, 円, 日, %,スペースを除去
    $cleaned = preg_replace('/[¥￥,、円日%\s]/', '', $value);
    // #VALUE! などのエラーを0にする
    if (preg_match('/^[#A-Z]/', $cleaned)) return 0;
    return (int)$cleaned;
}

function normalizeDate($value) {
    if (empty($value) || $value === '-') return '';
    // "2023/9/25" → "2023-09-25"
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $value, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    // "2023/9" → "2023-09"
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})$/', $value, $m)) {
        return sprintf('%04d-%02d', $m[1], $m[2]);
    }
    return $value;
}

/**
 * Google Sheets APIからデータを取得
 */
function fetchSheetData($accessToken, $range) {
    $url = "https://sheets.googleapis.com/v4/spreadsheets/" . PJ_LEDGER_SPREADSHEET_ID
         . "/values/" . urlencode($range)
         . "?valueRenderOption=UNFORMATTED_VALUE&dateTimeRenderOption=FORMATTED_STRING";

    $options = [
        'http' => [
            'header'  => "Authorization: Bearer {$accessToken}\r\n",
            'method'  => 'GET',
            'ignore_errors' => true,
            'timeout' => 30  // 大量データのため長めに
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true
        ]
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        throw new Exception('Google Sheets APIへの接続に失敗しました（タイムアウト）');
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        throw new Exception('Sheets API error: ' . ($data['error']['message'] ?? json_encode($data['error'])));
    }

    return $data['values'] ?? [];
}

/**
 * Googleアクセストークンを取得（既存のgoogle-drive-token.jsonを使用）
 */
function getGoogleAccessToken() {
    $configFile = __DIR__ . '/../config/google-config.json';
    $tokenFile = __DIR__ . '/../config/google-drive-token.json';

    if (!file_exists($tokenFile)) {
        throw new Exception('Google連携が設定されていません。設定画面からGoogle Driveを連携してください。');
    }

    $token = json_decode(file_get_contents($tokenFile), true);

    // トークンの有効期限をチェック
    $savedAt = strtotime($token['saved_at'] ?? '2000-01-01');
    $expiresIn = $token['expires_in'] ?? 3600;
    $expiresAt = $savedAt + $expiresIn - 300;

    if (time() > $expiresAt && isset($token['refresh_token'])) {
        // リフレッシュ
        $config = json_decode(file_get_contents($configFile), true);
        $params = [
            'client_id' => $config['client_id'] ?? '',
            'client_secret' => $config['client_secret'] ?? '',
            'refresh_token' => $token['refresh_token'],
            'grant_type' => 'refresh_token'
        ];

        $options = [
            'http' => [
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($params),
                'ignore_errors' => true,
                'timeout' => 10
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);

        if ($response !== false) {
            $newToken = json_decode($response, true);
            if (!isset($newToken['error'])) {
                if (!isset($newToken['refresh_token'])) {
                    $newToken['refresh_token'] = $token['refresh_token'];
                }
                $newToken['saved_at'] = date('Y-m-d H:i:s');
                file_put_contents($tokenFile, json_encode($newToken, JSON_PRETTY_PRINT));
                $token = $newToken;
            }
        }
    }

    return $token['access_token'] ?? null;
}
