<?php
/**
 * MF請求書同期ロジック（共通関数）
 * finance.php と api/sync-invoices.php の両方から使用
 *
 * 修正内容:
 * - 既存IDのスキップ → 上書き更新に変更
 * - 削除判定を billing_date + sales_date の両方でチェック
 */

/**
 * MFから取得した請求書データを data['mf_invoices'] に同期する
 *
 * @param array $data getData()で取得したデータ
 * @param array $invoices MF APIから取得した請求書配列
 * @param string $from 同期期間開始日 (Y-m-d)
 * @param string $to 同期期間終了日 (Y-m-d)
 * @return array ['data' => 更新後データ, 'new' => 新規件数, 'updated' => 更新件数, 'deleted' => 削除件数]
 */
function syncMfInvoices(array $data, array $invoices, string $from, string $to): array
{
    if (!isset($data['mf_invoices'])) {
        $data['mf_invoices'] = [];
    }

    // MFから取得した請求書のIDマップを作成
    $mfInvoiceIds = [];
    foreach ($invoices as $invoice) {
        $mfInvoiceIds[$invoice['id']] = true;
    }

    // 既存の請求書のIDマップを作成（インデックス付き）
    $existingIndex = [];
    foreach ($data['mf_invoices'] as $idx => $existingInvoice) {
        $existingIndex[$existingInvoice['id']] = $idx;
    }

    // MFで削除された請求書を検知して削除
    // billing_date または sales_date が同期対象期間内の請求書をチェック
    $deleteCount = 0;
    $data['mf_invoices'] = array_values(array_filter($data['mf_invoices'], function($invoice) use ($mfInvoiceIds, $from, $to, &$deleteCount) {
        // 日付フォーマットを統一（スラッシュ→ハイフンに正規化）
        $billingDate = str_replace('/', '-', $invoice['billing_date'] ?? '');
        $salesDate = str_replace('/', '-', $invoice['sales_date'] ?? '');

        // billing_date または sales_date が同期対象期間内かどうか確認
        $inPeriod = false;
        if ($billingDate >= $from && $billingDate <= $to) {
            $inPeriod = true;
        }
        if ($salesDate >= $from && $salesDate <= $to) {
            $inPeriod = true;
        }

        if ($inPeriod) {
            // 期間内の請求書で、MFに存在しない場合は削除
            if (!isset($mfInvoiceIds[$invoice['id']])) {
                $deleteCount++;
                return false; // 削除
            }
        }
        return true; // 保持
    }));

    // 削除後にインデックスを再構築
    $existingIndex = [];
    foreach ($data['mf_invoices'] as $idx => $existingInvoice) {
        $existingIndex[$existingInvoice['id']] = $idx;
    }

    $newCount = 0;
    $updateCount = 0;

    foreach ($invoices as $invoice) {
        $invoiceId = $invoice['id'] ?? '';
        $invoiceData = buildInvoiceData($invoice);

        if (isset($existingIndex[$invoiceId])) {
            // 既存の請求書 → 内容に変更があれば上書き更新（created_at は保持）
            $existing = $data['mf_invoices'][$existingIndex[$invoiceId]];
            $existingCreatedAt = $existing['created_at'] ?? date('Y-m-d H:i:s');
            $invoiceData['created_at'] = $existingCreatedAt;

            // 比較用: synced_at と created_at を除外して差分チェック
            $compareNew = $invoiceData;
            $compareOld = $existing;
            unset($compareNew['synced_at'], $compareNew['created_at']);
            unset($compareOld['synced_at'], $compareOld['created_at']);

            if ($compareNew !== $compareOld) {
                $data['mf_invoices'][$existingIndex[$invoiceId]] = $invoiceData;
                $updateCount++;
            }
        } else {
            // 新規の請求書
            $data['mf_invoices'][] = $invoiceData;
            $newCount++;
        }
    }

    // 同期時刻を記録
    $data['mf_sync_timestamp'] = date('Y-m-d H:i:s');

    return [
        'data' => $data,
        'new' => $newCount,
        'updated' => $updateCount,
        'deleted' => $deleteCount,
    ];
}

/**
 * MF APIレスポンスの請求書データを保存用フォーマットに変換
 *
 * @param array $invoice MF APIからの請求書データ
 * @return array 保存用フォーマット
 */
function buildInvoiceData(array $invoice): array
{
    // タグからPJ番号と担当者名を抽出
    $tags = $invoice['tag_names'] ?? [];
    $projectId = '';
    $assignee = '';
    $closingDate = '';

    foreach ($tags as $tag) {
        // PJ番号を抽出（P + 数字）
        if (preg_match('/^P\d+$/i', $tag)) {
            $projectId = $tag;
        }
        // 〆日を抽出（例: 20日〆, 末日〆）
        if (preg_match('/(末日|[\d]+日)〆/', $tag, $matches)) {
            $closingDate = $matches[1] . '〆';
        }
        // 担当者名を抽出（日本語の人名を想定）
        if (mb_strlen($tag) === 2 &&
            preg_match('/^[ぁ-んァ-ヶー一-龯]+$/', $tag) &&
            !preg_match('/(株式|有限|合同|本社|支店|営業|部|課|係|室|〆|メール|販売|レンタル|建設|工事|開発|総務|経理|人事|企画|管理|その他|郵送|派遣|修理|交換|水没|末締)/', $tag)) {
            $assignee = $tag;
        }
    }

    // 金額詳細を取得
    $subtotal = floatval($invoice['subtotal_price'] ?? 0);
    $tax = floatval($invoice['excise_price'] ?? 0);
    $total = floatval($invoice['total_price'] ?? 0);

    // 日付フォーマットを統一（スラッシュ→ハイフンに正規化）
    $billingDate = str_replace('/', '-', $invoice['billing_date'] ?? '');
    $dueDate = str_replace('/', '-', $invoice['due_date'] ?? '');
    $salesDate = str_replace('/', '-', $invoice['sales_date'] ?? '');

    return [
        'id' => $invoice['id'] ?? '',
        'billing_number' => $invoice['billing_number'] ?? '',
        'title' => $invoice['title'] ?? '',
        'partner_name' => $invoice['partner_name'] ?? '',
        'billing_date' => $billingDate,
        'due_date' => $dueDate,
        'sales_date' => $salesDate,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total_amount' => $total,
        'payment_status' => $invoice['payment_status'] ?? '未設定',
        'posting_status' => $invoice['posting_status'] ?? '未郵送',
        'email_status' => $invoice['email_status'] ?? '未送信',
        'memo' => $invoice['memo'] ?? '',
        'note' => $invoice['note'] ?? '',
        'tag_names' => $tags,
        'project_id' => $projectId,
        'assignee' => $assignee,
        'closing_date' => $closingDate,
        'pdf_url' => $invoice['pdf_url'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
        'synced_at' => date('Y-m-d H:i:s'),
    ];
}
