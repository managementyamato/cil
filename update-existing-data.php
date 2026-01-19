<?php
/**
 * 既存の請求書データの税抜き・担当者・〆日を再計算して更新
 * CLI専用スクリプト
 */

// CLIからの実行のみ許可
if (php_sapi_name() === 'cli') {
    // CLI実行時は直接ファイル操作
    define('DATA_FILE', __DIR__ . '/data.json');

    function getData() {
        if (!file_exists(DATA_FILE)) {
            return array();
        }
        $json = file_get_contents(DATA_FILE);
        return json_decode($json, true) ?? array();
    }

    function saveData($data) {
        file_put_contents(DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
} else {
    // Web実行時
    require_once 'config.php';
    // 編集権限チェック
    if (!canEdit()) {
        die('権限がありません');
    }
}

$data = getData();

if (!isset($data['mf_invoices']) || empty($data['mf_invoices'])) {
    die('請求書データがありません');
}

$updateCount = 0;

foreach ($data['mf_invoices'] as &$invoice) {
    $updated = false;

    // タグから担当者名と〆日を再抽出
    $tags = $invoice['tag_names'] ?? array();
    $projectId = $invoice['project_id'] ?? '';
    $assignee = '';
    $closingDate = '';

    foreach ($tags as $tag) {
        // PJ番号を抽出（P + 数字）
        if (empty($projectId) && preg_match('/^P\d+$/i', $tag)) {
            $projectId = $tag;
        }

        // 〆日を抽出（例: 20日〆, 末日〆）
        if (preg_match('/(末日|[\d]+日)〆/', $tag, $matches)) {
            $closingDate = $matches[1] . '〆';
        }

        // 担当者名を抽出（日本語の人名を想定）
        // 2文字の日本語で、会社名や部署名、一般名詞を除外
        if (mb_strlen($tag) === 2 &&
            preg_match('/^[ぁ-んァ-ヶー一-龯]+$/', $tag) &&
            !preg_match('/(株式|有限|合同|本社|支店|営業|部|課|係|室|〆|メール|販売|レンタル|建設|工事|開発|総務|経理|人事|企画|管理|その他|郵送|派遣|修理|交換|水没|末締)/', $tag)) {
            $assignee = $tag;
        }
    }

    // 担当者が変更された場合
    if ($assignee !== ($invoice['assignee'] ?? '')) {
        $invoice['assignee'] = $assignee;
        $updated = true;
    }

    // 〆日が変更された場合
    if ($closingDate !== ($invoice['closing_date'] ?? '')) {
        $invoice['closing_date'] = $closingDate;
        $updated = true;
    }

    // P番号が変更された場合
    if ($projectId !== ($invoice['project_id'] ?? '')) {
        $invoice['project_id'] = $projectId;
        $updated = true;
    }

    // 税抜き金額の再計算
    $subtotal = floatval($invoice['subtotal'] ?? 0);
    $tax = floatval($invoice['tax'] ?? 0);
    $total = floatval($invoice['total_amount'] ?? 0);

    // もしsubtotalが0で、total_amountがある場合は、明細から計算を試みる
    if ($subtotal === 0.0 && $total > 0) {
        if (isset($invoice['items']) && is_array($invoice['items'])) {
            $calculatedSubtotal = 0;
            foreach ($invoice['items'] as $item) {
                $calculatedSubtotal += floatval($item['price'] ?? 0) * floatval($item['quantity'] ?? 0);
            }
            if ($calculatedSubtotal > 0) {
                $subtotal = $calculatedSubtotal;
                $tax = $total - $subtotal;
                $updated = true;
            }
        }

        // それでもsubtotalが0の場合は、消費税10%として逆算
        if ($subtotal === 0.0 && $total > 0) {
            $subtotal = round($total / 1.1);
            $tax = $total - $subtotal;
            $updated = true;
        }

        if ($updated) {
            $invoice['subtotal'] = $subtotal;
            $invoice['tax'] = $tax;
        }
    }

    if ($updated) {
        $updateCount++;
    }
}

// データを保存
saveData($data);

echo "更新完了: {$updateCount}件の請求書データを更新しました\n";
echo '<a href="finance.php">損益ページに戻る</a>';
