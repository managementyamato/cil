<?php
require_once '../config/config.php';
require_once '../functions/profit-loss-functions.php';

// 管理者権限チェック
if (!isAdmin()) {
    header('Location: /pages/index.php');
    exit;
}

$message = '';
$messageType = '';
$uploadedData = null;

// CSVアップロード処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    try {
        $file = $_FILES['csv_file'];

        // ファイルアップロードエラーチェック
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('ファイルのアップロードに失敗しました');
        }

        // CSVファイルチェック
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($fileExtension !== 'csv') {
            throw new Exception('CSVファイルをアップロードしてください');
        }

        // CSVをパース
        $profitLossData = parseProfitLossCSV($file['tmp_name']);

        if (empty($profitLossData)) {
            throw new Exception('CSVデータが空です');
        }

        // データを保存
        $fiscalYear = $_POST['fiscal_year'] ?? date('Y');
        saveProfitLossData($fiscalYear, $profitLossData);

        $message = '損益計算書をアップロードしました（' . count($profitLossData) . '行）';
        $messageType = 'success';
        $uploadedData = $profitLossData;

    } catch (Exception $e) {
        $message = 'エラー: ' . $e->getMessage();
        $messageType = 'error';
    }
}

require_once '../functions/header.php';
?>

<style>
.upload-container {
    max-width: 1200px;
}

.upload-card {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.file-input-wrapper {
    position: relative;
    display: inline-block;
    width: 100%;
}

.file-input {
    width: 100%;
    padding: 1rem;
    border: 2px dashed var(--primary);
    border-radius: 6px;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.3s;
}

.file-input:hover {
    background: #e0f2fe;
    border-color: var(--primary-dark);
}

.preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    overflow-x: auto;
    display: block;
    max-height: 600px;
}

.preview-table thead {
    position: sticky;
    top: 0;
    background: var(--primary);
    color: white;
    z-index: 10;
}

.preview-table th,
.preview-table td {
    padding: 0.5rem;
    border: 1px solid var(--gray-300);
    text-align: right;
}

.preview-table th:first-child,
.preview-table th:nth-child(2),
.preview-table td:first-child,
.preview-table td:nth-child(2) {
    text-align: left;
    position: sticky;
    background: white;
    z-index: 5;
}

.preview-table th:first-child,
.preview-table td:first-child {
    left: 0;
}

.preview-table th:nth-child(2),
.preview-table td:nth-child(2) {
    left: 150px;
}

.preview-table thead th:first-child,
.preview-table thead th:nth-child(2) {
    background: var(--primary);
}

.preview-table tbody tr:nth-child(even) {
    background: #f9fafb;
}

.preview-table tbody tr:hover {
    background: #e0f2fe;
}

.section-header {
    font-weight: 700;
    background: #dbeafe !important;
    color: #1e40af;
}

.subsection-header {
    font-weight: 600;
    background: #eff6ff !important;
}

.number-cell {
    font-family: 'Consolas', 'Monaco', monospace;
}

.info-box {
    background: #dbeafe;
    color: #1e40af;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.info-box h3 {
    margin: 0 0 1rem 0;
    font-size: 1.125rem;
}

.info-box ul {
    margin: 0.5rem 0 0 1.5rem;
    padding: 0;
}
</style>

<div class="upload-container">
    <h2>損益計算書 CSVアップロード</h2>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <h3>📊 CSVフォーマット</h3>
        <p>以下の形式のCSVファイルをアップロードしてください：</p>
        <ul>
            <li><strong>列A:</strong> 勘定科目</li>
            <li><strong>列B:</strong> 補助科目</li>
            <li><strong>列C〜N:</strong> 9月〜8月の月別データ</li>
            <li><strong>列O:</strong> 決算整理</li>
            <li><strong>列P:</strong> 合計</li>
        </ul>
        <p style="margin-top: 1rem; padding: 0.75rem; background: #fef3c7; border-left: 3px solid #f59e0b;">
            <strong>注意:</strong> Excelで編集した場合は、必ず「CSV UTF-8 (カンマ区切り)」形式で保存してください。
        </p>
    </div>

    <div class="upload-card">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="fiscal_year">会計年度 *</label>
                <input
                    type="text"
                    class="form-input"
                    id="fiscal_year"
                    name="fiscal_year"
                    value="<?= date('Y') ?>"
                    placeholder="2025"
                    required
                    style="max-width: 200px;"
                >
                <div class="help-text">アップロードするデータの会計年度を入力してください</div>
            </div>

            <div class="form-group">
                <label for="csv_file">CSVファイル *</label>
                <div class="file-input-wrapper">
                    <input
                        type="file"
                        class="file-input"
                        id="csv_file"
                        name="csv_file"
                        accept=".csv"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                アップロード
            </button>
            <a href="/pages/profit-loss.php" class="btn btn-secondary">
                損益計算書を表示
            </a>
        </form>
    </div>

    <?php if ($uploadedData): ?>
        <div class="upload-card">
            <h3>アップロードされたデータのプレビュー</h3>
            <div style="overflow-x: auto;">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>勘定科目</th>
                            <th>補助科目</th>
                            <th>9月</th>
                            <th>10月</th>
                            <th>11月</th>
                            <th>12月</th>
                            <th>1月</th>
                            <th>2月</th>
                            <th>3月</th>
                            <th>4月</th>
                            <th>5月</th>
                            <th>6月</th>
                            <th>7月</th>
                            <th>8月</th>
                            <th>決算整理</th>
                            <th>合計</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uploadedData as $row): ?>
                            <tr class="<?= !empty($row['account']) && empty($row['sub_account']) ? 'section-header' : '' ?>">
                                <td><?= htmlspecialchars($row['account'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['sub_account'] ?? '') ?></td>
                                <?php foreach (['09', '10', '11', '12', '01', '02', '03', '04', '05', '06', '07', '08'] as $month): ?>
                                    <td class="number-cell">
                                        <?= isset($row['months'][$month]) ? number_format($row['months'][$month]) : '' ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="number-cell">
                                    <?= isset($row['adjustment']) ? number_format($row['adjustment']) : '' ?>
                                </td>
                                <td class="number-cell">
                                    <?= isset($row['total']) ? number_format($row['total']) : '' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../functions/footer.php'; ?>
