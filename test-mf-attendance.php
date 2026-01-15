<?php
/**
 * MF勤怠 CLI テストスクリプト
 */

require_once __DIR__ . '/mf-attendance-api.php';

echo "=== MF勤怠 API テスト ===\n\n";

try {
    // 設定確認
    echo "1. 設定確認\n";
    $isConfigured = MFAttendanceApiClient::isConfigured();
    echo "   認証済み: " . ($isConfigured ? "はい" : "いいえ") . "\n\n";

    if (!$isConfigured) {
        echo "エラー: まず /mf-attendance-settings.php でAPI KEYを設定してください。\n";
        echo "\n取得方法:\n";
        echo "1. マネーフォワード クラウド勤怠にログイン\n";
        echo "2. 「全権管理者メニュー」→「連携」→「外部連携」を開く\n";
        echo "3. 「外部システム連携用識別子」のAPI KEYをコピー\n";
        echo "4. 設定画面に貼り付けて保存\n";
        exit(1);
    }

    $client = new MFAttendanceApiClient();

    // テスト1: 従業員一覧取得
    echo "2. 従業員一覧取得をテスト\n";
    try {
        $response = $client->getEmployees(1, 10);
        $employees = $response['employees'] ?? $response['data'] ?? array();

        echo "   ✓ APIリクエスト成功\n";
        echo "   レスポンスキー: " . implode(', ', array_keys($response)) . "\n";
        echo "   取得件数: " . count($employees) . " 件\n\n";

        if (count($employees) > 0) {
            echo "   最初の従業員:\n";
            $firstEmployee = $employees[0];
            if (isset($firstEmployee['name'])) {
                echo "     名前: " . $firstEmployee['name'] . "\n";
            }
            if (isset($firstEmployee['employee_number'])) {
                echo "     従業員番号: " . $firstEmployee['employee_number'] . "\n";
            }
            echo "\n";
        }
    } catch (Exception $e) {
        echo "   ✗ APIリクエスト失敗: " . $e->getMessage() . "\n\n";
    }

    // テスト2: 勤怠データ取得
    echo "3. 勤怠データ取得をテスト\n";
    try {
        $from = date('Y-m-01'); // 今月の初日
        $to = date('Y-m-d');    // 今日

        echo "   期間: $from から $to\n";
        $response = $client->getAttendances($from, $to);
        $attendances = $response['attendances'] ?? $response['data'] ?? array();

        echo "   ✓ APIリクエスト成功\n";
        echo "   レスポンスキー: " . implode(', ', array_keys($response)) . "\n";
        echo "   取得件数: " . count($attendances) . " 件\n\n";

        if (count($attendances) > 0) {
            echo "   最初の勤怠データ:\n";
            $firstAttendance = $attendances[0];
            foreach ($firstAttendance as $key => $value) {
                if (!is_array($value) && !is_object($value)) {
                    echo "     " . $key . ": " . $value . "\n";
                }
            }
            echo "\n";
        }

    } catch (Exception $e) {
        echo "   ✗ APIリクエスト失敗: " . $e->getMessage() . "\n\n";
    }

    echo "✓ すべてのテストが完了しました！\n";

} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
