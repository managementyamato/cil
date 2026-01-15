<?php
/**
 * アルコールチェック管理 - メイン画面
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/photo-attendance-functions.php';

// 管理者・編集者権限チェック
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

// 本日の日付
$today = date('Y-m-d');

// 従業員一覧を取得
$employees = getEmployees();

// 従業員データがない場合
if (empty($employees)) {
    require_once __DIR__ . '/header.php';
    echo '<div class="card" style="max-width: 800px; margin: 2rem auto;">';
    echo '<div class="card-header"><h2 style="margin:0;">従業員データが登録されていません</h2></div>';
    echo '<div class="card-body">';
    echo '<p>アルコールチェック管理を使用するには、まず従業員マスタに従業員を登録してください。</p>';
    echo '<a href="employees.php" class="btn btn-primary">従業員マスタへ</a>';
    echo '</div></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

// 本日の写真アップロード状況を取得
$uploadStatus = getUploadStatusForDate($today);

require_once __DIR__ . '/header.php';
?>

<style>
.photo-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.status-grid {
    display: grid;
    gap: 1rem;
    margin-top: 20px;
}

.employee-row {
    display: grid;
    grid-template-columns: 200px 1fr 1fr 100px;
    gap: 1rem;
    align-items: center;
    background: white;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.employee-row.complete {
    background: #e8f5e9;
}

.employee-row.partial {
    background: #fff3e0;
}

.employee-row.missing {
    background: #ffebee;
}

.employee-name {
    font-weight: bold;
    color: var(--gray-900);
}

.photo-box {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.photo-preview {
    width: 150px;
    height: 150px;
    object-fit: cover;
    border-radius: 4px;
    border: 2px solid #ddd;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-badge.complete {
    background: #c8e6c9;
    color: #2e7d32;
}

.status-badge.partial {
    background: #ffe0b2;
    color: #e65100;
}

.status-badge.missing {
    background: #ffcdd2;
    color: #c62828;
}

.upload-time {
    font-size: 0.75rem;
    color: #666;
}

.no-photo {
    width: 150px;
    height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f5f5;
    border: 2px dashed #ddd;
    border-radius: 4px;
    color: #999;
    font-size: 0.875rem;
}

.header-row {
    display: grid;
    grid-template-columns: 200px 1fr 1fr 100px;
    gap: 1rem;
    font-weight: bold;
    padding: 0.5rem 1rem;
    color: var(--gray-600);
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    text-align: center;
}

.summary-number {
    font-size: 2rem;
    font-weight: bold;
    margin: 0.5rem 0;
}

.summary-label {
    color: var(--gray-600);
    font-size: 0.875rem;
}
</style>

<div class="photo-container">
    <div class="card">
        <div class="card-header">
            <h2 style="margin: 0;">アルコールチェック管理 - <?= date('Y年m月d日', strtotime($today)); ?></h2>
        </div>
        <div class="card-body">
            <!-- サマリー -->
            <?php
            $complete = 0;
            $partial = 0;
            $missing = 0;

            foreach ($employees as $emp) {
                $status = $uploadStatus[$emp['id']] ?? ['start' => null, 'end' => null];
                if ($status['start'] && $status['end']) {
                    $complete++;
                } elseif ($status['start'] || $status['end']) {
                    $partial++;
                } else {
                    $missing++;
                }
            }
            ?>

            <div class="summary-cards">
                <div class="summary-card" style="border-left: 4px solid #4caf50;">
                    <div class="summary-label">完了（2回アップロード済み）</div>
                    <div class="summary-number" style="color: #4caf50;"><?= $complete ?></div>
                </div>
                <div class="summary-card" style="border-left: 4px solid #ff9800;">
                    <div class="summary-label">部分完了（1回のみ）</div>
                    <div class="summary-number" style="color: #ff9800;"><?= $partial ?></div>
                </div>
                <div class="summary-card" style="border-left: 4px solid #f44336;">
                    <div class="summary-label">未アップロード</div>
                    <div class="summary-number" style="color: #f44336;"><?= $missing ?></div>
                </div>
            </div>

            <!-- ヘッダー -->
            <div class="header-row">
                <div>従業員名</div>
                <div>出勤前チェック</div>
                <div>退勤前チェック</div>
                <div>ステータス</div>
            </div>

            <!-- 従業員一覧 -->
            <div class="status-grid">
                <?php foreach ($employees as $employee): ?>
                    <?php
                    $status = $uploadStatus[$employee['id']] ?? ['start' => null, 'end' => null];
                    $rowClass = 'missing';
                    $badgeClass = 'missing';
                    $badgeText = '未アップロード';

                    if ($status['start'] && $status['end']) {
                        $rowClass = 'complete';
                        $badgeClass = 'complete';
                        $badgeText = '完了';
                    } elseif ($status['start'] || $status['end']) {
                        $rowClass = 'partial';
                        $badgeClass = 'partial';
                        $badgeText = '部分完了';
                    }
                    ?>
                    <div class="employee-row <?= $rowClass ?>">
                        <div class="employee-name"><?= htmlspecialchars($employee['name']); ?></div>

                        <!-- 出勤前チェック -->
                        <div class="photo-box">
                            <?php if ($status['start']): ?>
                                <img src="<?= htmlspecialchars($status['start']['photo_path']); ?>"
                                     alt="出勤前チェック"
                                     class="photo-preview"
                                     onclick="window.open(this.src, '_blank')">
                                <div class="upload-time">
                                    <?= date('H:i', strtotime($status['start']['uploaded_at'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="no-photo">未アップロード</div>
                            <?php endif; ?>
                        </div>

                        <!-- 退勤前チェック -->
                        <div class="photo-box">
                            <?php if ($status['end']): ?>
                                <img src="<?= htmlspecialchars($status['end']['photo_path']); ?>"
                                     alt="退勤前チェック"
                                     class="photo-preview"
                                     onclick="window.open(this.src, '_blank')">
                                <div class="upload-time">
                                    <?= date('H:i', strtotime($status['end']['uploaded_at'])); ?>
                                </div>
                            <?php else: ?>
                                <div class="no-photo">未アップロード</div>
                            <?php endif; ?>
                        </div>

                        <!-- ステータス -->
                        <div>
                            <span class="status-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
