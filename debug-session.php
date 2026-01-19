<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/photo-attendance-functions.php';

// 管理者のみアクセス可能
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/header.php';
?>

<style>
.debug-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.debug-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.debug-card h2 {
    margin-top: 0;
    margin-bottom: 1rem;
    color: #2d3748;
}

.debug-table {
    width: 100%;
    border-collapse: collapse;
}

.debug-table th,
.debug-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

.debug-table th {
    background: #f7fafc;
    font-weight: 600;
}

.status-ok {
    color: #22543d;
    background: #c6f6d5;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.875rem;
}

.status-error {
    color: #c62828;
    background: #ffcdd2;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.875rem;
}
</style>

<div class="debug-container">
    <h1>🔍 セッション診断</h1>

    <!-- セッション情報 -->
    <div class="debug-card">
        <h2>現在のセッション情報</h2>
        <table class="debug-table">
            <thead>
                <tr>
                    <th>キー</th>
                    <th>値</th>
                    <th>ステータス</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>user_email</td>
                    <td><?= htmlspecialchars($_SESSION['user_email'] ?? 'なし') ?></td>
                    <td><?= isset($_SESSION['user_email']) ? '<span class="status-ok">✓ 設定済み</span>' : '<span class="status-error">✗ 未設定</span>' ?></td>
                </tr>
                <tr>
                    <td>user_name</td>
                    <td><?= htmlspecialchars($_SESSION['user_name'] ?? 'なし') ?></td>
                    <td><?= isset($_SESSION['user_name']) ? '<span class="status-ok">✓ 設定済み</span>' : '<span class="status-error">✗ 未設定</span>' ?></td>
                </tr>
                <tr>
                    <td>user_role</td>
                    <td><?= htmlspecialchars($_SESSION['user_role'] ?? 'なし') ?></td>
                    <td><?= isset($_SESSION['user_role']) ? '<span class="status-ok">✓ 設定済み</span>' : '<span class="status-error">✗ 未設定</span>' ?></td>
                </tr>
                <tr>
                    <td>user_id (従業員ID)</td>
                    <td><?= htmlspecialchars($_SESSION['user_id'] ?? 'なし') ?></td>
                    <td><?= isset($_SESSION['user_id']) ? '<span class="status-ok">✓ 設定済み</span>' : '<span class="status-error">✗ 未設定（写真アップロードが使えません）</span>' ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 写真アップロード機能の状態 -->
    <div class="debug-card">
        <h2>写真アップロード機能の状態</h2>
        <?php
        $canUsePhotoUpload = isset($_SESSION['user_id']);
        $employees = getEmployees();
        $userEmployee = null;

        if ($canUsePhotoUpload) {
            foreach ($employees as $emp) {
                if ($emp['id'] == $_SESSION['user_id']) {
                    $userEmployee = $emp;
                    break;
                }
            }
        }
        ?>

        <table class="debug-table">
            <thead>
                <tr>
                    <th>チェック項目</th>
                    <th>ステータス</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>user_id設定</td>
                    <td><?= $canUsePhotoUpload ? '<span class="status-ok">✓ OK</span>' : '<span class="status-error">✗ NG</span>' ?></td>
                    <td><?= $canUsePhotoUpload ? 'セッションにuser_idが設定されています' : '再ログインが必要です' ?></td>
                </tr>
                <tr>
                    <td>従業員マッチング</td>
                    <td><?= $userEmployee ? '<span class="status-ok">✓ OK</span>' : '<span class="status-error">✗ NG</span>' ?></td>
                    <td>
                        <?php if ($userEmployee): ?>
                            従業員「<?= htmlspecialchars($userEmployee['name']) ?>」(ID: <?= $userEmployee['id'] ?>)とマッチング
                        <?php else: ?>
                            user_id <?= htmlspecialchars($_SESSION['user_id'] ?? 'なし') ?> に対応する従業員が見つかりません
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>アップロードディレクトリ</td>
                    <td>
                        <?php
                        require_once __DIR__ . '/photo-attendance-functions.php';
                        $dirExists = file_exists(PHOTO_UPLOAD_DIR);
                        $dirWritable = $dirExists && is_writable(PHOTO_UPLOAD_DIR);
                        echo $dirWritable ? '<span class="status-ok">✓ OK</span>' : '<span class="status-error">✗ NG</span>';
                        ?>
                    </td>
                    <td>
                        <?php if ($dirWritable): ?>
                            ディレクトリは存在し、書き込み可能です
                        <?php elseif ($dirExists): ?>
                            ディレクトリは存在しますが、書き込みできません
                        <?php else: ?>
                            ディレクトリが存在しません
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php if (!$canUsePhotoUpload): ?>
            <div style="margin-top: 1.5rem; padding: 1rem; background: #fff3e0; border-left: 4px solid #ff9800; border-radius: 4px;">
                <strong>⚠️ 対処方法:</strong><br>
                一度ログアウトして、再度ログインしてください。ログイン時にuser_idがセッションに設定されます。
                <div style="margin-top: 1rem;">
                    <a href="logout.php" class="btn btn-primary" style="display: inline-block; padding: 0.5rem 1rem; background: #3182ce; color: white; text-decoration: none; border-radius: 4px;">ログアウトして再ログイン</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- 全セッションデータ -->
    <div class="debug-card">
        <h2>全セッションデータ（デバッグ用）</h2>
        <pre style="background: #f7fafc; padding: 1rem; border-radius: 4px; overflow-x: auto;"><?= htmlspecialchars(print_r($_SESSION, true)) ?></pre>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
