<?php
require_once 'config.php';
$data = getData();

$total = count($data['troubles']);
$pending = count(array_filter($data['troubles'], function($t) { return $t['status'] === '未対応'; }));
$inProgress = count(array_filter($data['troubles'], function($t) { return $t['status'] === '対応中'; }));
$completed = count(array_filter($data['troubles'], function($t) { return $t['status'] === '完了'; }));

// 機器別統計
$deviceStats = [];
foreach ($data['troubles'] as $t) {
    $device = isset($t['deviceType']) ? $t['deviceType'] : 'その他';
    $deviceStats[$device] = (isset($deviceStats[$device]) ? $deviceStats[$device] : 0) + 1;
}
arsort($deviceStats);

require_once 'header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value" style="color: var(--primary);"><?= $total ?></div>
        <div class="stat-label">総件数</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: <?= $pending === 0 ? 'var(--success)' : 'var(--danger)' ?>;"><?= $pending ?></div>
        <div class="stat-label">未対応</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: <?= $inProgress === 0 ? 'var(--success)' : 'var(--warning)' ?>;"><?= $inProgress ?></div>
        <div class="stat-label">対応中</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--success);"><?= $completed ?></div>
        <div class="stat-label">完了</div>
    </div>
</div>

<div class="card">
    <h2 class="card-title">機器別トラブル件数</h2>
    <?php if (empty($deviceStats)): ?>
        <p style="color: var(--gray-500);">データがありません</p>
    <?php else: ?>
        <?php foreach ($deviceStats as $device => $count): ?>
            <div style="display: flex; padding: 0.75rem 0; border-bottom: 1px solid var(--gray-100);">
                <div style="width: 150px; font-weight: 500; color: var(--gray-500);"><?= htmlspecialchars($device) ?></div>
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="flex: 1; background: var(--gray-200); height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="width: <?= $total > 0 ? ($count / $total) * 100 : 0 ?>%; background: var(--primary); height: 100%;"></div>
                        </div>
                        <span><?= $count ?>件</span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="card-title">最近のトラブル</h2>
    <?php 
    $recentTroubles = array_slice($data['troubles'], 0, 5);
    if (empty($recentTroubles)): 
    ?>
        <p style="color: var(--gray-500);">データがありません</p>
    <?php else: ?>
        <?php foreach ($recentTroubles as $t): ?>
            <div style="padding: 0.75rem; border-left: 3px solid var(--gray-300); margin-left: 0.5rem; margin-bottom: 1rem;">
                <div style="font-size: 0.75rem; color: var(--gray-500);">
                    <?= date('Y/n/j H:i', strtotime($t['createdAt'])) ?>
                </div>
                <div style="font-size: 0.875rem; margin-top: 0.25rem;">
                    <strong><?= htmlspecialchars($t['pjNumber']) ?></strong> - 
                    <?= htmlspecialchars($t['deviceType']) ?>: 
                    <?= htmlspecialchars(mb_substr($t['content'], 0, 50)) ?>...
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
