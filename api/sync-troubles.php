<?php
/**
 * トラブル対応 スプレッドシート同期API
 * スプレッドシート「対応記録表」からトラブルデータを読み込み、data.jsonに同期する
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/google-sheets.php';

// 管理者のみ
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '管理者権限が必要です']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

verifyCsrfToken();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'sync';

// スプレッドシートID
$spreadsheetId = '1aD-VKgboXiYYrkkSp3bGWD3pOT87tctCWZTb16z0V6A';
$sheetName = '対応記録表';
$dataRange = 'A3:M'; // 3行目からデータ

try {
    $client = new GoogleSheetsClient();
    $token = $client->getAccessToken();

    if (!$token) {
        throw new Exception('Google認証が設定されていません。設定画面からGoogle連携を行ってください。');
    }

    // スプレッドシートからデータ取得
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/"
         . urlencode("{$sheetName}!{$dataRange}");

    $opts = [
        'http' => [
            'header' => "Authorization: Bearer {$token}\r\n",
            'method' => 'GET',
            'ignore_errors' => true,
            'timeout' => 30
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
    ];
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($url, false, $ctx);

    if ($response === false) {
        throw new Exception('Google Sheets APIへの接続に失敗しました');
    }

    $sheetsData = json_decode($response, true);

    if (isset($sheetsData['error'])) {
        throw new Exception('Sheets APIエラー: ' . ($sheetsData['error']['message'] ?? json_encode($sheetsData['error'])));
    }

    $rows = $sheetsData['values'] ?? [];

    if (empty($rows)) {
        echo json_encode(['success' => true, 'message' => 'スプレッドシートにデータがありません', 'count' => 0]);
        exit;
    }

    // スプレッドシートの行をトラブルデータに変換
    // 列マッピング:
    // A(0): 現場名 or プロジェクト番号 → pj_number
    // B(1): トラブル内容 → trouble_content
    // C(2): 対応内容 → response_content
    // D(3): 記入者 → reporter
    // E(4): 対応者 → responder
    // F(5): 状態 → status
    // G(6): 日付 → date
    // H(7): コールNo → call_no
    // I(8): ファーストコンタクト → project_contact (TRUE/FALSE)
    // J(9): 案件No → case_no
    // K(10): 社名 → company_name
    // L(11): お客様お名前 → customer_name
    // M(12): 様 → honorific

    $sheetTroubles = [];
    foreach ($rows as $row) {
        // 空行スキップ
        $pj = trim($row[0] ?? '');
        $content = trim($row[1] ?? '');
        if (empty($pj) && empty($content)) {
            continue;
        }

        // 状態のマッピング（スプシの表記をシステムの状態に変換）
        $rawStatus = trim($row[5] ?? '');
        $status = mapStatus($rawStatus);

        // 日付のフォーマット正規化
        $rawDate = trim($row[6] ?? '');
        $date = normalizeDate($rawDate);

        // PJ番号の正規化（p153 → P153）大文字統一
        if (preg_match('/^[Pp]\d+$/', $pj)) {
            $pj = strtoupper($pj);
        }

        $sheetTroubles[] = [
            'pj_number' => $pj,
            'trouble_content' => trim($row[1] ?? ''),
            'response_content' => trim($row[2] ?? ''),
            'reporter' => trim($row[3] ?? ''),
            'responder' => trim($row[4] ?? ''),
            'status' => $status,
            'date' => $date,
            'call_no' => trim($row[7] ?? ''),
            'project_contact' => strtoupper(trim($row[8] ?? '')) === 'TRUE',
            'case_no' => trim($row[9] ?? ''),
            'company_name' => trim($row[10] ?? ''),
            'customer_name' => trim($row[11] ?? ''),
            'honorific' => trim($row[12] ?? '') ?: '様',
        ];
    }

    if (empty($sheetTroubles)) {
        echo json_encode(['success' => true, 'message' => '有効なデータがありません', 'count' => 0]);
        exit;
    }

    // 既存のdata.jsonを読み込み
    $data = getData();
    if (!isset($data['troubles'])) {
        $data['troubles'] = [];
    }

    $existingTroubles = $data['troubles'];

    // 既存のトラブルデータと照合して、重複を検出
    // キー: pj_number + trouble_content + date で一致判定
    $existingKeys = [];
    foreach ($existingTroubles as $t) {
        $key = makeMatchKey($t);
        $existingKeys[$key] = true;
    }

    // 最大IDを取得
    $maxId = 0;
    foreach ($existingTroubles as $t) {
        if (isset($t['id']) && $t['id'] > $maxId) {
            $maxId = $t['id'];
        }
    }

    $addedCount = 0;
    $skippedCount = 0;
    $now = date('Y-m-d H:i:s');

    foreach ($sheetTroubles as $st) {
        $key = makeMatchKey($st);

        if (isset($existingKeys[$key])) {
            // 重複 → スキップ
            $skippedCount++;
            continue;
        }

        // 新規追加
        $maxId++;
        $st['id'] = $maxId;
        $st['created_at'] = $now;
        $st['updated_at'] = $now;
        $st['synced_from_sheet'] = true; // スプシからの同期フラグ

        $existingTroubles[] = $st;
        $existingKeys[$key] = true;
        $addedCount++;
    }

    // 保存
    $data['troubles'] = $existingTroubles;
    saveData($data);

    $message = "{$addedCount}件を追加しました";
    if ($skippedCount > 0) {
        $message .= "（{$skippedCount}件は既存のため スキップ）";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'added' => $addedCount,
        'skipped' => $skippedCount,
        'total_sheet' => count($sheetTroubles),
        'total_system' => count($existingTroubles)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * 重複判定用のキーを生成
 */
function makeMatchKey($trouble) {
    $pj = trim($trouble['pj_number'] ?? $trouble['project_name'] ?? '');
    // PJ番号を大文字に統一（p1 → P1）
    if (preg_match('/^[Pp]\d+$/', $pj)) {
        $pj = strtoupper($pj);
    }
    $content = trim($trouble['trouble_content'] ?? '');
    $date = trim($trouble['date'] ?? '');
    // 日付のフォーマットを正規化してから比較
    $date = normalizeDate($date);
    // PJ番号は大文字統一済みなので、内容のみ小文字比較
    return $pj . '||' . mb_strtolower($content) . '||' . $date;
}

/**
 * スプシの状態値をシステムの状態にマッピング
 */
function mapStatus($rawStatus) {
    $statusMap = [
        '未対応' => '未対応',
        '対応中' => '対応中',
        '保留' => '保留',
        '完了' => '完了',
        '解決' => '完了',
        // 数字付きの場合も対応
        '1.解決' => '完了',
        '2.対応中' => '対応中',
        '3.保留' => '保留',
        '0.未対応' => '未対応',
    ];

    // まず完全一致
    if (isset($statusMap[$rawStatus])) {
        return $statusMap[$rawStatus];
    }

    // 部分一致
    foreach ($statusMap as $pattern => $mapped) {
        if (mb_strpos($rawStatus, $pattern) !== false) {
            return $mapped;
        }
    }

    // キーワード検索
    if (mb_strpos($rawStatus, '解決') !== false || mb_strpos($rawStatus, '完了') !== false) {
        return '完了';
    }
    if (mb_strpos($rawStatus, '対応中') !== false) {
        return '対応中';
    }
    if (mb_strpos($rawStatus, '保留') !== false) {
        return '保留';
    }

    // デフォルト
    return $rawStatus ?: '未対応';
}

/**
 * 日付のフォーマットを正規化 → YYYY/MM/DD
 */
function normalizeDate($dateStr) {
    if (empty($dateStr)) return '';

    // "2025年9月/2" → "2025/9/2"
    $dateStr = str_replace('年', '/', $dateStr);
    $dateStr = str_replace('月', '/', $dateStr);
    $dateStr = str_replace('日', '', $dateStr);

    // 連続スラッシュを1つに
    $dateStr = preg_replace('#/+#', '/', trim($dateStr, '/'));

    // YYYY/M/D 形式に統一
    if (preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $dateStr, $m)) {
        return sprintf('%04d/%02d/%02d', $m[1], $m[2], $m[3]);
    }

    return $dateStr;
}
