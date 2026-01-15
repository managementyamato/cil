<?php
/**
 * マネーフォワード クラウド勤怠 接続テスト
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mf-attendance-api.php';

// 認証チェック
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/header.php';

$testResults = array();
$hasError = false;

// 設定確認
$isConfigured = MFAttendanceApiClient::isConfigured();
if (!$isConfigured) {
    $hasError = true;
    $testResults[] = array(
        'test' => '設定確認',
        'status' => 'error',
        'message' => 'API KEYが設定されていません。先に設定を完了してください。'
    );
}

// APIテスト
if ($isConfigured) {
    try {
        $client = new MFAttendanceApiClient();

        // テスト1: 従業員一覧取得
        try {
            $employees = $client->getEmployees(1, 10);
            $employeeCount = count($employees['employees'] ?? $employees['data'] ?? array());

            $testResults[] = array(
                'test' => '従業員一覧取得',
                'status' => 'success',
                'message' => "成功（{$employeeCount}件取得）",
                'data' => $employees
            );
        } catch (Exception $e) {
            $hasError = true;
            $testResults[] = array(
                'test' => '従業員一覧取得',
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }

        // テスト2: 勤怠データ取得（今月）
        if (!$hasError) {
            try {
                $from = date('Y-m-01'); // 今月の初日
                $to = date('Y-m-d');    // 今日

                $attendances = $client->getAttendances($from, $to);
                $attendanceCount = count($attendances['attendances'] ?? $attendances['data'] ?? array());

                $testResults[] = array(
                    'test' => '勤怠データ取得',
                    'status' => 'success',
                    'message' => "成功（{$from} 〜 {$to}で{$attendanceCount}件取得）",
                    'data' => $attendances
                );
            } catch (Exception $e) {
                $testResults[] = array(
                    'test' => '勤怠データ取得',
                    'status' => 'warning',
                    'message' => $e->getMessage()
                );
            }
        }

    } catch (Exception $e) {
        $hasError = true;
        $testResults[] = array(
            'test' => 'API初期化',
            'status' => 'error',
            'message' => $e->getMessage()
        );
    }
}
?>

<div class="container">
    <h1>マネーフォワード クラウド勤怠 接続テスト</h1>

    <div class="test-summary">
        <?php if ($hasError): ?>
            <div class="summary-box error">
                <h2>⚠ テスト失敗</h2>
                <p>いくつかのテストが失敗しました。設定を確認してください。</p>
            </div>
        <?php else: ?>
            <div class="summary-box success">
                <h2>✓ テスト成功</h2>
                <p>すべてのテストが正常に完了しました。</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="test-results">
        <h2>テスト結果</h2>

        <?php foreach ($testResults as $index => $result): ?>
            <div class="test-item <?php echo $result['status']; ?>">
                <div class="test-header">
                    <span class="test-number"><?php echo $index + 1; ?></span>
                    <span class="test-name"><?php echo htmlspecialchars($result['test']); ?></span>
                    <span class="test-status-badge <?php echo $result['status']; ?>">
                        <?php
                        switch ($result['status']) {
                            case 'success':
                                echo '✓ 成功';
                                break;
                            case 'error':
                                echo '✗ 失敗';
                                break;
                            case 'warning':
                                echo '⚠ 警告';
                                break;
                        }
                        ?>
                    </span>
                </div>
                <div class="test-message">
                    <?php echo htmlspecialchars($result['message']); ?>
                </div>
                <?php if (isset($result['data']) && is_array($result['data'])): ?>
                    <details class="test-details">
                        <summary>詳細データを表示</summary>
                        <pre><?php echo htmlspecialchars(json_encode($result['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
                    </details>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="actions">
        <a href="mf-attendance-settings.php" class="btn btn-primary">設定に戻る</a>
        <a href="settings.php" class="btn btn-secondary">メインメニュー</a>
        <button onclick="location.reload()" class="btn btn-info">再テスト</button>
    </div>
</div>

<style>
.test-summary {
    margin-bottom: 30px;
}

.summary-box {
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}

.summary-box.success {
    background: #C8E6C9;
    border: 2px solid #4CAF50;
}

.summary-box.error {
    background: #FFCDD2;
    border: 2px solid #F44336;
}

.summary-box h2 {
    margin: 0 0 10px 0;
}

.summary-box.success h2 {
    color: #2E7D32;
}

.summary-box.error h2 {
    color: #C62828;
}

.test-results {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.test-item {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
}

.test-item.success {
    border-left: 4px solid #4CAF50;
    background: #f9fff9;
}

.test-item.error {
    border-left: 4px solid #F44336;
    background: #fff9f9;
}

.test-item.warning {
    border-left: 4px solid #FF9800;
    background: #fffbf5;
}

.test-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 10px;
}

.test-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    background: #2196F3;
    color: white;
    border-radius: 50%;
    font-weight: bold;
}

.test-name {
    font-size: 1.1em;
    font-weight: bold;
    flex: 1;
}

.test-status-badge {
    padding: 5px 15px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 0.9em;
}

.test-status-badge.success {
    background: #4CAF50;
    color: white;
}

.test-status-badge.error {
    background: #F44336;
    color: white;
}

.test-status-badge.warning {
    background: #FF9800;
    color: white;
}

.test-message {
    padding-left: 45px;
    color: #333;
    line-height: 1.6;
}

.test-details {
    margin-top: 15px;
    padding-left: 45px;
}

.test-details summary {
    cursor: pointer;
    color: #2196F3;
    font-weight: bold;
    margin-bottom: 10px;
}

.test-details summary:hover {
    color: #1976D2;
}

.test-details pre {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
    font-size: 0.85em;
    border: 1px solid #ddd;
}

.actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: #2196F3;
    color: white;
}

.btn-primary:hover {
    background: #1976D2;
}

.btn-secondary {
    background: #757575;
    color: white;
}

.btn-secondary:hover {
    background: #616161;
}

.btn-info {
    background: #00BCD4;
    color: white;
}

.btn-info:hover {
    background: #0097A7;
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>
