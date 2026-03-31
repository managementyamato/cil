<?php
require_once '../api/auth.php';
require_once '../functions/header.php';

$data  = getData();
$leads = filterDeleted($data['leads'] ?? []);
usort($leads, function($a, $b) {
    return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
});

$employees = filterDeleted($data['employees'] ?? []);

$statusList = ['未接触', '商談中', '受注', '失注'];
$statusColors = [
    '未接触' => ['bg' => '#e3f2fd', 'text' => '#1565c0'],
    '商談中' => ['bg' => '#fff3e0', 'text' => '#e65100'],
    '受注'   => ['bg' => '#e8f5e9', 'text' => '#2e7d32'],
    '失注'   => ['bg' => '#f5f5f5', 'text' => '#757575'],
];

// ステータス別カウント
$counts = ['all' => count($leads)];
foreach ($statusList as $s) {
    $counts[$s] = count(array_filter($leads, fn($l) => ($l['status'] ?? '未接触') === $s));
}
?>

<style<?= nonceAttr() ?>>
/* ── タブ ── */
.tab-bar {
    display: flex;
    gap: 0.25rem;
    background: var(--gray-100);
    border-radius: 8px;
    padding: 0.25rem;
}
.tab-btn {
    padding: 0.45rem 1rem;
    border: none;
    background: transparent;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    color: var(--gray-600);
    transition: all 0.15s;
    white-space: nowrap;
}
.tab-btn:hover { color: var(--gray-900); background: rgba(255,255,255,0.6); }
.tab-btn.active {
    background: white;
    color: var(--gray-900);
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}
/* ── 名刺OCR ── */
.ocr-area {
    margin-bottom: 1.25rem;
    border: 1.5px dashed var(--gray-300);
    border-radius: 10px;
    overflow: hidden;
}
.ocr-drop-zone {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 1.25rem;
    background: var(--gray-50);
    cursor: pointer;
    transition: background 0.15s;
}
.ocr-drop-zone:hover, .ocr-drop-zone.drag-over { background: #eef2ff; border-color: var(--primary); }
.ocr-drop-text { font-size: 0.8rem; color: var(--gray-500); text-align: center; margin: 0; line-height: 1.5; }
.ocr-preview-wrap { padding: 1rem; background: white; }
.ocr-preview-img {
    width: 100%;
    max-height: 160px;
    object-fit: contain;
    border-radius: 6px;
    border: 1px solid var(--gray-200);
    display: block;
    margin-bottom: 0.75rem;
}
.ocr-actions { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; }
.ocr-progress-bar {
    height: 6px;
    background: var(--gray-200);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 0.4rem;
}
.ocr-progress-fill {
    height: 100%;
    background: var(--primary);
    border-radius: 3px;
    width: 0%;
    transition: width 0.3s;
}
.ocr-progress-text { font-size: 0.78rem; color: var(--gray-500); margin: 0; text-align: center; }
/* ── ステータスバッジ ── */
.status-badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}
.lead-table td { vertical-align: middle; }
.tab-counts {
    display: inline-block;
    background: var(--gray-200);
    color: var(--gray-600);
    border-radius: 10px;
    font-size: 0.7rem;
    padding: 0.1rem 0.45rem;
    margin-left: 0.3rem;
    font-weight: 600;
}
.tab-btn.active .tab-counts {
    background: rgba(255,255,255,0.3);
    color: inherit;
}
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
        <h2>リード管理</h2>
        <?php if (canEdit()): ?>
        <button class="btn btn-primary" id="btnAddLead">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            新規登録
        </button>
        <?php endif; ?>
    </div>

    <!-- タブ -->
    <div class="tab-bar mb-2">
        <button class="tab-btn active" data-filter="all">
            すべて<span class="tab-counts"><?= $counts['all'] ?></span>
        </button>
        <?php foreach ($statusList as $s): ?>
        <button class="tab-btn" data-filter="<?= htmlspecialchars($s) ?>">
            <?= htmlspecialchars($s) ?><span class="tab-counts"><?= $counts[$s] ?></span>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table lead-table">
                <thead>
                    <tr>
                        <th>会社名</th>
                        <th>担当者</th>
                        <th>役職</th>
                        <th>電話</th>
                        <th>メール</th>
                        <th>ステータス</th>
                        <th>担当営業</th>
                        <th>登録日</th>
                        <?php if (canEdit()): ?><th style="width:80px"></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="leadsTableBody">
                <?php foreach ($leads as $lead): ?>
                <?php
                    $st    = $lead['status'] ?? '未接触';
                    $color = $statusColors[$st] ?? $statusColors['未接触'];
                ?>
                <tr data-id="<?= htmlspecialchars($lead['id']) ?>"
                    data-status="<?= htmlspecialchars($st) ?>">
                    <td><strong><?= htmlspecialchars($lead['company_name'] ?? '') ?></strong></td>
                    <td><?= htmlspecialchars($lead['person_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($lead['title'] ?? '') ?></td>
                    <td><?= htmlspecialchars($lead['phone'] ?? '') ?></td>
                    <td><?= htmlspecialchars($lead['email'] ?? '') ?></td>
                    <td>
                        <span class="status-badge" style="background:<?= $color['bg'] ?>;color:<?= $color['text'] ?>">
                            <?= htmlspecialchars($st) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($lead['sales_assignee'] ?? '') ?></td>
                    <td><?= htmlspecialchars(substr($lead['created_at'] ?? '', 0, 10)) ?></td>
                    <?php if (canEdit()): ?>
                    <td>
                        <button class="btn btn-sm btn-outline btn-edit-lead"
                            data-id="<?= htmlspecialchars($lead['id']) ?>"
                            data-company="<?= htmlspecialchars($lead['company_name'] ?? '') ?>"
                            data-person="<?= htmlspecialchars($lead['person_name'] ?? '') ?>"
                            data-title="<?= htmlspecialchars($lead['title'] ?? '') ?>"
                            data-phone="<?= htmlspecialchars($lead['phone'] ?? '') ?>"
                            data-email="<?= htmlspecialchars($lead['email'] ?? '') ?>"
                            data-memo="<?= htmlspecialchars($lead['memo'] ?? '') ?>"
                            data-status="<?= htmlspecialchars($st) ?>"
                            data-assignee="<?= htmlspecialchars($lead['sales_assignee'] ?? '') ?>"
                            data-assignee-email="<?= htmlspecialchars($lead['sales_email'] ?? '') ?>">
                            編集
                        </button>
                        <?php if (canDelete()): ?>
                        <button class="btn btn-sm btn-danger btn-delete-lead"
                            data-id="<?= htmlspecialchars($lead['id']) ?>"
                            data-name="<?= htmlspecialchars($lead['company_name'] ?? '') ?>">
                            削除
                        </button>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($leads)): ?>
                <tr id="noLeadsRow">
                    <td colspan="<?= canEdit() ? 9 : 8 ?>" class="text-center text-gray-400 p-2rem">
                        リードが登録されていません
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 新規登録/編集モーダル -->
<?php if (canEdit()): ?>
<div class="modal" id="leadModal">
    <div class="modal-content" style="max-width:560px">
        <div class="modal-header">
            <h3 class="modal-title" id="leadModalTitle">リード登録</h3>
            <button class="modal-close" id="btnLeadModalClose">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="leadId">

            <!-- 名刺OCRエリア -->
            <div class="ocr-area" id="ocrArea">
                <div class="ocr-drop-zone" id="ocrDropZone">
                    <input type="file" id="ocrFileInput" accept="image/*" capture="environment" style="display:none">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--gray-400)"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <p class="ocr-drop-text">名刺の画像をドロップ<br>またはクリックして選択</p>
                    <button type="button" class="btn btn-sm btn-outline" id="btnOcrSelect" style="margin-top:0.5rem">
                        📷 画像を選択
                    </button>
                </div>
                <!-- プレビュー＋読み取り -->
                <div class="ocr-preview-wrap" id="ocrPreviewWrap" style="display:none">
                    <img id="ocrPreview" class="ocr-preview-img" alt="名刺プレビュー">
                    <div class="ocr-actions">
                        <button type="button" class="btn btn-sm btn-primary" id="btnOcrRun">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            読み取る
                        </button>
                        <button type="button" class="btn btn-sm btn-outline" id="btnOcrClear">画像を変える</button>
                    </div>
                    <!-- 進捗 -->
                    <div id="ocrProgress" style="display:none">
                        <div class="ocr-progress-bar"><div class="ocr-progress-fill" id="ocrProgressFill"></div></div>
                        <p class="ocr-progress-text" id="ocrProgressText">準備中...</p>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">会社名 <span class="text-danger">*</span></label>
                <input type="text" class="form-input" id="leadCompanyName" placeholder="例: 株式会社〇〇">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">担当者名</label>
                    <input type="text" class="form-input" id="leadPersonName" placeholder="例: 山田 太郎">
                </div>
                <div class="form-group">
                    <label class="form-label">役職</label>
                    <input type="text" class="form-input" id="leadTitle" placeholder="例: 部長">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">電話番号</label>
                    <input type="text" class="form-input" id="leadPhone" placeholder="例: 03-0000-0000">
                </div>
                <div class="form-group">
                    <label class="form-label">メールアドレス</label>
                    <input type="email" class="form-input" id="leadEmail" placeholder="例: yamada@example.com">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">ステータス</label>
                <select class="form-input" id="leadStatus">
                    <?php foreach ($statusList as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">担当営業員</label>
                <select class="form-input" id="leadSalesAssignee">
                    <option value="">選択してください</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= htmlspecialchars($emp['name'] ?? '') ?>"
                        data-email="<?= htmlspecialchars($emp['email'] ?? '') ?>">
                        <?= htmlspecialchars($emp['name'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="leadSalesEmail">
            </div>
            <div class="form-group">
                <label class="form-label">メモ</label>
                <textarea class="form-input" id="leadMemo" rows="3" placeholder="備考・メモ"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="btnLeadCancel">キャンセル</button>
            <button class="btn btn-primary" id="btnLeadSave">保存</button>
        </div>
    </div>
</div>
<?php endif; ?>


<script<?= nonceAttr() ?>>
(function() {
    var csrfToken = '<?= generateCsrfToken() ?>';
    var statusColors = <?= json_encode($statusColors) ?>;

    // タブフィルター
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            var filter = this.getAttribute('data-filter');
            document.querySelectorAll('#leadsTableBody tr[data-id]').forEach(function(row) {
                row.style.display = (filter === 'all' || row.getAttribute('data-status') === filter) ? '' : 'none';
            });
        });
    });

    <?php if (canEdit()): ?>
    var modal    = document.getElementById('leadModal');
    var titleEl  = document.getElementById('leadModalTitle');
    var idEl     = document.getElementById('leadId');
    var companyEl = document.getElementById('leadCompanyName');
    var personEl  = document.getElementById('leadPersonName');
    var titleFld  = document.getElementById('leadTitle');
    var phoneEl   = document.getElementById('leadPhone');
    var emailEl   = document.getElementById('leadEmail');
    var statusEl  = document.getElementById('leadStatus');
    var assigneeEl = document.getElementById('leadSalesAssignee');
    var assigneeEmailEl = document.getElementById('leadSalesEmail');
    var memoEl    = document.getElementById('leadMemo');

    function openModal(mode, data) {
        titleEl.textContent = mode === 'create' ? 'リード登録' : 'リード編集';
        idEl.value     = data.id || '';
        companyEl.value = data.company || '';
        personEl.value  = data.person || '';
        titleFld.value  = data.title || '';
        phoneEl.value   = data.phone || '';
        emailEl.value   = data.email || '';
        statusEl.value  = data.status || '未接触';
        memoEl.value    = data.memo || '';
        // 担当営業員
        assigneeEl.value = data.assignee || '';
        assigneeEmailEl.value = data.assigneeEmail || '';
        modal.classList.add('active');
        companyEl.focus();
    }

    function closeModal() { modal.classList.remove('active'); }

    document.getElementById('btnAddLead').addEventListener('click', function() {
        openModal('create', {});
    });
    document.getElementById('btnLeadModalClose').addEventListener('click', closeModal);
    document.getElementById('btnLeadCancel').addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });

    // 担当営業員選択時にメールをセット
    assigneeEl.addEventListener('change', function() {
        var opt = this.options[this.selectedIndex];
        assigneeEmailEl.value = opt ? (opt.getAttribute('data-email') || '') : '';
    });

    // 編集ボタン
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-edit-lead');
        if (!btn) return;
        openModal('edit', {
            id:           btn.getAttribute('data-id'),
            company:      btn.getAttribute('data-company'),
            person:       btn.getAttribute('data-person'),
            title:        btn.getAttribute('data-title'),
            phone:        btn.getAttribute('data-phone'),
            email:        btn.getAttribute('data-email'),
            memo:         btn.getAttribute('data-memo'),
            status:       btn.getAttribute('data-status'),
            assignee:     btn.getAttribute('data-assignee'),
            assigneeEmail: btn.getAttribute('data-assignee-email'),
        });
    });

    // 削除ボタン
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-delete-lead');
        if (!btn) return;
        var id   = btn.getAttribute('data-id');
        var name = btn.getAttribute('data-name');
        if (!confirm('「' + name + '」を削除しますか？')) return;
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fd.append('csrf_token', csrfToken);
        fetch('/api/leads.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    var row = document.querySelector('tr[data-id="' + id + '"]');
                    if (row) row.remove();
                    showAlert('削除しました', 'success');
                } else {
                    showAlert(res.message || 'エラーが発生しました', 'error');
                }
            });
    });

    // 保存
    document.getElementById('btnLeadSave').addEventListener('click', function() {
        var company = companyEl.value.trim();
        if (!company) { showAlert('会社名を入力してください', 'error'); return; }

        var id = idEl.value;
        var fd = new FormData();
        fd.append('action', id ? 'update' : 'create');
        if (id) fd.append('id', id);
        fd.append('company_name', company);
        fd.append('person_name', personEl.value.trim());
        fd.append('title', titleFld.value.trim());
        fd.append('phone', phoneEl.value.trim());
        fd.append('email', emailEl.value.trim());
        fd.append('memo', memoEl.value.trim());
        fd.append('status', statusEl.value);
        fd.append('sales_assignee', assigneeEl.value.trim());
        fd.append('sales_email', assigneeEmailEl.value.trim());
        fd.append('csrf_token', csrfToken);

        fetch('/api/leads.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    showAlert(id ? '更新しました' : '登録しました', 'success');
                    closeModal();
                    setTimeout(function() { location.reload(); }, 600);
                } else {
                    showAlert(res.message || 'エラーが発生しました', 'error');
                }
            });
    });
    // ── 名刺OCR ─────────────────────────────────────────────────────────
    var ocrFileInput  = document.getElementById('ocrFileInput');
    var ocrDropZone   = document.getElementById('ocrDropZone');
    var ocrPreviewWrap = document.getElementById('ocrPreviewWrap');
    var ocrPreview    = document.getElementById('ocrPreview');
    var ocrProgress   = document.getElementById('ocrProgress');
    var ocrProgressFill = document.getElementById('ocrProgressFill');
    var ocrProgressText = document.getElementById('ocrProgressText');
    var currentOcrFile = null;
    var tesseractLoaded = false;

    // Tesseract.js を遅延ロード
    function loadTesseract(callback) {
        if (tesseractLoaded) { callback(); return; }
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
        s.onload = function() { tesseractLoaded = true; callback(); };
        s.onerror = function() { showAlert('OCRライブラリの読み込みに失敗しました。インターネット接続を確認してください。', 'danger'); };
        document.head.appendChild(s);
    }

    // ドロップゾーンクリック
    document.getElementById('btnOcrSelect').addEventListener('click', function(e) {
        e.stopPropagation();
        ocrFileInput.click();
    });
    ocrDropZone.addEventListener('click', function() { ocrFileInput.click(); });

    // ドラッグ＆ドロップ
    ocrDropZone.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('drag-over'); });
    ocrDropZone.addEventListener('dragleave', function() { this.classList.remove('drag-over'); });
    ocrDropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        var file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) setOcrImage(file);
    });

    // ファイル選択
    ocrFileInput.addEventListener('change', function() {
        if (this.files[0]) setOcrImage(this.files[0]);
    });

    function setOcrImage(file) {
        currentOcrFile = file;
        var reader = new FileReader();
        reader.onload = function(e) {
            ocrPreview.src = e.target.result;
            ocrDropZone.style.display = 'none';
            ocrPreviewWrap.style.display = 'block';
            ocrProgress.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }

    // 画像を変える
    document.getElementById('btnOcrClear').addEventListener('click', function() {
        currentOcrFile = null;
        ocrFileInput.value = '';
        ocrDropZone.style.display = 'flex';
        ocrPreviewWrap.style.display = 'none';
    });

    // 読み取り実行
    document.getElementById('btnOcrRun').addEventListener('click', function() {
        if (!currentOcrFile) return;
        var btn = this;
        btn.disabled = true;
        ocrProgress.style.display = 'block';
        setOcrProgress(0, 'OCRライブラリを読み込み中...');

        loadTesseract(function() {
            setOcrProgress(10, '画像を解析中...');
            Tesseract.recognize(currentOcrFile, 'jpn+eng', {
                logger: function(m) {
                    if (m.status === 'recognizing text') {
                        var pct = Math.round(10 + m.progress * 85);
                        setOcrProgress(pct, '文字を認識中... ' + Math.round(m.progress * 100) + '%');
                    }
                }
            }).then(function(result) {
                setOcrProgress(100, '完了');
                var parsed = parseBusinessCard(result.data.text);
                applyParsedData(parsed);
                setTimeout(function() {
                    ocrProgress.style.display = 'none';
                    btn.disabled = false;
                }, 600);
                showAlert('読み取りが完了しました。内容を確認してください。', 'success');
            }).catch(function(err) {
                console.error(err);
                showAlert('読み取りに失敗しました。画像を確認してください。', 'danger');
                ocrProgress.style.display = 'none';
                btn.disabled = false;
            });
        });
    });

    function setOcrProgress(pct, text) {
        ocrProgressFill.style.width = pct + '%';
        ocrProgressText.textContent = text;
    }

    // ── テキストパース ──────────────────────────────────────────────────
    function parseBusinessCard(text) {
        var lines = text.split('\n').map(function(l) { return l.trim(); }).filter(function(l) { return l.length > 0; });
        var result = { company: '', person: '', title: '', phone: '', email: '' };

        // メールアドレス
        var emailMatch = text.match(/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/);
        if (emailMatch) result.email = emailMatch[0];

        // 電話番号（固定・携帯・FAX対応）
        var phoneMatch = text.match(/0\d{1,4}[\-\s・]\d{1,4}[\-\s・]\d{3,4}/);
        if (!phoneMatch) phoneMatch = text.match(/0\d{9,10}/);
        if (phoneMatch) result.phone = phoneMatch[0].replace(/\s/g, '-');

        // 会社名（株式会社系キーワードを含む行）
        var companyPatterns = /株式会社|有限会社|合同会社|合資会社|（株）|㈱|（有）|㈲|Corp\.|Inc\.|Ltd\.|Co\./;
        for (var i = 0; i < lines.length; i++) {
            if (companyPatterns.test(lines[i])) {
                // 「株式会社」が前株・後株どちらでも取得
                result.company = lines[i].replace(/[\s　]+/g, ' ').trim();
                break;
            }
        }

        // 役職（キーワードマッチ）
        var titleKeywords = ['代表取締役', '取締役', '社長', '副社長', '専務', '常務', '部長', '副部長',
            '課長', '係長', '主任', 'マネージャー', 'ディレクター', 'チーフ',
            'CEO', 'COO', 'CFO', 'CTO', 'CMO', 'Director', 'Manager', 'President'];
        for (var j = 0; j < lines.length; j++) {
            for (var k = 0; k < titleKeywords.length; k++) {
                if (lines[j].includes(titleKeywords[k])) {
                    result.title = lines[j].replace(/[\s　]+/g, ' ').trim();
                    break;
                }
            }
            if (result.title) break;
        }

        // 人名（会社名・役職・電話・メール・URLを除いた短い行から推定）
        var skipPatterns = /@|http|Tel|FAX|fax|〒|\d{3}[-\s]\d|\d{7,}|株式|有限|合同|合資|Corp|Inc|Ltd/;
        var usedLines = [result.company, result.title].filter(Boolean);
        for (var n = 0; n < lines.length; n++) {
            var ln = lines[n];
            if (usedLines.indexOf(ln) !== -1) continue;
            if (skipPatterns.test(ln)) continue;
            if (result.email && ln.includes(result.email)) continue;
            if (result.phone && ln.includes(result.phone.replace(/-/g, ''))) continue;
            // 2〜8文字の日本語っぽい行を人名候補とする
            var cleanLn = ln.replace(/[\s　]/g, '');
            if (cleanLn.length >= 2 && cleanLn.length <= 10 && /[\u3000-\u9fff]/.test(cleanLn)) {
                result.person = ln.replace(/[\s　]+/g, ' ').trim();
                break;
            }
        }

        return result;
    }

    function applyParsedData(parsed) {
        if (parsed.company && !companyEl.value) companyEl.value = parsed.company;
        if (parsed.person  && !personEl.value)  personEl.value  = parsed.person;
        if (parsed.title   && !titleFld.value)  titleFld.value  = parsed.title;
        if (parsed.phone   && !phoneEl.value)   phoneEl.value   = parsed.phone;
        if (parsed.email   && !emailEl.value)   emailEl.value   = parsed.email;
        // すでに値がある場合は上書きしない（編集中の内容を保護）
    }

    // モーダルを閉じたときOCRエリアをリセット
    var _origCloseModal = closeModal;
    closeModal = function() {
        _origCloseModal();
        currentOcrFile = null;
        ocrFileInput.value = '';
        ocrDropZone.style.display = 'flex';
        ocrPreviewWrap.style.display = 'none';
        ocrProgress.style.display = 'none';
    };

    <?php endif; ?>
})();
</script>

<?php require_once '../functions/footer.php'; ?>
