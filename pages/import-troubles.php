<?php
require_once '../config/config.php';

$error = '';
$success = '';
$importedCount = 0;
$skippedCount = 0;
$errors = [];

// インポート処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if (!canEdit()) {
        $error = '編集権限がありません';
    } else {
        $file = $_FILES['csv_file'];

        // ファイルアップロードエラーチェック
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'ファイルのアップロードに失敗しました';
        } elseif ($file['size'] === 0) {
            $error = 'ファイルが空です';
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB制限
            $error = 'ファイルサイズが大きすぎます（最大5MB）';
        } else {
            // CSVファイルを読み込み
            $handle = fopen($file['tmp_name'], 'r');

            if ($handle === false) {
                $error = 'ファイルを開けませんでした';
            } else {
                $data = getData();

                // BOM削除
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }

                // ヘッダー行を読み込み（スキップ）
                $headers = fgetcsv($handle);

                if ($headers === false) {
                    $error = 'CSVファイルの形式が正しくありません';
                } else {
                    // 最大IDを取得
                    $maxId = 0;
                    foreach ($data['troubles'] as $t) {
                        if ($t['id'] > $maxId) $maxId = $t['id'];
                    }

                    $rowNumber = 1; // ヘッダーの次の行から

                    // データ行を読み込み
                    while (($row = fgetcsv($handle)) !== false) {
                        $rowNumber++;

                        // 空行をスキップ
                        if (empty(array_filter($row))) {
                            continue;
                        }

                        // データが10列あることを確認
                        if (count($row) < 10) {
                            $skippedCount++;
                            $errors[] = "{$rowNumber}行目: 列数が不足しています（" . count($row) . "列）";
                            continue;
                        }

                        // データを取得
                        $pjNumber = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', trim($row[0])));
                        $pjName = trim($row[1]);
                        $deviceType = trim($row[2]);
                        $content = trim($row[3]);
                        $solution = trim($row[4]);
                        $reporter = trim($row[5]);
                        $contactName = trim($row[6]);
                        $contact = trim($row[7]);
                        $status = trim($row[8]);
                        $assignee = trim($row[9]);

                        // 必須項目チェック
                        if (empty($pjNumber) || empty($pjName) || empty($deviceType) || empty($content)) {
                            $skippedCount++;
                            $errors[] = "{$rowNumber}行目: 必須項目（PJ番号、現場名、機器種別、トラブル内容）が入力されていません";
                            continue;
                        }

                        // 機器種別の検証
                        $validDeviceTypes = ['モニたろう', 'モニすけ', 'モニまる', 'モニんじゃ', 'ゲンバルジャー', 'その他'];
                        if (!in_array($deviceType, $validDeviceTypes)) {
                            $skippedCount++;
                            $errors[] = "{$rowNumber}行目: 機器種別が正しくありません（{$deviceType}）。有効な値: " . implode(', ', $validDeviceTypes);
                            continue;
                        }

                        // ステータスの検証とデフォルト値
                        $validStatuses = ['未対応', '対応中', '保留', '完了'];
                        if (empty($status)) {
                            $status = '未対応'; // デフォルト
                        } elseif (!in_array($status, $validStatuses)) {
                            $skippedCount++;
                            $errors[] = "{$rowNumber}行目: ステータスが正しくありません（{$status}）。有効な値: " . implode(', ', $validStatuses);
                            continue;
                        }

                        // PJが存在するか確認、なければ作成
                        $foundPj = null;
                        foreach ($data['projects'] as $p) {
                            if ($p['id'] === $pjNumber) {
                                $foundPj = $p;
                                break;
                            }
                        }

                        if (!$foundPj) {
                            // 新規PJを作成
                            $newPj = ['id' => $pjNumber, 'name' => $pjName];
                            $data['projects'][] = $newPj;
                            $foundPj = $newPj;
                        }

                        // トラブルデータを作成
                        $maxId++;
                        $newTrouble = [
                            'id' => $maxId,
                            'pjNumber' => $pjNumber,
                            'pjName' => $foundPj['name'],
                            'deviceType' => $deviceType,
                            'content' => $content,
                            'solution' => $solution,
                            'reporter' => $reporter,
                            'contactName' => $contactName,
                            'contact' => $contact,
                            'assignee' => $assignee,
                            'status' => $status,
                            'createdAt' => date('c'),
                            'updatedAt' => date('c'),
                            'history' => [
                                ['date' => date('c'), 'action' => 'CSVインポート']
                            ]
                        ];

                        array_unshift($data['troubles'], $newTrouble);
                        $importedCount++;
                    }

                    // データを保存
                    if ($importedCount > 0) {
                        saveData($data);
                        $success = "{$importedCount}件のトラブルをインポートしました";
                        if ($skippedCount > 0) {
                            $success .= "（{$skippedCount}件スキップ）";
                        }
                    } else {
                        $error = 'インポート可能なデータがありませんでした';
                    }
                }

                fclose($handle);
            }
        }
    }
}

require_once '../functions/header.php';
?>

<div class="card">
    <h2 class="card-title">トラブルデータのインポート</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
            <div style="margin-top: 1rem;">
                <a href="list.php" class="btn btn-primary">トラブル一覧を見る</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-warning">
            <strong>エラー詳細:</strong>
            <ul style="margin: 0.5rem 0 0 1.5rem; padding: 0;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div style="background: var(--gray-50); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
        <h3 style="margin-top: 0; font-size: 1rem; color: var(--gray-700);">インポート手順</h3>
        <ol style="margin: 0.5rem 0 0 1.5rem; padding: 0; color: var(--gray-600); font-size: 0.875rem;">
            <li>CSVテンプレートをダウンロード</li>
            <li>Excelやスプレッドシートでデータを入力</li>
            <li>CSV形式で保存（UTF-8推奨）</li>
            <li>下のフォームからファイルをアップロード</li>
        </ol>
        <div style="margin-top: 1rem;">
            <a href="download-template.php" class="btn btn-secondary">
                CSVテンプレートをダウンロード
            </a>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label required">CSVファイル</label>
            <input type="file" class="form-input" name="csv_file" accept=".csv" required>
            <small style="color: var(--gray-500); display: block; margin-top: 0.5rem;">
                ※最大ファイルサイズ: 5MB
            </small>
        </div>

        <div style="background: var(--gray-50); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <h4 style="margin-top: 0; font-size: 0.875rem; color: var(--gray-700);">注意事項</h4>
            <ul style="margin: 0.5rem 0 0 1.5rem; padding: 0; color: var(--gray-600); font-size: 0.875rem;">
                <li>必須項目: PJ番号、現場名、機器種別、トラブル内容</li>
                <li>機器種別: モニたろう、モニすけ、モニまる、モニんじゃ、ゲンバルジャー、その他</li>
                <li>ステータス: 未対応、対応中、保留、完了（空欄の場合は「未対応」）</li>
                <li>既存のPJ番号がある場合は自動的に紐付けられます</li>
                <li>新規PJ番号の場合は自動的にプロジェクトが作成されます</li>
            </ul>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            CSVをインポート
        </button>
    </form>

    <div style="margin-top: 1.5rem; text-align: center;">
        <a href="list.php" style="color: var(--gray-500); text-decoration: none;">← トラブル一覧に戻る</a>
    </div>
</div>

<?php require_once '../functions/footer.php'; ?>
