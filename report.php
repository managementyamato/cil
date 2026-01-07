<?php
require_once 'config.php';
$data = getData();

$error = '';
$success = '';

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pjNumber = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['pj_number'] ?? ''));
    $newPjName = trim($_POST['new_pj_name'] ?? '');
    $deviceType = $_POST['device_type'] ?? '';
    $content = trim($_POST['content'] ?? '');
    $solution = trim($_POST['solution'] ?? '');
    $reporter = $_POST['reporter'] ?? '';
    
    if (empty($pjNumber) || empty($deviceType) || empty($content)) {
        $error = '必須項目を入力してください';
    } else {
        // PJを探す
        $foundPj = null;
        foreach ($data['projects'] as $p) {
            if ($p['id'] === $pjNumber) {
                $foundPj = $p;
                break;
            }
        }
        
        // 未登録PJの場合は新規登録
        if (!$foundPj) {
            if (empty($newPjName)) {
                $error = '新規PJの現場名を入力してください';
            } else {
                $newPj = ['id' => $pjNumber, 'name' => $newPjName];
                $data['projects'][] = $newPj;
                $foundPj = $newPj;
            }
        }
        
        if (!$error) {
            // 新しいトラブルを追加
            $maxId = 0;
            foreach ($data['troubles'] as $t) {
                if ($t['id'] > $maxId) $maxId = $t['id'];
            }
            
            $newTrouble = [
                'id' => $maxId + 1,
                'pjNumber' => $pjNumber,
                'pjName' => $foundPj['name'],
                'deviceType' => $deviceType,
                'content' => $content,
                'solution' => $solution,
                'reporter' => $reporter,
                'assignee' => '',
                'status' => '未対応',
                'createdAt' => date('c'),
                'updatedAt' => date('c'),
                'history' => [
                    ['date' => date('c'), 'action' => '報告受付']
                ]
            ];
            
            array_unshift($data['troubles'], $newTrouble);
            saveData($data);
            
            header('Location: list.php?reported=1');
            exit;
        }
    }
}

require_once 'header.php';
?>

<div class="card">
    <h2 class="card-title">トラブル報告</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST" id="report-form">
        <div class="form-group">
            <label class="form-label required">PJ番号</label>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="text" class="form-input" id="pj-number" name="pj_number" placeholder="例: 001" style="flex: 0 0 120px;" required>
                <span id="pj-name-display" style="color: var(--gray-500); font-size: 0.875rem;"></span>
            </div>
            <div id="pj-suggestions" style="margin-top: 0.5rem;"></div>
            <div id="new-pj-name-container" style="display: none; margin-top: 0.75rem;">
                <label class="form-label required">現場名（新規PJ）</label>
                <input type="text" class="form-input" id="new-pj-name" name="new_pj_name" placeholder="現場名を入力してください">
                <small style="color: var(--gray-500);">※このPJは新規登録されます</small>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label required">機器種別</label>
            <select class="form-select" name="device_type" required>
                <option value="">選択してください</option>
                <option value="モニたろう">モニたろう</option>
                <option value="モニすけ">モニすけ</option>
                <option value="モニまる">モニまる</option>
                <option value="モニんじゃ">モニんじゃ</option>
                <option value="ゲンバルジャー">ゲンバルジャー</option>
                <option value="その他">その他</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label required">トラブル内容</label>
            <textarea class="form-textarea" name="content" placeholder="発生している症状を具体的に記載してください" required></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">解決方法</label>
            <textarea class="form-textarea" name="solution" placeholder="解決済みの場合は対応内容を記載" style="min-height: 80px;"></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">報告者</label>
            <select class="form-select" name="reporter">
                <option value="">選択してください</option>
                <?php foreach ($data['assignees'] as $a): ?>
                    <option value="<?= htmlspecialchars($a['name']) ?>"><?= htmlspecialchars($a['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary btn-block">報告する</button>
    </form>
</div>

<script>
// PJデータをJavaScriptに渡す
const projects = <?= json_encode($data['projects']) ?>;

document.getElementById('pj-number').addEventListener('input', function(e) {
    // 小文字と数字のみに制限
    e.target.value = e.target.value.toLowerCase().replace(/[^a-z0-9]/g, '');
    
    const input = e.target.value.trim();
    const display = document.getElementById('pj-name-display');
    const suggestions = document.getElementById('pj-suggestions');
    const newPjContainer = document.getElementById('new-pj-name-container');
    
    display.textContent = '';
    suggestions.innerHTML = '';
    newPjContainer.style.display = 'none';
    
    if (!input) return;
    
    // 完全一致を探す
    const exactMatch = projects.find(p => p.id === input);
    if (exactMatch) {
        display.textContent = '→ ' + exactMatch.name;
        display.style.color = 'var(--success)';
        return;
    }
    
    // 部分一致のサジェスト
    const matches = projects.filter(p => 
        p.id.includes(input) || p.name.includes(input)
    ).slice(0, 5);
    
    if (matches.length > 0) {
        display.textContent = '候補:';
        display.style.color = 'var(--gray-500)';
        suggestions.innerHTML = matches.map(p => 
            `<button type="button" class="btn btn-secondary btn-sm" onclick="selectPj('${p.id}', '${p.name.replace(/'/g, "\\'")}')" style="margin-right: 0.5rem; margin-bottom: 0.5rem;">
                ${p.id} - ${p.name}
            </button>`
        ).join('');
    }
    
    // 未登録のPJ番号の場合、現場名入力欄を表示
    display.innerHTML = '<span style="color: var(--warning);">※未登録のPJ番号です</span>';
    newPjContainer.style.display = 'block';
});

function selectPj(id, name) {
    document.getElementById('pj-number').value = id;
    document.getElementById('pj-name-display').textContent = '→ ' + name;
    document.getElementById('pj-name-display').style.color = 'var(--success)';
    document.getElementById('pj-suggestions').innerHTML = '';
    document.getElementById('new-pj-name-container').style.display = 'none';
}
</script>

<?php require_once 'footer.php'; ?>
