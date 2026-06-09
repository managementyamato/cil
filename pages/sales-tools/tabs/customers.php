<?php /* 顧客 タブ（営業情報統合: 顧客マスター × AM連動） */ ?>
<?php $cdCanEdit = hasPermission('product'); // 編集は製品技術部以上（API canEdit と一致） ?>
<style<?= nonceAttr() ?>>
.cd-sub { color: var(--gray-600); font-size: 0.88rem; margin-bottom: 1rem; }
.cd-rank { display: inline-flex; align-items: center; justify-content: center; min-width: 1.6rem; height: 1.6rem; padding: 0 0.45rem; border-radius: 9999px; color: #fff; font-weight: 700; font-size: 0.8rem; }
.cd-rank.empty { background: var(--gray-300); color: var(--gray-600); }
.cd-filters { display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center; background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 0.8rem; margin-bottom: 1rem; }
.cd-filters .form-input { max-width: 260px; }
.cd-count { margin-left: auto; color: var(--gray-500); font-size: 0.82rem; }
.cd-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; border: 1px solid var(--gray-200); }
.cd-table th, .cd-table td { padding: 0.7rem 0.9rem; text-align: left; border-bottom: 1px solid var(--gray-100); font-size: 0.9rem; }
.cd-table th { background: var(--gray-50); color: var(--gray-500); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; }
.cd-row { cursor: pointer; transition: background 0.12s; }
.cd-row:hover { background: var(--gray-50); }
.cd-row.cd-row-active { background: var(--primary-light); }
.cd-row .cd-arrow { color: var(--gray-400); }
.cd-detail-actions { margin-left: auto; display: flex; gap: 0.5rem; }
.cd-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: 480px; max-width: 100vw; background: #fff; box-shadow: -4px 0 20px rgba(0,0,0,0.10); z-index: 500; transform: translateX(100%); transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; }
.cd-drawer.active { transform: translateX(0); }
.cd-drawer-head { display: flex; align-items: center; gap: 0.6rem; padding: 0.9rem 1.2rem; border-bottom: 1px solid var(--gray-200); flex-shrink: 0; min-height: 0; }
.cd-drawer-head h3 { margin: 0; font-size: 1.1rem; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cd-drawer-close { background: none; border: none; font-size: 1.4rem; line-height: 1; cursor: pointer; color: var(--gray-400); padding: 0.2rem 0.4rem; border-radius: 6px; flex-shrink: 0; }
.cd-drawer-close:hover { background: var(--gray-100); color: var(--gray-900); }
.cd-drawer-body { flex: 1; overflow-y: auto; padding: 1rem 1.2rem; }
.cd-drawer-body .cd-info-grid { grid-template-columns: 1fr; }
@media (max-width: 640px) { .cd-drawer { width: 100vw; } }
.cd-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1rem 1.2rem; margin-bottom: 1rem; }
.cd-card h4 { margin: 0 0 0.75rem; font-size: 0.82rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.04em; }
.cd-am { display: flex; align-items: center; gap: 0.8rem; }
.cd-am-avatar { width: 42px; height: 42px; border-radius: 50%; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }
.cd-am-info { flex: 1; min-width: 0; }
.cd-am-name { font-weight: 600; }
.cd-am-meta { color: var(--gray-500); font-size: 0.82rem; }
.cd-cc-list { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.cd-cc-chip { display: inline-flex; align-items: center; gap: 0.4rem; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 9999px; padding: 0.3rem 0.7rem; font-size: 0.82rem; }
.cd-cc-chip .cd-cc-role { color: var(--gray-500); }
.cd-cc-chip button { background: none; border: none; color: var(--gray-400); cursor: pointer; padding: 0; line-height: 1; }
.cd-cc-chip button:hover { color: #dc2626; }
.cd-mail-warn { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 8px; padding: 0.7rem 0.9rem; font-size: 0.85rem; margin-top: 0.8rem; display: flex; gap: 0.5rem; align-items: flex-start; }
.cd-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 1.5rem; }
@media (max-width: 700px) { .cd-info-grid { grid-template-columns: 1fr; } }
.cd-info-row { display: flex; justify-content: space-between; gap: 1rem; padding: 0.4rem 0; border-bottom: 1px dashed var(--gray-100); }
.cd-info-label { color: var(--gray-500); font-size: 0.85rem; }
.cd-info-val { font-weight: 500; text-align: right; }
.cd-recent { width: 100%; border-collapse: collapse; }
.cd-recent th, .cd-recent td { padding: 0.45rem 0.6rem; text-align: left; border-bottom: 1px solid var(--gray-100); font-size: 0.85rem; }
.cd-recent th { color: var(--gray-500); font-size: 0.72rem; }
.cd-recent td.amount { text-align: right; font-variant-numeric: tabular-nums; }
.cd-rank-note { color: var(--gray-500); font-size: 0.8rem; margin-top: 0.4rem; }
.cd-memo { color: var(--gray-600); font-size: 0.85rem; margin-top: 0.5rem; white-space: pre-line; }
.cd-amno { font-weight: 600; color: var(--gray-500); font-size: 0.8rem; white-space: nowrap; }
.cd-rank-ch { color: var(--gray-400); font-size: 0.78rem; margin-left: 0.2rem; }
.cd-status { display: inline-block; padding: 0.1rem 0.5rem; border-radius: 9999px; font-size: 0.72rem; font-weight: 600; }
.cd-status-active { background: #dcfce7; color: #166534; }
.cd-status-dormant { background: #f3f4f6; color: #6b7280; }
.cd-match-mini { font-size: 0.68rem; color: #b45309; background: #fef3c7; border-radius: 4px; padding: 0 0.3rem; vertical-align: middle; }
.cd-loading { text-align: center; color: var(--gray-400); padding: 2rem; }
.cd-pager { display: flex; align-items: center; gap: 0.4rem; justify-content: center; flex-wrap: wrap; margin-top: 0.9rem; }
.cd-pager .cd-pgsize { margin-right: auto; color: var(--gray-500); font-size: 0.8rem; display: flex; align-items: center; gap: 0.4rem; }
.cd-pager .cd-pgsize select { padding: 0.2rem 0.4rem; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 0.8rem; }
.cd-pg-btn { min-width: 2rem; padding: 0.3rem 0.55rem; border: 1px solid var(--gray-300); background: #fff; border-radius: 6px; cursor: pointer; font-size: 0.82rem; color: var(--gray-700); }
.cd-pg-btn:hover:not(:disabled) { background: var(--gray-50); }
.cd-pg-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.cd-pg-btn:disabled { opacity: 0.4; cursor: default; }
.cd-pg-ellipsis { color: var(--gray-400); padding: 0 0.2rem; }
</style>

<div class="st-panel <?= $activeTab === 'customers' ? 'active' : '' ?>" id="panel-customers" role="tabpanel">
    <div class="cd-sub">アカウントマネジメント（顧客 × 主担当AM連動）。メール送信時は主担当AM＋CC候補を必ずCCに入れてください。</div>

    <!-- 一覧ビュー -->
    <div id="cdListView">
        <div class="cd-filters">
            <input type="text" id="cdSearch" class="form-input" placeholder="顧客名で検索…" autocomplete="off">
            <select id="cdStatusFilter" class="form-input" style="max-width:140px;">
                <option value="">ステータス: 全</option>
                <option value="既存">既存</option>
                <option value="休眠">休眠</option>
            </select>
            <select id="cdTantoFilter" class="form-input" style="max-width:150px;">
                <option value="">担当: 全</option>
            </select>
            <select id="cdRankFilter" class="form-input" style="max-width:180px;">
                <option value="">ランク: 全</option>
                <option value="S">S：上位ディーラー</option>
                <option value="A">A：標準ディーラー</option>
                <option value="B">B：新規開拓・直販</option>
            </select>
            <?php if ($cdCanEdit): ?>
            <button type="button" class="btn btn-sm btn-secondary" id="cdSyncMfBtn" title="MF取引先マスタから顧客を同期（新規追加＋住所・電話などの補完）">MFから同期</button>
            <?php endif; ?>
            <span class="cd-count" id="cdCount"></span>
        </div>
        <table class="cd-table">
            <thead>
                <tr><th>AM#</th><th>顧客名</th><th>ステータス</th><th>担当</th><th>ランク</th><th>優先度</th><th></th></tr>
            </thead>
            <tbody id="cdListBody">
                <tr><td colspan="7" class="cd-loading">読み込み中…</td></tr>
            </tbody>
        </table>
        <div class="cd-pager" id="cdPager"></div>
    </div>

    <!-- 詳細ドロワー -->
    <div id="cdDrawer" class="cd-drawer">
        <div class="cd-drawer-head">
            <h3 id="cdDetailName">--</h3>
            <span class="cd-rank empty" id="cdDetailRank">--</span>
            <span id="cdDetailMatch"></span>
            <button type="button" class="cd-drawer-close" id="cdDrawerClose">&times;</button>
        </div>
        <div class="cd-drawer-body">
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem;">
                <?php if ($cdCanEdit): ?>
                <button type="button" class="btn btn-sm btn-secondary" id="cdEditBtn">編集</button>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-primary" id="cdMailBtn">メール作成</button>
            </div>

            <div class="cd-card">
                <h4>主担当AM</h4>
                <div class="cd-am" id="cdAmBlock"><div class="cd-am-info"><span class="cd-am-meta">未設定</span></div></div>
                <h4 style="margin-top:1rem;">CCに入れる人
                    <?php if ($cdCanEdit): ?>
                    <button type="button" class="btn btn-sm btn-secondary" id="cdCcAddBtn" style="margin-left:0.5rem;">+ 追加</button>
                    <?php endif; ?>
                </h4>
                <div class="cd-cc-list" id="cdCcList"></div>
                <div class="cd-mail-warn"><strong>!</strong><span>メール送信時は上記の主担当AM+CC候補 全員を必ずCCに入れてください（全営業必須）。</span></div>
            </div>

            <div class="cd-card">
                <h4>アカウント情報</h4>
                <div class="cd-info-grid" id="cdInfoGrid"></div>
                <div class="cd-rank-note" id="cdRankNote"></div>
                <div class="cd-memo" id="cdMemo"></div>
            </div>

            <div class="cd-card">
                <h4>最近の取引（請求）</h4>
                <table class="cd-recent">
                    <thead><tr><th>請求番号</th><th>件名</th><th>請求日</th><th class="amount">金額</th></tr></thead>
                    <tbody id="cdRecentBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($cdCanEdit): ?>
<!-- 基本情報 編集モーダル -->
<div id="cdEditModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>アカウント情報を編集</h3>
            <button type="button" class="close" data-close-modal="cdEditModal">&times;</button>
        </div>
        <form id="cdEditForm">
            <div class="modal-body">
                <input type="hidden" id="cdEditId">
                <div class="form-group"><label for="cdEditAmNumber">AMナンバー</label><input type="text" id="cdEditAmNumber" class="form-input" placeholder="例: AM1"></div>
                <div class="form-group">
                    <label for="cdEditStatus">ステータス</label>
                    <select id="cdEditStatus" class="form-input"><option value="">未設定</option><option value="既存">既存</option><option value="休眠">休眠</option></select>
                </div>
                <div class="form-group"><label for="cdEditType">種別</label><input type="text" id="cdEditType" class="form-input" placeholder="例: ディーラー"></div>
                <div class="form-group"><label for="cdEditTypeMemo">種別メモ</label><input type="text" id="cdEditTypeMemo" class="form-input" placeholder="例: ディーラー(建機最大手)"></div>
                <div class="form-group"><label for="cdEditHq">本社所在地</label><input type="text" id="cdEditHq" class="form-input"></div>
                <div class="form-group"><label for="cdEditPriority">優先度</label><input type="text" id="cdEditPriority" class="form-input"></div>
                <div class="form-group"><label for="cdEditTanto">担当</label><input type="text" id="cdEditTanto" class="form-input" placeholder="例: 鈴木"></div>
                <div class="form-group">
                    <label for="cdEditRank">ランク現在（価格表と共通）</label>
                    <select id="cdEditRank" class="form-input">
                        <option value="">未設定</option>
                        <option value="S">S：上位ディーラー</option>
                        <option value="A">A：標準ディーラー</option>
                        <option value="B">B：新規開拓・直販</option>
                    </select>
                    <small class="form-hint" id="cdSuggestHint" style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;"></small>
                </div>
                <div class="form-group">
                    <label for="cdEditRankCh">ランクチャレンジ（目標）</label>
                    <select id="cdEditRankCh" class="form-input"><option value="">なし</option><option value="S">S</option><option value="A">A</option><option value="B">B</option></select>
                </div>
                <div class="form-group">
                    <label for="cdEditAm">主担当AM（メール宛先用・社員）</label>
                    <select id="cdEditAm" class="form-input"><option value="">未設定</option></select>
                </div>
                <div class="form-group"><label for="cdEditMemo">メモ</label><textarea id="cdEditMemo" class="form-input" rows="3"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="cdEditModal">キャンセル</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- CC候補 追加/編集モーダル -->
<div id="cdCcModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="cdCcModalTitle">CC候補を追加</h3>
            <button type="button" class="close" data-close-modal="cdCcModal">&times;</button>
        </div>
        <form id="cdCcForm">
            <div class="modal-body">
                <input type="hidden" id="cdCcId">
                <div class="form-group"><label for="cdCcEmployee">社員から選択（任意）</label><select id="cdCcEmployee" class="form-input"><option value="">手入力する</option></select></div>
                <div class="form-group"><label for="cdCcName">氏名</label><input type="text" id="cdCcName" class="form-input"></div>
                <div class="form-group"><label for="cdCcEmail">メールアドレス</label><input type="email" id="cdCcEmail" class="form-input"></div>
                <div class="form-group"><label for="cdCcRole">役割ラベル</label><input type="text" id="cdCcRole" class="form-input" placeholder="例: 西井部長（契約・問題対応）"></div>
                <div class="form-group"><label for="cdCcNote">用途メモ</label><input type="text" id="cdCcNote" class="form-input" placeholder="例: 進捗案件のみ"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="cdCcModal">キャンセル</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- メール作成モーダル -->
<div id="cdMailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>メール作成</h3>
            <button type="button" class="close" data-close-modal="cdMailModal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group"><label>宛先（To）— 主担当AM</label><input type="text" id="cdMailTo" class="form-input" readonly></div>
            <div class="form-group"><label>CC（AM＋CC候補 全員）</label><textarea id="cdMailCc" class="form-input" rows="3" readonly></textarea></div>
            <div class="cd-mail-warn"><strong>!</strong><span>この宛先・CCは全営業必須の運用ルールです。CCから外さないでください。</span></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cdMailCopy">宛先をコピー</button>
            <a href="#" class="btn btn-primary" id="cdMailOpen" target="_blank" rel="noopener">メールソフトで開く</a>
        </div>
    </div>
</div>

<script<?= nonceAttr() ?>>
(function() {
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const canEdit = <?= $cdCanEdit ? 'true' : 'false' ?>;
    // 価格表と統一: S=上位ディーラー / A=標準ディーラー / B=新規開拓・直販
    const RANK_COLORS = { S:'#7c3aed', A:'#2563eb', B:'#059669' };
    const RANK_LABELS = { S:'上位ディーラー', A:'標準ディーラー', B:'新規開拓・直販' };
    const MATCH_META = {
        mf:        { label:'MF確定',   color:'#1d4ed8', bg:'#dbeafe' },
        dup:       { label:'重複候補', color:'#b45309', bg:'#fef3c7' },
        unmatched: { label:'未照合',   color:'#6b7280', bg:'#f3f4f6' }
    };
    function matchBadge(ms){ const m = MATCH_META[ms] || MATCH_META.unmatched; return '<span style="display:inline-block; padding:0.1rem 0.5rem; border-radius:9999px; font-size:0.72rem; font-weight:600; color:'+m.color+'; background:'+m.bg+';">'+m.label+'</span>'; }
    let employees = [], currentDetail = null, allRows = [], inited = false, lastCounts = null;
    let cdPage = 1, cdPageSize = 50;

    function rankBadge(el, rank) {
        if (rank && RANK_COLORS[rank]) { el.textContent = rank; el.className = 'cd-rank'; el.style.background = RANK_COLORS[rank]; }
        else { el.textContent = '—'; el.className = 'cd-rank empty'; el.style.background = ''; }
    }
    async function apiGet(params) {
        const res = await fetch('/api/customer-directory.php?' + new URLSearchParams(params), { headers: { 'X-CSRF-Token': csrfToken } });
        const json = await res.json();
        if (!json.success) throw new Error(json.error || '取得に失敗しました');
        return json.data;
    }
    async function apiPost(payload) {
        const res = await fetch('/api/customer-directory.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify(payload) });
        const json = await res.json();
        if (!json.success) throw new Error(json.error || '処理に失敗しました');
        return json.data;
    }
    function fmtYen(v) { return (v === null || v === '' || v === undefined) ? '—' : '¥' + Number(v).toLocaleString('ja-JP'); }
    function fmtDate(v) { return v ? String(v).substring(0, 10) : '—'; }

    function statusBadge(st) {
        if (st === '既存') return '<span class="cd-status cd-status-active">既存</span>';
        if (st === '休眠') return '<span class="cd-status cd-status-dormant">休眠</span>';
        return st ? escapeHtml(st) : '—';
    }
    function rankCell(r) {
        const cur = r.rank ? '<span class="cd-rank" style="background:' + (RANK_COLORS[r.rank]||'') + '">' + escapeHtml(r.rank) + '</span>' : '<span class="cd-rank empty">—</span>';
        const ch = r.rank_challenge ? '<span class="cd-rank-ch">→' + escapeHtml(r.rank_challenge) + '</span>' : '';
        return cur + ch;
    }

    async function loadList() {
        const body = document.getElementById('cdListBody');
        body.innerHTML = '<tr><td colspan="7" class="cd-loading">読み込み中…</td></tr>';
        try {
            const data = await apiGet({
                action: 'list',
                q: document.getElementById('cdSearch').value.trim(),
                rank: document.getElementById('cdRankFilter').value,
                status: document.getElementById('cdStatusFilter').value,
                tanto: document.getElementById('cdTantoFilter').value
            });
            allRows = data.customers; lastCounts = data.counts || null; cdPage = 1;
            populateTanto(data.tanto_list || []);
            renderList();
        } catch (e) { body.innerHTML = '<tr><td colspan="7" class="cd-loading">' + escapeHtml(e.message) + '</td></tr>'; }
    }
    let tantoPopulated = false;
    function populateTanto(list) {
        if (tantoPopulated) return;
        const sel = document.getElementById('cdTantoFilter');
        list.forEach(t => { const o = document.createElement('option'); o.value = t; o.textContent = t; sel.appendChild(o); });
        tantoPopulated = true;
    }
    function renderList() {
        const body = document.getElementById('cdListBody');
        let countText = allRows.length + '件';
        if (lastCounts) countText += '（全体: 既存 ' + (lastCounts['既存']||0) + ' / 休眠 ' + (lastCounts['休眠']||0) + '）';
        document.getElementById('cdCount').textContent = countText;
        if (!allRows.length) { body.innerHTML = '<tr><td colspan="7" class="cd-loading">該当するアカウントがありません</td></tr>'; renderPager(0, 0); return; }

        const totalPages = Math.max(1, Math.ceil(allRows.length / cdPageSize));
        if (cdPage > totalPages) cdPage = totalPages;
        if (cdPage < 1) cdPage = 1;
        const start = (cdPage - 1) * cdPageSize;
        const pageRows = allRows.slice(start, start + cdPageSize);

        body.innerHTML = '';
        pageRows.forEach(r => {
            const tr = document.createElement('tr'); tr.className = 'cd-row'; tr._cdId = r.id;
            if (r.id === activeRowId) tr.classList.add('cd-row-active');
            const nameCell = escapeHtml(r.companyName) + ' ' +
                (r.match_status !== 'mf' ? '<span class="cd-match-mini">' + (r.match_status === 'dup' ? '重複候補' : '未照合') + '</span>' : '');
            tr.innerHTML =
                '<td class="cd-amno">' + escapeHtml(r.am_number || '') + '</td>' +
                '<td>' + nameCell + '</td>' +
                '<td>' + statusBadge(r.account_status) + '</td>' +
                '<td>' + (r.am_person ? escapeHtml(r.am_person) : '<span style="color:var(--gray-400)">--</span>') + '</td>' +
                '<td>' + rankCell(r) + '</td>' +
                '<td>' + (r.priority && r.priority !== '-' ? escapeHtml(r.priority) : '--') + '</td>' +
                '<td class="cd-arrow">></td>';
            tr.addEventListener('click', () => openDetail(r.id));
            body.appendChild(tr);
        });
        renderPager(allRows.length, totalPages);
    }

    // ページネーション描画（表示件数選択＋前後＋ページ番号）
    function renderPager(total, totalPages) {
        const pager = document.getElementById('cdPager');
        if (!pager) return;
        if (total === 0) { pager.innerHTML = ''; return; }
        const sizeSel = '<span class="cd-pgsize">表示件数 <select id="cdPageSizeSel">' +
            [30, 50, 100].map(n => '<option value="' + n + '"' + (n === cdPageSize ? ' selected' : '') + '>' + n + '</option>').join('') +
            '</select></span>';
        if (totalPages <= 1) { pager.innerHTML = sizeSel; bindPager(); return; }

        // 表示するページ番号（先頭/末尾/現在周辺）
        const nums = [];
        const push = (n) => { if (!nums.includes(n) && n >= 1 && n <= totalPages) nums.push(n); };
        push(1); push(2);
        for (let i = cdPage - 1; i <= cdPage + 1; i++) push(i);
        push(totalPages - 1); push(totalPages);
        nums.sort((a, b) => a - b);

        let btns = '<button type="button" class="cd-pg-btn" data-pg="prev"' + (cdPage <= 1 ? ' disabled' : '') + '>‹</button>';
        let prev = 0;
        nums.forEach(n => {
            if (prev && n - prev > 1) btns += '<span class="cd-pg-ellipsis">…</span>';
            btns += '<button type="button" class="cd-pg-btn' + (n === cdPage ? ' active' : '') + '" data-pg="' + n + '">' + n + '</button>';
            prev = n;
        });
        btns += '<button type="button" class="cd-pg-btn" data-pg="next"' + (cdPage >= totalPages ? ' disabled' : '') + '>›</button>';
        pager.innerHTML = sizeSel + btns;
        bindPager();
    }

    function bindPager() {
        const pager = document.getElementById('cdPager');
        if (!pager) return;
        const sel = document.getElementById('cdPageSizeSel');
        if (sel) sel.addEventListener('change', () => {
            cdPageSize = parseInt(sel.value, 10) || 50;
            cdPage = 1;
            renderList();
        });
        pager.querySelectorAll('.cd-pg-btn[data-pg]').forEach(b => {
            b.addEventListener('click', () => {
                const v = b.dataset.pg;
                const totalPages = Math.max(1, Math.ceil(allRows.length / cdPageSize));
                if (v === 'prev') cdPage = Math.max(1, cdPage - 1);
                else if (v === 'next') cdPage = Math.min(totalPages, cdPage + 1);
                else cdPage = parseInt(v, 10) || 1;
                renderList();
                document.getElementById('cdListView').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }
    const $drawer = document.getElementById('cdDrawer');
    let activeRowId = null;

    function openDrawer() { $drawer.classList.add('active'); }
    function closeDrawer() {
        $drawer.classList.remove('active');
        activeRowId = null;
        document.querySelectorAll('#cdListBody .cd-row-active').forEach(r => r.classList.remove('cd-row-active'));
    }
    function highlightRow(id) {
        document.querySelectorAll('#cdListBody .cd-row-active').forEach(r => r.classList.remove('cd-row-active'));
        document.querySelectorAll('#cdListBody .cd-row').forEach(r => {
            if (r._cdId === id) r.classList.add('cd-row-active');
        });
        activeRowId = id;
    }

    document.getElementById('cdDrawerClose').addEventListener('click', closeDrawer);

    async function openDetail(id) {
        // 即座にドロワーを開く（ローディング状態）
        highlightRow(id);
        document.getElementById('cdDetailName').textContent = '読み込み中...';
        document.getElementById('cdDetailRank').textContent = '--';
        document.getElementById('cdDetailRank').className = 'cd-rank empty';
        document.getElementById('cdDetailRank').style.background = '';
        var mb = document.getElementById('cdDetailMatch'); if (mb) mb.innerHTML = '';
        document.getElementById('cdAmBlock').innerHTML = '<div class="cd-am-info"><span class="cd-am-meta" style="color:var(--gray-400)">読み込み中...</span></div>';
        document.getElementById('cdCcList').innerHTML = '';
        document.getElementById('cdInfoGrid').innerHTML = '<div style="text-align:center;color:var(--gray-400);padding:1rem;">読み込み中...</div>';
        document.getElementById('cdRankNote').textContent = '';
        document.getElementById('cdMemo').textContent = '';
        document.getElementById('cdRecentBody').innerHTML = '<tr><td colspan="4" style="color:var(--gray-400);text-align:center;">読み込み中...</td></tr>';
        openDrawer();
        // スクロールをトップに戻す
        $drawer.querySelector('.cd-drawer-body').scrollTop = 0;

        try {
            const d = await apiGet({ action: 'detail', id }); currentDetail = d;
            document.getElementById('cdDetailName').textContent = d.customer.companyName;
            rankBadge(document.getElementById('cdDetailRank'), d.rank);
            if (mb) mb.innerHTML = matchBadge(d.match_status);
            const amBlock = document.getElementById('cdAmBlock');
            if (d.am) {
                amBlock.innerHTML = '<div class="cd-am-avatar">' + escapeHtml((d.am.name||'?').substring(0,1)) + '</div>' +
                    '<div class="cd-am-info"><div class="cd-am-name">' + escapeHtml(d.am.name) + '</div>' +
                    '<div class="cd-am-meta">' + escapeHtml(d.am.email||'') + (d.am.phone ? ' / ' + escapeHtml(d.am.phone) : '') + '</div></div>' +
                    '<button type="button" class="btn btn-sm btn-secondary" id="cdAmMailBtn">AMへメール</button>';
                const amMailBtn = document.getElementById('cdAmMailBtn');
                if (amMailBtn) amMailBtn.addEventListener('click', openMailCompose);
            } else { amBlock.innerHTML = '<div class="cd-am-info"><span class="cd-am-meta">未設定</span></div>'; }
            renderCc(d.cc);
            const cust = d.customer;
            const rows = [
                ['AMナンバー', cust.am_number || '--'],
                ['ステータス', cust.account_status || '--'],
                ['種別', cust.account_type || '--'],
                ['種別メモ', cust.account_type_memo || '--'],
                ['本社所在地', cust.hq_location || '--'],
                ['優先度', (cust.priority && cust.priority !== '-') ? cust.priority : '--'],
                ['担当', cust.am_person || '--'],
                ['直近5ヶ月 合計請求', fmtYen(d.recent5_total)],
                ['直近5ヶ月 月平均', fmtYen(d.recent5_avg)],
            ];
            document.getElementById('cdInfoGrid').innerHTML = rows.map(([k, v]) => '<div class="cd-info-row"><span class="cd-info-label">' + k + '</span><span class="cd-info-val">' + escapeHtml(String(v)) + '</span></div>').join('');
            const rankLabel = d.rank ? (d.rank + '：' + (RANK_LABELS[d.rank] || '')) : '未設定';
            const chLabel = cust.rank_challenge ? '　チャレンジ: ' + cust.rank_challenge + '：' + (RANK_LABELS[cust.rank_challenge] || '') : '';
            document.getElementById('cdRankNote').textContent = 'ランク現在: ' + rankLabel + chLabel + ' ／ MF実績の目安: ' + (d.suggested_rank || '--') + '。';
            const memoEl = document.getElementById('cdMemo');
            if (memoEl) memoEl.textContent = cust.am_memo ? ('メモ: ' + cust.am_memo) : '';

            const rb = document.getElementById('cdRecentBody');
            rb.innerHTML = (d.recent && d.recent.length) ? d.recent.map(iv => '<tr><td>' + escapeHtml(iv.billing_number||'--') + '</td><td>' + escapeHtml(iv.title||'--') + '</td><td>' + fmtDate(iv.billing_date) + '</td><td class="amount">' + fmtYen(iv.total_amount) + '</td></tr>').join('') : '<tr><td colspan="4" style="color:var(--gray-400)">取引履歴がありません</td></tr>';
        } catch (e) { showToast(e.message, 'danger'); closeDrawer(); }
    }
    function renderCc(ccList) {
        const wrap = document.getElementById('cdCcList');
        if (!ccList || !ccList.length) { wrap.innerHTML = '<span style="color:var(--gray-400);font-size:0.85rem;">CC候補が未登録です</span>'; return; }
        wrap.innerHTML = '';
        ccList.forEach(cc => {
            const chip = document.createElement('span'); chip.className = 'cd-cc-chip';
            let html = '<span>' + escapeHtml(cc.name || cc.email || '名称未設定') + '</span>';
            if (cc.role_label) html += '<span class="cd-cc-role">' + escapeHtml(cc.role_label) + '</span>';
            chip.innerHTML = html;
            if (canEdit) {
                const edit = document.createElement('button'); edit.textContent = '✎'; edit.title = '編集'; edit.addEventListener('click', () => openCcModal(cc));
                const del = document.createElement('button'); del.textContent = '×'; del.title = '削除'; del.addEventListener('click', () => deleteCc(cc.id));
                chip.appendChild(edit); chip.appendChild(del);
            }
            wrap.appendChild(chip);
        });
    }
    function openMailCompose() {
        if (!currentDetail) return;
        const to = currentDetail.am && currentDetail.am.email ? currentDetail.am.email : '';
        const ccEmails = [];
        if (currentDetail.am && currentDetail.am.email) ccEmails.push(currentDetail.am.email);
        (currentDetail.cc || []).forEach(cc => { if (cc.email) ccEmails.push(cc.email); });
        const uniqueCc = [...new Set(ccEmails)];
        document.getElementById('cdMailTo').value = to || '（主担当AMのメール未設定）';
        document.getElementById('cdMailCc').value = uniqueCc.join(', ');
        document.getElementById('cdMailOpen').href = 'mailto:' + encodeURIComponent(to) + '?cc=' + encodeURIComponent(uniqueCc.join(',')) + '&subject=' + encodeURIComponent('【' + currentDetail.customer.companyName + '】');
        openModal('cdMailModal');
    }
    document.getElementById('cdMailBtn').addEventListener('click', openMailCompose);
    document.getElementById('cdMailCopy').addEventListener('click', () => {
        navigator.clipboard.writeText('To: ' + document.getElementById('cdMailTo').value + '\nCc: ' + document.getElementById('cdMailCc').value).then(() => showToast('宛先をコピーしました', 'success'));
    });
    let searchTimer;
    document.getElementById('cdSearch').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadList, 250); });
    document.getElementById('cdRankFilter').addEventListener('change', loadList);
    document.getElementById('cdStatusFilter').addEventListener('change', loadList);
    document.getElementById('cdTantoFilter').addEventListener('change', loadList);

    async function loadEmployees() {
        try {
            const data = await apiGet({ action: 'employees' }); employees = data.employees;
            if (canEdit) {
                ['cdEditAm', 'cdCcEmployee'].forEach(selId => {
                    const sel = document.getElementById(selId); if (!sel) return;
                    employees.forEach(e => { const o = document.createElement('option'); o.value = e.id; o.textContent = e.name + (e.department ? '（' + e.department + '）' : ''); o.dataset.email = e.email || ''; o.dataset.name = e.name || ''; sel.appendChild(o); });
                });
            }
        } catch (e) {}
    }

<?php if ($cdCanEdit): ?>
    document.getElementById('cdEditBtn').addEventListener('click', () => {
        if (!currentDetail) return; const c = currentDetail.customer;
        document.getElementById('cdEditId').value = c.id;
        document.getElementById('cdEditAmNumber').value = c.am_number || '';
        document.getElementById('cdEditStatus').value = c.account_status || '';
        document.getElementById('cdEditType').value = c.account_type || '';
        document.getElementById('cdEditTypeMemo').value = c.account_type_memo || '';
        document.getElementById('cdEditHq').value = c.hq_location || '';
        document.getElementById('cdEditPriority').value = (c.priority && c.priority !== '-') ? c.priority : '';
        document.getElementById('cdEditTanto').value = c.am_person || '';
        document.getElementById('cdEditRank').value = c.customer_rank || '';
        document.getElementById('cdEditRankCh').value = c.rank_challenge || '';
        document.getElementById('cdEditAm').value = c.am_employee_id || '';
        document.getElementById('cdEditMemo').value = c.am_memo || '';
        const sg = currentDetail.suggested_rank;
        const hint = document.getElementById('cdSuggestHint');
        hint.innerHTML = '';
        const span = document.createElement('span');
        span.textContent = 'MF実績の目安: ' + (sg || '—') + '（年間請求 ' + fmtYen(currentDetail.annual_sales) + '）';
        hint.appendChild(span);
        if (sg) {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'btn btn-sm btn-secondary'; b.textContent = '目安を採用';
            b.addEventListener('click', () => { document.getElementById('cdEditRank').value = sg; });
            hint.appendChild(b);
        }
        openModal('cdEditModal');
    });
    document.getElementById('cdEditForm').addEventListener('submit', async (e) => {
        e.preventDefault(); const btn = e.target.querySelector('[type="submit"]'); btn.disabled = true;
        try {
            await apiPost({ action: 'update_basic', id: document.getElementById('cdEditId').value,
                am_employee_id: document.getElementById('cdEditAm').value,
                rank: document.getElementById('cdEditRank').value,
                rank_challenge: document.getElementById('cdEditRankCh').value,
                am_number: document.getElementById('cdEditAmNumber').value,
                account_status: document.getElementById('cdEditStatus').value,
                account_type: document.getElementById('cdEditType').value,
                account_type_memo: document.getElementById('cdEditTypeMemo').value,
                hq_location: document.getElementById('cdEditHq').value,
                priority: document.getElementById('cdEditPriority').value,
                am_person: document.getElementById('cdEditTanto').value,
                am_memo: document.getElementById('cdEditMemo').value });
            showToast('保存しました', 'success'); closeModal('cdEditModal'); await openDetail(document.getElementById('cdEditId').value); loadList();
        } catch (err) { showToast(err.message, 'danger'); } finally { btn.disabled = false; }
    });
    document.getElementById('cdCcAddBtn').addEventListener('click', () => openCcModal(null));
    document.getElementById('cdCcEmployee').addEventListener('change', (e) => {
        const opt = e.target.selectedOptions[0];
        if (opt && opt.value) { document.getElementById('cdCcName').value = opt.dataset.name || ''; document.getElementById('cdCcEmail').value = opt.dataset.email || ''; }
    });
    function openCcModal(cc) {
        document.getElementById('cdCcForm').reset();
        document.getElementById('cdCcModalTitle').textContent = cc ? 'CC候補を編集' : 'CC候補を追加';
        document.getElementById('cdCcId').value = cc ? cc.id : '';
        document.getElementById('cdCcEmployee').value = cc && cc.employee_id ? cc.employee_id : '';
        document.getElementById('cdCcName').value = cc ? (cc.name || '') : '';
        document.getElementById('cdCcEmail').value = cc ? (cc.email || '') : '';
        document.getElementById('cdCcRole').value = cc ? (cc.role_label || '') : '';
        document.getElementById('cdCcNote').value = cc ? (cc.note || '') : '';
        openModal('cdCcModal');
    }
    document.getElementById('cdCcForm').addEventListener('submit', async (e) => {
        e.preventDefault(); const btn = e.target.querySelector('[type="submit"]'); btn.disabled = true;
        const id = document.getElementById('cdCcId').value;
        const payload = { action: id ? 'cc_update' : 'cc_add', employee_id: document.getElementById('cdCcEmployee').value, name: document.getElementById('cdCcName').value, email: document.getElementById('cdCcEmail').value, role_label: document.getElementById('cdCcRole').value, note: document.getElementById('cdCcNote').value };
        if (id) payload.id = id; else payload.customer_id = currentDetail.customer.id;
        try { await apiPost(payload); showToast('保存しました', 'success'); closeModal('cdCcModal'); await openDetail(currentDetail.customer.id); }
        catch (err) { showToast(err.message, 'danger'); } finally { btn.disabled = false; }
    });
    async function deleteCc(id) {
        if (!confirm('このCC候補を削除しますか？')) return;
        try { await apiPost({ action: 'cc_delete', id }); showToast('削除しました', 'success'); await openDetail(currentDetail.customer.id); }
        catch (err) { showToast(err.message, 'danger'); }
    }
    // MFから同期（ループしない版: 完了時はトースト＋一覧再読込のみ。完了ジョブは即dismiss）
    const syncMfBtn = document.getElementById('cdSyncMfBtn');
    if (syncMfBtn) {
        let mfSyncTimer = null;
        syncMfBtn.addEventListener('click', async () => {
            if (!confirm('MF取引先マスタから顧客を同期しますか？\n・新規取引先は追加されます\n・既存顧客の住所/電話などが補完されます\n・バックグラウンドで実行され、完了すると一覧が更新されます')) return;
            const orig = syncMfBtn.textContent;
            syncMfBtn.disabled = true; syncMfBtn.textContent = '起動中…';
            try {
                const res = await fetch('/api/sync-partners.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken }
                });
                const data = await res.json();
                if (!data.success) { showToast('同期エラー: ' + (data.error || '失敗しました'), 'danger'); syncMfBtn.disabled = false; syncMfBtn.textContent = orig; return; }
                if (!data.job_id) { showToast(data.message || '同期しました', 'success'); syncMfBtn.disabled = false; syncMfBtn.textContent = orig; loadList(); return; }
                showToast('MF同期を開始しました（バックグラウンド実行）', 'info');
                if (typeof window.checkBackgroundJobs === 'function') window.checkBackgroundJobs();
                // 完了監視
                if (mfSyncTimer) clearInterval(mfSyncTimer);
                const started = Math.floor(Date.now() / 1000);
                let tries = 0;
                mfSyncTimer = setInterval(async () => {
                    tries++;
                    if (tries > 150) { clearInterval(mfSyncTimer); mfSyncTimer = null; syncMfBtn.disabled = false; syncMfBtn.textContent = orig; return; }
                    try {
                        const r = await fetch('/api/background-job.php?action=active');
                        const jobs = (await r.json()).jobs || {};
                        const job = Object.values(jobs).find(x => x.id === data.job_id);
                        if (!job) return;
                        if (job.status === 'completed' && (job.completed_at || 0) >= started) {
                            clearInterval(mfSyncTimer); mfSyncTimer = null;
                            fetch('/api/background-job.php?action=dismiss&job_id=' + encodeURIComponent(data.job_id)).catch(() => {});
                            syncMfBtn.disabled = false; syncMfBtn.textContent = orig;
                            showToast('MF同期が完了しました。一覧を更新します', 'success');
                            loadList(); // 強制リロードせず一覧だけ更新（ループ防止）
                        } else if (job.status === 'failed' && (job.completed_at || 0) >= started) {
                            clearInterval(mfSyncTimer); mfSyncTimer = null;
                            fetch('/api/background-job.php?action=dismiss&job_id=' + encodeURIComponent(data.job_id)).catch(() => {});
                            syncMfBtn.disabled = false; syncMfBtn.textContent = orig;
                            showToast('MF同期に失敗: ' + (job.error || job.message || '不明'), 'danger');
                        }
                    } catch (_) {}
                }, 2000);
            } catch (e) {
                showToast('同期エラー: ' + e.message, 'danger');
                syncMfBtn.disabled = false; syncMfBtn.textContent = orig;
            }
        });
    }

    const recomputeBtn = document.getElementById('cdRecomputeBtn');
    if (recomputeBtn) {
        recomputeBtn.addEventListener('click', async () => {
            recomputeBtn.disabled = true; const orig = recomputeBtn.textContent; recomputeBtn.textContent = '再計算中…';
            try { const d = await apiPost({ action: 'recompute_ranks' }); showToast(d.updated + '件のランクを更新しました', 'success'); loadList(); }
            catch (err) { showToast(err.message, 'danger'); } finally { recomputeBtn.disabled = false; recomputeBtn.textContent = orig; }
        });
    }
<?php endif; ?>

    // モーダルの閉じる(✕)・キャンセルボタンを配線（common-utils は自動配線しない）
    const CD_MODALS = ['cdEditModal', 'cdCcModal', 'cdMailModal'];
    document.addEventListener('click', e => {
        const cb = e.target.closest('[data-close-modal]');
        if (cb && CD_MODALS.includes(cb.dataset.closeModal)) closeModal(cb.dataset.closeModal);
    });

    // タブ遅延初期化（顧客タブが表示されたら1回だけロード）
    function cdInit() { if (inited) return; inited = true; loadEmployees().then(loadList); }
    if (document.querySelector('#panel-customers.active')) cdInit();
    document.querySelectorAll('.st-tab[data-tab="customers"]').forEach(t => t.addEventListener('click', cdInit));
})();
</script>
