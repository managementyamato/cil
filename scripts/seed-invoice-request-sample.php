<?php
/**
 * テスト用シード: 共有された依頼サンプル（中部プラントサービス様 P914）を invoice_requests に登録
 * 実行URL: /scripts/seed-invoice-request-sample.php
 * （admin のみ・複数回実行しても source_row_id で重複防止）
 */
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_email'])) { http_response_code(401); echo json_encode(['error' => 'login required']); exit; }
if (!isAdmin()) { http_response_code(403); echo json_encode(['error' => 'admin only']); exit; }

header('Content-Type: application/json; charset=utf-8');

$now = date('Y-m-d H:i:s');
$sourceRowId = 'sample_p914_chubu_plant';  // 重複防止用キー

$data = getData();
if (!isset($data['invoice_requests'])) $data['invoice_requests'] = [];

// 既に同じ source_row_id で登録済みならスキップ
foreach ($data['invoice_requests'] as $r) {
    if (($r['source_row_id'] ?? '') === $sourceRowId && empty($r['deleted_at'])) {
        echo json_encode([
            'success' => true,
            'message' => '既にサンプル依頼が登録されています',
            'existing_id' => $r['id'],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

$sample = [
    'id'                => uniqid('ir_'),
    'source'            => 'sample',
    'source_row_id'     => $sourceRowId,
    'source_timestamp'  => $now,

    // 基本情報
    'requester_name'    => '小黒',
    'attached_file_id'  => '1f8ZNEn4wBXB1wZvaYSLO5MzFGjWJZANh',
    'pj_number'         => 'P914',
    'subject'           => '中部プラントサービス様 JERA武豊火力発電所 屋外用LEDディスプレイ「モニたろう」90インチ(1920x1280) レンタル',

    // 請求先
    'partner_name'        => 'マツオカ建機株式会社',
    'partner_department'  => '営業部',
    'mf_partner_id'       => '',  // ※ MF送信前に手動で入力
    'billing_method_1'    => '郵送',
    'billing_method_2'    => 'メール',

    // 依頼種別
    'request_type'      => '新規レンタル（継続あり）',

    // レンタル詳細
    'billing_start_date' => '2026-04-20',
    'payment_due_date'   => '2026-05-31',
    'closing_day'        => '末日〆',
    'rental_period'      => '1年未満',
    'auto_renew'         => 1,
    'has_prorated'       => 1,

    // 品目
    'items' => [
        [
            'name'               => '「モニたろう」90インチ標準精細モデル基本セット',
            'initial_unit_price' => 3833,
            'initial_days'       => 10,
            'monthly_unit_price' => 116500,
            'monthly_quantity'   => 1,
            'monthly_unit'       => '月',
            'tax_type'           => '10%対象',
        ],
        [
            'name'               => 'ゲンバルジャープレーヤーレンタル',
            'initial_unit_price' => 166,
            'initial_days'       => 10,
            'monthly_unit_price' => 5000,
            'monthly_quantity'   => 1,
            'monthly_unit'       => '月',
            'tax_type'           => '10%対象',
        ],
        [
            'name'               => 'ゲンバルジャーアカウント利用料',
            'initial_unit_price' => 333,
            'initial_days'       => 10,
            'monthly_unit_price' => 10000,
            'monthly_quantity'   => 1,
            'monthly_unit'       => '月',
            'tax_type'           => '10%対象',
        ],
    ],

    // メモ
    'notes'         => '',
    'special_notes' => "データ通信料、基本管理料、設置費、設置時運送料、ゲンバルジャー初期設定費用は初回に請求し自動作成なし\n撤去費、撤去時運送料は撤去時に請求\nこれに沿って請求書を作成してできるのであればタグ付けまでしていきたい",

    // ステータス
    'status'        => 'pending',

    'created_at'    => $now,
    'updated_at'    => $now,
];

$data['invoice_requests'][] = $sample;
saveData($data);

// 初回請求合計を計算（参考表示用）
$initialTotal = 0;
foreach ($sample['items'] as $it) {
    $initialTotal += ($it['initial_unit_price'] * $it['initial_days']);
}
$monthlyTotal = 0;
foreach ($sample['items'] as $it) {
    $monthlyTotal += ($it['monthly_unit_price'] * $it['monthly_quantity']);
}

echo json_encode([
    'success'         => true,
    'message'         => 'サンプル依頼を登録しました',
    'id'              => $sample['id'],
    'pj_number'       => $sample['pj_number'],
    'subject'         => $sample['subject'],
    'partner_name'    => $sample['partner_name'],
    'items_count'     => count($sample['items']),
    'initial_total_excl_tax' => $initialTotal,
    'monthly_total_excl_tax' => $monthlyTotal,
    'next_step'       => '/pages/invoice-requests.php を開いて確認',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
