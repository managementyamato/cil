<?php
/**
 * スライド閲覧・確認ページ
 *
 * 全員: 自分に割り当てられたスライドを閲覧し「確認済み」を記録
 * admin: スライドの登録・編集・削除、確認状況の管理
 *
 * IN_HUB_PAGE 定数があれば社内ハブから include されているのでヘッダー出力をスキップ。
 */
$_inHub = defined('IN_HUB_PAGE');
if (!$_inHub) {
    require_once '../api/auth.php';
    require_once '../functions/header.php';
}

$userEmail = $_SESSION['user_email'];
$userRole  = $_SESSION['user_role'];
$canManage = isAdmin();

// 表示タブ（user: 閲覧タブ、manage: 管理タブ）
$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'manage' && $canManage ? 'manage' : 'user';
?>
<style<?= nonceAttr() ?>>
/* ========== スライドページ全体 ========== */
.slides-page { max-width: 1100px; margin: 0 auto; }

/* ---- ヘッダー ---- */
.slides-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    gap: 1rem;
    flex-wrap: wrap;
}
.slides-header h2 {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* ---- タブ ---- */
.tab-nav {
    display: flex;
    gap: 0.25rem;
    background: var(--gray-100);
    border-radius: 8px;
    padding: 0.25rem;
    margin-bottom: 1.5rem;
}
.tab-btn {
    padding: 0.5rem 1.25rem;
    border: none;
    background: transparent;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    color: var(--gray-600);
    transition: all 0.15s;
    text-decoration: none;
    display: inline-block;
}
.tab-btn.active {
    background: white;
    color: var(--gray-900);
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}

/* ---- スライドカード一覧 ---- */
.slides-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}
.slide-card {
    background: white;
    border-radius: 12px;
    border: 1px solid var(--gray-200);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: box-shadow 0.15s, transform 0.15s;
}
.slide-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.slide-card-header {
    padding: 1rem 1.25rem 0.75rem;
    border-bottom: 1px solid var(--gray-100);
}
.slide-card-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0 0 0.4rem;
    line-height: 1.4;
}
.slide-card-desc {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin: 0;
    line-height: 1.5;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.slide-card-body {
    padding: 0.75rem 1.25rem;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.slide-meta-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    color: var(--gray-500);
}
.slide-meta-row svg { flex-shrink: 0; }

/* ---- 確認ステータスバッジ ---- */
.badge-confirmed {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: var(--success-light, #d1fae5);
    color: #065f46;
    border-radius: 20px;
    padding: 0.2rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-pending {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: var(--warning-light, #fef3c7);
    color: #92400e;
    border-radius: 20px;
    padding: 0.2rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-not-required {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: var(--gray-100);
    color: var(--gray-500);
    border-radius: 20px;
    padding: 0.2rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-overdue {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: var(--danger-light, #fee2e2);
    color: #991b1b;
    border-radius: 20px;
    padding: 0.2rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.slide-card-footer {
    padding: 0.75rem 1.25rem;
    border-top: 1px solid var(--gray-100);
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* ---- 空状態 ---- */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--gray-400);
}
.empty-state svg { margin-bottom: 1rem; opacity: 0.4; }
.empty-state p { font-size: 1rem; margin: 0; }

/* ---- 管理タブ: テーブル ---- */
.manage-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.manage-table th {
    text-align: left;
    padding: 0.75rem 1rem;
    background: var(--gray-50);
    border-bottom: 2px solid var(--gray-200);
    font-weight: 600;
    color: var(--gray-700);
    white-space: nowrap;
}
.manage-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.manage-table tr:hover td { background: var(--gray-50); }
.manage-table .col-title { max-width: 240px; }
.manage-table .col-title a { font-weight: 600; color: var(--primary); text-decoration: none; }
.manage-table .col-title a:hover { text-decoration: underline; }

/* ---- モーダル (スライド閲覧) ---- */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9000;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 1.5rem;
    overflow-y: auto;
    display: none;
}
.modal-overlay.active { display: flex; }
.slide-modal {
    background: white;
    border-radius: 14px;
    width: 100%;
    max-width: 1000px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.2);
    display: flex;
    flex-direction: column;
    min-height: 80vh;
    max-height: 90vh;
}
.slide-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--gray-200);
    flex-shrink: 0;
}
.slide-modal-header h3 {
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0;
    color: var(--gray-900);
}
.slide-modal-iframe-wrap {
    flex: 1;
    overflow: hidden;
}
.slide-modal-iframe-wrap iframe {
    width: 100%;
    height: 100%;
    border: none;
    min-height: 500px;
}
.slide-modal-footer {
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    gap: 1rem;
    flex-wrap: wrap;
}
.slide-modal-footer .hint {
    font-size: 0.8rem;
    color: var(--gray-500);
}

/* ---- 登録/編集フォームモーダル ---- */
.form-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 9100;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}
.form-modal-overlay.active { display: flex; }
.form-modal {
    background: white;
    border-radius: 12px;
    width: 100%;
    max-width: 560px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.18);
    overflow: hidden;
}
.form-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--gray-200);
    font-weight: 700;
    font-size: 1rem;
    color: var(--gray-900);
}
.form-modal-body { padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.form-modal-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: 0.75rem; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--gray-700); margin-bottom: 0.35rem; }
.form-group input, .form-group textarea, .form-group select {
    width: 100%;
    padding: 0.55rem 0.75rem;
    border: 1px solid var(--gray-300);
    border-radius: 7px;
    font-size: 0.875rem;
    font-family: inherit;
    transition: border-color 0.15s;
    box-sizing: border-box;
}
.form-group input:focus, .form-group textarea:focus, .form-group select:focus {
    outline: none;
    border-color: var(--primary);
}
.form-group textarea { resize: vertical; min-height: 80px; }
.checkbox-group { display: flex; gap: 1rem; flex-wrap: wrap; }
.checkbox-group label { display: flex; align-items: center; gap: 0.35rem; font-size: 0.85rem; font-weight: 400; cursor: pointer; }

/* ---- 確認者一覧モーダル ---- */
.confirm-list-modal {
    background: white;
    border-radius: 12px;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.18);
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}
.confirm-list-body {
    overflow-y: auto;
    padding: 0.75rem 1.25rem;
    flex: 1;
}
.confirm-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 0;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.85rem;
}
.confirm-item:last-child { border-bottom: none; }
.confirm-item .name { font-weight: 500; color: var(--gray-800); }
.confirm-item .date { color: var(--gray-500); font-size: 0.78rem; }

/* ---- 進捗バー ---- */
.progress-wrap { margin-top: 0.4rem; }
.progress-bar-outer {
    background: var(--gray-100);
    border-radius: 4px;
    height: 6px;
    overflow: hidden;
}
.progress-bar-inner {
    height: 100%;
    background: var(--primary);
    border-radius: 4px;
    transition: width 0.4s ease;
}
.progress-label { font-size: 0.72rem; color: var(--gray-500); margin-top: 0.2rem; }

/* ---- フィルター ---- */
.filter-bar {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    align-items: center;
}
.filter-btn {
    padding: 0.35rem 0.875rem;
    border-radius: 20px;
    border: 1px solid var(--gray-300);
    background: white;
    font-size: 0.78rem;
    font-weight: 500;
    cursor: pointer;
    color: var(--gray-600);
    transition: all 0.15s;
}
.filter-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}
</style>

<div class="slides-page">
    <?php if (!$_inHub) { require_once __DIR__ . '/../functions/hub-tabs.php'; renderHubTabs('internal'); } ?>
    <div class="slides-header">
        <div></div>
        <div class="slides-header-actions">
            <?php if ($canManage): ?>
            <?= uiNewButton('新規登録', ['id' => 'openCreateBtn']) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canManage): ?>
    <div class="tab-nav">
        <a href="?tab=user"   class="tab-btn <?= $activeTab === 'user'   ? 'active' : '' ?>">📄 閲覧・確認</a>
        <a href="?tab=manage" class="tab-btn <?= $activeTab === 'manage' ? 'active' : '' ?>">⚙️ 管理</a>
    </div>
    <?php endif; ?>

    <!-- ===================== 閲覧タブ ===================== -->
    <div id="tabUser" <?= $activeTab !== 'user' ? 'style="display:none"' : '' ?>>
        <div class="filter-bar">
            <button class="filter-btn active" data-filter="all">すべて</button>
            <button class="filter-btn" data-filter="pending">未確認</button>
            <button class="filter-btn" data-filter="confirmed">確認済み</button>
        </div>
        <div class="slides-grid" id="slidesGrid">
            <div style="grid-column:1/-1; text-align:center; padding:3rem; color:var(--gray-400);">読み込み中...</div>
        </div>
    </div>

    <!-- ===================== 管理タブ ===================== -->
    <?php if ($canManage): ?>
    <div id="tabManage" <?= $activeTab !== 'manage' ? 'style="display:none"' : '' ?>>
        <div style="overflow-x:auto; background:white; border-radius:12px; border:1px solid var(--gray-200);">
            <table class="manage-table" id="manageTable">
                <thead>
                    <tr>
                        <th>タイトル</th>
                        <th>対象</th>
                        <th>期限</th>
                        <th>確認状況</th>
                        <th>登録日</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="manageTableBody">
                    <tr><td colspan="6" style="text-align:center;padding:3rem;color:var(--gray-400);">読み込み中...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ===================== スライド閲覧モーダル ===================== -->
<div class="modal-overlay" id="slideViewModal">
    <div class="slide-modal">
        <div class="slide-modal-header">
            <h3 id="slideModalTitle">マニュアル</h3>
            <button class="btn btn-ghost" id="closeSlideModal" style="padding:0.25rem 0.5rem;">✕</button>
        </div>
        <div class="slide-modal-iframe-wrap">
            <iframe id="slideIframe" src="about:blank" allowfullscreen></iframe>
        </div>
        <div class="slide-modal-footer">
            <span class="hint" id="slideModalHint">マニュアルを最後まで確認してから「確認しました」を押してください。</span>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <button class="btn btn-ghost" id="closeSlideModal2">閉じる</button>
                <button class="btn btn-success" id="confirmBtn" style="display:none;">
                    ✓ 確認しました
                </button>
                <span class="badge-confirmed" id="alreadyConfirmedBadge" style="display:none;">
                    ✓ 確認済み
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ===================== 登録/編集フォームモーダル ===================== -->
<?php if ($canManage): ?>
<div class="form-modal-overlay" id="formModalOverlay">
    <div class="form-modal">
        <div class="form-modal-header">
            <span id="formModalTitle">マニュアルを追加</span>
            <button class="btn btn-ghost" id="closeFormModal" style="padding:0.25rem 0.5rem;">✕</button>
        </div>
        <div class="form-modal-body">
            <input type="hidden" id="formSlideId">
            <div class="form-group">
                <label>タイトル <span style="color:var(--danger)">*</span></label>
                <input type="text" id="formTitle" placeholder="例: 駐車場ルール（東京営業所）" maxlength="100">
            </div>
            <div class="form-group">
                <label>Google Docs URL <span style="color:var(--danger)">*</span></label>
                <input type="url" id="formUrl" placeholder="https://docs.google.com/document/d/...">
                <div style="font-size:0.75rem;color:var(--gray-400);margin-top:0.3rem;">Google Docs のURLを貼り付けてください（/edit まで含めて）</div>
            </div>
            <div class="form-group">
                <label>概要・説明</label>
                <textarea id="formDescription" placeholder="このマニュアルの内容・目的を簡単に説明（任意）"></textarea>
            </div>
            <div class="form-group">
                <label>対象部署（チェックなし = 全員対象）</label>
                <div class="checkbox-group">
                    <label><input type="checkbox" name="required_for" value="sales"> 営業部</label>
                    <label><input type="checkbox" name="required_for" value="product"> 製品技術部</label>
                    <label><input type="checkbox" name="required_for" value="admin"> 管理部</label>
                </div>
            </div>
            <div class="form-group">
                <label>確認期限（任意）</label>
                <input type="date" id="formDueDate">
            </div>
        </div>
        <div class="form-modal-footer">
            <button class="btn btn-ghost" id="cancelFormBtn">キャンセル</button>
            <button class="btn btn-primary" id="saveSlideBtn">保存する</button>
        </div>
    </div>
</div>

<!-- ===================== 確認者一覧モーダル ===================== -->
<div class="form-modal-overlay" id="confirmListOverlay">
    <div class="confirm-list-modal">
        <div class="form-modal-header">
            <span>確認状況</span>
            <button class="btn btn-ghost" id="closeConfirmList" style="padding:0.25rem 0.5rem;">✕</button>
        </div>
        <div class="confirm-list-body" id="confirmListBody">
            読み込み中...
        </div>
        <div class="form-modal-footer">
            <button class="btn btn-ghost" id="closeConfirmList2">閉じる</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script<?= nonceAttr() ?>>
(function(){
    var CSRF = '<?= generateCsrfToken() ?>';
    var IS_ADMIN = <?= $canManage ? 'true' : 'false' ?>;
    var USER_ROLE = '<?= htmlspecialchars($userRole) ?>';
    var slidesData = [];

    // ===================== データ取得 =====================
    async function loadSlides() {
        try {
            var res = await fetch('/api/slides.php?action=list', { credentials: 'same-origin' });
            var json = await res.json();
            if (!json.success) throw new Error(json.error || json.message || '不明なエラー');
            slidesData = json.data.slides || [];
            renderUserGrid('all');
            if (IS_ADMIN) renderManageTable();
        } catch(e) {
            document.getElementById('slidesGrid').innerHTML =
                '<div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--danger);">読み込みに失敗しました: ' + escapeHtml(e.message) + '</div>';
        }
    }

    // ===================== 閲覧タブ: グリッド描画 =====================
    function renderUserGrid(filter) {
        var grid = document.getElementById('slidesGrid');
        var list = slidesData.filter(function(s) {
            if (!s.is_required_for_me) return filter === 'all'; // 対象外は「すべて」のみ表示
            if (filter === 'pending')   return !s.confirmed;
            if (filter === 'confirmed') return  s.confirmed;
            return true;
        });

        if (!list.length) {
            var msg = filter === 'confirmed' ? '確認済みのマニュアルはありません。' :
                      filter === 'pending'   ? '未確認のマニュアルはありません。' :
                                               'マニュアルが登録されていません。';
            grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg><p>' + escapeHtml(msg) + '</p></div>';
            return;
        }

        var html = '';
        list.forEach(function(s) {
            var today = new Date().toISOString().slice(0,10);
            var isOverdue = !s.confirmed && s.due_date && s.due_date < today;
            var statusBadge = '';
            if (!s.is_required_for_me) {
                statusBadge = '<span class="badge-not-required">対象外</span>';
            } else if (s.confirmed) {
                statusBadge = '<span class="badge-confirmed">✓ 確認済み</span>';
            } else if (isOverdue) {
                statusBadge = '<span class="badge-overdue">⚠ 期限超過</span>';
            } else {
                statusBadge = '<span class="badge-pending">未確認</span>';
            }

            var dueLabel = '';
            if (s.due_date) {
                var d = new Date(s.due_date);
                dueLabel = '<div class="slide-meta-row"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>' +
                    (isOverdue ? '<span style="color:var(--danger);font-weight:600;">' : '') +
                    '期限: ' + escapeHtml(s.due_date) +
                    (isOverdue ? '</span>' : '') + '</div>';
            }
            var confirmedLabel = s.confirmed && s.confirmed_at ?
                '<div class="slide-meta-row" style="color:#065f46;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>確認日: ' + escapeHtml(s.confirmed_at.slice(0,10)) + '</div>' : '';

            html += '<div class="slide-card" data-id="' + escapeHtml(s.id) + '">' +
                '<div class="slide-card-header">' +
                    '<p class="slide-card-title">' + escapeHtml(s.title) + '</p>' +
                    (s.description ? '<p class="slide-card-desc">' + escapeHtml(s.description) + '</p>' : '') +
                '</div>' +
                '<div class="slide-card-body">' +
                    statusBadge +
                    dueLabel +
                    confirmedLabel +
                '</div>' +
                '<div class="slide-card-footer">' +
                    '<button class="btn btn-primary btn-sm open-slide-btn" data-id="' + escapeHtml(s.id) + '">閲覧する</button>' +
                '</div>' +
            '</div>';
        });
        grid.innerHTML = html;

        // イベント: 閲覧ボタン
        grid.querySelectorAll('.open-slide-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                openSlideModal(btn.dataset.id);
            });
        });
    }

    // ===================== フィルターボタン =====================
    document.querySelectorAll('.filter-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            renderUserGrid(btn.dataset.filter);
        });
    });

    // ===================== スライド閲覧モーダル =====================
    var currentSlideId = null;

    function openSlideModal(slideId) {
        var slide = slidesData.find(function(s) { return s.id === slideId; });
        if (!slide) return;

        currentSlideId = slideId;
        document.getElementById('slideModalTitle').textContent = slide.title;

        // iframeにURLをセット（埋め込みURLに変換）
        var embedUrl = convertToEmbedUrl(slide.google_docs_url);
        document.getElementById('slideIframe').src = embedUrl;

        // 確認ボタン表示切り替え
        var confirmBtn = document.getElementById('confirmBtn');
        var alreadyBadge = document.getElementById('alreadyConfirmedBadge');
        var hint = document.getElementById('slideModalHint');
        if (slide.confirmed) {
            confirmBtn.style.display = 'none';
            alreadyBadge.style.display = 'inline-flex';
            hint.textContent = '確認済みのマニュアルです。';
        } else if (!slide.is_required_for_me) {
            confirmBtn.style.display = 'none';
            alreadyBadge.style.display = 'none';
            hint.textContent = 'このマニュアルはあなたの部署の必須確認対象外です。';
        } else {
            confirmBtn.style.display = 'inline-flex';
            alreadyBadge.style.display = 'none';
            hint.textContent = 'マニュアルを確認したら「確認しました」を押してください。';
        }

        document.getElementById('slideViewModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function convertToEmbedUrl(url) {
        // Google Docs: /edit → /preview
        return url.replace(/\/edit([?#].*)?$/, '/preview');
    }

    function closeSlideModal() {
        document.getElementById('slideViewModal').classList.remove('active');
        document.getElementById('slideIframe').src = 'about:blank';
        document.body.style.overflow = '';
        currentSlideId = null;
    }

    document.getElementById('closeSlideModal').addEventListener('click', closeSlideModal);
    document.getElementById('closeSlideModal2').addEventListener('click', closeSlideModal);
    // 背景クリックでは閉じない（×ボタン・キャンセルのみ）

    // 確認ボタン
    document.getElementById('confirmBtn').addEventListener('click', async function() {
        if (!currentSlideId) return;
        var btn = this;
        btn.disabled = true;
        btn.textContent = '送信中...';
        try {
            var res = await fetch('/api/slides.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ action: 'confirm', slide_id: currentSlideId }),
                credentials: 'same-origin',
            });
            var json = await res.json();
            if (!json.success) throw new Error(json.error || json.message || '不明なエラー');

            // UIを更新
            var slide = slidesData.find(function(s) { return s.id === currentSlideId; });
            if (slide) {
                slide.confirmed = true;
                slide.confirmed_at = new Date().toISOString().slice(0,19).replace('T',' ');
            }
            btn.style.display = 'none';
            document.getElementById('alreadyConfirmedBadge').style.display = 'inline-flex';
            document.getElementById('slideModalHint').textContent = '確認済みとして記録しました。';
            renderUserGrid(document.querySelector('.filter-btn.active')?.dataset.filter || 'all');
            if (IS_ADMIN) renderManageTable();
        } catch(e) {
            alert('エラー: ' + e.message);
            btn.disabled = false;
            btn.innerHTML = '✓ 確認しました';
        }
    });

    // ===================== 管理タブ: テーブル描画 =====================
    function renderManageTable() {
        var tbody = document.getElementById('manageTableBody');
        if (!tbody) return;
        if (!slidesData.length) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:3rem;color:var(--gray-400);">マニュアルが登録されていません。</td></tr>';
            return;
        }
        var roles = { sales: '営業部', product: '製品技術部', admin: '管理部' };
        var html = '';
        slidesData.forEach(function(s) {
            var reqFor = (s.required_for && s.required_for.length)
                ? s.required_for.map(function(r){ return roles[r] || r; }).join('、')
                : '全員';
            var today = new Date().toISOString().slice(0,10);
            var dueLabel = s.due_date
                ? (s.due_date < today ? '<span style="color:var(--danger);font-weight:600;">' + escapeHtml(s.due_date) + ' (期限超過)</span>' : escapeHtml(s.due_date))
                : '<span style="color:var(--gray-400);">-</span>';

            html += '<tr>' +
                '<td class="col-title"><a href="' + escapeHtml(s.google_docs_url) + '" target="_blank" rel="noopener">' + escapeHtml(s.title) + ' ↗</a></td>' +
                '<td>' + escapeHtml(reqFor) + '</td>' +
                '<td>' + dueLabel + '</td>' +
                '<td>' +
                    '<button class="btn btn-ghost btn-sm show-confirmations-btn" data-id="' + escapeHtml(s.id) + '" data-title="' + escapeHtml(s.title) + '" style="font-size:0.78rem;">確認状況を見る</button>' +
                '</td>' +
                '<td style="color:var(--gray-500);font-size:0.8rem;">' + escapeHtml((s.created_at || '').slice(0,10)) + '</td>' +
                '<td style="white-space:nowrap;">' +
                    '<button class="btn btn-ghost btn-sm edit-slide-btn" data-id="' + escapeHtml(s.id) + '" style="margin-right:0.25rem;">編集</button>' +
                    '<button class="btn btn-danger btn-sm delete-slide-btn" data-id="' + escapeHtml(s.id) + '" data-title="' + escapeHtml(s.title) + '">削除</button>' +
                '</td>' +
            '</tr>';
        });
        tbody.innerHTML = html;

        // イベント: 編集ボタン
        tbody.querySelectorAll('.edit-slide-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { openEditForm(btn.dataset.id); });
        });
        // イベント: 削除ボタン
        tbody.querySelectorAll('.delete-slide-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { deleteSlide(btn.dataset.id, btn.dataset.title); });
        });
        // イベント: 確認状況ボタン
        tbody.querySelectorAll('.show-confirmations-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { showConfirmations(btn.dataset.id, btn.dataset.title); });
        });
    }

    // ===================== 登録フォーム =====================
    function openCreateForm() {
        document.getElementById('formModalTitle').textContent = 'マニュアルを追加';
        document.getElementById('formSlideId').value = '';
        document.getElementById('formTitle').value = '';
        document.getElementById('formUrl').value = '';
        document.getElementById('formDescription').value = '';
        document.getElementById('formDueDate').value = '';
        document.querySelectorAll('input[name="required_for"]').forEach(function(cb) { cb.checked = false; });
        document.getElementById('formModalOverlay').classList.add('active');
        document.getElementById('formTitle').focus();
    }
    function openEditForm(slideId) {
        var slide = slidesData.find(function(s) { return s.id === slideId; });
        if (!slide) return;
        document.getElementById('formModalTitle').textContent = 'マニュアルを編集';
        document.getElementById('formSlideId').value = slide.id;
        document.getElementById('formTitle').value = slide.title;
        document.getElementById('formUrl').value = slide.google_docs_url;
        document.getElementById('formDescription').value = slide.description || '';
        document.getElementById('formDueDate').value = slide.due_date || '';
        var reqFor = slide.required_for || [];
        document.querySelectorAll('input[name="required_for"]').forEach(function(cb) {
            cb.checked = reqFor.includes(cb.value);
        });
        document.getElementById('formModalOverlay').classList.add('active');
        document.getElementById('formTitle').focus();
    }
    function closeFormModal() {
        document.getElementById('formModalOverlay').classList.remove('active');
    }

    if (IS_ADMIN) {
        document.getElementById('openCreateBtn').addEventListener('click', openCreateForm);
        document.getElementById('closeFormModal').addEventListener('click', closeFormModal);
        document.getElementById('cancelFormBtn').addEventListener('click', closeFormModal);
        // 背景クリックでは閉じない（×ボタン・キャンセルのみ）

        // 保存
        document.getElementById('saveSlideBtn').addEventListener('click', async function() {
            var btn = this;
            var id    = document.getElementById('formSlideId').value.trim();
            var title = document.getElementById('formTitle').value.trim();
            var url   = document.getElementById('formUrl').value.trim();
            var desc  = document.getElementById('formDescription').value.trim();
            var due   = document.getElementById('formDueDate').value.trim() || null;
            var reqFor = Array.from(document.querySelectorAll('input[name="required_for"]:checked')).map(function(cb){ return cb.value; });

            if (!title) { alert('タイトルを入力してください'); return; }
            if (!url)   { alert('Google Docs URLを入力してください'); return; }
            if (!url.startsWith('https://docs.google.com/')) { alert('Google Docs のURLを入力してください'); return; }

            btn.disabled = true;
            var action = id ? 'update' : 'create';
            var payload = { action: action, title: title, google_docs_url: url, description: desc, required_for: reqFor, due_date: due };
            if (id) payload.id = id;

            try {
                var res = await fetch('/api/slides.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body: JSON.stringify(payload),
                    credentials: 'same-origin',
                });
                var json = await res.json();
                if (!json.success) throw new Error(json.error || json.message || '不明なエラー');
                closeFormModal();
                await loadSlides();
            } catch(e) {
                alert('保存に失敗しました: ' + e.message);
            }
            btn.disabled = false;
        });

        // 削除
        var deleteSlide = async function(id, title) {
            if (!confirm('「' + title + '」を削除しますか？\n確認記録も参照できなくなります。')) return;
            try {
                var res = await fetch('/api/slides.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body: JSON.stringify({ action: 'delete', id: id }),
                    credentials: 'same-origin',
                });
                var json = await res.json();
                if (!json.success) throw new Error(json.error || json.message || '不明なエラー');
                await loadSlides();
            } catch(e) {
                alert('削除に失敗しました: ' + e.message);
            }
        }

        // 確認状況
        var showConfirmations = async function(slideId, slideTitle) {
            document.getElementById('confirmListBody').innerHTML = '読み込み中...';
            document.getElementById('confirmListOverlay').classList.add('active');
            try {
                var res = await fetch('/api/slides.php?action=confirmations&slide_id=' + encodeURIComponent(slideId), { credentials: 'same-origin' });
                var json = await res.json();
                if (!json.success) throw new Error(json.error || json.message || '不明なエラー');
                var list = json.data.confirmations || [];
                if (!list.length) {
                    document.getElementById('confirmListBody').innerHTML = '<div style="padding:2rem;text-align:center;color:var(--gray-400);">まだ誰も確認していません。</div>';
                    return;
                }
                // 日時降順ソート
                list.sort(function(a,b){ return (b.confirmed_at||'').localeCompare(a.confirmed_at||''); });
                var html = '<div style="font-size:0.78rem;color:var(--gray-500);padding:0.5rem 0 0.75rem;font-weight:600;">「' + escapeHtml(slideTitle) + '」の確認者 (' + list.length + '名)</div>';
                list.forEach(function(c) {
                    html += '<div class="confirm-item">' +
                        '<span class="name">' + escapeHtml(c.user_name || c.user_email) + '</span>' +
                        '<span class="date">' + escapeHtml((c.confirmed_at||'').slice(0,16).replace('T',' ')) + '</span>' +
                    '</div>';
                });
                document.getElementById('confirmListBody').innerHTML = html;
            } catch(e) {
                document.getElementById('confirmListBody').innerHTML = '<div style="padding:2rem;text-align:center;color:var(--danger);">読み込み失敗: ' + escapeHtml(e.message) + '</div>';
            }
        }

        document.getElementById('closeConfirmList').addEventListener('click', function() {
            document.getElementById('confirmListOverlay').classList.remove('active');
        });
        document.getElementById('closeConfirmList2').addEventListener('click', function() {
            document.getElementById('confirmListOverlay').classList.remove('active');
        });
        // 背景クリックでは閉じない（×ボタン・キャンセルのみ）
    }

    // ===================== 初期読み込み =====================
    loadSlides();
})();
</script>

<?php require_once '../functions/footer.php'; ?>
