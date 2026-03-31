<?php
require_once '../api/auth.php';
require_once '../functions/header.php';

$data        = getData();
$currentUser = $_SESSION['user_email'];
$userName    = $_SESSION['user_name'] ?? $currentUser;

$allReports = filterDeleted($data['weekly_reports'] ?? []);

$myReports = array_values(array_filter($allReports, fn($r) => ($r['user_email'] ?? '') === $currentUser));
usort($myReports, fn($a, $b) => strcmp($b['week_start'] ?? '', $a['week_start'] ?? ''));

$viewReports = isAdmin() ? $allReports : $myReports;
usort($viewReports, fn($a, $b) => strcmp($b['week_start'] ?? '', $a['week_start'] ?? ''));

// 今週
$today      = date('Y-m-d');
$dayOfWeek  = (int)date('N');
$thisMonday = date('Y-m-d', strtotime('-' . ($dayOfWeek - 1) . ' days'));
$thisFriday = date('Y-m-d', strtotime($thisMonday . ' +4 days'));

// 今週の自分の週報
$myThisWeek = null;
foreach ($myReports as $r) {
    if (($r['week_start'] ?? '') === $thisMonday) { $myThisWeek = $r; break; }
}

// 前週の今期の役割（引き継ぎ用）
$prevRoleContent = '';
foreach ($myReports as $r) {
    if (($r['week_start'] ?? '') < $thisMonday && !empty($r['sec_role'])) {
        $prevRoleContent = $r['sec_role'];
        break;
    }
}

// 社員リスト（秘匿メッセージ送信先）
$employees = filterDeleted($data['employees'] ?? []);
$employees = array_values(array_filter($employees, function($e) use ($currentUser) {
    $email = $e['email'] ?? '';
    if (function_exists('decryptValue') && str_starts_with($email, 'enc:')) {
        try {
            require_once __DIR__ . '/../functions/encryption.php';
            $email = decryptValue($email);
        } catch (Exception $e2) { return false; }
    }
    return !empty($email) && $email !== $currentUser;
}));

// 社員メールを復号して取得するヘルパー
function getEmpEmail($emp) {
    $email = $emp['email'] ?? '';
    if (str_starts_with($email, 'enc:')) {
        try {
            require_once __DIR__ . '/../functions/encryption.php';
            return decryptValue($email);
        } catch (Exception $e) { return ''; }
    }
    return $email;
}

// 各セクションの初期値
$sectionDefaults = [
    'sec_role'        => $myThisWeek['sec_role'] ?? $prevRoleContent,
    'sec_report'      => $myThisWeek['sec_report'] ?? '',
    'sec_issues'      => $myThisWeek['sec_issues'] ?? '',
    'sec_next_goals'  => $myThisWeek['sec_next_goals'] ?? '',
    'sec_second_area' => $myThisWeek['sec_second_area'] ?? '',
    'sec_misc'        => $myThisWeek['sec_misc'] ?? '',
];
$privateMessage    = $myThisWeek['private_message'] ?? '';
$privateRecipients = $myThisWeek['private_recipients'] ?? [];

$sections = [
    ['key' => 'sec_role',        'label' => '今期の役割'],
    ['key' => 'sec_report',      'label' => '今週の報告'],
    ['key' => 'sec_issues',      'label' => '現在抱えている課題'],
    ['key' => 'sec_next_goals',  'label' => '次週目標・計画'],
    ['key' => 'sec_second_area', 'label' => 'いま思いつく第二領域活動'],
    ['key' => 'sec_misc',        'label' => '報告・連絡・相談事項'],
];

$statusLabels = ['draft' => '下書き', 'submitted' => '提出済み'];
$statusColors = [
    'draft'     => ['bg' => '#fff3e0', 'text' => '#e65100'],
    'submitted' => ['bg' => '#e8f5e9', 'text' => '#2e7d32'],
];

// 提出済みかどうか（ロック判定）
$isSubmitted = ($myThisWeek['status'] ?? '') === 'submitted';
?>
<style<?= nonceAttr() ?>>
/* ── ツールバー ── */
.report-editor-wrap {
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    overflow: hidden;
}
.report-toolbar {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid var(--gray-200);
    background: var(--gray-50);
    flex-wrap: wrap;
}
.report-toolbar select {
    padding: 0.25rem 0.5rem;
    border: 1px solid var(--gray-300);
    border-radius: 4px;
    font-size: 0.8rem;
    background: white;
    cursor: pointer;
}
.toolbar-btn {
    width: 28px;
    height: 28px;
    border: 1px solid transparent;
    border-radius: 4px;
    background: none;
    cursor: pointer;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.1s;
    color: var(--gray-700);
}
.toolbar-btn:hover { background: var(--gray-200); border-color: var(--gray-300); }
.toolbar-btn.active { background: var(--primary-light, #e0e7ff); border-color: var(--primary); color: var(--primary); }
.toolbar-divider { width: 1px; height: 20px; background: var(--gray-300); margin: 0 0.25rem; }

/* ── セクション ── */
.report-section { border-bottom: 1px solid var(--gray-100); }
.report-section:last-child { border-bottom: none; }
.section-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem 0.5rem;
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--gray-800);
    user-select: none;
    pointer-events: none;
}
.section-header::before {
    content: '■';
    color: var(--primary);
    font-size: 0.7rem;
}
.section-editor {
    min-height: 80px;
    padding: 0.5rem 1rem 0.75rem 1.5rem;
    outline: none;
    font-size: 0.9rem;
    line-height: 1.7;
    color: var(--gray-800);
}
.section-editor:focus {
    background: #fafbff;
}
.section-editor p { margin: 0 0 0.25rem; }
.section-editor ul, .section-editor ol { margin: 0 0 0.25rem; padding-left: 1.5rem; }
.section-editor h2 { font-size: 1rem; margin: 0.5rem 0 0.25rem; }
.section-editor h3 { font-size: 0.9rem; margin: 0.4rem 0 0.2rem; }
.section-editor:empty::before {
    content: attr(data-placeholder);
    color: var(--gray-400);
    pointer-events: none;
}
.section-editor img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    border: 1px solid var(--gray-200);
    margin: 0.25rem 0;
    display: block;
    cursor: pointer;
}
.img-uploading {
    opacity: 0.5;
    outline: 2px dashed var(--primary);
}
/* ── 秘匿メッセージ ── */
.private-msg-card {
    background: #fffbeb;
    border: 1px solid #fbbf24;
    border-radius: 10px;
    padding: 1.25rem;
    margin-top: 1.25rem;
}
.private-msg-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 700;
    color: #92400e;
    margin-bottom: 0.25rem;
}
.private-msg-subtitle {
    font-size: 0.8rem;
    color: #b45309;
    margin-bottom: 1rem;
}
.recipient-select-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}
.recipient-label { font-size: 0.82rem; color: #92400e; font-weight: 600; }
.recipient-all-btn {
    font-size: 0.78rem;
    background: none;
    border: none;
    color: #b45309;
    cursor: pointer;
    text-decoration: underline;
}
.recipient-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}
.recipient-pill {
    padding: 0.3rem 0.9rem;
    border: 1.5px solid #fbbf24;
    border-radius: 20px;
    background: white;
    cursor: pointer;
    font-size: 0.82rem;
    color: #92400e;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.recipient-pill:hover { background: #fef3c7; }
.recipient-pill.selected { background: #fef3c7; border-color: #d97706; font-weight: 600; }
.recipient-pill .pill-avatar {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #fbbf24;
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}
.private-msg-textarea {
    width: 100%;
    border: 1px solid #fbbf24;
    border-radius: 6px;
    padding: 0.625rem 0.75rem;
    font-size: 0.875rem;
    background: white;
    resize: vertical;
    min-height: 80px;
    outline: none;
    box-sizing: border-box;
}
.private-msg-textarea:focus { border-color: #d97706; box-shadow: 0 0 0 2px rgba(251,191,36,0.2); }
/* ── アクションボタン ── */
.report-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1.25rem;
}
/* ── 過去の週報 ── */
.past-report-card {
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 1rem;
}
.past-report-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.875rem 1.25rem;
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    cursor: pointer;
    user-select: none;
}
.past-report-header:hover { background: var(--gray-100); }
.past-report-body { display: none; padding: 1.25rem; }
.past-report-body.open { display: block; }
.past-section-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0.75rem 0 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.past-section-title::before { content: '■'; color: var(--primary); font-size: 0.6rem; }
.past-section-content {
    font-size: 0.875rem;
    line-height: 1.6;
    color: var(--gray-700);
}
.past-section-content p { margin: 0 0 0.25rem; }
.past-section-content ul, .past-section-content ol { margin: 0; padding-left: 1.5rem; }
.private-msg-view {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: #fffbeb;
    border: 1px solid #fbbf24;
    border-radius: 6px;
    font-size: 0.85rem;
}
.status-pill {
    display: inline-block;
    padding: 0.15rem 0.6rem;
    border-radius: 10px;
    font-size: 0.72rem;
    font-weight: 600;
}
/* ── 提出済みロック ── */
.submitted-lock {
    background: var(--gray-50);
    color: var(--gray-600);
    cursor: default;
}
.submitted-lock:focus { background: var(--gray-50); }
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
        <h2>週報</h2>
        <?php if ($myThisWeek && ($myThisWeek['status'] ?? '') === 'submitted'): ?>
        <span class="status-pill" style="background:#e8f5e9;color:#2e7d32">今週は提出済み</span>
        <?php endif; ?>
    </div>

    <!-- 今週の入力フォーム -->
    <div class="card mb-2">
        <div class="card-body">
            <div class="d-flex justify-between align-center mb-05">
                <div>
                    <strong>週報内容</strong>
                    <p class="text-13 text-gray-500 m-0">各セクションに今週の活動内容を記入してください</p>
                </div>
                <span class="text-gray-400 text-13"><?= htmlspecialchars($thisMonday) ?> 〜 <?= htmlspecialchars($thisFriday) ?></span>
            </div>

            <?php if ($isSubmitted): ?>
            <div class="alert alert-success" style="margin-bottom:1rem;">
                ✅ 今週の週報は提出済みです。内容を変更する場合は管理者にご連絡ください。
            </div>
            <?php endif; ?>

            <!-- ツールバー + エディター -->
            <div class="report-editor-wrap">
                <!-- 共有ツールバー（提出済みは非表示） -->
                <div class="report-toolbar" id="reportToolbar" <?= $isSubmitted ? 'style="display:none"' : '' ?>>
                    <select id="toolbarFormat" title="見出し">
                        <option value="">標準</option>
                        <option value="h2">見出し 2</option>
                        <option value="h3">見出し 3</option>
                    </select>
                    <div class="toolbar-divider"></div>
                    <button type="button" class="toolbar-btn" data-cmd="bold" title="太字"><b>B</b></button>
                    <button type="button" class="toolbar-btn" data-cmd="underline" title="下線"><u>U</u></button>
                    <button type="button" class="toolbar-btn" data-cmd="strikeThrough" title="取り消し線"><s>S</s></button>
                    <button type="button" class="toolbar-btn" data-cmd="italic" title="斜体"><em>I</em></button>
                    <div class="toolbar-divider"></div>
                    <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList" title="箇条書き">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1" fill="currentColor"/><circle cx="4" cy="12" r="1" fill="currentColor"/><circle cx="4" cy="18" r="1" fill="currentColor"/></svg>
                    </button>
                    <button type="button" class="toolbar-btn" data-cmd="insertOrderedList" title="番号付きリスト">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
                    </button>
                    <div class="toolbar-divider"></div>
                    <button type="button" class="toolbar-btn" data-cmd="removeFormat" title="書式クリア">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/><line x1="3" y1="3" x2="21" y2="21" stroke="currentColor" stroke-width="1.5"/></svg>
                    </button>
                </div>

                <!-- セクション -->
                <?php foreach ($sections as $sec): ?>
                <div class="report-section">
                    <div class="section-header"><?= htmlspecialchars($sec['label']) ?></div>
                    <div class="section-editor <?= $isSubmitted ? 'submitted-lock' : '' ?>"
                         contenteditable="<?= $isSubmitted ? 'false' : 'true' ?>"
                         id="editor-<?= htmlspecialchars($sec['key']) ?>"
                         data-key="<?= htmlspecialchars($sec['key']) ?>"
                         data-placeholder="<?= htmlspecialchars($sec['label']) ?>を入力..."
                    ><?php
                        $val = $sectionDefaults[$sec['key']] ?? '';
                        echo $val ?: '';
                    ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- 秘匿メッセージ -->
            <div class="private-msg-card" <?= $isSubmitted ? 'style="opacity:0.7;pointer-events:none;"' : '' ?>>
                <div class="private-msg-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    秘匿メッセージ
                </div>
                <div class="private-msg-subtitle">選択した送信先と投稿者のみが閲覧できます。空欄の場合は送信されません。</div>

                <div class="recipient-select-row">
                    <span class="recipient-label">送信先を選択</span>
                    <button type="button" class="recipient-all-btn" id="btnSelectAllRecipients">全選択</button>
                </div>

                <div class="recipient-pills" id="recipientPills">
                    <?php foreach ($employees as $emp): ?>
                    <?php
                        $empEmail = getEmpEmail($emp);
                        if (empty($empEmail)) continue;
                        $isSelected = in_array($empEmail, $privateRecipients);
                        $dept = $emp['department'] ?? $emp['role'] ?? '';
                        $initial = mb_substr($emp['name'] ?? '?', 0, 1);
                    ?>
                    <button type="button"
                        class="recipient-pill <?= $isSelected ? 'selected' : '' ?>"
                        data-email="<?= htmlspecialchars($empEmail) ?>"
                        data-name="<?= htmlspecialchars($emp['name'] ?? '') ?>">
                        <span class="pill-avatar"><?= htmlspecialchars($initial) ?></span>
                        <?= htmlspecialchars($emp['name'] ?? '') ?><?= $dept ? '（' . htmlspecialchars($dept) . '）' : '' ?>
                    </button>
                    <?php endforeach; ?>
                    <?php if (empty($employees)): ?>
                    <span class="text-gray-400 text-13">送信先となる社員が登録されていません</span>
                    <?php endif; ?>
                </div>

                <textarea
                    class="private-msg-textarea"
                    id="privateMessage"
                    rows="3"
                    placeholder="上司にのみ伝えたいことがあれば入力..."><?= htmlspecialchars($privateMessage) ?></textarea>
            </div>

            <!-- アクションボタン（提出済みは非表示） -->
            <?php if (!$isSubmitted): ?>
            <div class="report-actions">
                <button type="button" class="btn btn-outline" id="btnSaveDraft">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    下書き保存
                </button>
                <button type="button" class="btn btn-primary" id="btnSubmit">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    提出する
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 過去の週報 -->
    <?php
    $pastReports = array_values(array_filter($viewReports, function($r) use ($thisMonday, $currentUser) {
        // 今週の自分の週報は除外（フォームに表示済み）
        return !(($r['user_email'] ?? '') === $currentUser && ($r['week_start'] ?? '') === $thisMonday);
    }));
    ?>
    <?php if (!empty($pastReports)): ?>
    <h3 class="mb-1 text-16">過去の週報<?= isAdmin() ? '（全員）' : '' ?></h3>
    <?php foreach ($pastReports as $report): ?>
    <?php
        $st     = $report['status'] ?? 'draft';
        $color  = $statusColors[$st] ?? $statusColors['draft'];
        $label  = $statusLabels[$st] ?? '下書き';
        $wStart = $report['week_start'] ?? '';
        $wEnd   = $report['week_end'] ?? date('Y-m-d', strtotime($wStart . ' +4 days'));

        // 秘匿メッセージ閲覧権限
        $canSeePrivate = isAdmin()
            || ($report['user_email'] ?? '') === $currentUser
            || in_array($currentUser, $report['private_recipients'] ?? []);
    ?>
    <div class="past-report-card">
        <div class="past-report-header" data-toggle="report-<?= htmlspecialchars($report['id']) ?>">
            <div class="d-flex align-center gap-1">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                <strong><?= htmlspecialchars($wStart . ' 〜 ' . $wEnd) ?></strong>
                <?php if (isAdmin()): ?>
                <span class="text-gray-500 text-13"><?= htmlspecialchars($report['user_name'] ?? $report['user_email'] ?? '') ?></span>
                <?php endif; ?>
            </div>
            <div class="d-flex align-center gap-1">
                <span class="status-pill" style="background:<?= $color['bg'] ?>;color:<?= $color['text'] ?>">
                    <?= htmlspecialchars($label) ?>
                </span>
                <?php if (canDelete()): ?>
                <button class="btn btn-sm btn-danger btn-delete-report"
                    data-id="<?= htmlspecialchars($report['id']) ?>"
                    data-week="<?= htmlspecialchars($wStart) ?>"
                    onclick="event.stopPropagation()">
                    削除
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="past-report-body" id="report-<?= htmlspecialchars($report['id']) ?>">
            <?php foreach ($sections as $sec): ?>
            <?php $content = $report[$sec['key']] ?? ''; ?>
            <?php if (!empty($content)): ?>
            <div class="past-section-title"><?= htmlspecialchars($sec['label']) ?></div>
            <div class="past-section-content"><?= $content // HTML as-is (already sanitized on save) ?></div>
            <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($canSeePrivate && !empty($report['private_message'])): ?>
            <div class="private-msg-view">
                <div class="d-flex align-center gap-05 mb-05" style="color:#92400e;font-weight:700;font-size:0.82rem">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    秘匿メッセージ
                    <?php if (!empty($report['private_recipients'])): ?>
                    <span class="text-gray-400 text-11">（送信先: <?= htmlspecialchars(implode(', ', $report['private_recipients'])) ?>）</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:0.875rem;color:#92400e"><?= nl2br(htmlspecialchars($report['private_message'])) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($report['submitted_at'])): ?>
            <div class="text-gray-400 text-12 mt-075">提出日時: <?= htmlspecialchars($report['submitted_at']) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script<?= nonceAttr() ?>>
(function() {
    var csrfToken   = '<?= generateCsrfToken() ?>';
    var weekStart   = '<?= htmlspecialchars($thisMonday) ?>';
    var lastFocused = null;

    // ── エディターフォーカス管理 ───────────────────────────────────────
    document.querySelectorAll('.section-editor').forEach(function(el) {
        el.addEventListener('focus', function() {
            lastFocused = el;
        });
        // プレースホルダー制御
        el.addEventListener('input', function() {
            if (this.innerHTML === '<br>') this.innerHTML = '';
        });

        // ── 画像ペースト ──────────────────────────────────────────────
        el.addEventListener('paste', function(e) {
            var clipData = e.clipboardData;
            if (!clipData || !clipData.items) return;
            var imageFile = null;
            for (var i = 0; i < clipData.items.length; i++) {
                if (clipData.items[i].type.startsWith('image/')) {
                    imageFile = clipData.items[i].getAsFile();
                    break;
                }
            }
            if (!imageFile) return;
            e.preventDefault();
            uploadEditorImage(imageFile, el);
        });
    });

    // ── 画像アップロード処理 ──────────────────────────────────────────
    function uploadEditorImage(file, targetEditor) {
        var blobUrl = URL.createObjectURL(file);

        // insertImage はcontenteditable用の専用コマンドで最も安定
        targetEditor.focus();
        document.execCommand('insertImage', false, blobUrl);

        // 挿入したimgをblobUrlで特定（img.srcは絶対URLで返る）
        var placeholder = null;
        targetEditor.querySelectorAll('img').forEach(function(img) {
            if (img.src === blobUrl) { placeholder = img; }
        });
        if (placeholder) placeholder.classList.add('img-uploading');

        // サーバーにアップロード
        var fd = new FormData();
        fd.append('image', file);
        fd.append('csrf_token', csrfToken);

        fetch('/api/upload-weekly-image.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    if (placeholder) {
                        placeholder.src = res.data.url;
                        placeholder.classList.remove('img-uploading');
                    }
                } else {
                    if (placeholder) placeholder.remove();
                    showAlert('画像のアップロードに失敗しました: ' + (res.message || ''), 'danger');
                }
                URL.revokeObjectURL(blobUrl);
            })
            .catch(function() {
                if (placeholder) placeholder.remove();
                URL.revokeObjectURL(blobUrl);
                showAlert('画像のアップロードに失敗しました', 'danger');
            });
    }

    // ── ツールバー ───────────────────────────────────────────────────
    document.querySelectorAll('.toolbar-btn').forEach(function(btn) {
        btn.addEventListener('mousedown', function(e) {
            e.preventDefault(); // フォーカスを外さない
            var cmd = this.getAttribute('data-cmd');
            if (lastFocused) lastFocused.focus();
            document.execCommand(cmd, false, null);
            updateToolbarState();
        });
    });

    document.getElementById('toolbarFormat').addEventListener('change', function() {
        if (lastFocused) lastFocused.focus();
        var val = this.value;
        document.execCommand('formatBlock', false, val || 'p');
        this.value = '';
    });

    function updateToolbarState() {
        ['bold', 'underline', 'strikeThrough', 'italic'].forEach(function(cmd) {
            var key = cmd;
            var btn = document.querySelector('[data-cmd="' + key + '"]');
            if (btn) btn.classList.toggle('active', document.queryCommandState(cmd));
        });
    }
    document.addEventListener('selectionchange', updateToolbarState);

    // ── セクション内容を収集 ──────────────────────────────────────────
    function collectSections() {
        var result = {};
        document.querySelectorAll('.section-editor').forEach(function(el) {
            result[el.getAttribute('data-key')] = el.innerHTML.trim();
        });
        return result;
    }

    // ── 送信先（秘匿メッセージ） ─────────────────────────────────────
    var selectedRecipients = <?= json_encode($privateRecipients) ?>;

    document.querySelectorAll('.recipient-pill').forEach(function(pill) {
        pill.addEventListener('click', function() {
            var email = this.getAttribute('data-email');
            var idx   = selectedRecipients.indexOf(email);
            if (idx === -1) {
                selectedRecipients.push(email);
                this.classList.add('selected');
            } else {
                selectedRecipients.splice(idx, 1);
                this.classList.remove('selected');
            }
        });
    });

    document.getElementById('btnSelectAllRecipients').addEventListener('click', function() {
        var pills = document.querySelectorAll('.recipient-pill');
        var allSelected = selectedRecipients.length === pills.length;
        selectedRecipients = [];
        pills.forEach(function(p) {
            if (!allSelected) {
                selectedRecipients.push(p.getAttribute('data-email'));
                p.classList.add('selected');
            } else {
                p.classList.remove('selected');
            }
        });
    });

    // ── 送信共通処理 ─────────────────────────────────────────────────
    function postReport(action, callback) {
        var sections = collectSections();
        var fd = new FormData();
        fd.append('action', action);
        fd.append('week_start', weekStart);
        fd.append('private_message', document.getElementById('privateMessage').value.trim());
        fd.append('private_recipients', JSON.stringify(selectedRecipients));
        fd.append('csrf_token', csrfToken);
        Object.keys(sections).forEach(function(k) { fd.append(k, sections[k]); });

        fetch('/api/weekly-reports.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) { if (callback) callback(res); }
                else showAlert(res.message || 'エラーが発生しました', 'error');
            })
            .catch(function() { showAlert('通信エラーが発生しました', 'error'); });
    }

    document.getElementById('btnSaveDraft').addEventListener('click', function() {
        postReport('save', function() { showAlert('下書きを保存しました', 'success'); });
    });

    document.getElementById('btnSubmit').addEventListener('click', function() {
        var sections = collectSections();
        var hasContent = Object.values(sections).some(function(v) {
            return v.replace(/<[^>]+>/g, '').trim().length > 0;
        });
        if (!hasContent) { showAlert('いずれかのセクションに内容を入力してください', 'error'); return; }
        if (!confirm('社長に提出しますか？')) return;
        postReport('submit', function() {
            showAlert('提出しました', 'success');
            setTimeout(function() { location.reload(); }, 700);
        });
    });

    // ── 過去の週報アコーディオン ─────────────────────────────────────
    document.querySelectorAll('.past-report-header').forEach(function(header) {
        header.addEventListener('click', function() {
            var target = this.getAttribute('data-toggle');
            var body = document.getElementById(target);
            if (!body) return;
            var isOpen = body.classList.toggle('open');
            var arrow = this.querySelector('svg');
            if (arrow) arrow.style.transform = isOpen ? 'rotate(90deg)' : '';
        });
    });

    // ── 削除 ─────────────────────────────────────────────────────────
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-delete-report');
        if (!btn) return;
        var id   = btn.getAttribute('data-id');
        var week = btn.getAttribute('data-week');
        if (!confirm('週報（' + week + '〜）を削除しますか？')) return;
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fd.append('csrf_token', csrfToken);
        fetch('/api/weekly-reports.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    btn.closest('.past-report-card').remove();
                    showAlert('削除しました', 'success');
                } else {
                    showAlert(res.message || 'エラーが発生しました', 'error');
                }
            });
    });
})();
</script>

<?php require_once '../functions/footer.php'; ?>
