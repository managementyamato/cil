<?php
/**
 * トラブルデータインポート用CSVテンプレートダウンロード
 */

// CSVヘッダー
$headers = [
    'PJ番号',
    '現場名',
    '機器種別',
    'トラブル内容',
    '解決方法',
    '報告者',
    '連絡先（名前）',
    '連絡先（電話・メール）',
    'ステータス',
    '担当者'
];

// サンプルデータ（2行）
$sampleData = [
    [
        '001',
        '○○現場',
        'モニたろう',
        'カメラが映らない',
        '電源ケーブルを接続し直したら復旧',
        '山田太郎',
        '田中一郎',
        '090-1234-5678',
        '完了',
        '佐藤花子'
    ],
    [
        '002',
        '△△現場',
        'モニすけ',
        'ネットワークに接続できない',
        '',
        '鈴木次郎',
        '山本二郎',
        'yamamoto@example.com',
        '未対応',
        ''
    ]
];

// CSV出力設定
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="trouble_import_template.csv"');

// BOM付きUTF-8で出力（Excelで文字化けしないように）
echo "\xEF\xBB\xBF";

// 出力バッファを開く
$output = fopen('php://output', 'w');

// ヘッダー行を出力
fputcsv($output, $headers);

// サンプルデータを出力
foreach ($sampleData as $row) {
    fputcsv($output, $row);
}

// 空行を3行追加（ユーザーが入力しやすいように）
for ($i = 0; $i < 3; $i++) {
    fputcsv($output, array_fill(0, count($headers), ''));
}

fclose($output);
exit;
