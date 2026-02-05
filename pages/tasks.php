<?php
require_once '../api/auth.php';

$data = getData();
$tasks = $data['tasks'] ?? [];
$employees = $data['employees'] ?? [];

// タスクをリスト化（ToDo と 完了済み）
$tasksByStatus = [
    'todo' => [],
    'done' => [],
];

foreach ($tasks as $task) {
    $status = $task['status'] ?? 'todo';
    if (empty($task['parent_id'])) {
        if ($status === 'done') {
            $tasksByStatus['done'][] = $task;
        } else {
            $tasksByStatus['todo'][] = $task;
        }
    }
}

// 各ステータス内でorderでソート
foreach ($tasksByStatus as $status => &$statusTasks) {
    usort($statusTasks, fn($a, $b) => ($a['order'] ?? 0) - ($b['order'] ?? 0));
}
unset($statusTasks);

// サブタスクをカウント
function countSubtasks($taskId, $allTasks) {
    $total = 0;
    $completed = 0;
    foreach ($allTasks as $t) {
        if (($t['parent_id'] ?? '') === $taskId) {
            $total++;
            if (($t['status'] ?? '') === 'done') {
                $completed++;
            }
        }
    }
    return ['total' => $total, 'completed' => $completed];
}

// ステータス設定（ToDo と 完了済み）
$statusConfig = [
    'todo' => ['label' => 'ToDo', 'color' => 'gray', 'icon' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><path d="M9 12l2 2 4-4"/>'],
    'done' => ['label' => '完了済み', 'color' => 'success', 'icon' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
];

require_once '../functions/header.php';
?>

<!-- SortableJS CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<style>
/* かんばんボード */
.kanban-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.kanban-header h1 {
    margin: 0;
    font-size: 1.5rem;
}
.kanban-actions {
    display: flex;
    gap: 0.75rem;
}

.kanban-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.kanban-column {
    background: var(--gray-100);
    border-radius: 12px;
    padding: 1rem;
}

.kanban-column-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--gray-200);
}

.column-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.column-icon svg {
    width: 16px;
    height: 16px;
}

.kanban-column[data-status="todo"] .column-icon { background: var(--gray-200); color: var(--gray-700); }
.kanban-column[data-status="done"] .column-icon { background: var(--success-light); color: var(--success); }
.kanban-column[data-status="done"] .task-card { opacity: 0.7; }
.kanban-column[data-status="done"] .task-title { text-decoration: line-through; color: var(--gray-500); }
.kanban-column[data-status="in_progress"] .column-icon { background: var(--primary-light); color: var(--primary); }
.kanban-column[data-status="review"] .column-icon { background: var(--purple-light); color: var(--purple); }
.kanban-column[data-status="done"] .column-icon { background: var(--success-light); color: var(--success); }

.column-title {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--gray-800);
    flex: 1;
}

.task-count {
    background: var(--gray-200);
    color: var(--gray-600);
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    border-radius: 10px;
}

.kanban-tasks {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    min-height: 60px;
}

/* タスクカード */
.task-card {
    background: white;
    border-radius: 10px;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid var(--gray-200);
    cursor: grab;
    transition: all 0.2s;
    width: 280px;
    flex-shrink: 0;
}

.task-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.task-card.sortable-ghost {
    opacity: 0.4;
    background: var(--primary-light);
}

.task-card.sortable-chosen {
    transform: rotate(2deg);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

/* 優先度ボーダー */
.task-card.priority-urgent { border-left: 4px solid var(--danger); }
.task-card.priority-high { border-left: 4px solid var(--warning); }
.task-card.priority-medium { border-left: 4px solid var(--primary); }
.task-card.priority-low { border-left: 4px solid var(--gray-300); }

.task-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-800);
    margin-bottom: 0.5rem;
    line-height: 1.4;
}

.task-description {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-bottom: 0.75rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.task-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.75rem;
}

.task-due {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--gray-500);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    background: var(--gray-100);
}
.task-due.overdue {
    background: var(--danger-light);
    color: #c62828;
}
.task-due.soon {
    background: var(--warning-light);
    color: #e65100;
}

.task-assignee {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--gray-600);
}
.task-assignee-avatar {
    width: 22px;
    height: 22px;
    background: var(--primary-light);
    color: var(--primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    font-weight: 600;
}

.task-subtasks {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin-top: 0.5rem;
    font-size: 0.75rem;
    color: var(--gray-500);
}
.task-subtasks svg {
    width: 12px;
    height: 12px;
}

.task-labels {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    margin-bottom: 0.5rem;
}
.task-label {
    font-size: 0.65rem;
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    font-weight: 600;
}
.task-label.urgent { background: var(--danger-light); color: #c62828; }
.task-label.high { background: var(--warning-light); color: #e65100; }

/* タスク追加ボタン（カラム内） */
.add-task-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: white;
    border: 2px dashed var(--gray-300);
    border-radius: 8px;
    color: var(--gray-500);
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
    width: 140px;
    flex-shrink: 0;
}
.add-task-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-light);
}

/* モーダル追加スタイル */
.priority-select {
    display: flex;
    gap: 0.5rem;
}
.priority-option {
    flex: 1;
    padding: 0.5rem;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    text-align: center;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.2s;
}
.priority-option:hover {
    border-color: var(--gray-400);
}
.priority-option.selected {
    border-color: var(--primary);
    background: var(--primary-light);
}
.priority-option[data-priority="urgent"].selected { border-color: var(--danger); background: var(--danger-light); }
.priority-option[data-priority="high"].selected { border-color: var(--warning); background: var(--warning-light); }

@media (max-width: 768px) {
    .kanban-tasks {
        flex-direction: column;
    }
    .task-card {
        width: 100%;
    }
}
</style>

<div class="kanban-header">
    <h1>タスク管理</h1>
</div>

<!-- かんばんボード -->
<div class="kanban-container">
    <?php foreach ($statusConfig as $status => $config): ?>
    <div class="kanban-column" data-status="<?= $status ?>">
        <div class="kanban-column-header">
            <div class="column-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <?= $config['icon'] ?>
                </svg>
            </div>
            <span class="column-title"><?= $config['label'] ?></span>
            <span class="task-count" id="count-<?= $status ?>"><?= count($tasksByStatus[$status]) ?></span>
        </div>
        <div class="kanban-tasks" id="column-<?= $status ?>">
            <?php foreach ($tasksByStatus[$status] as $task):
                $subtasks = countSubtasks($task['id'], $tasks);
                $priority = $task['priority'] ?? 'medium';
                $dueClass = '';
                if (!empty($task['due_date'])) {
                    $dueDate = strtotime($task['due_date']);
                    $today = strtotime(date('Y-m-d'));
                    if ($dueDate < $today) {
                        $dueClass = 'overdue';
                    } elseif ($dueDate <= $today + 3 * 86400) {
                        $dueClass = 'soon';
                    }
                }
            ?>
            <div class="task-card priority-<?= $priority ?>"
                 data-id="<?= htmlspecialchars($task['id']) ?>"
                 data-assignee="<?= htmlspecialchars($task['assignee_id'] ?? '') ?>"
                 data-priority="<?= htmlspecialchars($priority) ?>"
                 onclick="openEditTaskModal(<?= htmlspecialchars(json_encode($task, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)) ?>)">
                <?php if ($priority === 'urgent' || $priority === 'high'): ?>
                <div class="task-labels">
                    <span class="task-label <?= $priority ?>"><?= $priority === 'urgent' ? '緊急' : '高' ?></span>
                </div>
                <?php endif; ?>
                <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                <?php if (!empty($task['description'])): ?>
                <div class="task-description"><?= htmlspecialchars($task['description']) ?></div>
                <?php endif; ?>
                <div class="task-meta">
                    <?php if (!empty($task['due_date'])): ?>
                    <span class="task-due <?= $dueClass ?>">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?= date('n/j', strtotime($task['due_date'])) ?>
                    </span>
                    <?php else: ?>
                    <span></span>
                    <?php endif; ?>
                    <?php if (!empty($task['assignee_name'])): ?>
                    <span class="task-assignee">
                        <span class="task-assignee-avatar"><?= mb_substr($task['assignee_name'], 0, 1) ?></span>
                        <?= htmlspecialchars($task['assignee_name']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if ($subtasks['total'] > 0): ?>
                <div class="task-subtasks">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <?= $subtasks['completed'] ?>/<?= $subtasks['total'] ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if ($status === 'todo'): ?>
            <button class="add-task-btn" onclick="openAddTaskModal('<?= $status ?>')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                追加
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- タスク追加モーダル -->
<div id="addTaskModal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3>タスク追加</h3>
            <span class="close" onclick="closeModal('addTaskModal')">&times;</span>
        </div>
        <div class="modal-body">
            <input type="hidden" id="add_status" value="todo">
            <div class="form-group">
                <label>タイトル <span style="color:#dc2626;">*</span></label>
                <input type="text" class="form-input" id="add_title" required placeholder="タスクのタイトルを入力">
            </div>
            <div class="form-group">
                <label>説明</label>
                <textarea class="form-input" id="add_description" rows="3" placeholder="詳細な説明（任意）"></textarea>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label>担当者</label>
                    <select class="form-input" id="add_assignee">
                        <option value="">未割り当て</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= htmlspecialchars($emp['id']) ?>" data-name="<?= htmlspecialchars($emp['name']) ?>">
                            <?= htmlspecialchars($emp['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>期限</label>
                    <input type="date" class="form-input" id="add_due_date">
                </div>
            </div>
            <div class="form-group">
                <label>優先度</label>
                <div class="priority-select">
                    <div class="priority-option" data-priority="low" onclick="selectPriority(this, 'add')">低</div>
                    <div class="priority-option selected" data-priority="medium" onclick="selectPriority(this, 'add')">中</div>
                    <div class="priority-option" data-priority="high" onclick="selectPriority(this, 'add')">高</div>
                    <div class="priority-option" data-priority="urgent" onclick="selectPriority(this, 'add')">緊急</div>
                </div>
                <input type="hidden" id="add_priority" value="medium">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addTaskModal')">キャンセル</button>
            <button type="button" class="btn btn-primary" onclick="createTask()">追加</button>
        </div>
    </div>
</div>

<!-- タスク編集モーダル -->
<div id="editTaskModal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3>タスク編集</h3>
            <span class="close" onclick="closeModal('editTaskModal')">&times;</span>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit_id">
            <div class="form-group">
                <label>タイトル <span style="color:#dc2626;">*</span></label>
                <input type="text" class="form-input" id="edit_title" required>
            </div>
            <div class="form-group">
                <label>説明</label>
                <textarea class="form-input" id="edit_description" rows="3"></textarea>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label>担当者</label>
                    <select class="form-input" id="edit_assignee">
                        <option value="">未割り当て</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= htmlspecialchars($emp['id']) ?>" data-name="<?= htmlspecialchars($emp['name']) ?>">
                            <?= htmlspecialchars($emp['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>期限</label>
                    <input type="date" class="form-input" id="edit_due_date">
                </div>
            </div>
            <input type="hidden" id="edit_status" value="todo">
            <div class="form-group">
                <label>優先度</label>
                <div class="priority-select">
                    <div class="priority-option" data-priority="low" onclick="selectPriority(this, 'edit')">低</div>
                    <div class="priority-option" data-priority="medium" onclick="selectPriority(this, 'edit')">中</div>
                    <div class="priority-option" data-priority="high" onclick="selectPriority(this, 'edit')">高</div>
                    <div class="priority-option" data-priority="urgent" onclick="selectPriority(this, 'edit')">緊急</div>
                </div>
                <input type="hidden" id="edit_priority" value="medium">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-danger" onclick="deleteTask()" style="margin-right:auto;">削除</button>
            <button type="button" class="btn btn-secondary" onclick="closeModal('editTaskModal')">キャンセル</button>
            <button type="button" class="btn btn-warning" id="btn_reopen" onclick="reopenTask()" style="display:none;">未完了に戻す</button>
            <button type="button" class="btn btn-success" id="btn_complete" onclick="completeTask()">完了</button>
            <button type="button" class="btn btn-primary" onclick="updateTask()">更新</button>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= generateCsrfToken() ?>';
const currentUserId = '<?= $_SESSION['user_id'] ?? '' ?>';

// SortableJS 初期化
document.querySelectorAll('.kanban-tasks').forEach(column => {
    new Sortable(column, {
        group: 'kanban',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onEnd: function(evt) {
            const taskId = evt.item.dataset.id;
            const newStatus = evt.to.closest('.kanban-column').dataset.status;
            const newOrder = evt.newIndex;

            // API呼び出し
            fetch('/api/tasks-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    action: 'move',
                    task_id: taskId,
                    new_status: newStatus,
                    new_order: newOrder
                })
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    alert('移動に失敗しました: ' + data.message);
                    location.reload();
                }
                updateTaskCounts();
            })
            .catch(err => {
                console.error('Error:', err);
                location.reload();
            });
        }
    });
});

// タスク数更新
function updateTaskCounts() {
    const column = document.getElementById('column-todo');
    if (column) {
        const count = column.querySelectorAll('.task-card').length;
        document.getElementById('count-todo').textContent = count;
    }
}

// モーダル操作
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function openAddTaskModal(status) {
    document.getElementById('add_status').value = status || 'todo';
    document.getElementById('add_title').value = '';
    document.getElementById('add_description').value = '';
    document.getElementById('add_assignee').value = '';
    document.getElementById('add_due_date').value = '';
    document.getElementById('add_priority').value = 'medium';
    // 優先度選択リセット
    document.querySelectorAll('#addTaskModal .priority-option').forEach(opt => {
        opt.classList.toggle('selected', opt.dataset.priority === 'medium');
    });
    openModal('addTaskModal');
}

function openEditTaskModal(task) {
    document.getElementById('edit_id').value = task.id;
    document.getElementById('edit_title').value = task.title || '';
    document.getElementById('edit_description').value = task.description || '';
    document.getElementById('edit_assignee').value = task.assignee_id || '';
    document.getElementById('edit_due_date').value = task.due_date || '';
    document.getElementById('edit_status').value = task.status || 'todo';
    document.getElementById('edit_priority').value = task.priority || 'medium';
    // 優先度選択
    document.querySelectorAll('#editTaskModal .priority-option').forEach(opt => {
        opt.classList.toggle('selected', opt.dataset.priority === (task.priority || 'medium'));
    });
    // 完了済みかどうかでボタンを切り替え
    const isDone = (task.status === 'done');
    document.getElementById('btn_complete').style.display = isDone ? 'none' : '';
    document.getElementById('btn_reopen').style.display = isDone ? '' : 'none';
    openModal('editTaskModal');
}

function selectPriority(el, prefix) {
    el.parentElement.querySelectorAll('.priority-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    el.classList.add('selected');
    document.getElementById(prefix + '_priority').value = el.dataset.priority;
}

// タスク作成
function createTask() {
    const title = document.getElementById('add_title').value.trim();
    if (!title) {
        alert('タイトルを入力してください');
        return;
    }

    const assigneeSelect = document.getElementById('add_assignee');
    const assigneeId = assigneeSelect.value;
    const assigneeName = assigneeSelect.selectedOptions[0]?.dataset?.name || '';

    const data = {
        action: 'create',
        title: title,
        description: document.getElementById('add_description').value.trim(),
        status: document.getElementById('add_status').value,
        priority: document.getElementById('add_priority').value,
        assignee_id: assigneeId,
        assignee_name: assigneeName,
        due_date: document.getElementById('add_due_date').value
    };

    fetch('/api/tasks-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            location.reload();
        } else {
            alert('エラー: ' + result.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('通信エラーが発生しました');
    });
}

// タスク更新
function updateTask() {
    const taskId = document.getElementById('edit_id').value;
    if (!taskId) return;

    const title = document.getElementById('edit_title').value.trim();
    if (!title) {
        alert('タイトルを入力してください');
        return;
    }

    const assigneeSelect = document.getElementById('edit_assignee');
    const assigneeId = assigneeSelect.value;
    const assigneeName = assigneeSelect.selectedOptions[0]?.dataset?.name || '';

    const data = {
        action: 'update',
        id: taskId,
        title: title,
        description: document.getElementById('edit_description').value.trim(),
        status: document.getElementById('edit_status').value,
        priority: document.getElementById('edit_priority').value,
        assignee_id: assigneeId,
        assignee_name: assigneeName,
        due_date: document.getElementById('edit_due_date').value
    };

    fetch('/api/tasks-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            location.reload();
        } else {
            alert('エラー: ' + result.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('通信エラーが発生しました');
    });
}

// タスク削除
function deleteTask() {
    const taskId = document.getElementById('edit_id').value;
    if (!taskId) return;

    if (!confirm('このタスクを削除しますか？')) return;

    fetch('/api/tasks-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            action: 'delete',
            id: taskId
        })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            location.reload();
        } else {
            alert('エラー: ' + result.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('通信エラーが発生しました');
    });
}

// タスク完了
function completeTask() {
    const taskId = document.getElementById('edit_id').value;
    if (!taskId) return;

    fetch('/api/tasks-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            action: 'update',
            id: taskId,
            status: 'done'
        })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            location.reload();
        } else {
            alert('エラー: ' + result.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('通信エラーが発生しました');
    });
}

// タスクを未完了に戻す
function reopenTask() {
    const taskId = document.getElementById('edit_id').value;
    if (!taskId) return;

    fetch('/api/tasks-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            action: 'update',
            id: taskId,
            status: 'todo'
        })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            location.reload();
        } else {
            alert('エラー: ' + result.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('通信エラーが発生しました');
    });
}

// モーダル外クリックで閉じる
window.onclick = function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
}
</script>

<?php require_once '../functions/footer.php'; ?>
