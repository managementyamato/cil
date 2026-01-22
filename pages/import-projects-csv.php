<?php
/**
 * プロジェクトCSVインポート（1回限りの使用）
 */
require_once '../config/config.php';

// 管理者のみアクセス可能
if (!isAdmin()) {
    die('管理者権限が必要です');
}

$data = getData();
$message = '';
$messageType = '';
$importedCount = 0;
$skippedCount = 0;
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $csvData = array();
        $handle = fopen($file['tmp_name'], 'r');

        if ($handle !== false) {
            // BOM除去
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            // ヘッダー行をスキップ（24行目まで）
            $skipRows = isset($_POST['skip_rows']) ? (int)$_POST['skip_rows'] : 24;
            for ($i = 0; $i < $skipRows; $i++) {
                fgets($handle);
            }

            // データ読み込み
            while (($row = fgetcsv($handle)) !== false) {
                // 空行スキップ
                if (empty(array_filter($row))) {
                    continue;
                }
                $csvData[] = $row;
            }
            fclose($handle);

            // データ処理
            if (!isset($data['projects'])) {
                $data['projects'] = array();
            }

            foreach ($csvData as $index => $row) {
                try {
                    // A列(No)が空ならスキップ
                    if (empty($row[0])) {
                        continue;
                    }

                    // B列: PJ番号 (必須)
                    $pjNumber = trim($row[1] ?? '');
                    if (empty($pjNumber)) {
                        $skippedCount++;
                        $errors[] = "行" . ($index + $skipRows + 1) . ": PJ番号が空です";
                        continue;
                    }

                    // 既存チェック
                    $exists = false;
                    foreach ($data['projects'] as $proj) {
                        if ($proj['id'] === $pjNumber) {
                            $exists = true;
                            break;
                        }
                    }

                    if ($exists) {
                        $skippedCount++;
                        $errors[] = "行" . ($index + $skipRows + 1) . ": $pjNumber は既に登録済みです";
                        continue;
                    }

                    // C列: 営業担当
                    $salesAssignee = trim($row[2] ?? '');

                    // D列: YA担当
                    $yaAssignee = trim($row[3] ?? '');

                    // E列: 案件発生日
                    $occurrenceDate = trim($row[4] ?? '');
                    if (!empty($occurrenceDate) && preg_match('/^\d{4}\/\d{1,2}$/', $occurrenceDate)) {
                        // 2023/9 → 2023-09-01 に変換
                        $dateParts = explode('/', $occurrenceDate);
                        $occurrenceDate = $dateParts[0] . '-' . str_pad($dateParts[1], 2, '0', STR_PAD_LEFT) . '-01';
                    }

                    // F列: スペース
                    // G列: 請求書番号
                    $invoiceNumber = trim($row[6] ?? '');

                    // H列: 案件名・スペース名
                    $siteName = trim($row[7] ?? '');
                    if (empty($siteName)) {
                        $siteName = $pjNumber; // 案件名が空ならPJ番号を使用
                    }

                    // I列: ディーラー
                    $dealerName = trim($row[8] ?? '');

                    // J列: 営業所名
                    $customerName = trim($row[9] ?? '');

                    // K列: 連絡先メールアドレス
                    $contactEmail = trim($row[10] ?? '');

                    // L列: 種別
                    $transactionType = trim($row[11] ?? '');
                    // "レンタル" → "レンタル", "販売" → "販売", それ以外は空
                    if (!in_array($transactionType, ['レンタル', '販売'])) {
                        $transactionType = '';
                    }

                    // M列: メーカー
                    $manufacturer = trim($row[12] ?? '');

                    // 新規プロジェクト作成
                    $newProject = array(
                        'id' => $pjNumber,
                        'name' => $siteName,
                        'occurrence_date' => $occurrenceDate,
                        'transaction_type' => $transactionType,
                        'sales_assignee' => $salesAssignee,
                        'customer_name' => $customerName,
                        'dealer_name' => $dealerName,
                        'general_contractor' => '',
                        'site_address' => '',
                        'site_phone' => '',
                        'equipment_model' => $manufacturer, // メーカーを機器型番として保存
                        'install_schedule_date' => '',
                        'delivery_date' => '',
                        'sales_amount' => '',
                        'gross_profit' => '',
                        'cost_amount' => '',
                        'payment_terms' => '',
                        'payment_due_date' => '',
                        'billing_destination' => '',
                        'delivery_destination' => '',
                        'delivery_method' => '',
                        'warranty_period' => '',
                        'warranty_start_date' => '',
                        'warranty_end_date' => '',
                        'memo' => !empty($invoiceNumber) ? "請求書番号: $invoiceNumber" : '',
                        'chat_url' => '',
                        'contact_email' => $contactEmail,
                        'ya_assignee' => $yaAssignee,
                        'created_at' => date('Y-m-d H:i:s')
                    );

                    $data['projects'][] = $newProject;
                    $importedCount++;

                } catch (Exception $e) {
                    $skippedCount++;
                    $errors[] = "行" . ($index + $skipRows + 1) . ": エラー - " . $e->getMessage();
                }
            }

            // データ保存
            if ($importedCount > 0) {
                saveData($data);
                $message = "インポート完了: {$importedCount}件登録、{$skippedCount}件スキップ";
                $messageType = 'success';
            } else {
                $message = "インポート失敗: 登録できるデータがありませんでした";
                $messageType = 'error';
            }
        } else {
            $message = 'CSVファイルの読み込みに失敗しました';
            $messageType = 'error';
        }
    } else {
        $message = 'ファイルのアップロードに失敗しました';
        $messageType = 'error';
    }
}

require_once '../functions/header.php';
?>

<style>
    .import-container {
        max-width: 900px;
        margin: 20px auto;
        padding: 20px;
    }
    .import-card {
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        font-weight: bold;
        margin-bottom: 8px;
        color: #333;
    }
    .form-group input[type="file"],
    .form-group input[type="number"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    .btn-import {
        background: #4CAF50;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        cursor: pointer;
        transition: background 0.3s;
    }
    .btn-import:hover {
        background: #45a049;
    }
    .btn-cancel {
        background: #f5f5f5;
        color: #333;
        padding: 12px 30px;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        margin-left: 10px;
    }
    .btn-cancel:hover {
        background: #e0e0e0;
    }
    .message {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
    }
    .message.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .message.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    .instructions {
        background: #e3f2fd;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
        border-left: 4px solid #2196F3;
    }
    .instructions h3 {
        margin-top: 0;
        color: #1976D2;
    }
    .instructions ol {
        margin: 10px 0;
        padding-left: 20px;
    }
    .instructions li {
        margin: 5px 0;
    }
    .error-list {
        background: #fff3cd;
        padding: 15px;
        border-radius: 4px;
        margin-top: 20px;
        border-left: 4px solid #ffc107;
        max-height: 300px;
        overflow-y: auto;
    }
    .error-list h4 {
        margin-top: 0;
        color: #856404;
    }
    .error-list ul {
        margin: 10px 0;
        padding-left: 20px;
    }
    .error-list li {
        margin: 5px 0;
        color: #856404;
    }
    .warning {
        background: #fff3cd;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
        border-left: 4px solid #ffc107;
    }
    .warning strong {
        color: #856404;
    }
</style>

<div class="import-container">
    <h1>プロジェクトCSVインポート</h1>

    <div class="warning">
        <strong>⚠ 注意:</strong> このページは1回限りの使用を想定しています。インポート完了後は削除することを推奨します。
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="error-list">
            <h4>⚠ スキップされた行の詳細:</h4>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="import-card">
        <div class="instructions">
            <h3>📋 インポート手順</h3>
            <ol>
                <li>スプレッドシートを開く</li>
                <li>「ファイル」→「ダウンロード」→「カンマ区切り形式(.csv)」を選択</li>
                <li>ダウンロードしたCSVファイルを下記からアップロード</li>
                <li>「インポート実行」をクリック</li>
            </ol>
            <p><strong>対応列:</strong> B列(PJ番号), C列(営業担当), D列(YA担当), E列(案件発生日), G列(請求書番号), H列(案件名), I列(ディーラー), J列(営業所名), K列(連絡先), L列(種別), M列(メーカー)</p>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>CSVファイル選択</label>
                <input type="file" name="csv_file" accept=".csv" required>
            </div>

            <div class="form-group">
                <label>スキップする行数（ヘッダー行）</label>
                <input type="number" name="skip_rows" value="24" min="0" max="100">
                <small style="color: #666; display: block; margin-top: 5px;">
                    デフォルト: 24行（スプレッドシートの24行目まではヘッダー）
                </small>
            </div>

            <div style="margin-top: 30px;">
                <button type="submit" class="btn-import">インポート実行</button>
                <a href="master.php" class="btn-cancel">キャンセル</a>
            </div>
        </form>
    </div>

    <?php if ($importedCount > 0): ?>
        <div style="margin-top: 30px; text-align: center;">
            <a href="master.php" style="display: inline-block; padding: 15px 40px; background: #2196F3; color: white; text-decoration: none; border-radius: 4px; font-size: 16px;">
                プロジェクト管理に戻る
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../functions/footer.php'; ?>
