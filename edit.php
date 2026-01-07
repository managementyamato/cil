<?php
require_once 'config.php';
$data = getData();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$trouble = null;
$troubleIndex = -1;

foreach ($data['troubles'] as $index => $t) {
    if ($t['id'] === $id) {
        $trouble = $t;
        $troubleIndex = $index;
        break;
    }
}

if (!$trouble) {
    header('Location: list.php');
    exit;
}

$error = '';

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newAssignee = isset($_POST['assignee']) ? $_POST['assignee'] : '';
    $newStatus = isset($_POST['status']) ? $_POST['status'] : '';
    $newSolution = trim(isset($_POST['solution']) ? $_POST['solution'] : '');
    $note = trim(isset($_POST['note']) ? $_POST['note'] : '');
    
    $oldAssignee = isset($trouble['assignee']) ? $trouble['assignee'] : '';
    $oldSolution = isset($trouble['solution']) ? $trouble['solution'] : '';
    
    // 履歴追加
    if (!isset($trouble['history'])) {
        $trouble['history'] = array();
    }
    
    if ($newAssignee !== $oldAssignee) {
        $trouble['history'][] = array(
            'date' => date('c'),
            'action' => $newAssignee ? $newAssignee . 'がアサインされました' : 'アサインが解除されました'
        );
    }
    
    if ($newStatus !== $trouble['status']) {
        $trouble['history'][] = array(
            'date' => date('c'),
            'action' => 'ステータスを「' . $newStatus . '」に変更'
        );
    }
    
    if ($newSolution !== $oldSolution) {
        $trouble['history'][] = array(
            'date' => date('c'),
            'action' => '解決方法を更新'
        );
    }
    
    if ($note) {
        $trouble['history'][] = array(
            'date' => date('c'),
            'action' => $note
        );
    }
    
    $trouble['assignee'] = $newAssignee;
    $trouble['status'] = $newStatus;
    $trouble['solution'] = $newSolution;
    $trouble['updatedAt'] = date('c');
    
    $data['troubles'][$troubleIndex] = $trouble;
    saveData($data);
    
    header('Location: list.php?updated=1');
    exit;
}

require_once 'header.php';
?>

<div class="card">
    <h2 class="card-title">トラブル編集 #<?= $trouble['id'] ?></h2>
    
    <!-- 基本情報（読み取り専用） -->
    <div style="background: var(--gray-50); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
        <div style="display: grid; gap: 0.5rem;">
            <?php $pjName = isset($trouble['pjName']) ? $trouble['pjName'] : ''; ?>
            <?php $reporter = isset($trouble['reporter']) ? $trouble['reporter'] : '-'; ?>
            <?php $contactName = isset($trouble['contactName']) ? $trouble['contactName'] : ''; ?>
            <?php $contact = isset($trouble['contact']) ? $trouble['contact'] : ''; ?>
            <?php
            $contactDisplay = '';
            if ($contactName && $contact) {
                $contactDisplay = $contactName . ' (' . $contact . ')';
            } elseif ($contactName) {
                $contactDisplay = $contactName;
            } elseif ($contact) {
                $contactDisplay = $contact;
            } else {
                $contactDisplay = '-';
            }
            ?>
            <div><strong>PJ番号:</strong> <?= htmlspecialchars($trouble['pjNumber']) ?> - <?= htmlspecialchars($pjName) ?></div>
            <div><strong>機器:</strong> <?= htmlspecialchars($trouble['deviceType']) ?></div>
            <div><strong>報告者:</strong> <?= htmlspecialchars($reporter) ?></div>
            <div><strong>連絡先:</strong> <?= htmlspecialchars($contactDisplay) ?></div>
            <div><strong>登録日:</strong> <?= date('Y/n/j H:i', strtotime($trouble['createdAt'])) ?></div>
        </div>
    </div>
    
    <div style="margin-bottom: 1.5rem;">
        <label class="form-label">トラブル内容</label>
        <div style="background: var(--gray-50); padding: 1rem; border-radius: 8px; white-space: pre-wrap;">
            <?= htmlspecialchars($trouble['content']) ?>
        </div>
    </div>
    
    <form method="POST">
        <div class="form-group">
            <label class="form-label">対応者</label>
            <select class="form-select" name="assignee">
                <option value="">未割当</option>
                <?php 
                $currentAssignee = isset($trouble['assignee']) ? $trouble['assignee'] : '';
                foreach ($data['assignees'] as $a): 
                ?>
                    <option value="<?= htmlspecialchars($a['name']) ?>" <?= $currentAssignee === $a['name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">ステータス</label>
            <select class="form-select" name="status">
                <option value="未対応" <?= $trouble['status'] === '未対応' ? 'selected' : '' ?>>未対応</option>
                <option value="対応中" <?= $trouble['status'] === '対応中' ? 'selected' : '' ?>>対応中</option>
                <option value="保留" <?= $trouble['status'] === '保留' ? 'selected' : '' ?>>保留</option>
                <option value="完了" <?= $trouble['status'] === '完了' ? 'selected' : '' ?>>完了</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">解決方法</label>
            <?php $solution = isset($trouble['solution']) ? $trouble['solution'] : ''; ?>
            <textarea class="form-textarea" name="solution" placeholder="解決方法を記載"><?= htmlspecialchars($solution) ?></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">対応メモ</label>
            <textarea class="form-textarea" name="note" placeholder="対応内容を記録（履歴に追加）" style="min-height: 80px;"></textarea>
        </div>
        
        <div style="display: flex; gap: 1rem;">
            <a href="list.php" class="btn btn-secondary">キャンセル</a>
            <button type="submit" class="btn btn-primary">保存</button>
        </div>
    </form>
</div>

<div class="card">
    <h2 class="card-title">対応履歴</h2>
    <?php 
    $history = isset($trouble['history']) ? $trouble['history'] : array();
    $history = array_reverse($history);
    foreach ($history as $h): 
    ?>
        <div style="padding: 0.75rem; border-left: 3px solid var(--gray-300); margin-left: 0.5rem; margin-bottom: 1rem;">
            <div style="font-size: 0.75rem; color: var(--gray-500);">
                <?= date('Y/n/j H:i', strtotime($h['date'])) ?>
            </div>
            <div style="font-size: 0.875rem; margin-top: 0.25rem;">
                <?= htmlspecialchars($h['action']) ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once 'footer.php'; ?>
