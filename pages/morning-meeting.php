<?php
require_once '../api/auth.php';
require_once '../functions/header.php';

$data      = getData();
$employees = filterDeleted($data['employees'] ?? []);
$today     = date('Y-m-d');

// 朝礼が存在する日付
$allTodos = filterDeleted($data['morning_todos'] ?? []);
$dates = array_values(array_unique(array_filter(
    array_map(fn($t) => $t['meeting_date'] ?? '', $allTodos)
)));
rsort($dates);

// TODOが存在する最新日付をデフォルトに（なければ今日）
$defaultDate  = !empty($dates) ? $dates[0] : $today;
$selectedDate = $_GET['date'] ?? $defaultDate;
if (!in_array($selectedDate, $dates)) $selectedDate = $defaultDate;

$todosForDate = array_values(array_filter($allTodos, fn($t) => ($t['meeting_date'] ?? '') === $selectedDate));
usort($todosForDate, fn($a, $b) => strcmp($a['created_at'] ?? '', $b['created_at'] ?? ''));

$canEdit = canEdit();
?>

<style<?= nonceAttr() ?>>
/* 日付セレクター */
.meeting-date-selector {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}
.date-btn {
    padding: 0.4rem 1rem;
    border: 1px solid var(--gray-300);
    border-radius: 20px;
    background: white;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.15s;
}
.date-btn:hover { background: var(--gray-50); }
.date-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

/* 進捗バー */
.todo-progress {
    padding: 1rem 1.25rem 0.75rem;
    border-bottom: 1px solid var(--gray-100);
}
.todo-progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-bottom: 0.4rem;
}
.todo-progress-bar {
    height: 6px;
    background: var(--gray-100);
    border-radius: 3px;
    overflow: hidden;
}
.todo-progress-fill {
    height: 100%;
    background: var(--success);
    border-radius: 3px;
    transition: width 0.4s ease;
}

/* TODOアイテム */
.todo-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--gray-100);
    transition: background 0.1s;
}
.todo-item:hover { background: var(--gray-50); }
.todo-item:last-child { border-bottom: none; }
.todo-item.is-done { opacity: 0.6; }

/* チェックボタン */
.todo-check {
    width: 26px;
    height: 26px;
    border: 2px solid var(--gray-300);
    border-radius: 50%;
    cursor: pointer;
    flex-shrink: 0;
    margin-top: 1px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    background: white;
}
.todo-check:hover { border-color: var(--success); background: #f0faf7; }
.todo-check.done {
    background: var(--success);
    border-color: var(--success);
    color: white;
}

/* テキスト部 */
.todo-body { flex: 1; min-width: 0; }
.todo-title {
    font-weight: 500;
    font-size: 0.95rem;
    color: var(--gray-900);
    line-height: 1.4;
}
.todo-title.done {
    text-decoration: line-through;
    color: var(--gray-400);
}
.todo-desc {
    font-size: 0.82rem;
    color: var(--gray-500);
    margin-top: 0.25rem;
    line-height: 1.5;
}

/* タグ行 */
.todo-tags {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
}
.todo-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.15rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}
.todo-tag-assignee {
    background: #eef2ff;
    color: #4338ca;
}
.todo-tag-due {
    background: #fef3c7;
    color: #92400e;
}
.todo-tag-due.overdue {
    background: #fee2e2;
    color: #991b1b;
}

.todo-actions { display: flex; gap: 0.4rem; flex-shrink: 0; }

/* NotebookLMボタン */
.notebooklm-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1.25rem;
    background: #4285f4;
    color: white;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: background 0.15s;
}
.notebooklm-btn:hover { background: #3367d6; color: white; }

/* インポート */
.import-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: background 0.1s;
}
.import-item:hover { background: var(--gray-50); }
.import-item.selected { background: #eef2ff; border-color: var(--primary); }
.import-item input[type="checkbox"] { margin-top: 2px; flex-shrink: 0; }
.import-item-title { font-weight: 500; font-size: 0.9rem; }
.import-item-desc { font-size: 0.78rem; color: var(--gray-500); margin-top: 0.2rem; }
.gdoc-title {
    font-size: 0.8rem;
    color: var(--gray-500);
    padding: 0.5rem 0.75rem;
    background: var(--gray-50);
    border-radius: 6px;
    margin-bottom: 1rem;
}
.import-loading { text-align: center; padding: 2rem; color: var(--gray-400); }

/* モーダル */
.modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10001; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-content { background: white; border-radius: 12px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 8px 32px rgba(0,0,0,0.2); }
.modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; }
.modal-body { padding: 1.25rem 1.5rem; }
.modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: 0.75rem; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--gray-500); line-height: 1; }
</style>

<div class="page-container">
    <div class="page-header">
        <h2>朝礼TODO</h2>
        <div class="d-flex gap-1 align-center flex-wrap">
            <!-- NotebookLMリンク -->
            <a href="https://notebooklm.google.com/" target="_blank" rel="noopener" class="notebooklm-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                NotebookLMで確認
            </a>
            <?php if ($canEdit): ?>
            <button class="btn btn-outline" id="btnImportGdoc">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v8"/><path d="M8 6l4 4 4-4"/><rect x="2" y="14" width="20" height="8" rx="2"/></svg>
                Googleドキュメントから読み込む
            </button>
            <button class="btn btn-primary" id="btnAddTodo">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                TODO追加
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- 日付セレクター -->
    <div class="meeting-date-selector" id="dateBtns">
        <?php foreach (array_slice($dates, 0, 10) as $d): ?>
        <button class="date-btn <?= $d === $selectedDate ? 'active' : '' ?>"
            data-date="<?= htmlspecialchars($d) ?>">
            <?= htmlspecialchars($d) ?><?= $d === $today ? ' (今日)' : '' ?>
        </button>
        <?php endforeach; ?>
    </div>

    <?php
        $totalCount = count($todosForDate);
        $doneCount  = count(array_filter($todosForDate, fn($t) => ($t['status'] ?? 'open') === 'done'));
        $progressPct = $totalCount > 0 ? round($doneCount / $totalCount * 100) : 0;
        $today = date('Y-m-d');
    ?>
    <div class="card">
        <?php if ($totalCount > 0): ?>
        <div class="todo-progress">
            <div class="todo-progress-label">
                <span>進捗</span>
                <span><?= $doneCount ?> / <?= $totalCount ?> 完了</span>
            </div>
            <div class="todo-progress-bar">
                <div class="todo-progress-fill" style="width:<?= $progressPct ?>%"></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card-body p-0" id="todoListContainer">
            <?php if (empty($todosForDate)): ?>
            <div class="text-center p-3rem text-gray-400" id="emptyState">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mb-2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <p>この日のTODOはありません</p>
            </div>
            <?php else: ?>
            <?php foreach ($todosForDate as $todo): ?>
            <?php
                $isDone   = ($todo['status'] ?? 'open') === 'done';
                $dueDate  = $todo['due_date'] ?? '';
                $isOverdue = $dueDate && !$isDone && $dueDate < $today;
            ?>
            <div class="todo-item <?= $isDone ? 'is-done' : '' ?>" data-id="<?= htmlspecialchars($todo['id']) ?>">
                <div class="todo-check <?= $isDone ? 'done' : '' ?>"
                    data-action="toggle"
                    data-id="<?= htmlspecialchars($todo['id']) ?>"
                    title="完了/未完了を切り替え">
                    <?php if ($isDone): ?>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php endif; ?>
                </div>
                <div class="todo-body">
                    <div class="todo-title <?= $isDone ? 'done' : '' ?>">
                        <?= htmlspecialchars($todo['title']) ?>
                    </div>
                    <?php if (!empty($todo['description'])): ?>
                    <div class="todo-desc"><?= htmlspecialchars($todo['description']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($todo['assignee']) || $dueDate): ?>
                    <div class="todo-tags">
                        <?php if (!empty($todo['assignee'])): ?>
                        <span class="todo-tag todo-tag-assignee">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <?= htmlspecialchars($todo['assignee']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($dueDate): ?>
                        <span class="todo-tag todo-tag-due <?= $isOverdue ? 'overdue' : '' ?>">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <?= htmlspecialchars($dueDate) ?><?= $isOverdue ? ' 期限超過' : '' ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($canEdit): ?>
                <div class="todo-actions">
                    <button class="btn btn-sm btn-outline btn-edit-todo"
                        data-id="<?= htmlspecialchars($todo['id']) ?>"
                        data-date="<?= htmlspecialchars($todo['meeting_date'] ?? '') ?>"
                        data-title="<?= htmlspecialchars($todo['title']) ?>"
                        data-desc="<?= htmlspecialchars($todo['description'] ?? '') ?>"
                        data-assignee="<?= htmlspecialchars($todo['assignee'] ?? '') ?>"
                        data-assignee-email="<?= htmlspecialchars($todo['assignee_email'] ?? '') ?>"
                        data-due="<?= htmlspecialchars($todo['due_date'] ?? '') ?>">
                        編集
                    </button>
                    <?php if (canDelete()): ?>
                    <button class="btn btn-sm btn-danger btn-delete-todo"
                        data-id="<?= htmlspecialchars($todo['id']) ?>"
                        data-title="<?= htmlspecialchars($todo['title']) ?>">
                        削除
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Google Docsインポートモーダル -->
<?php if ($canEdit): ?>
<div class="modal" id="importModal">
    <div class="modal-content" style="max-width:600px">
        <div class="modal-header">
            <h3 class="modal-title">Googleドキュメントから読み込む</h3>
            <button class="modal-close" id="btnImportClose">&times;</button>
        </div>
        <div class="modal-body" id="importModalBody">
            <div id="importUrlSection">
                <div class="form-group mb-1">
                    <label class="form-label">GoogleドキュメントのURL</label>
                    <input type="text" class="form-input" id="gdocUrlInput"
                        placeholder="https://docs.google.com/document/d/...">
                    <div class="text-13 text-gray-500 mt-05">※ドキュメントは「リンクを知っている全員が閲覧可能」に設定してください</div>
                </div>
                <div class="modal-footer" style="padding: 0; border: none; margin-top: 0.5rem;">
                    <button class="btn btn-secondary" id="btnImportCancelUrl">キャンセル</button>
                    <button class="btn btn-primary" id="btnImportFetch">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v8"/><path d="M8 6l4 4 4-4"/><rect x="2" y="14" width="20" height="8" rx="2"/></svg>
                        読み込む
                    </button>
                </div>
            </div>
            <div id="importResultSection" style="display:none; max-height:55vh; overflow-y:auto"></div>
        </div>
        <div class="modal-footer" id="importModalFooter" style="display:none">
            <div class="text-gray-500 text-13 mr-auto" id="importSelectedCount">0件選択中</div>
            <button class="btn btn-secondary" id="btnImportCancel">キャンセル</button>
            <button class="btn btn-outline" id="btnImportSelectAll">全選択</button>
            <button class="btn btn-primary" id="btnImportConfirm">選択した項目を追加</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- TODO追加/編集モーダル -->
<?php if ($canEdit): ?>
<div class="modal" id="todoModal">
    <div class="modal-content" style="max-width:500px">
        <div class="modal-header">
            <h3 class="modal-title" id="todoModalTitle">TODO追加</h3>
            <button class="modal-close" id="btnTodoClose">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="todoId">
            <div class="form-group">
                <label class="form-label">朝礼日 <span class="text-danger">*</span></label>
                <input type="date" class="form-input" id="todoDate" value="<?= htmlspecialchars($selectedDate) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">タイトル <span class="text-danger">*</span></label>
                <input type="text" class="form-input" id="todoTitle" placeholder="例: 〇〇の資料を準備する">
            </div>
            <div class="form-group">
                <label class="form-label">詳細</label>
                <textarea class="form-input" id="todoDesc" rows="2" placeholder="補足説明（任意）"></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">担当者</label>
                    <select class="form-input" id="todoAssignee">
                        <option value="">選択してください</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= htmlspecialchars($emp['name'] ?? '') ?>"
                            data-email="<?= htmlspecialchars($emp['email'] ?? '') ?>">
                            <?= htmlspecialchars($emp['name'] ?? '') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" id="todoAssigneeEmail">
                </div>
                <div class="form-group">
                    <label class="form-label">期日</label>
                    <input type="date" class="form-input" id="todoDue">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="btnTodoCancel">キャンセル</button>
            <button class="btn btn-primary" id="btnTodoSave">保存</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script<?= nonceAttr() ?>>
(function() {
    var csrfToken    = '<?= generateCsrfToken() ?>';
    var currentDate  = '<?= htmlspecialchars($selectedDate) ?>';

    // 日付ボタン切り替え
    document.querySelectorAll('.date-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var d = this.getAttribute('data-date');
            location.href = 'morning-meeting.php?date=' + encodeURIComponent(d);
        });
    });

    // チェック切り替え
    document.addEventListener('click', function(e) {
        var el = e.target.closest('[data-action="toggle"]');
        if (!el) return;
        var id = el.getAttribute('data-id');
        var fd = new FormData();
        fd.append('action', 'toggle_status');
        fd.append('id', id);
        fd.append('csrf_token', csrfToken);
        fetch('/api/morning-meeting.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    var isDone = res.data.todo.status === 'done';
                    el.classList.toggle('done', isDone);
                    el.innerHTML = isDone
                        ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>'
                        : '';
                    var titleEl = el.closest('.todo-item').querySelector('.todo-title');
                    if (titleEl) titleEl.classList.toggle('done', isDone);
                } else {
                    showAlert(res.message || 'エラーが発生しました', 'error');
                }
            });
    });

    <?php if ($canEdit): ?>
    // ─── Google Docsインポート ─────────────────────────────────────────
    var importModal      = document.getElementById('importModal');
    var importBody       = document.getElementById('importModalBody');
    var importFooter     = document.getElementById('importModalFooter');
    var importCountEl    = document.getElementById('importSelectedCount');
    var importedTodos    = [];

    function updateImportCount() {
        var checked = importBody.querySelectorAll('input[type="checkbox"]:checked').length;
        importCountEl.textContent = checked + '件選択中';
    }

    var gdocUrlInput     = document.getElementById('gdocUrlInput');
    var importUrlSection = document.getElementById('importUrlSection');
    var importResultSection = document.getElementById('importResultSection');

    function resetImportModal() {
        importUrlSection.style.display = '';
        importResultSection.style.display = 'none';
        importResultSection.innerHTML = '';
        importFooter.style.display = 'none';
        if (gdocUrlInput) gdocUrlInput.value = '';
    }

    document.getElementById('btnImportGdoc').addEventListener('click', function() {
        resetImportModal();
        importModal.classList.add('active');
    });

    document.getElementById('btnImportFetch').addEventListener('click', function() {
        var url = gdocUrlInput.value.trim();
        if (!url) {
            showAlert('URLを入力してください', 'error');
            return;
        }
        // URLからドキュメントIDを抽出
        var match = url.match(/\/document\/d\/([a-zA-Z0-9_-]+)/);
        if (!match) {
            showAlert('GoogleドキュメントのURLを正しく入力してください', 'error');
            return;
        }
        var docId = match[1];

        importUrlSection.style.display = 'none';
        importResultSection.style.display = '';
        importResultSection.innerHTML = '<div class="import-loading"><div class="spinner w-24 h-24 mb-05 mx-auto"></div>ドキュメントを取得中...</div>';

        fetch('/api/fetch-google-doc.php?doc_id=' + encodeURIComponent(docId))
            .then(function(r) {
                return r.text().then(function(text) {
                    try {
                        return JSON.parse(text);
                    } catch(e) {
                        throw new Error('PARSE_ERROR:' + text.substring(0, 300));
                    }
                });
            })
            .then(function(res) {
                if (!res.success || !res.data.todos.length) {
                    var msg = res.error || res.message || '取得できる項目がありませんでした';
                    importResultSection.innerHTML = '<div class="text-center p-2rem text-gray-400">' + escapeHtml(msg) + '</div>';
                    return;
                }
                importedTodos = res.data.todos;
                var html = '';
                if (res.data.doc_title) {
                    html += '<div class="gdoc-title">📄 ' + escapeHtml(res.data.doc_title) + '</div>';
                }
                html += '<p class="text-13 text-gray-500 mb-1">追加したい項目にチェックを入れてください</p>';
                res.data.todos.forEach(function(todo, idx) {
                    html += '<label class="import-item" id="import-item-' + idx + '">'
                        + '<input type="checkbox" data-idx="' + idx + '" checked>'
                        + '<div>'
                        + '<div class="import-item-title">' + escapeHtml(todo.title) + '</div>'
                        + (todo.description ? '<div class="import-item-desc">' + escapeHtml(todo.description) + '</div>' : '')
                        + '</div>'
                        + '</label>';
                });
                importResultSection.innerHTML = html;
                importFooter.style.display = 'flex';
                updateImportCount();

                importResultSection.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                    cb.addEventListener('change', function() {
                        var item = document.getElementById('import-item-' + this.getAttribute('data-idx'));
                        if (item) item.classList.toggle('selected', this.checked);
                        updateImportCount();
                    });
                    var item = document.getElementById('import-item-' + cb.getAttribute('data-idx'));
                    if (item) item.classList.add('selected');
                });
            })
            .catch(function(err) {
                var detail = err && err.message && err.message.indexOf('PARSE_ERROR:') === 0
                    ? '<br><small style="word-break:break-all;font-size:0.72rem;color:#999">' + escapeHtml(err.message.replace('PARSE_ERROR:', '')) + '</small>'
                    : '';
                importResultSection.innerHTML = '<div class="p-2rem text-danger text-center">ドキュメントの取得に失敗しました' + detail + '</div>';
            });
    });

    // Enterキーで読み込む
    if (gdocUrlInput) {
        gdocUrlInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') document.getElementById('btnImportFetch').click();
        });
    }

    function closeImportModal() {
        importModal.classList.remove('active');
        resetImportModal();
    }
    document.getElementById('btnImportClose').addEventListener('click', closeImportModal);
    document.getElementById('btnImportCancel').addEventListener('click', closeImportModal);
    document.getElementById('btnImportCancelUrl').addEventListener('click', closeImportModal);
    importModal.addEventListener('click', function(e) { if (e.target === importModal) closeImportModal(); });

    document.getElementById('btnImportSelectAll').addEventListener('click', function() {
        var cbs = importBody.querySelectorAll('input[type="checkbox"]');
        var allChecked = Array.from(cbs).every(function(cb) { return cb.checked; });
        cbs.forEach(function(cb) {
            cb.checked = !allChecked;
            var item = document.getElementById('import-item-' + cb.getAttribute('data-idx'));
            if (item) item.classList.toggle('selected', cb.checked);
        });
        this.textContent = allChecked ? '全選択' : '全解除';
        updateImportCount();
    });

    document.getElementById('btnImportConfirm').addEventListener('click', function() {
        var selected = Array.from(importBody.querySelectorAll('input[type="checkbox"]:checked'))
            .map(function(cb) { return importedTodos[parseInt(cb.getAttribute('data-idx'))]; })
            .filter(Boolean);

        if (selected.length === 0) {
            showAlert('項目を1つ以上選択してください', 'error');
            return;
        }

        var confirmBtn = this;
        confirmBtn.disabled = true;
        confirmBtn.textContent = '登録中...';

        // 順番に登録
        var promises = selected.map(function(todo) {
            var fd = new FormData();
            fd.append('action', 'create');
            fd.append('meeting_date', currentDate);
            fd.append('title', todo.title);
            fd.append('description', todo.description || '');
            fd.append('csrf_token', csrfToken);
            return fetch('/api/morning-meeting.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); });
        });

        Promise.all(promises).then(function(results) {
            var succeeded = results.filter(function(r) { return r.success; }).length;
            closeImportModal();
            showAlert(succeeded + '件のTODOを追加しました', 'success');
            setTimeout(function() {
                location.href = 'morning-meeting.php?date=' + encodeURIComponent(currentDate);
            }, 600);
        }).catch(function() {
            showAlert('一部の登録に失敗しました', 'error');
            confirmBtn.disabled = false;
            confirmBtn.textContent = '選択した項目を追加';
        });
    });

    // ─── TODO追加/編集モーダル ──────────────────────────────────────────
    var modal       = document.getElementById('todoModal');
    var titleEl     = document.getElementById('todoModalTitle');
    var idEl        = document.getElementById('todoId');
    var dateEl      = document.getElementById('todoDate');
    var titleInput  = document.getElementById('todoTitle');
    var descEl      = document.getElementById('todoDesc');
    var assigneeEl  = document.getElementById('todoAssignee');
    var assigneeEmailEl = document.getElementById('todoAssigneeEmail');
    var dueEl       = document.getElementById('todoDue');

    function openModal(mode, data) {
        titleEl.textContent = mode === 'create' ? 'TODO追加' : 'TODO編集';
        idEl.value    = data.id || '';
        dateEl.value  = data.date || currentDate;
        titleInput.value = data.title || '';
        descEl.value  = data.desc || '';
        assigneeEl.value = data.assignee || '';
        assigneeEmailEl.value = data.assigneeEmail || '';
        dueEl.value   = data.due || '';
        modal.classList.add('active');
        titleInput.focus();
    }

    function closeModal() { modal.classList.remove('active'); }

    document.getElementById('btnAddTodo').addEventListener('click', function() {
        openModal('create', {});
    });
    document.getElementById('btnTodoClose').addEventListener('click', closeModal);
    document.getElementById('btnTodoCancel').addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });

    assigneeEl.addEventListener('change', function() {
        var opt = this.options[this.selectedIndex];
        assigneeEmailEl.value = opt ? (opt.getAttribute('data-email') || '') : '';
    });

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-edit-todo');
        if (!btn) return;
        openModal('edit', {
            id:       btn.getAttribute('data-id'),
            date:     btn.getAttribute('data-date'),
            title:    btn.getAttribute('data-title'),
            desc:     btn.getAttribute('data-desc'),
            assignee: btn.getAttribute('data-assignee'),
            assigneeEmail: btn.getAttribute('data-assignee-email'),
            due:      btn.getAttribute('data-due'),
        });
    });

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-delete-todo');
        if (!btn) return;
        var id    = btn.getAttribute('data-id');
        var title = btn.getAttribute('data-title');
        if (!confirm('「' + title + '」を削除しますか？')) return;
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fd.append('csrf_token', csrfToken);
        fetch('/api/morning-meeting.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    var item = document.querySelector('.todo-item[data-id="' + id + '"]');
                    if (item) item.remove();
                    showAlert('削除しました', 'success');
                } else {
                    showAlert(res.message || 'エラーが発生しました', 'error');
                }
            });
    });

    document.getElementById('btnTodoSave').addEventListener('click', function() {
        var title = titleInput.value.trim();
        if (!title) { showAlert('タイトルを入力してください', 'error'); return; }

        var id = idEl.value;
        var fd = new FormData();
        fd.append('action', id ? 'update' : 'create');
        if (id) fd.append('id', id);
        fd.append('meeting_date', dateEl.value);
        fd.append('title', title);
        fd.append('description', descEl.value.trim());
        fd.append('assignee', assigneeEl.value.trim());
        fd.append('assignee_email', assigneeEmailEl.value.trim());
        fd.append('due_date', dueEl.value);
        fd.append('csrf_token', csrfToken);

        fetch('/api/morning-meeting.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    showAlert(id ? '更新しました' : '追加しました', 'success');
                    closeModal();
                    var newDate = dateEl.value;
                    setTimeout(function() {
                        location.href = 'morning-meeting.php?date=' + encodeURIComponent(newDate);
                    }, 400);
                } else {
                    showAlert(res.message || 'エラーが発生しました', 'error');
                }
            });
    });
    <?php endif; ?>
})();
</script>

<?php require_once '../functions/footer.php'; ?>
