<?php /* sales-tools JavaScript (Sprint 1 抽出) */ ?>
<script<?= nonceAttr() ?>>
(function() {
    // タブ切り替え(クライアントサイドでも反応)
    document.querySelectorAll('.st-tab').forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            // URLパラメータでも切り替わるが、SPA的にも動かす
            var target = tab.dataset.tab;
            if (!target) return;
            // モディファイアキーや middleclick はそのまま通す
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
            e.preventDefault();
            document.querySelectorAll('.st-tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.st-panel').forEach(function(p) { p.classList.remove('active'); });
            tab.classList.add('active');
            var panel = document.getElementById('panel-' + target);
            if (panel) panel.classList.add('active');
            // URLパラメータも更新(履歴に残す)
            var url = new URL(window.location.href);
            url.searchParams.set('tab', target);
            window.history.replaceState(null, '', url.toString());
        });
    });

    // 検索フィルタ(製品別タブのカードを絞り込み)
    var searchInput = document.getElementById('stSearchInput');
    var emptyState = document.getElementById('stEmptyState');
    if (searchInput) {
        // 200ms debounce で統一 (UI統一ガイドライン)
        var stSearchTimer = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(stSearchTimer);
            stSearchTimer = setTimeout(function(){
                var q = (searchInput.value || '').trim().toLowerCase();
                var anyVisible = false;
                document.querySelectorAll('#stProductGrid .st-product-card').forEach(function(card) {
                    var name = card.dataset.searchName || '';
                    var match = q === '' || name.indexOf(q) !== -1;
                    card.style.display = match ? '' : 'none';
                    if (match) anyVisible = true;
                });
                if (emptyState) emptyState.style.display = anyVisible ? 'none' : '';
            }, 200);
        });
    }

    // 製品カードは <a target="_blank"> としてサーバ側で生成済み。
    // 一部のブラウザ拡張機能が target 属性を剥がして同タブ遷移にする問題があるため、
    // JS でも明示的に新規タブで開くようにフックする（防御的二重化）。
    document.querySelectorAll('#stProductGrid a.st-product-card.is-clickable').forEach(function(card){
        var url = card.getAttribute('href');
        if (!url || url === '#') return;
        card.addEventListener('click', function(e){
            // Ctrl/Cmd/Shift/middleclick はブラウザのデフォルト挙動に任せる
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
            // 既定動作（同タブ遷移）を止めて、明示的に新規タブで開く
            e.preventDefault();
            window.open(url, '_blank', 'noopener,noreferrer');
        });
    });

    // ========== 見積作成(Quote Builder) ==========
    // マスターは functions/sales-master.php から PHP 経由で注入
    var productMaster  = <?= json_encode(getDemoProductMaster(),  JSON_UNESCAPED_UNICODE) ?>;
    var customerMaster = <?= json_encode(getDemoCustomerMaster(), JSON_UNESCAPED_UNICODE) ?>;

    var typeLabels = {
        product: '製品',
        install: '施工費',
        shipping: '配送費',
        other: 'その他'
    };

    var itemList = document.getElementById('qbItemList');
    var emptyBox = document.getElementById('qbEmpty');
    var totalsBox = document.getElementById('qbTotals');

    function formatYen(n) {
        if (isNaN(n) || n === null) n = 0;
        return Math.round(n).toLocaleString('ja-JP') + ' 円';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function(c) {
            return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
        });
    }

    function recalcAll() {
        var items = document.querySelectorAll('#qbItemList .qb-item');
        var subtotal = 0;
        items.forEach(function(row) {
            var qty = parseFloat(row.querySelector('.qb-qty').value) || 0;
            var price = parseFloat(row.querySelector('.qb-price').value) || 0;
            var sub = qty * price;
            row.querySelector('.qb-subtotal').textContent = formatYen(sub);
            subtotal += sub;
        });
        var tax = Math.floor(subtotal * 0.1);
        document.getElementById('qbSubtotal').textContent = formatYen(subtotal);
        document.getElementById('qbTax').textContent = formatYen(tax);
        document.getElementById('qbGrand').textContent = formatYen(subtotal + tax);

        var hasItems = items.length > 0;
        if (emptyBox) emptyBox.style.display = hasItems ? 'none' : '';
        if (totalsBox) totalsBox.style.display = hasItems ? '' : 'none';
    }

    function buildItemRow(type) {
        var row = document.createElement('div');
        row.className = 'qb-item';
        row.dataset.type = type;
        var label = typeLabels[type] || 'その他';

        var nameHtml;
        if (type === 'product') {
            var options = '<option value="">製品を選択...</option>';
            productMaster.forEach(function(p) {
                options += '<option value="' + escapeHtml(p.id) + '" data-price="' + p.price + '">' + escapeHtml(p.name) + '</option>';
            });
            nameHtml = '<select class="form-input qb-name qb-name-product">' + options + '</select>';
        } else {
            var ph = type === 'install' ? '例: 取付工事' : type === 'shipping' ? '例: 配送費(東京→大阪)' : '例: 諸経費';
            nameHtml = '<input type="text" class="form-input qb-name" placeholder="' + escapeHtml(ph) + '">';
        }

        row.innerHTML =
            '<span class="qb-item-type ' + type + '">' + escapeHtml(label) + '</span>' +
            nameHtml +
            '<input type="number" class="form-input qb-qty" min="0" step="1" placeholder="数量" value="1">' +
            '<input type="number" class="form-input qb-price" min="0" step="1" placeholder="単価" value="0">' +
            '<span class="qb-subtotal">0 円</span>' +
            '<span></span>' +
            '<button type="button" class="qb-delete" title="削除" aria-label="削除">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                    '<polyline points="3 6 5 6 21 6"/>' +
                    '<path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/>' +
                    '<path d="M10 11v6"/><path d="M14 11v6"/>' +
                '</svg>' +
            '</button>';

        // 製品セレクト → 価格自動セット
        if (type === 'product') {
            row.querySelector('.qb-name-product').addEventListener('change', function(e) {
                var opt = e.target.options[e.target.selectedIndex];
                var p = parseFloat(opt.getAttribute('data-price')) || 0;
                row.querySelector('.qb-price').value = p;
                recalcAll();
            });
        }

        // 数量・単価変更で再計算
        row.querySelector('.qb-qty').addEventListener('input', recalcAll);
        row.querySelector('.qb-price').addEventListener('input', recalcAll);

        // 削除
        row.querySelector('.qb-delete').addEventListener('click', function() {
            row.remove();
            recalcAll();
        });

        return row;
    }

    // 追加ボタン
    document.querySelectorAll('.qb-add-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var type = btn.dataset.addType;
            var row = buildItemRow(type);
            if (itemList) {
                itemList.appendChild(row);
                recalcAll();
                var firstInput = row.querySelector('select, input');
                if (firstInput) firstInput.focus();
            }
        });
    });

    // 顧客検索 → ランク・AM表示(簡易)
    var custInput = document.getElementById('qbCustomer');
    var rankHint = document.getElementById('qbRankHint');
    var rankBadge = document.getElementById('qbRankBadge');
    var amName = document.getElementById('qbAmName');
    if (custInput) {
        custInput.addEventListener('input', function() {
            var q = custInput.value.trim();
            if (!q) { rankHint.style.display = 'none'; return; }
            var hit = customerMaster.find(function(c) { return c.name.indexOf(q) !== -1 || q.indexOf(c.name) !== -1; });
            if (hit) {
                rankBadge.innerHTML = '<span class="qb-rank-badge ' + hit.rank.toLowerCase() + '">' + hit.rank + '</span>';
                amName.textContent = hit.am;
                rankHint.style.display = '';
            } else {
                rankHint.style.display = 'none';
            }
        });
    }

    // 日付の初期値(見積日=今日 / 有効期限=30日後)
    var todayStr = new Date().toISOString().slice(0, 10);
    var expire = new Date(); expire.setDate(expire.getDate() + 30);
    var expireStr = expire.toISOString().slice(0, 10);
    var issueDateEl = document.getElementById('qbIssueDate');
    var expireDateEl = document.getElementById('qbExpireDate');
    if (issueDateEl && !issueDateEl.value) issueDateEl.value = todayStr;
    if (expireDateEl && !expireDateEl.value) expireDateEl.value = expireStr;

    // 保存・PDF・クリア(現状はプレースホルダ動作)
    var saveBtn = document.getElementById('qbSaveBtn');
    var pdfBtn = document.getElementById('qbPdfBtn');
    var resetBtn = document.getElementById('qbResetBtn');
    if (saveBtn) saveBtn.addEventListener('click', function() {
        var payload = {
            subject: document.getElementById('qbSubject').value,
            customer: document.getElementById('qbCustomer').value,
            issueDate: issueDateEl ? issueDateEl.value : '',
            expireDate: expireDateEl ? expireDateEl.value : '',
            items: Array.from(document.querySelectorAll('#qbItemList .qb-item')).map(function(r) {
                var nameEl = r.querySelector('.qb-name');
                return {
                    type: r.dataset.type,
                    name: nameEl.tagName === 'SELECT' ? (nameEl.options[nameEl.selectedIndex] || {}).text || '' : nameEl.value,
                    qty: parseFloat(r.querySelector('.qb-qty').value) || 0,
                    price: parseFloat(r.querySelector('.qb-price').value) || 0
                };
            })
        };
        console.log('[QuoteBuilder] save payload:', payload);
        if (typeof showToast === 'function') {
            showToast('見積を保存しました(現状は画面のみ・サーバ未連携)', 'success', 3500);
        } else {
            alert('保存対象(コンソール出力):\n' + JSON.stringify(payload, null, 2));
        }
    });
    if (pdfBtn) pdfBtn.addEventListener('click', function() {
        if (typeof showToast === 'function') {
            showToast('PDF出力は次フェーズで実装予定です', 'info', 3000);
        } else {
            alert('PDF出力は次フェーズで実装予定です');
        }
    });
    if (resetBtn) resetBtn.addEventListener('click', function() {
        if (!confirm('入力をクリアしますか?')) return;
        document.getElementById('qbSubject').value = '';
        document.getElementById('qbCustomer').value = '';
        rankHint.style.display = 'none';
        if (issueDateEl) issueDateEl.value = todayStr;
        if (expireDateEl) expireDateEl.value = expireStr;
        if (itemList) itemList.innerHTML = '';
        recalcAll();
    });

    // 初期状態の空表示
    recalcAll();

    // ========== リード管理 ==========
    var CSRF = <?= json_encode($csrfToken) ?>;
    var CAN_EDIT_LEAD = <?= $canEditLead ? 'true' : 'false' ?>;
    var CAN_DELETE_LEAD = <?= $canDeleteLead ? 'true' : 'false' ?>;
    var CURRENT_USER_NAME = <?= json_encode($currentUserName) ?>;

    var leadsCache = [];
    var leadFilterQuery = '';
    var leadFilterStatus = '';
    var leadEditingId = null;
    var leadPendingImageDataUrl = '';

    // 複数名刺スキャン用キュー
    var leadScanQueue = [];
    var leadScanTotal = 0;
    var leadScanStats = { saved: 0, skipped: 0, errors: 0 };
    var leadInQueueMode = false;

    var $leadSearch = document.getElementById('leadSearch');
    var $leadStatusFilter = document.getElementById('leadStatusFilter');
    var $leadTbody = document.getElementById('leadTbody');
    var $leadEmpty = document.getElementById('leadEmpty');
    var $leadStatusSummary = document.getElementById('leadStatusSummary');
    var $leadTable = document.getElementById('leadTable');
    var $leadModal = document.getElementById('leadModal');
    var $leadModalTitle = document.getElementById('leadModalTitle');
    var $leadModalImageWrap = document.getElementById('leadModalImageWrap');
    var $leadModalImage = document.getElementById('leadModalImage');
    var $leadScanInput = document.getElementById('leadScanInput');
    var $leadScanOverlay = document.getElementById('leadScanOverlay');

    var leadStatusList = ['新規','接触済','商談中','成約','失注'];

    function leadShowToast(msg, type) {
        if (typeof showToast === 'function') showToast(msg, type || 'info', 3500);
        else if (type === 'error') alert(msg);
    }

    function leadOpenModal() {
        $leadModal.classList.add('open');
        $leadModal.setAttribute('aria-hidden', 'false');
        setTimeout(function(){ document.getElementById('leadFCompany').focus(); }, 50);
    }
    function leadCloseModal() {
        $leadModal.classList.remove('open');
        $leadModal.setAttribute('aria-hidden', 'true');
        leadEditingId = null;
        leadPendingImageDataUrl = '';
        $leadModalImage.src = '';
        $leadModalImageWrap.style.display = 'none';
    }
    function leadHandleCancelClose() {
        var wasInQueue = leadInQueueMode;
        leadCloseModal();
        if (wasInQueue) leadQueueAdvance('skipped');
    }
    document.querySelectorAll('[data-close-modal]').forEach(function(el){
        el.addEventListener('click', leadHandleCancelClose);
    });
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && $leadModal.classList.contains('open')) leadHandleCancelClose();
    });

    function leadResetForm() {
        ['leadFCompany','leadFPerson','leadFTitle','leadFDept','leadFPhone','leadFMobile',
         'leadFEmail','leadFFax','leadFWebsite','leadFAm','leadFAddress','leadFNotes']
        .forEach(function(id){ document.getElementById(id).value = ''; });
        document.getElementById('leadFStatus').value = '新規';
        $leadModalImageWrap.style.display = 'none';
        $leadModalImage.src = '';
    }

    function leadFillForm(lead) {
        document.getElementById('leadFCompany').value = lead.company_name || '';
        document.getElementById('leadFPerson').value  = lead.person_name || '';
        document.getElementById('leadFTitle').value   = lead.title || '';
        document.getElementById('leadFDept').value    = lead.department || '';
        document.getElementById('leadFPhone').value   = lead.phone || '';
        document.getElementById('leadFMobile').value  = lead.mobile || '';
        document.getElementById('leadFEmail').value   = lead.email || '';
        document.getElementById('leadFFax').value     = lead.fax || '';
        document.getElementById('leadFWebsite').value = lead.website || '';
        document.getElementById('leadFAm').value      = lead.am || '';
        document.getElementById('leadFAddress').value = lead.address || '';
        document.getElementById('leadFNotes').value   = lead.notes || '';
        document.getElementById('leadFStatus').value  = lead.status || '新規';

        if (lead.business_card_image_path) {
            $leadModalImage.src = '../' + lead.business_card_image_path;
            $leadModalImageWrap.style.display = '';
        } else {
            $leadModalImageWrap.style.display = 'none';
            $leadModalImage.src = '';
        }
    }

    function leadCollectForm() {
        return {
            company_name: document.getElementById('leadFCompany').value.trim(),
            person_name:  document.getElementById('leadFPerson').value.trim(),
            title:        document.getElementById('leadFTitle').value.trim(),
            department:   document.getElementById('leadFDept').value.trim(),
            phone:        document.getElementById('leadFPhone').value.trim(),
            mobile:       document.getElementById('leadFMobile').value.trim(),
            email:        document.getElementById('leadFEmail').value.trim(),
            fax:          document.getElementById('leadFFax').value.trim(),
            website:      document.getElementById('leadFWebsite').value.trim(),
            am:           document.getElementById('leadFAm').value.trim(),
            address:      document.getElementById('leadFAddress').value.trim(),
            notes:        document.getElementById('leadFNotes').value.trim(),
            status:       document.getElementById('leadFStatus').value
        };
    }

    function leadStatusBadge(s) {
        var safe = leadStatusList.indexOf(s) >= 0 ? s : '新規';
        return '<span class="lead-status-badge s-' + safe + '">' + safe + '</span>';
    }

    function leadRender() {
        var q = leadFilterQuery.toLowerCase();
        var rows = leadsCache.filter(function(l){
            if (leadFilterStatus && l.status !== leadFilterStatus) return false;
            if (!q) return true;
            var hay = ((l.company_name||'') + ' ' + (l.person_name||'') + ' ' +
                       (l.email||'') + ' ' + (l.phone||'') + ' ' + (l.mobile||'') + ' ' +
                       (l.notes||'')).toLowerCase();
            return hay.indexOf(q) !== -1;
        });

        if (rows.length === 0) {
            $leadTable.style.display = 'none';
            $leadEmpty.style.display = '';
        } else {
            $leadTable.style.display = '';
            $leadEmpty.style.display = 'none';
        }

        var html = rows.map(function(l){
            var sourceBadge = l.source === 'business_card'
                ? '<span class="lead-source-badge business_card" title="名刺OCR">名刺</span>'
                : '';
            var contact = '';
            if (l.phone)  contact += '<div class="lead-contact-row">TEL: ' + escapeHtml(l.phone) + '</div>';
            if (l.mobile) contact += '<div class="lead-contact-row">携帯: ' + escapeHtml(l.mobile) + '</div>';
            if (l.email)  contact += '<div class="lead-contact-row">' + escapeHtml(l.email) + '</div>';
            var titleDept = [l.title, l.department].filter(Boolean).map(escapeHtml).join(' / ');
            var createdAt = (l.created_at || '').slice(0, 10);

            var actions = '';
            if (CAN_EDIT_LEAD) {
                actions += '<button type="button" class="lead-ibtn" data-edit="' + escapeHtml(l.id) + '" title="編集">' +
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
                    '</button>';
            }
            if (CAN_DELETE_LEAD) {
                actions += '<button type="button" class="lead-ibtn danger" data-delete="' + escapeHtml(l.id) + '" title="削除">' +
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>' +
                    '</button>';
            }

            return '<tr>' +
                '<td>' +
                    '<div class="lead-company">' + escapeHtml(l.company_name || '(無題)') + sourceBadge + '</div>' +
                    (l.person_name ? '<div class="lead-person">' + escapeHtml(l.person_name) + '</div>' : '') +
                '</td>' +
                '<td>' + (contact || '<span style="color: var(--gray-400);">—</span>') + '</td>' +
                '<td>' + (titleDept || '<span style="color: var(--gray-400);">—</span>') + '</td>' +
                '<td>' + leadStatusBadge(l.status) + '</td>' +
                '<td>' + escapeHtml(createdAt) + '</td>' +
                '<td><div class="lead-row-btns">' + actions + '</div></td>' +
            '</tr>';
        }).join('');
        $leadTbody.innerHTML = html;

        // 行の編集・削除イベント
        $leadTbody.querySelectorAll('[data-edit]').forEach(function(btn){
            btn.addEventListener('click', function(){ leadEdit(btn.getAttribute('data-edit')); });
        });
        $leadTbody.querySelectorAll('[data-delete]').forEach(function(btn){
            btn.addEventListener('click', function(){ leadDelete(btn.getAttribute('data-delete')); });
        });
    }

    function leadRenderStatusSummary(counts) {
        if (!counts) { $leadStatusSummary.innerHTML = ''; return; }
        $leadStatusSummary.innerHTML = leadStatusList.map(function(st){
            return '<span class="chip">' + escapeHtml(st) + ' <b>' + (counts[st] || 0) + '</b></span>';
        }).join('');
    }

    function leadFetch() {
        return fetch('../api/leads-api.php?action=list', { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(j){
                if (!j.success) throw new Error(j.error || '取得失敗');
                leadsCache = j.data.leads || [];
                leadRenderStatusSummary(j.data.status_counts);
                leadRender();
            })
            .catch(function(e){ leadShowToast('リード一覧の取得に失敗: ' + e.message, 'error'); });
    }

    function leadEdit(id) {
        var lead = leadsCache.find(function(l){ return l.id === id; });
        if (!lead) return;
        leadEditingId = id;
        $leadModalTitle.textContent = 'リード編集';
        leadResetForm();
        leadFillForm(lead);
        leadOpenModal();
    }

    function leadDelete(id) {
        var lead = leadsCache.find(function(l){ return l.id === id; });
        if (!lead) return;
        if (!confirm('リード「' + (lead.company_name || '') + '」を削除しますか?')) return;
        fetch('../api/leads-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ action: 'delete', csrf_token: CSRF, id: id })
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) throw new Error(j.error || '削除失敗');
            leadShowToast('リードを削除しました', 'success');
            leadFetch();
        })
        .catch(function(e){ leadShowToast('削除失敗: ' + e.message, 'error'); });
    }

    function leadSave() {
        var payload = leadCollectForm();
        if (!payload.company_name) { leadShowToast('会社名は必須です', 'error'); return; }

        var body = Object.assign({ csrf_token: CSRF }, payload);
        if (leadEditingId) {
            body.action = 'update';
            body.id = leadEditingId;
        } else {
            body.action = 'create';
            body.source = leadPendingImageDataUrl ? 'business_card' : 'manual';
            if (leadPendingImageDataUrl) body.image_data_url = leadPendingImageDataUrl;
            if (!payload.am && CURRENT_USER_NAME) body.am = CURRENT_USER_NAME;
        }

        fetch('../api/leads-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(body)
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) throw new Error(j.error || '保存失敗');
            // 単発（手動追加・編集）はトースト表示。キュー中は最後にまとめて表示
            if (!leadInQueueMode) {
                leadShowToast(leadEditingId ? 'リードを更新しました' : 'リードを登録しました', 'success');
            }
            var wasInQueue = leadInQueueMode;
            leadCloseModal();
            leadFetch();
            if (wasInQueue) leadQueueAdvance('saved');
        })
        .catch(function(e){
            leadShowToast('保存失敗: ' + e.message, 'error');
            // キュー中の保存失敗は error として次に進める
            if (leadInQueueMode) {
                leadCloseModal();
                leadQueueAdvance('error');
            }
        });
    }

    // 手動追加
    document.getElementById('leadAddBtn').addEventListener('click', function(){
        if (!CAN_EDIT_LEAD) { leadShowToast('権限がありません', 'error'); return; }
        leadEditingId = null;
        leadPendingImageDataUrl = '';
        $leadModalTitle.textContent = 'リード登録';
        leadResetForm();
        if (CURRENT_USER_NAME) document.getElementById('leadFAm').value = CURRENT_USER_NAME;
        leadOpenModal();
    });

    // 名刺スキャン
    document.getElementById('leadScanBtn').addEventListener('click', function(){
        if (!CAN_EDIT_LEAD) { leadShowToast('権限がありません', 'error'); return; }
        $leadScanInput.value = '';
        $leadScanInput.click();
    });

    // --- 複数名刺スキャン: キュー処理 ---
    function leadFinishQueue() {
        if (leadScanTotal > 1) {
            var parts = ['名刺スキャン完了: 登録 ' + leadScanStats.saved + '件'];
            if (leadScanStats.skipped > 0) parts.push('スキップ ' + leadScanStats.skipped + '件');
            if (leadScanStats.errors > 0)  parts.push('失敗 ' + leadScanStats.errors + '件');
            leadShowToast(parts.join(' / '), leadScanStats.errors > 0 ? 'warning' : 'success');
        }
        leadScanQueue = [];
        leadScanTotal = 0;
        leadScanStats = { saved: 0, skipped: 0, errors: 0 };
        leadInQueueMode = false;
    }

    function leadQueueAdvance(status) {
        if (!leadInQueueMode) return;
        if (status === 'saved')        leadScanStats.saved++;
        else if (status === 'skipped') leadScanStats.skipped++;
        else if (status === 'error')   leadScanStats.errors++;

        if (leadScanQueue.length === 0) {
            leadFinishQueue();
            return;
        }
        leadProcessNextFromQueue();
    }

    function leadProcessNextFromQueue() {
        var f = leadScanQueue.shift();
        var idx = leadScanTotal - leadScanQueue.length; // 現在処理中の枚数

        // 10MB 超はスキップ
        if (f.size > 10 * 1024 * 1024) {
            leadShowToast('「' + f.name + '」は10MBを超えるためスキップしました', 'error');
            leadQueueAdvance('error');
            return;
        }

        var overlayText = document.getElementById('leadScanOverlayText');
        if (overlayText) {
            overlayText.textContent = leadScanTotal > 1
                ? ('名刺を解析中… (' + idx + ' / ' + leadScanTotal + ')')
                : '名刺を解析中…';
        }
        $leadScanOverlay.classList.add('open');

        var fd = new FormData();
        fd.append('image', f);
        fd.append('csrf_token', CSRF);

        fetch('../api/business-card-ocr.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': CSRF },
            body: fd
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            $leadScanOverlay.classList.remove('open');
            if (!j.success) throw new Error(j.error || 'OCR失敗');
            leadEditingId = null;
            leadPendingImageDataUrl = j.data.image_data_url || '';
            $leadModalTitle.textContent = leadScanTotal > 1
                ? ('名刺の解析結果 (' + idx + ' / ' + leadScanTotal + ')')
                : '名刺の解析結果（内容を確認して保存）';
            leadResetForm();
            leadFillForm(j.data.fields || {});
            if (CURRENT_USER_NAME) document.getElementById('leadFAm').value = CURRENT_USER_NAME;
            if (leadPendingImageDataUrl) {
                $leadModalImage.src = leadPendingImageDataUrl;
                $leadModalImageWrap.style.display = '';
            }
            leadOpenModal();
        })
        .catch(function(e){
            $leadScanOverlay.classList.remove('open');
            leadShowToast('「' + f.name + '」解析失敗: ' + e.message, 'error');
            leadQueueAdvance('error');
        });
    }

    $leadScanInput.addEventListener('change', function(){
        var files = Array.from($leadScanInput.files || []);
        $leadScanInput.value = ''; // 同じファイルを再選択できるようにクリア
        if (files.length === 0) return;

        leadScanQueue  = files.slice();
        leadScanTotal  = files.length;
        leadScanStats  = { saved: 0, skipped: 0, errors: 0 };
        leadInQueueMode = true;
        leadProcessNextFromQueue();
    });

    document.getElementById('leadSaveBtn').addEventListener('click', leadSave);

    // 検索・フィルタ
    var leadSearchTimer;
    $leadSearch.addEventListener('input', function(){
        clearTimeout(leadSearchTimer);
        leadSearchTimer = setTimeout(function(){
            leadFilterQuery = $leadSearch.value.trim();
            leadRender();
        }, 150);
    });
    $leadStatusFilter.addEventListener('change', function(){
        leadFilterStatus = $leadStatusFilter.value;
        leadRender();
    });

    // 初回フェッチ（リードタブがアクティブな時のみ即時実行・他タブからの切替時にも再取得）
    var leadsLoaded = false;
    function leadEnsureLoaded() {
        if (leadsLoaded) return;
        leadsLoaded = true;
        leadFetch();
    }
    if (document.querySelector('#panel-leads.active')) leadEnsureLoaded();
    document.querySelectorAll('.st-tab[data-tab="leads"]').forEach(function(t){
        t.addEventListener('click', function(){ leadEnsureLoaded(); });
    });

    // ========== 価格表（製品リスト → 詳細） ==========
    // 製品定義は config/sales-tools-products.json から読み込む
    // match 文字列を JS RegExp に変換してから利用
    var PP_CONFIG_RAW = <?= json_encode($ppProductsForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    function ppHydrateConfigItem(item) {
        var pattern;
        try { pattern = new RegExp(item.match, item.flags || ''); }
        catch (e) { pattern = /$.^/; /* マッチしない安全な regex */ }
        return {
            id: item.id, name: item.name, sub: item.sub,
            color: item.color, icon: item.icon, match: pattern
        };
    }
    var PP_PRODUCTS = (PP_CONFIG_RAW.products || []).map(ppHydrateConfigItem);
    var PP_COMMON   = (PP_CONFIG_RAW.common   || []).map(ppHydrateConfigItem);

    // 各製品 id に対応する外部リンク（HP）— config/external-links.json 由来
    // 管理: /pages/external-links.php
    var PP_LINKS = <?= json_encode($ppLinksForJs ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var ppData = null;
    var ppLoaded = false;
    var ppListFilter = '';  // 一覧の検索フィルタ
    var PP_FAV_KEY = 'pp_favs_v1';

    function ppGetFavs() {
        try {
            var raw = localStorage.getItem(PP_FAV_KEY);
            var arr = raw ? JSON.parse(raw) : [];
            return Array.isArray(arr) ? arr : [];
        } catch (e) { return []; }
    }
    function ppSetFavs(arr) {
        try { localStorage.setItem(PP_FAV_KEY, JSON.stringify(arr)); } catch (e) {}
    }
    function ppIsFav(id) { return ppGetFavs().indexOf(id) !== -1; }
    function ppToggleFav(id) {
        var favs = ppGetFavs();
        var idx = favs.indexOf(id);
        if (idx >= 0) favs.splice(idx, 1); else favs.push(id);
        ppSetFavs(favs);
    }

    function ppEnsureLoaded() {
        if (ppLoaded) return;
        ppLoaded = true;
        fetch('../api/price-list-get.php', { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(result){
                if (result.success && result.data && result.data.available) {
                    ppData = result.data;
                } else {
                    ppData = { sheets: [] };
                }
                ppRenderList();
            })
            .catch(function(e){
                document.getElementById('ppProductList').innerHTML =
                    '<div class="pp-empty-state">読み込みエラー: ' + escapeHtml(e.message) + '</div>';
            });
    }

    function ppMatchSheets(pattern) {
        if (!ppData || !ppData.sheets) return [];
        return ppData.sheets.filter(function(s){ return pattern.test(s.title || ''); });
    }

    function ppMatchesFilter(item) {
        if (!ppListFilter) return true;
        var q = ppListFilter.toLowerCase();
        return (item.name || '').toLowerCase().indexOf(q) >= 0
            || (item.sub  || '').toLowerCase().indexOf(q) >= 0;
    }

    function ppRenderRow(item, count) {
        var disabled = count === 0;
        var fav = ppIsFav(item.id);
        return '<div class="pp-product-row" data-id="' + escapeHtml(item.id) + '"' + (disabled ? ' style="opacity:0.55;cursor:default;"' : '') + '>' +
            '<button type="button" class="pp-fav-btn ' + (fav ? 'active' : '') + '" data-fav-id="' + escapeHtml(item.id) + '" title="' + (fav ? 'ピンを外す' : 'ピン留めする') + '">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="' + (fav ? 'currentColor' : 'none') + '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>' +
            '</button>' +
            '<div class="pp-product-icon c-' + item.color + '">' +
                '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + item.icon + '</svg>' +
            '</div>' +
            '<div class="pp-product-info">' +
                '<div class="pp-product-name">' + escapeHtml(item.name) + ' 価格表</div>' +
                '<div class="pp-product-sub">' + (disabled ? 'データなし' : escapeHtml(item.sub || 'クリックして表示') + (count > 0 ? '（資料 ' + count + '件）' : '')) + '</div>' +
            '</div>' +
            '<button type="button" class="pp-product-action">' +
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>' +
                '表示' +
            '</button>' +
        '</div>';
    }

    function ppRenderList() {
        var listEl       = document.getElementById('ppProductList');
        var emptyHero    = document.getElementById('ppEmptyHero');
        var reimportBtn  = document.getElementById('ppSyncBtn');
        var statusEl     = document.getElementById('ppSyncStatus');
        var searchWrap   = document.querySelector('#panel-pricing .pp-search-wrap');

        // データなし判定 → 空状態ヒーロー表示、製品リストと検索を隠す
        var hasData = ppData && ppData.sheets && ppData.sheets.length > 0;
        if (!hasData) {
            if (emptyHero) emptyHero.style.display = '';
            listEl.style.display = 'none';
            if (searchWrap) searchWrap.style.display = 'none';
            if (statusEl) statusEl.style.display = 'none';
            if (reimportBtn) reimportBtn.style.display = 'none';
            return;
        }

        if (emptyHero) emptyHero.style.display = 'none';
        listEl.style.display = '';
        if (searchWrap) searchWrap.style.display = '';

        // インポート済み: 同期日時は最小限の表示（admin のみ「再インポート」を表示）
        if (statusEl) {
            statusEl.style.display = '';
            statusEl.textContent = 'インポート済み';
            statusEl.title = ppData.synced_at ? ('最終インポート: ' + ppData.synced_at) : '';
        }
        if (reimportBtn) reimportBtn.style.display = '';

        // お気に入りを上位に表示
        var favs = ppGetFavs();
        var favItems = [];
        var normalItems = [];
        var allItems = PP_PRODUCTS.concat(PP_COMMON);
        allItems.forEach(function(item){
            if (!ppMatchesFilter(item)) return;
            if (favs.indexOf(item.id) >= 0) favItems.push(item);
            else normalItems.push(item);
        });

        // PP_PRODUCTS と PP_COMMON の区別を保つため、ピン以外は元の順序で
        // 「お気に入り」セクション → 「製品」セクション → 「共通参照」セクション
        var html = '';
        if (favItems.length > 0) {
            html += '<div class="pp-divider pp-divider-fav">★ ピン留め</div>';
            favItems.forEach(function(item){
                html += ppRenderRow(item, ppMatchSheets(item.match).length);
            });
        }

        var productNormal = normalItems.filter(function(i){ return PP_PRODUCTS.indexOf(i) >= 0; });
        var commonNormal  = normalItems.filter(function(i){ return PP_COMMON.indexOf(i)   >= 0; });

        if (productNormal.length > 0) {
            if (favItems.length > 0) html += '<div class="pp-divider">製品</div>';
            productNormal.forEach(function(p){ html += ppRenderRow(p, ppMatchSheets(p.match).length); });
        }
        if (commonNormal.length > 0) {
            html += '<div class="pp-divider">共通参照</div>';
            commonNormal.forEach(function(c){ html += ppRenderRow(c, ppMatchSheets(c.match).length); });
        }

        if (html === '') {
            html = '<div class="pp-empty-state">該当する価格表がありません</div>';
        }

        listEl.innerHTML = html;
        listEl.querySelectorAll('.pp-product-row').forEach(function(row){
            row.addEventListener('click', function(e){
                // ピンボタンクリックは詳細表示に飛ばさない
                if (e.target.closest('.pp-fav-btn')) return;
                if (row.style.opacity === '0.55') return;
                ppOpenDetail(row.getAttribute('data-id'));
            });
        });
        listEl.querySelectorAll('.pp-fav-btn').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.stopPropagation();
                ppToggleFav(btn.getAttribute('data-fav-id'));
                ppRenderList(); // 並び直し
            });
        });
    }

    // 一覧検索入力のバインド (200ms debounce で統一)
    var ppListSearchEl = document.getElementById('ppListSearch');
    var ppListSearchTimer = null;
    if (ppListSearchEl) {
        ppListSearchEl.addEventListener('input', function(){
            var v = this.value;
            clearTimeout(ppListSearchTimer);
            ppListSearchTimer = setTimeout(function(){
                ppListFilter = v.trim();
                if (ppLoaded) ppRenderList();
            }, 200);
        });
    }
    // 空状態ヒーローのインポートボタン
    var ppImportFirstBtn = document.getElementById('ppImportFirstBtn');
    if (ppImportFirstBtn) {
        ppImportFirstBtn.addEventListener('click', function(){
            var syncBtn = document.getElementById('ppSyncBtn');
            if (syncBtn) syncBtn.click();
        });
    }

    // ========== さっと価格を調べる: モーダルウィザード ==========
    var ppQqState = { tier: null, productId: null, variantIdx: null, mode: null };
    function ppQqOpen() {
        if (!ppData || !ppData.sheets) ppEnsureLoaded();
        document.getElementById('ppQuoteModal').style.display = '';
        ppQqState = { tier: null, productId: null, variantIdx: null, mode: null };
        ppQqRefresh();
        // 製品プルダウンを設定
        var prodSel = document.getElementById('ppQqProduct');
        var allItems = PP_PRODUCTS.concat(PP_COMMON);
        var hasDataItems = allItems.filter(function(item){ return ppMatchSheets(item.match).length > 0; });
        prodSel.innerHTML = '<option value="">製品を選択…</option>' + hasDataItems.map(function(item){
            return '<option value="' + escapeHtml(item.id) + '">' + escapeHtml(item.name) + '</option>';
        }).join('');
        // 選択をクリア
        document.querySelectorAll('#ppQqTiers .pp-quote-choice, #ppQqModes .pp-quote-choice').forEach(function(b){ b.classList.remove('active'); });
        document.getElementById('ppQqVariant').innerHTML = '<option value="">サイズ・型番…</option>';
        document.getElementById('ppQqVariant').disabled = true;
    }
    function ppQqClose() { document.getElementById('ppQuoteModal').style.display = 'none'; }
    function ppQqRefresh() {
        var resultEl = document.getElementById('ppQqResult');
        if (ppQqState.tier === null || ppQqState.productId === null || ppQqState.variantIdx === null || ppQqState.mode === null) {
            resultEl.style.display = 'none';
            return;
        }
        var item = PP_PRODUCTS.find(function(p){ return p.id === ppQqState.productId; })
                || PP_COMMON.find(function(c){ return c.id === ppQqState.productId; });
        if (!item) { resultEl.style.display = 'none'; return; }
        var sheets = ppMatchSheets(item.match);
        // 全シートの normalized rows を集約
        var allRows = [];
        sheets.forEach(function(s){
            if (s.normalized && s.normalized.rows) {
                s.normalized.rows.forEach(function(r){ allRows.push(r); });
            }
        });
        var row = allRows[ppQqState.variantIdx];
        if (!row) { resultEl.style.display = 'none'; return; }
        var label = ({ 'sale': '販売価格', 'rent-1': '①月額', 'rent-2': '②月額', 'rent-3': '③月額' })[ppQqState.mode];
        var amount = ppGetPrice(row, ppQqState.tier, label);
        resultEl.style.display = '';
        if (amount === null) {
            document.getElementById('ppQqPrice').textContent = '価格なし';
            document.getElementById('ppQqExplain').textContent = '該当する組合せの価格は登録されていません。';
            return;
        }
        var tierLabel = { S: '上位ディーラー', A: '標準ディーラー', B: '新規開拓' }[ppQqState.tier] || ppQqState.tier + '層';
        var modeLabel = { 'sale': '販売', 'rent-1': '短期レンタル(月額)', 'rent-2': '中期レンタル(月額)', 'rent-3': '長期レンタル(月額)' }[ppQqState.mode];
        document.getElementById('ppQqPrice').textContent = '¥' + amount.toLocaleString('ja-JP') + (ppQqState.mode === 'sale' ? '' : ' / 月');
        document.getElementById('ppQqExplain').innerHTML =
            '対象: <strong>' + escapeHtml(item.name) + ' / ' + escapeHtml(row.display_name) + '</strong><br>' +
            'お客様: <strong>' + escapeHtml(tierLabel) + ' (' + escapeHtml(ppQqState.tier) + '層)</strong> / 取引: <strong>' + escapeHtml(modeLabel) + '</strong>';
        // コピー用テキスト
        document.getElementById('ppQqResult').setAttribute('data-copy',
            item.name + ' / ' + row.display_name + ' / ' + tierLabel + ' / ' + modeLabel + ' / ¥' + amount.toLocaleString('ja-JP')
        );
    }

    // ボタン: モーダル起動
    var ppQqBtn = document.getElementById('ppQuickQuoteBtn');
    if (ppQqBtn) ppQqBtn.addEventListener('click', ppQqOpen);
    var ppQqCloseBtn = document.getElementById('ppQuoteClose');
    if (ppQqCloseBtn) ppQqCloseBtn.addEventListener('click', ppQqClose);
    var ppQqBackdrop = document.querySelector('#ppQuoteModal .pp-quote-backdrop');
    if (ppQqBackdrop) ppQqBackdrop.addEventListener('click', ppQqClose);
    // ESCで閉じる
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && document.getElementById('ppQuoteModal').style.display !== 'none') {
            ppQqClose();
        }
    });
    // 各ステップのバインド
    document.querySelectorAll('#ppQqTiers .pp-quote-choice').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('#ppQqTiers .pp-quote-choice').forEach(function(b){ b.classList.remove('active'); });
            btn.classList.add('active');
            ppQqState.tier = btn.getAttribute('data-tier');
            ppQqRefresh();
        });
    });
    document.querySelectorAll('#ppQqModes .pp-quote-choice').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('#ppQqModes .pp-quote-choice').forEach(function(b){ b.classList.remove('active'); });
            btn.classList.add('active');
            ppQqState.mode = btn.getAttribute('data-mode');
            ppQqRefresh();
        });
    });
    document.getElementById('ppQqProduct').addEventListener('change', function(){
        ppQqState.productId = this.value || null;
        ppQqState.variantIdx = null;
        var variantSel = document.getElementById('ppQqVariant');
        if (!ppQqState.productId) {
            variantSel.disabled = true;
            variantSel.innerHTML = '<option value="">サイズ・型番…</option>';
            ppQqRefresh();
            return;
        }
        var item = PP_PRODUCTS.find(function(p){ return p.id === ppQqState.productId; })
                || PP_COMMON.find(function(c){ return c.id === ppQqState.productId; });
        var sheets = ppMatchSheets(item.match);
        var allRows = [];
        sheets.forEach(function(s){
            if (s.normalized && s.normalized.rows) {
                s.normalized.rows.forEach(function(r){ allRows.push(r); });
            }
        });
        variantSel.innerHTML = '<option value="">サイズ・型番…</option>' + allRows.map(function(r, i){
            return '<option value="' + i + '">' + escapeHtml(r.display_name) + '</option>';
        }).join('');
        variantSel.disabled = false;
        ppQqRefresh();
    });
    document.getElementById('ppQqVariant').addEventListener('change', function(){
        ppQqState.variantIdx = this.value === '' ? null : parseInt(this.value, 10);
        ppQqRefresh();
    });
    // クリップボードコピー
    var ppQqCopyBtn = document.getElementById('ppQqCopy');
    if (ppQqCopyBtn) {
        ppQqCopyBtn.addEventListener('click', function(){
            var text = document.getElementById('ppQqResult').getAttribute('data-copy') || '';
            if (!text) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function(){
                    var orig = ppQqCopyBtn.textContent;
                    ppQqCopyBtn.textContent = 'コピーしました';
                    setTimeout(function(){ ppQqCopyBtn.textContent = orig; }, 1500);
                });
            }
        });
    }

    // ----- 共通ヘルパー -----
    function ppIsEmptyRow(row) {
        if (!Array.isArray(row)) return true;
        return !row.some(function(c){ return String(c == null ? '' : c).trim() !== ''; });
    }
    function ppIsHeaderRow(row) {
        if (!Array.isArray(row)) return false;
        return row.some(function(c){ return /[SABCD]層/.test(String(c == null ? '' : c)); });
    }
    // セクション行（"81インチ" のような単独セル）の判定
    function ppDetectSection(row) {
        if (!Array.isArray(row)) return null;
        var nonEmpty = row.filter(function(c){ return String(c == null ? '' : c).trim() !== ''; });
        if (nonEmpty.length !== 1) return null;
        var v = String(nonEmpty[0]).trim();
        var m;
        if (m = v.match(/^(\d+(?:\.\d+)?)\s*(インチ|inch)$/i)) return { type: 'インチ', value: m[1] };
        if (m = v.match(/^(\d+(?:\.\d+)?)\s*mm$/i))           return { type: 'mm',     value: m[1] };
        return null;
    }

    // シートを正規化: セクションを列にインライン化 + 重複ヘッダ除去
    function ppNormalizeSheet(values) {
        if (!values || values.length === 0) return values;
        // セクションが存在するか走査
        var hasSection = values.some(function(r){ return ppDetectSection(r) !== null; });
        if (!hasSection) {
            // セクションがないなら空行除去のみして返す
            return values.filter(function(r){ return !ppIsEmptyRow(r); });
        }
        var sec = ppDetectSection(values.find(function(r){ return ppDetectSection(r); }));
        var addedField = sec.type;

        var output = [];
        var currentSection = '';
        var headerInserted = false;
        for (var r = 0; r < values.length; r++) {
            var row = values[r];
            var asSec = ppDetectSection(row);
            if (asSec) { currentSection = asSec.value; continue; }
            if (ppIsEmptyRow(row)) continue;
            if (ppIsHeaderRow(row)) {
                if (!headerInserted) {
                    output.push([addedField].concat(row));
                    headerInserted = true;
                }
                continue;
            }
            output.push([currentSection].concat(row || []));
        }
        return output;
    }

    // 列ヘッダから列タイプを決定
    function ppDetectColumnTypes(headerRow) {
        if (!Array.isArray(headerRow)) return [];
        return headerRow.map(function(h){
            var s = String(h == null ? '' : h).replace(/\s+/g, '');
            if (/(月額|販売価格|定価|仕入|原価|利益|料金|単価|送料|請求|キャンセル)/.test(s)) return 'price';
            if (/(率$|^限界粗利率|%|％|OFF)/.test(s)) return 'pct';
            if (/(平米|サイズ|寸法|タテ|ヨコ|インチ|ピッチ|mm)/.test(s)) return 'measure';
            return 'text';
        });
    }

    // セル値整形（列タイプに従う）
    function ppFormatCell(value, type) {
        var s = String(value == null ? '' : value).trim();
        if (s === '') return { text: '', cls: '' };
        if (type === 'price') {
            var n = parseInt(s.replace(/[¥￥,，円\s]/g, ''), 10);
            if (!isNaN(n) && n > 0) return { text: '¥' + n.toLocaleString('ja-JP'), cls: 'pp-yen' };
            return { text: s, cls: '' };
        }
        if (type === 'pct')     return { text: s, cls: 'pp-pct' };
        if (type === 'measure') return { text: s, cls: 'pp-num' };
        return { text: s, cls: '' };
    }

    // 行をテキスト形式に整形（コピー機能用）
    // 例: "モニたろう / 81インチ / S層 ¥350,000 / A層 ¥300,000"
    function ppRowToCopyText(headerRow, row, colTypes) {
        if (!Array.isArray(row)) return '';
        var parts = [];
        for (var c = 0; c < row.length; c++) {
            var hdr = headerRow ? String(headerRow[c] == null ? '' : headerRow[c]).replace(/[\r\n]+/g, ' ').trim() : '';
            var t   = colTypes[c] || 'text';
            var ff  = ppFormatCell(row[c], t);
            if (!ff.text) continue;
            if (hdr) parts.push(hdr + ' ' + ff.text);
            else     parts.push(ff.text);
        }
        return parts.join(' / ');
    }

    // ----- 表レンダラー（正規化済み values を前提） -----
    // badgeCol: バッジ表示する列インデックス（製品シリーズ列、未指定なら -1）
    function ppRenderTable(values, badgeCol) {
        if (typeof badgeCol === 'undefined') badgeCol = -1;
        if (!values || !values.length) {
            return '<div style="color: var(--gray-500); padding: 1rem; text-align:center;">データなし</div>';
        }
        var rows = values.filter(function(r){ return !ppIsEmptyRow(r); });
        if (rows.length === 0) {
            return '<div style="color: var(--gray-500); padding: 1rem; text-align:center;">データなし</div>';
        }

        var cols = 0;
        rows.forEach(function(r){ if (r.length > cols) cols = r.length; });

        // ヘッダ行: ランクラベルを含む or 1行目が全部文字列ならヘッダ
        var headerIdx = -1;
        for (var i = 0; i < Math.min(2, rows.length); i++) {
            if (ppIsHeaderRow(rows[i])) { headerIdx = i; break; }
        }
        if (headerIdx < 0) {
            var f = rows[0];
            var anyNum = f.some(function(c){
                var s = String(c == null ? '' : c).trim();
                return /^[¥￥]?\d[\d,，.]*$/.test(s);
            });
            if (!anyNum && f.some(function(c){ return String(c || '').trim() !== ''; })) headerIdx = 0;
        }
        var headerRow = headerIdx >= 0 ? rows[headerIdx] : null;
        var colTypes  = headerRow ? ppDetectColumnTypes(headerRow) : [];

        // 検索フィルタ適用（ヘッダ行は常に表示）
        var filteredRows = rows;
        if (ppDetailFilter) {
            var q = ppDetailFilter.toLowerCase();
            filteredRows = rows.filter(function(row, i){
                if (i === headerIdx) return true;
                return row.some(function(c){
                    return String(c == null ? '' : c).toLowerCase().indexOf(q) >= 0;
                });
            });
            // ヘッダしか残らなければ「ヒットなし」表示
            if (filteredRows.length <= (headerIdx >= 0 ? 1 : 0)) {
                return '<div class="pp-data-table-wrap" style="padding:1.5rem;text-align:center;color:var(--gray-500);">"' + escapeHtml(ppDetailFilter) + '" にマッチする行はありません</div>';
            }
        }

        // データ行のコピー用テキストをあらかじめ計算
        var rowTexts = filteredRows.map(function(row, i){
            if (i === filteredRows.indexOf(rows[headerIdx])) return '';
            return ppRowToCopyText(headerRow, row, colTypes);
        });

        // テーブル版
        var html = '<div class="pp-data-table-wrap"><table class="pp-data-table"><tbody>';
        filteredRows.forEach(function(row, fi){
            var origIdx = rows.indexOf(row);
            var isH = (origIdx === headerIdx);
            html += '<tr' + (isH ? ' class="pp-header"' : '') + '>';
            // コピー列（固定左 sticky）
            if (isH) {
                html += '<td class="pp-copy-cell"></td>';
            } else {
                var copyText = ppRowToCopyText(headerRow, row, colTypes);
                html += '<td class="pp-copy-cell">' +
                    '<button type="button" class="pp-copy-btn" data-copy="' + escapeHtml(copyText) + '" title="この行をコピー">' +
                        '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>' +
                    '</button>' +
                '</td>';
            }
            for (var c = 0; c < cols; c++) {
                var cls = [];
                if (isH) {
                    var hs = String(row[c] == null ? '' : row[c]).replace(/^[SABCD]層[\r\n]*/u, '');
                    html += '<td' + (cls.length ? ' class="' + cls.join(' ') + '"' : '') + '>' +
                            escapeHtml(hs) + '</td>';
                } else {
                    var t = colTypes[c] || 'text';
                    var ff = ppFormatCell(row[c], t);
                    if (ff.cls) cls.push(ff.cls);
                    var content;
                    if (c === badgeCol && ff.text) {
                        content = '<span class="pp-badge">' + escapeHtml(ff.text) + '</span>';
                    } else {
                        content = escapeHtml(ff.text);
                    }
                    html += '<td' + (cls.length ? ' class="' + cls.join(' ') + '"' : '') + '>' +
                            content + '</td>';
                }
            }
            html += '</tr>';
        });
        html += '</tbody></table></div>';

        // モバイル用カード版（CSS @media で table を隠してこちらを表示）
        var cardsHtml = '<div class="pp-data-table-as-cards">';
        filteredRows.forEach(function(row, fi){
            var origIdx = rows.indexOf(row);
            if (origIdx === headerIdx) return;
            // 最初の非空セルをタイトルに、それ以外を label/value 行に
            var firstNonEmpty = -1;
            for (var ci = 0; ci < row.length; ci++) {
                var s = String(row[ci] == null ? '' : row[ci]).trim();
                if (s !== '') { firstNonEmpty = ci; break; }
            }
            if (firstNonEmpty < 0) return;
            var title = String(row[firstNonEmpty]).trim();
            cardsHtml += '<div class="pp-mcard">';
            cardsHtml += '<div class="pp-mcard-title">' + escapeHtml(title) + '</div>';
            for (var c = 0; c < row.length; c++) {
                if (c === firstNonEmpty) continue;
                var t = colTypes[c] || 'text';
                var ff = ppFormatCell(row[c], t);
                if (!ff.text) continue;
                var hdr = headerRow ? String(headerRow[c] == null ? '' : headerRow[c]).replace(/[\r\n]+/g, ' ').replace(/^[SABCD]層[\s]*/, '$&').trim() : '';
                cardsHtml += '<div class="pp-mcard-row">' +
                    '<span class="pp-mcard-label">' + escapeHtml(hdr || ('列' + (c + 1))) + '</span>' +
                    '<span class="pp-mcard-value">' + escapeHtml(ff.text) + '</span>' +
                '</div>';
            }
            var copyText2 = ppRowToCopyText(headerRow, row, colTypes);
            cardsHtml += '<div class="pp-mcard-actions">' +
                '<button type="button" class="pp-copy-btn" data-copy="' + escapeHtml(copyText2) + '" title="この行をコピー">' +
                    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> コピー' +
                '</button>' +
            '</div>';
            cardsHtml += '</div>';
        });
        cardsHtml += '</div>';
        return html + cardsHtml;
    }

    // 詳細ビューの状態
    var ppCurrentMatched = [];
    var ppCurrentSubIdx  = 0;
    var ppCurrentRank    = null; // 'S' / 'A' / 'B' / 'C' / 'D'
    var ppCurrentSeries  = null; // null = すべて、それ以外は系列名
    var ppProdVariantIdx = 0;    // 商品ページ型の選択中バリアント
    var ppProdTier       = 'A';  // 商品ページ型の選択中の層
    var ppProdMode       = 'sale'; // 商品ページ型の取引形態
    var ppDetailFilter   = '';   // 詳細表内のフリーテキスト検索

    // ヘッダ行の終端を検出（先頭3行までを対象）
    function ppDetectHeaderEnd(values) {
        var rankPattern = /[SABCD]層/;
        var lastHeaderRow = -1;
        for (var r = 0; r < Math.min(3, values.length); r++) {
            var row = values[r] || [];
            for (var c = 0; c < row.length; c++) {
                if (rankPattern.test(String(row[c] == null ? '' : row[c]))) {
                    lastHeaderRow = r;
                    break;
                }
            }
        }
        return lastHeaderRow;
    }

    // シリーズ列（製品グルーピング列）を共通列の中から検出
    // 優先順: ヘッダ名が "製品シリーズ/シリーズ/型式/モデル" → 上記なら採用
    // 次点: distinct 2〜20で繰り返しがあるヒューリスティック
    function ppDetectSeriesCol(values, info) {
        if (!info || !info.commonCols || !info.commonCols.length) return -1;
        var headerEnd = ppDetectHeaderEnd(values);

        // 1) ヘッダ名でマッチ
        if (headerEnd >= 0) {
            var headerRow = values[headerEnd] || [];
            for (var i = 0; i < info.commonCols.length; i++) {
                var c = info.commonCols[i];
                var h = String(headerRow[c] == null ? '' : headerRow[c]).trim();
                if (/(製品シリーズ|シリーズ|型式|モデル)/.test(h)) return c;
            }
        }

        // 2) ヒューリスティック
        var dataStart = headerEnd >= 0 ? headerEnd + 1 : 0;
        var dataRows = values.slice(dataStart).filter(function(r){
            return Array.isArray(r) && r.some(function(c){ return String(c == null ? '' : c).trim() !== ''; });
        });
        if (dataRows.length < 4) return -1;

        // ヘッダ文字列（除外用）
        var headerVals = {};
        if (headerEnd >= 0) {
            (values[headerEnd] || []).forEach(function(c){
                var s = String(c == null ? '' : c).trim();
                if (s) headerVals[s] = true;
            });
        }

        var best = -1, bestScore = 0;
        for (var ii = 0; ii < info.commonCols.length; ii++) {
            var col = info.commonCols[ii];
            var vals = dataRows.map(function(r){ return String(r[col] == null ? '' : r[col]).trim(); })
                                .filter(function(v){ return v && !headerVals[v]; });
            if (vals.length < dataRows.length * 0.5) continue;
            var uniq = {};
            vals.forEach(function(v){ uniq[v] = (uniq[v] || 0) + 1; });
            var distinct = Object.keys(uniq).length;
            if (distinct < 2 || distinct > 20) continue;
            if (vals.length < distinct * 1.5) continue;
            var score = (vals.length / distinct) + (10 - Math.abs(distinct - 5)) * 0.3;
            if (score > bestScore) { bestScore = score; best = col; }
        }
        return best;
    }

    // dataRows + seriesCol → ユニーク値の一覧（出現順）と件数
    function ppCollectSeries(values, seriesCol) {
        var headerEnd = ppDetectHeaderEnd(values);
        var dataStart = headerEnd >= 0 ? headerEnd + 1 : 0;
        var dataRows = values.slice(dataStart);

        // ヘッダ文字列をブラックリスト化（"製品シリーズ" がデータとして混入するのを防ぐ）
        var headerVals = {};
        if (headerEnd >= 0) {
            (values[headerEnd] || []).forEach(function(c){
                var s = String(c == null ? '' : c).trim();
                if (s) headerVals[s] = true;
            });
        }

        var seen = {};
        var order = [];
        dataRows.forEach(function(r){
            if (!Array.isArray(r)) return;
            var v = String(r[seriesCol] == null ? '' : r[seriesCol]).trim();
            if (!v) return;
            if (headerVals[v]) return;
            if (!(v in seen)) { seen[v] = 0; order.push(v); }
            seen[v]++;
        });
        return order.map(function(v){ return { value: v, count: seen[v] }; });
    }

    // values をシリーズで絞り込む。ヘッダ行は残す。
    function ppFilterBySeries(values, seriesCol, seriesValue) {
        if (seriesCol < 0 || !seriesValue) return values;
        var headerEnd = ppDetectHeaderEnd(values);
        return values.filter(function(r, idx){
            if (idx <= headerEnd) return true;
            if (!Array.isArray(r)) return false;
            return String(r[seriesCol] == null ? '' : r[seriesCol]).trim() === seriesValue;
        });
    }

    // 列ごとのランクを検出（複数行ヘッダ対応）
    function ppDetectRanks(values) {
        if (!values || !values.length) return { hasRanks: false };
        var maxCols = 0;
        values.forEach(function(r){ if (Array.isArray(r) && r.length > maxCols) maxCols = r.length; });
        if (maxCols === 0) return { hasRanks: false };

        var colRank = new Array(maxCols).fill(null);
        var rankPattern = /([SABCD])層/;
        var scanRows = Math.min(3, values.length);
        for (var r = 0; r < scanRows; r++) {
            var row = values[r] || [];
            for (var c = 0; c < row.length; c++) {
                if (colRank[c]) continue;
                var m = String(row[c] == null ? '' : row[c]).match(rankPattern);
                if (m) colRank[c] = m[1];
            }
        }

        // ランクが出てきた最初の列の位置
        var firstRankCol = colRank.findIndex(function(v){ return v !== null; });
        if (firstRankCol < 0) return { hasRanks: false };

        // 各ランクの開始位置を順番に拾い、次のランクの直前までをその範囲とする
        var anchors = [];
        for (var c2 = 0; c2 < maxCols; c2++) {
            if (colRank[c2]) anchors.push({ col: c2, rank: colRank[c2] });
        }
        var ranks = []; // [{rank, cols: []}]
        for (var i = 0; i < anchors.length; i++) {
            var start = anchors[i].col;
            var end   = (i + 1 < anchors.length) ? anchors[i+1].col - 1 : maxCols - 1;
            var existing = ranks.find(function(rk){ return rk.rank === anchors[i].rank; });
            if (!existing) { existing = { rank: anchors[i].rank, cols: [] }; ranks.push(existing); }
            for (var c3 = start; c3 <= end; c3++) existing.cols.push(c3);
        }

        // 共通列 = 1番目のランク列より左
        var commonCols = [];
        for (var k = 0; k < firstRankCol; k++) commonCols.push(k);

        return { hasRanks: true, commonCols: commonCols, ranks: ranks, firstRankCol: firstRankCol };
    }

    // ランク絞り込みテーブル
    function ppRenderRankFilteredTable(values, rankFilter, info, badgeCol) {
        if (typeof badgeCol === 'undefined') badgeCol = -1;
        var pickedCols = info.commonCols.concat(
            (info.ranks.find(function(r){ return r.rank === rankFilter; }) || { cols: [] }).cols
        );
        var filtered = values.map(function(row){
            return pickedCols.map(function(c){ return (row && row[c] != null) ? row[c] : ''; });
        });
        // ヘッダから「○層」プレフィックスを削除して見やすく
        for (var h = 0; h < Math.min(2, filtered.length); h++) {
            filtered[h] = filtered[h].map(function(cell, idx){
                if (idx < info.commonCols.length) return cell;
                var s = String(cell == null ? '' : cell);
                return s.replace(/^[SABCD]層[\s\r\n]*/u, '').trim();
            });
        }
        // 元のbadgeColが commonCols 内にあれば、フィルタ後の対応列に変換
        var newBadgeCol = -1;
        if (badgeCol >= 0) {
            newBadgeCol = pickedCols.indexOf(badgeCol);
        }
        return ppRenderTable(filtered, newBadgeCol);
    }

    function ppRenderSubtabs() {
        var html = ppCurrentMatched.map(function(s, i){
            var rows = (s.values || []).filter(function(r){
                return Array.isArray(r) && r.some(function(c){ return String(c == null ? '' : c).trim() !== ''; });
            }).length;
            return '<button type="button" class="pp-subtab' + (i === ppCurrentSubIdx ? ' active' : '') + '" data-idx="' + i + '">' +
                escapeHtml(s.title) +
                '<span class="pp-subtab-count">' + rows + '</span>' +
            '</button>';
        }).join('');
        return html;
    }

    // ===== 正規化データ（rank-pricing / flat-list）をカード表示する =====
    function ppRowToText(row) {
        var parts = [row.display_name || ''];
        (row.attributes || []).forEach(function(a){
            if (a.value) parts.push(a.label + ' ' + a.value);
        });
        (row.prices || []).forEach(function(p){
            parts.push((p.group ? '[' + p.group + '層] ' : '') + p.label + ' ¥' + p.amount.toLocaleString('ja-JP'));
        });
        return parts.join(' / ');
    }

    function ppFilterNormalizedRows(rows, filter) {
        if (!filter) return rows;
        var q = filter.toLowerCase();
        return rows.filter(function(r){
            if ((r.display_name || '').toLowerCase().indexOf(q) >= 0) return true;
            var attrs = r.attributes || [];
            for (var i = 0; i < attrs.length; i++) {
                if ((attrs[i].value || '').toString().toLowerCase().indexOf(q) >= 0) return true;
            }
            var prices = r.prices || [];
            for (var i = 0; i < prices.length; i++) {
                if ((prices[i].label || '').toLowerCase().indexOf(q) >= 0) return true;
                if (String(prices[i].amount || '').indexOf(q) >= 0) return true;
            }
            return false;
        });
    }

    function ppCollectNormalizedSeries(rows) {
        var map = {};
        rows.forEach(function(r){
            (r.attributes || []).forEach(function(a){
                if (/(シリーズ|型式|型番)/.test(a.label) && a.value) {
                    map[a.value] = (map[a.value] || 0) + 1;
                }
            });
        });
        var arr = Object.keys(map).map(function(v){ return { value: v, count: map[v] }; });
        arr.sort(function(a, b){ return b.count - a.count; });
        return arr;
    }

    function ppMatchesSeries(row, series) {
        if (!series) return true;
        var attrs = row.attributes || [];
        for (var i = 0; i < attrs.length; i++) {
            if (/(シリーズ|型式|型番)/.test(attrs[i].label) && attrs[i].value === series) return true;
        }
        return false;
    }

    function ppRenderNormalizedCard(row, rankFilter, badgeSeries) {
        var html = '<div class="pp-norm-card">';
        html += '<div class="pp-norm-head">';
        html += '<h4 class="pp-norm-title">' + escapeHtml(row.display_name || '');
        if (badgeSeries) html += ' <span class="pp-badge">' + escapeHtml(badgeSeries) + '</span>';
        html += '</h4>';
        html += '</div>';

        // 属性セクション
        if (row.attributes && row.attributes.length > 0) {
            html += '<div class="pp-norm-attrs">';
            row.attributes.forEach(function(a){
                html += '<span class="pp-attr">' +
                    '<span class="pp-attr-label">' + escapeHtml(a.label) + '</span>' +
                    '<span class="pp-attr-value">' + escapeHtml(String(a.value || '')) + '</span>' +
                '</span>';
            });
            html += '</div>';
        }

        // 価格セクション（rank でグループ化）
        if (row.prices && row.prices.length > 0) {
            var byRank = {};
            var rankList = [];
            row.prices.forEach(function(p){
                var g = p.group || '';
                if (!(g in byRank)) { byRank[g] = []; rankList.push(g); }
                byRank[g].push(p);
            });
            html += '<div class="pp-norm-prices">';
            rankList.forEach(function(g){
                // ランクフィルタ
                if (rankFilter && g !== rankFilter) return;
                html += '<div class="pp-norm-rank-group ' + (g ? '' : 'no-rank') + '">';
                if (g) html += '<div class="pp-norm-rank-label rank-' + g + '">' + g + '層</div>';
                html += '<div class="pp-norm-price-list">';
                byRank[g].forEach(function(p){
                    html += '<div class="pp-norm-price">' +
                        '<span class="pp-norm-price-label">' + escapeHtml(p.label || '') + '</span>' +
                        '<span class="pp-norm-price-amount">¥' + (p.amount || 0).toLocaleString('ja-JP') + '</span>' +
                    '</div>';
                });
                html += '</div></div>';
            });
            html += '</div>';
        }

        // コピー用テキスト
        var copyText = ppRowToText(row);
        html += '<div class="pp-norm-card-actions">' +
            '<button type="button" class="pp-copy-btn" data-copy="' + escapeHtml(copyText) + '" title="この行をコピー">' +
                '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> コピー' +
            '</button>' +
        '</div>';
        html += '</div>';
        return html;
    }

    // 顧客視点ビュー: 層ごとにセクション化し、各セクションに対象製品の価格を一覧表示
    var PP_TIER_META = {
        S: { label: '上位ディーラー (S層)', desc: '大興物産・レンタルニッケン等' },
        A: { label: '標準ディーラー (A層)', desc: 'その他の取引先ディーラー' },
        B: { label: '新規開拓 (B層)',       desc: 'エンドユーザー直接' },
        C: { label: 'C層',                desc: '' },
        D: { label: 'D層',                desc: '' }
    };
    function ppGetPrice(row, group, label) {
        var p = (row.prices || []).find(function(x){ return x.group === group && x.label === label; });
        return p ? p.amount : null;
    }
    function ppMinMaxRentalMonthly(row, group) {
        var labels = ['①月額','②月額','③月額','販売']; // 月額系優先
        var vals = (row.prices || []).filter(function(p){
            return p.group === group && /(月額|月|レンタル|R)/.test(p.label) && p.label !== '販売価格';
        }).map(function(p){ return p.amount; }).filter(function(v){ return v > 0; });
        if (vals.length === 0) return null;
        return { min: Math.min.apply(null, vals), max: Math.max.apply(null, vals) };
    }
    // ====== 商品ページ型ビュー（Amazon/Tesla風） ======
    function ppRenderProductPage(sheet) {
        var norm = sheet.normalized;
        if (!norm || !norm.rows || norm.rows.length === 0) return null;

        // 検索フィルタを適用してバリアント候補を絞る
        var rows = ppFilterNormalizedRows(norm.rows, ppDetailFilter);
        if (rows.length === 0) {
            return '<div class="pp-norm-empty">"' + escapeHtml(ppDetailFilter) + '" にマッチする行はありません</div>';
        }

        // シリーズリスト（製品シリーズ/型式/型番のいずれか）
        var seriesList = ppCollectNormalizedSeries(rows);
        // シリーズが1つも無いケースは「(指定なし)」として動かす
        if (seriesList.length === 0) seriesList = [{ value: '', count: rows.length }];

        // 現在のシリーズが存在しなければ最初を選択
        if (ppCurrentSeries === null || !seriesList.find(function(s){ return s.value === ppCurrentSeries; })) {
            ppCurrentSeries = seriesList[0].value;
        }

        // 該当シリーズの全行
        var filtered = ppCurrentSeries === ''
            ? rows
            : rows.filter(function(r){ return ppMatchesSeries(r, ppCurrentSeries); });
        if (filtered.length === 0) {
            // フォールバック: 全行
            ppCurrentSeries = seriesList[0].value;
            filtered = rows;
        }

        // 範囲チェック
        if (ppProdVariantIdx >= filtered.length) ppProdVariantIdx = 0;
        var current = filtered[ppProdVariantIdx];

        // シリーズ option
        var seriesOptions = seriesList.map(function(s){
            var label = (s.value || '(指定なし)') + ' (' + s.count + ')';
            return '<option value="' + escapeHtml(s.value) + '"' + (s.value === ppCurrentSeries ? ' selected' : '') + '>' +
                escapeHtml(label) +
            '</option>';
        }).join('');

        // 型番/サイズ option (現在シリーズ内のみ)
        // ラベル戦略: 型番(YA-XXX 等) があればそれ、無ければサイズ+画面サイズ
        var variantOptions = filtered.map(function(r, i){
            var attrs = r.attributes || [];
            var inch  = attrs.find(function(a){ return /(インチ|inch)/.test(a.label); });
            var modelAttr = attrs.find(function(a){ return /^(YA型番|型式|型番)$/.test(a.label); });
            var screen = attrs.find(function(a){ return /(画面サイズ|寸法)/.test(a.label); });
            var sizeAttr = attrs.find(function(a){ return /^サイズ$/.test(a.label) && !/(画面)/.test(a.label); });

            var parts = [];
            if (modelAttr && modelAttr.value) parts.push(modelAttr.value);
            if (inch && inch.value) parts.push(inch.value + '"');
            else if (sizeAttr && sizeAttr.value) parts.push(sizeAttr.value);
            if (screen && screen.value && parts.length < 2) parts.push(screen.value);

            var label = parts.length > 0 ? parts.join(' / ') : (r.display_name || '行 ' + (i+1));
            return '<option value="' + i + '"' + (i === ppProdVariantIdx ? ' selected' : '') + '>' +
                escapeHtml(label) +
            '</option>';
        }).join('');

        // current row の取引形態モード（その層・形態で価格が取れる組合せを動的に判定）
        var modeOptions = [
            { key: 'sale',   label: '販売',     priceLabel: '販売価格' },
            { key: 'rent-1', label: '短期(1-3M)', priceLabel: '①月額' },
            { key: 'rent-2', label: '中期(3-6M)', priceLabel: '②月額' },
            { key: 'rent-3', label: '長期(6M+)',  priceLabel: '③月額' }
        ];
        // 該当 row に存在する label のみ表示
        var availableLabels = {};
        (current.prices || []).forEach(function(p){ availableLabels[p.label] = true; });
        var visibleModes = modeOptions.filter(function(m){ return availableLabels[m.priceLabel]; });
        if (visibleModes.length === 0) visibleModes = modeOptions; // fallback
        // 現在の mode が利用不能なら最初の利用可能なものへ
        if (!visibleModes.find(function(m){ return m.key === ppProdMode; })) {
            ppProdMode = visibleModes[0].key;
        }
        var currentModeDef = visibleModes.find(function(m){ return m.key === ppProdMode; });
        var priceLabel = currentModeDef ? currentModeDef.priceLabel : '販売価格';

        // 価格表示
        var price = ppGetPrice(current, ppProdTier, priceLabel);
        var tierName = { S: '上位ディーラー', A: '標準ディーラー', B: '新規開拓', C: 'C層', D: 'D層' }[ppProdTier] || (ppProdTier + '層');
        var suffix = (ppProdMode === 'sale' || price === null) ? '' : '/ 月';

        // hero データ
        var item = PP_PRODUCTS.find(function(p){ return p.id === ppCurrentItemId; })
                || PP_COMMON.find(function(c){ return c.id === ppCurrentItemId; });
        var heroColor = item ? item.color : 'blue';
        var heroIcon  = item ? item.icon  : '';
        var category  = item ? (item.sub || '') : '';
        var productName = item ? item.name : (sheet.title || '');

        // スペック行: display_name と attributes
        var specRows = [];
        // display_name (型番が入る) を最上部に
        specRows.push({ label: '型番', value: (current.display_name || '').replace(/\s*\([^)]+\)\s*$/, '') });
        (current.attributes || []).slice(0, 6).forEach(function(a){
            if (!a.value) return;
            specRows.push({ label: a.label, value: a.value });
        });

        // hero サブテキスト: 最初の 2-3 attribute を結合
        var heroSpecParts = [];
        ['インチ数', '画面サイズ', '平米数', 'サイズ'].forEach(function(k){
            var a = (current.attributes || []).find(function(x){ return x.label === k; });
            if (a && a.value) heroSpecParts.push(k === 'インチ数' ? a.value + 'インチ' : a.value);
        });
        var heroSpec = heroSpecParts.join(' / ');

        // 全層の現在 mode 価格（tier ボタンに表示）
        var allTiers = (norm.rank_order && norm.rank_order.length > 0) ? norm.rank_order : ['B'];
        // S/A/B 3層のみ UI 表示（C/D は別途）
        var displayTiers = allTiers.filter(function(t){ return ['S','A','B'].indexOf(t) >= 0; });
        if (displayTiers.length === 0) displayTiers = ['A'];

        var tierBtns = displayTiers.map(function(g){
            var p = ppGetPrice(current, g, priceLabel);
            var gName = { S: '上位ディーラー', A: '標準ディーラー', B: '新規開拓' }[g] || (g + '層');
            return '<button type="button" class="pp-prod-tier-btn tier-' + g + (g === ppProdTier ? ' active' : '') + '" data-tier="' + g + '">' +
                '<div class="pp-prod-tier-btn-label">' + escapeHtml(gName) + '</div>' +
                '<div class="pp-prod-tier-btn-price">' + (p !== null ? '¥' + p.toLocaleString('ja-JP') : '—') + '</div>' +
            '</button>';
        }).join('');

        // 取引形態モードボタン
        var modeBtns = visibleModes.map(function(m){
            return '<button type="button" data-mode="' + m.key + '" class="' + (m.key === ppProdMode ? 'active' : '') + '">' +
                escapeHtml(m.label) +
            '</button>';
        }).join('');

        var html =
            '<div class="pp-prod-wrap">' +
                // シリーズ + 型番ドロップダウン
                '<div class="pp-prod-selector">' +
                    '<div class="pp-prod-selector-item">' +
                        '<label for="ppSeriesSelect">製品シリーズ</label>' +
                        '<select id="ppSeriesSelect">' + seriesOptions + '</select>' +
                    '</div>' +
                    '<div class="pp-prod-selector-item">' +
                        '<label for="ppVariantSelect">型番・サイズ</label>' +
                        '<select id="ppVariantSelect">' + variantOptions + '</select>' +
                    '</div>' +
                    '<div class="pp-prod-selector-meta">' + filtered.length + '件 / 全' + rows.length + '件</div>' +
                '</div>' +
                // 商品ページ本体
                '<div class="pp-prod-frame">' +
                    '<div class="pp-prod-grid">' +
                        '<div class="pp-prod-hero c-' + escapeHtml(heroColor) + '">' +
                            '<div class="pp-prod-hero-icon">' +
                                '<svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' + heroIcon + '</svg>' +
                            '</div>' +
                            (category ? '<div class="pp-prod-hero-category">' + escapeHtml(category) + '</div>' : '') +
                            '<h2 class="pp-prod-hero-title">' + escapeHtml(productName) + '</h2>' +
                            (heroSpec ? '<div class="pp-prod-hero-spec">' + escapeHtml(heroSpec) + '</div>' : '') +
                        '</div>' +
                        '<div class="pp-prod-info">' +
                            (visibleModes.length > 1 ? '<div class="pp-prod-info-mode">' + modeBtns + '</div>' : '') +
                            '<div class="pp-prod-price-hero">' +
                                '<div class="pp-prod-price-label">' + escapeHtml(tierName) + ' (' + escapeHtml(ppProdTier) + '層) への ' + escapeHtml(currentModeDef.label) + '</div>' +
                                (price !== null
                                    ? '<span class="pp-prod-price-value">¥' + price.toLocaleString('ja-JP') + '</span>' +
                                      (suffix ? '<span class="pp-prod-price-suffix">' + suffix + '</span>' : '')
                                    : '<span class="pp-prod-price-empty">価格未登録</span>') +
                            '</div>' +
                            '<div class="pp-prod-tier-row">' + tierBtns + '</div>' +
                            '<ul class="pp-prod-specs">' +
                                specRows.map(function(r){
                                    return '<li><span>' + escapeHtml(r.label) + '</span><span>' + escapeHtml(String(r.value)) + '</span></li>';
                                }).join('') +
                            '</ul>' +
                            '<div class="pp-prod-cta">' +
                                '<button type="button" class="primary" data-prod-action="quote">この内容で見積を作る</button>' +
                                '<button type="button" class="secondary" data-prod-action="copy">この行をコピー</button>' +
                                (PP_LINKS[ppCurrentItemId] && PP_LINKS[ppCurrentItemId].url
                                    ? '<a class="pp-prod-hp-btn" href="' + escapeHtml(PP_LINKS[ppCurrentItemId].url) + '" target="_blank" rel="noopener" title="製品HPを新規タブで開く">' +
                                          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                                              (PP_LINKS[ppCurrentItemId].svg || '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>') +
                                          '</svg>' +
                                          '<span>HP を見る</span>' +
                                      '</a>'
                                    : '') +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

        return html;
    }

    // 旧名関数（フォールバック用に保持）
    function ppRenderCustomerView(sheet) {
        var norm = sheet.normalized;
        if (!norm || !norm.rows || norm.rows.length === 0) return null;

        var rows = ppFilterNormalizedRows(norm.rows, ppDetailFilter);
        if (rows.length === 0) {
            return '<div class="pp-norm-empty">"' + escapeHtml(ppDetailFilter) + '" にマッチする行はありません</div>';
        }

        var meta = '<div class="pp-section-meta">' +
            '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>' +
            ' ' + rows.length + ' 件' +
            '</div>';

        // シリーズフィルタ（残す）
        var seriesPills = '';
        var seriesList = ppCollectNormalizedSeries(rows);
        if (seriesList.length >= 2) {
            if (ppCurrentSeries && !seriesList.find(function(s){ return s.value === ppCurrentSeries; })) {
                ppCurrentSeries = null;
            }
            seriesPills = '<div class="pp-series-row">' +
                '<span class="pp-series-label">シリーズ</span>' +
                '<button type="button" class="pp-series-pill' + (ppCurrentSeries === null ? ' active' : '') + '" data-series="">' +
                    'すべて<span class="pp-series-count">' + rows.length + '</span>' +
                '</button>' +
                seriesList.map(function(s){
                    return '<button type="button" class="pp-series-pill' + (ppCurrentSeries === s.value ? ' active' : '') + '" data-series="' + escapeHtml(s.value) + '">' +
                        escapeHtml(s.value) +
                        '<span class="pp-series-count">' + s.count + '</span>' +
                    '</button>';
                }).join('') +
            '</div>';
        }

        // シリーズで絞り込み
        var filtered = rows.filter(function(r){ return ppMatchesSeries(r, ppCurrentSeries); });
        if (filtered.length === 0) {
            return meta + seriesPills + '<div class="pp-norm-empty">該当する行がありません</div>';
        }

        // 層リスト（normalized の rank_order から、なければ B のみ）
        var tiers = (norm.rank_order && norm.rank_order.length > 0)
            ? norm.rank_order.slice()
            : ['B'];

        // 各層セクションを構築
        var sectionsHtml = tiers.map(function(g){
            var meta = PP_TIER_META[g] || { mark: '·', label: g + '層', desc: '' };
            // この層に1つでも価格があるか
            var hasAny = filtered.some(function(r){
                return (r.prices || []).some(function(p){ return p.group === g; });
            });
            // テーブル行
            var rowsHtml = filtered.map(function(r){
                var sale  = ppGetPrice(r, g, '販売価格');
                var rentRange = ppMinMaxRentalMonthly(r, g);
                // スペック (インチ・サイズ)
                var inch = (r.attributes || []).find(function(a){ return a.label === 'インチ数' || a.label === 'インチ'; });
                var size = (r.attributes || []).find(function(a){ return a.label === '画面サイズ' || a.label === 'サイズ'; });
                var specParts = [];
                if (inch && inch.value) specParts.push(inch.value + '"');
                if (size && size.value && (!inch || inch.value !== size.value)) specParts.push(size.value);
                var copyText = (function(){
                    var p = [r.display_name];
                    p.push('[' + meta.label + ']');
                    if (sale !== null) p.push('販売 ¥' + sale.toLocaleString('ja-JP'));
                    if (rentRange) p.push('月額 ¥' + rentRange.min.toLocaleString('ja-JP') + '〜¥' + rentRange.max.toLocaleString('ja-JP'));
                    return p.join(' / ');
                })();
                return '<div class="pp-cust-row" style="grid-template-columns: 2fr 1fr 1.4fr 40px;">' +
                    '<div>' +
                        '<div class="pp-cust-product">' + escapeHtml(r.display_name) + '</div>' +
                        (specParts.length ? '<div class="pp-cust-spec">' + escapeHtml(specParts.join(' / ')) + '</div>' : '') +
                    '</div>' +
                    '<div class="pp-cust-cell sale ' + (sale === null ? 'empty' : '') + '">' +
                        (sale !== null ? '¥' + sale.toLocaleString('ja-JP') : '—') +
                    '</div>' +
                    '<div class="pp-cust-cell pp-cust-cell-rental ' + (!rentRange ? 'empty' : '') + '">' +
                        (rentRange
                            ? (rentRange.min === rentRange.max
                                ? '¥' + rentRange.min.toLocaleString('ja-JP') + '/月'
                                : '¥' + rentRange.min.toLocaleString('ja-JP') + '〜¥' + rentRange.max.toLocaleString('ja-JP') + '/月')
                            : '—') +
                    '</div>' +
                    '<div style="text-align:right;">' +
                        '<button type="button" class="pp-copy-btn" data-copy="' + escapeHtml(copyText) + '" title="この行をコピー">' +
                            '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>' +
                        '</button>' +
                    '</div>' +
                '</div>';
            }).join('');

            return '<div class="pp-cust-section">' +
                '<div class="pp-cust-head tier-' + g + '">' +
                    '<span class="pp-cust-head-tier">' + escapeHtml(g) + '</span>' +
                    '<span class="pp-cust-head-title">' + escapeHtml(meta.label) + '</span>' +
                    (meta.desc ? '<span class="pp-cust-head-desc">' + escapeHtml(meta.desc) + '</span>' : '') +
                '</div>' +
                '<div class="pp-cust-body">' +
                    '<div class="pp-cust-table-head" style="grid-template-columns: 2fr 1fr 1.4fr 40px;">' +
                        '<div>製品 / 仕様</div>' +
                        '<div style="text-align:right;">販売価格</div>' +
                        '<div style="text-align:right;">月額レンタル</div>' +
                        '<div></div>' +
                    '</div>' +
                    (hasAny ? rowsHtml : '<div class="pp-cust-empty">この層の価格は登録されていません</div>') +
                '</div>' +
            '</div>';
        }).join('');

        return meta + seriesPills + '<div class="pp-cust-list">' + sectionsHtml + '</div>';
    }

    function ppRenderNormalized(sheet) {
        var norm = sheet.normalized;
        if (!norm || !norm.rows || norm.rows.length === 0) return null;

        var rows = ppFilterNormalizedRows(norm.rows, ppDetailFilter);
        if (rows.length === 0) {
            return '<div class="pp-norm-empty">"' + escapeHtml(ppDetailFilter) + '" にマッチする行はありません</div>';
        }

        var meta = '<div class="pp-section-meta">' +
            '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>' +
            ' ' + rows.length + ' 件' +
            '</div>';

        // 層フィルタ（rank-pricing 時のみ）
        var rankFilter = null;
        var rankTabs = '';
        if (norm.type === 'rank-pricing' && norm.rank_order && norm.rank_order.length > 0) {
            // 現在の rank が存在しなければ先頭に
            if (!ppCurrentRank || norm.rank_order.indexOf(ppCurrentRank) < 0) {
                ppCurrentRank = norm.rank_order[0];
            }
            rankFilter = ppCurrentRank;
            rankTabs = '<div class="pp-rank-cards">' +
                norm.rank_order.map(function(r){
                    var isActive = (r === ppCurrentRank);
                    return '<div role="button" tabindex="0" class="pp-rank-card rank-' + r + (isActive ? ' active' : '') + '" data-rank="' + r + '">' +
                        '<div class="pp-rank-card-check">✓</div>' +
                        '<div class="pp-rank-card-letter">' + r + '</div>' +
                        '<div class="pp-rank-card-label">' + r + '層</div>' +
                        '<div class="pp-rank-card-desc">' + rows.length + ' 製品</div>' +
                    '</div>';
                }).join('') +
            '</div>';
        }

        // シリーズフィルタ
        var seriesPills = '';
        var seriesList = ppCollectNormalizedSeries(rows);
        if (seriesList.length >= 2) {
            // currentSeries が一覧に無ければクリア
            if (ppCurrentSeries && !seriesList.find(function(s){ return s.value === ppCurrentSeries; })) {
                ppCurrentSeries = null;
            }
            seriesPills = '<div class="pp-series-row">' +
                '<span class="pp-series-label">シリーズ</span>' +
                '<button type="button" class="pp-series-pill' + (ppCurrentSeries === null ? ' active' : '') + '" data-series="">' +
                    'すべて<span class="pp-series-count">' + rows.length + '</span>' +
                '</button>' +
                seriesList.map(function(s){
                    return '<button type="button" class="pp-series-pill' + (ppCurrentSeries === s.value ? ' active' : '') + '" data-series="' + escapeHtml(s.value) + '">' +
                        escapeHtml(s.value) +
                        '<span class="pp-series-count">' + s.count + '</span>' +
                    '</button>';
                }).join('') +
            '</div>';
        }

        // カードリスト
        var cardsHtml = '<div class="pp-norm-list">';
        var visibleCount = 0;
        rows.forEach(function(r){
            if (!ppMatchesSeries(r, ppCurrentSeries)) return;
            // バッジ用シリーズを抽出（タイトルに既に含まれていない場合のみ）
            var badge = null;
            var displayLower = (r.display_name || '').toLowerCase();
            (r.attributes || []).some(function(a){
                if (/(シリーズ|型式|型番)/.test(a.label) && a.value) {
                    if (displayLower.indexOf(String(a.value).toLowerCase()) < 0) badge = a.value;
                    return true;
                }
                return false;
            });
            cardsHtml += ppRenderNormalizedCard(r, rankFilter, badge);
            visibleCount++;
        });
        cardsHtml += '</div>';
        if (visibleCount === 0) {
            cardsHtml = '<div class="pp-norm-empty">該当する行がありません</div>';
        }

        return meta + rankTabs + seriesPills + cardsHtml;
    }

    function ppRenderActiveSubsheet() {
        if (!ppCurrentMatched.length) return '';
        var s = ppCurrentMatched[ppCurrentSubIdx] || ppCurrentMatched[0];

        // 正規化データが使えるなら商品ページ型ビューを最優先表示
        if (s.normalized && Array.isArray(s.normalized.rows) && s.normalized.rows.length > 0) {
            return ppRenderProductPage(s);
        }

        // フォールバック: 旧テーブル描画
        // 元データを正規化: セクション行（"81インチ"）を列にインライン化 + 重複ヘッダ除去
        var values = ppNormalizeSheet(s.values || []);
        var rowCount = values.filter(function(r){
            return Array.isArray(r) && r.some(function(c){ return String(c == null ? '' : c).trim() !== ''; });
        }).length;
        var meta = '<div class="pp-section-meta">' +
            '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>' +
            ' ' + rowCount + ' 行' +
            '</div>';

        // 層検出
        var info = ppDetectRanks(values);
        var seriesCol = ppDetectSeriesCol(values, info.hasRanks ? info : { commonCols: values[0] ? values[0].map(function(_, i){ return i; }) : [] });

        // --- 層あり ---
        if (info.hasRanks) {
            // 現在のランクが今のサブシートの中に無ければ先頭にリセット
            var rankExists = info.ranks.find(function(r){ return r.rank === ppCurrentRank; });
            if (!rankExists) ppCurrentRank = info.ranks[0].rank;

            // 各ランクの該当行数（共通列にデータがある行数）でカウント
            var headerEndForCount = ppDetectHeaderEnd(values);
            var dataStartForCount = headerEndForCount >= 0 ? headerEndForCount + 1 : 0;
            var dataRowCount = values.slice(dataStartForCount).filter(function(r){
                return Array.isArray(r) && r.some(function(c){ return String(c == null ? '' : c).trim() !== ''; });
            }).length;

            var tabs = '<div class="pp-rank-cards">' +
                info.ranks.map(function(r){
                    var isActive = (r.rank === ppCurrentRank);
                    return '<div role="button" tabindex="0" class="pp-rank-card rank-' + r.rank + (isActive ? ' active' : '') + '" data-rank="' + r.rank + '">' +
                        '<div class="pp-rank-card-check">✓</div>' +
                        '<div class="pp-rank-card-letter">' + r.rank + '</div>' +
                        '<div class="pp-rank-card-label">' + r.rank + '層</div>' +
                        '<div class="pp-rank-card-desc">' + dataRowCount + ' 製品</div>' +
                    '</div>';
                }).join('') +
            '</div>';

            // シリーズフィルタ
            var seriesHtml = '';
            var filteredValues = values;
            if (seriesCol >= 0) {
                var seriesList = ppCollectSeries(values, seriesCol);
                if (seriesList.length >= 2) {
                    // currentSeries が一覧に無ければ「すべて」に
                    if (ppCurrentSeries && !seriesList.find(function(s){ return s.value === ppCurrentSeries; })) {
                        ppCurrentSeries = null;
                    }
                    seriesHtml = '<div class="pp-series-row">' +
                        '<span class="pp-series-label">シリーズ</span>' +
                        '<button type="button" class="pp-series-pill' + (ppCurrentSeries === null ? ' active' : '') + '" data-series="">' +
                            'すべて<span class="pp-series-count">' + values.length + '</span>' +
                        '</button>' +
                        seriesList.map(function(s){
                            var safe = s.value.replace(/"/g, '&quot;');
                            return '<button type="button" class="pp-series-pill' + (ppCurrentSeries === s.value ? ' active' : '') + '" data-series="' + escapeHtml(s.value) + '">' +
                                escapeHtml(s.value) +
                                '<span class="pp-series-count">' + s.count + '</span>' +
                            '</button>';
                        }).join('') +
                    '</div>';
                    if (ppCurrentSeries) {
                        filteredValues = ppFilterBySeries(values, seriesCol, ppCurrentSeries);
                    }
                }
            }

            var table = ppRenderRankFilteredTable(filteredValues, ppCurrentRank, info, seriesCol);
            return meta + tabs + seriesHtml + table;
        }

        // --- 層なし: シリーズフィルタだけ適用（あれば） ---
        if (seriesCol >= 0) {
            var seriesListN = ppCollectSeries(values, seriesCol);
            if (seriesListN.length >= 2) {
                if (ppCurrentSeries && !seriesListN.find(function(s){ return s.value === ppCurrentSeries; })) {
                    ppCurrentSeries = null;
                }
                var seriesOnly = '<div class="pp-series-row">' +
                    '<span class="pp-series-label">シリーズ</span>' +
                    '<button type="button" class="pp-series-pill' + (ppCurrentSeries === null ? ' active' : '') + '" data-series="">' +
                        'すべて<span class="pp-series-count">' + values.length + '</span>' +
                    '</button>' +
                    seriesListN.map(function(s){
                        return '<button type="button" class="pp-series-pill' + (ppCurrentSeries === s.value ? ' active' : '') + '" data-series="' + escapeHtml(s.value) + '">' +
                            escapeHtml(s.value) +
                            '<span class="pp-series-count">' + s.count + '</span>' +
                        '</button>';
                    }).join('') +
                '</div>';
                var v2 = ppCurrentSeries ? ppFilterBySeries(values, seriesCol, ppCurrentSeries) : values;
                return meta + seriesOnly + ppRenderTable(v2, seriesCol);
            }
        }
        return meta + ppRenderTable(values);
    }

    function ppShowSub(i) {
        ppCurrentSubIdx = i;
        ppCurrentRank   = null; // サブシート切替時にランク・シリーズもリセット
        ppCurrentSeries = null;
        var tabs = document.getElementById('ppDetailSubtabs');
        if (tabs) {
            tabs.querySelectorAll('.pp-subtab').forEach(function(b, idx){
                if (idx === i) b.classList.add('active'); else b.classList.remove('active');
            });
        }
        var content = document.getElementById('ppDetailContent');
        if (content) {
            content.innerHTML = ppRenderActiveSubsheet();
            ppBindDetailEvents();
        }
    }

    // 層カード + シリーズピルのクリックイベント
    function ppBindDetailEvents() {
        var content = document.getElementById('ppDetailContent');
        if (!content) return;
        content.querySelectorAll('.pp-rank-card').forEach(function(card){
            var trigger = function(){
                ppCurrentRank = card.getAttribute('data-rank');
                // ランク切替時はシリーズも「すべて」に戻す
                ppCurrentSeries = null;
                content.innerHTML = ppRenderActiveSubsheet();
                ppBindDetailEvents();
            };
            card.addEventListener('click', trigger);
            card.addEventListener('keydown', function(e){
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); trigger(); }
            });
        });
        content.querySelectorAll('.pp-series-pill').forEach(function(btn){
            btn.addEventListener('click', function(){
                var v = btn.getAttribute('data-series');
                ppCurrentSeries = (v === '' || v == null) ? null : v;
                content.innerHTML = ppRenderActiveSubsheet();
                ppBindDetailEvents();
            });
        });
        // 行コピーボタン
        content.querySelectorAll('.pp-copy-btn').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.stopPropagation();
                var text = btn.getAttribute('data-copy') || '';
                if (!text) return;
                var done = function(ok){
                    btn.classList.add('copied');
                    var origHtml = btn.innerHTML;
                    btn.innerHTML = ok
                        ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
                        : '!';
                    setTimeout(function(){
                        btn.classList.remove('copied');
                        btn.innerHTML = origHtml;
                    }, 1200);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function(){ done(true); }, function(){ done(false); });
                } else {
                    // fallback
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    try { document.execCommand('copy'); done(true); }
                    catch (err) { done(false); }
                    document.body.removeChild(ta);
                }
            });
        });

        // === 商品ページ型: シリーズドロップダウン ===
        var seriesSel = content.querySelector('#ppSeriesSelect');
        if (seriesSel) {
            seriesSel.addEventListener('change', function(){
                ppCurrentSeries = seriesSel.value;
                ppProdVariantIdx = 0; // シリーズ切替時はバリアント1番目に
                content.innerHTML = ppRenderActiveSubsheet();
                ppBindDetailEvents();
            });
        }
        // === 商品ページ型: 型番・サイズドロップダウン ===
        var variantSel = content.querySelector('#ppVariantSelect');
        if (variantSel) {
            variantSel.addEventListener('change', function(){
                ppProdVariantIdx = parseInt(variantSel.value, 10) || 0;
                content.innerHTML = ppRenderActiveSubsheet();
                ppBindDetailEvents();
            });
        }
        // === 商品ページ型: 層ボタン ===
        content.querySelectorAll('.pp-prod-tier-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                ppProdTier = btn.getAttribute('data-tier');
                content.innerHTML = ppRenderActiveSubsheet();
                ppBindDetailEvents();
            });
        });
        // === 商品ページ型: 取引形態モード ===
        content.querySelectorAll('.pp-prod-info-mode button').forEach(function(btn){
            btn.addEventListener('click', function(){
                ppProdMode = btn.getAttribute('data-mode');
                content.innerHTML = ppRenderActiveSubsheet();
                ppBindDetailEvents();
            });
        });
        // === 商品ページ型: CTA ===
        content.querySelectorAll('[data-prod-action]').forEach(function(btn){
            btn.addEventListener('click', function(){
                var act = btn.getAttribute('data-prod-action');
                if (act === 'quote') {
                    // 見積作成タブへ遷移
                    var url = new URL(window.location.href);
                    url.searchParams.set('tab', 'create');
                    window.location.href = url.toString();
                } else if (act === 'copy') {
                    // 現在の選択をクリップボードへ
                    var labelEl  = content.querySelector('.pp-prod-price-label');
                    var titleEl  = content.querySelector('.pp-prod-hero-title');
                    var specEl   = content.querySelector('.pp-prod-hero-spec');
                    var valueEl  = content.querySelector('.pp-prod-price-value');
                    var suffixEl = content.querySelector('.pp-prod-price-suffix');
                    var text = [titleEl, specEl, labelEl].filter(function(x){ return x; }).map(function(x){ return x.textContent.trim(); }).join(' / ') +
                               ' : ' + (valueEl ? valueEl.textContent.trim() : '') + (suffixEl ? ' ' + suffixEl.textContent.trim() : '');
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(text).then(function(){
                            btn.textContent = 'コピー完了';
                            setTimeout(function(){ btn.textContent = 'この行をコピー'; }, 1500);
                        });
                    }
                }
            });
        });
    }
    // 互換のため旧名でも呼べるように
    var ppBindRankTabs = ppBindDetailEvents;

    // 詳細検索のバインド（IME 確定を考慮して input + 200ms debounce）
    var ppDetailSearchEl = document.getElementById('ppDetailSearch');
    var ppDetailSearchTimer = null;
    if (ppDetailSearchEl) {
        ppDetailSearchEl.addEventListener('input', function(){
            var val = this.value.trim();
            if (ppDetailSearchTimer) clearTimeout(ppDetailSearchTimer);
            ppDetailSearchTimer = setTimeout(function(){
                ppDetailFilter = val;
                var content = document.getElementById('ppDetailContent');
                if (content && document.getElementById('ppDetailView').style.display !== 'none') {
                    content.innerHTML = ppRenderActiveSubsheet();
                    ppBindDetailEvents();
                }
            }, 200);
        });
    }

    var ppCurrentItemId = null;
    function ppOpenDetail(id) {
        var item = PP_PRODUCTS.find(function(p){ return p.id === id; })
                || PP_COMMON.find(function(c){ return c.id === id; });
        if (!item) return;
        ppCurrentItemId  = id;
        ppCurrentMatched = ppMatchSheets(item.match);
        ppCurrentSubIdx  = 0;
        ppDetailFilter   = '';
        ppProdVariantIdx = 0;
        ppProdTier       = 'A';
        ppProdMode       = 'sale';
        if (ppDetailSearchEl) ppDetailSearchEl.value = '';

        document.getElementById('ppDetailTitle').textContent = item.name + ' 価格表';
        var body = document.getElementById('ppDetailBody');
        if (ppCurrentMatched.length === 0) {
            body.innerHTML = '<div class="pp-empty-state">この資料はまだありません</div>';
        } else if (ppCurrentMatched.length === 1) {
            // 1件しかない場合はサブタブを出さない
            ppCurrentRank = null;
            ppCurrentSeries = null;
            body.innerHTML =
                '<div id="ppDetailContent">' + ppRenderActiveSubsheet() + '</div>';
            ppBindDetailEvents();
        } else {
            ppCurrentRank = null;
            ppCurrentSeries = null;
            body.innerHTML =
                '<div class="pp-subtabs" id="ppDetailSubtabs">' + ppRenderSubtabs() + '</div>' +
                '<div id="ppDetailContent">' + ppRenderActiveSubsheet() + '</div>';
            document.getElementById('ppDetailSubtabs').querySelectorAll('.pp-subtab').forEach(function(btn){
                btn.addEventListener('click', function(){
                    ppShowSub(parseInt(btn.getAttribute('data-idx'), 10));
                });
            });
            ppBindDetailEvents();
        }
        document.getElementById('ppListView').style.display = 'none';
        document.getElementById('ppDetailView').style.display = '';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function ppCloseDetail() {
        document.getElementById('ppDetailView').style.display = 'none';
        document.getElementById('ppListView').style.display = '';
    }

    var ppBackBtn = document.getElementById('ppBack');
    if (ppBackBtn) ppBackBtn.addEventListener('click', ppCloseDetail);

    // タブ起動時に読み込み
    if (document.querySelector('#panel-pricing.active')) ppEnsureLoaded();
    document.querySelectorAll('.st-tab[data-tab="pricing"]').forEach(function(t){
        t.addEventListener('click', function(){ ppEnsureLoaded(); });
    });

    // 同期ボタン（admin のみ存在）
    var ppSyncBtn = document.getElementById('ppSyncBtn');
    if (ppSyncBtn) {
        ppSyncBtn.addEventListener('click', function(){
            if (!confirm('Google ドライブから価格表を再同期します。よろしいですか?\n（約30項目で30秒〜1分かかります）')) return;
            ppSyncBtn.disabled = true;
            var orig = ppSyncBtn.innerHTML;
            ppSyncBtn.innerHTML = '同期中…';
            fetch('../api/price-list-sync.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ csrf_token: CSRF, action: 'sync' })
            })
            .then(function(r){ return r.json(); })
            .then(function(j){
                if (!j.success) throw new Error(j.error || '同期失敗');
                var msg = '同期完了: ' + j.data.sheet_count + '件';
                if (j.data.errors && j.data.errors.length) msg += ' (' + j.data.errors.length + '件エラー)';
                if (typeof showToast === 'function') showToast(msg, j.data.errors.length ? 'warning' : 'success', 5000);
                ppLoaded = false;
                ppEnsureLoaded();
            })
            .catch(function(e){
                if (typeof showToast === 'function') showToast('同期失敗: ' + e.message, 'error');
                else alert('同期失敗: ' + e.message);
            })
            .then(function(){
                ppSyncBtn.disabled = false;
                ppSyncBtn.innerHTML = orig;
            });
        });
    }

    // ========== AI見積アシスタント ==========
    var $qbAiModal   = document.getElementById('qbAiModal');
    var $qbAiOverlay = document.getElementById('qbAiOverlay');
    var $qbAiInput   = document.getElementById('qbAiInput');
    var $qbAiWarn    = document.getElementById('qbAiWarn');

    function qbAiOpenModal() {
        $qbAiWarn.style.display = 'none';
        $qbAiModal.classList.add('open');
        $qbAiModal.setAttribute('aria-hidden', 'false');
        setTimeout(function(){ $qbAiInput.focus(); }, 50);
    }
    function qbAiCloseModal() {
        $qbAiModal.classList.remove('open');
        $qbAiModal.setAttribute('aria-hidden', 'true');
    }

    document.getElementById('qbAiOpen').addEventListener('click', qbAiOpenModal);
    document.querySelectorAll('[data-close-ai-modal]').forEach(function(el){
        el.addEventListener('click', qbAiCloseModal);
    });
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && $qbAiModal.classList.contains('open')) qbAiCloseModal();
    });

    // AIレスポンス → 既存の見積作成フォームに反映
    function qbAiPopulate(quote) {
        if (!quote) return;
        document.getElementById('qbSubject').value = quote.subject || '';

        var custEl = document.getElementById('qbCustomer');
        custEl.value = quote.customer || '';
        // 顧客検索のランク表示を更新するため input イベントを発火
        custEl.dispatchEvent(new Event('input'));

        if (quote.issue_date)  document.getElementById('qbIssueDate').value  = quote.issue_date;
        if (quote.expire_date) document.getElementById('qbExpireDate').value = quote.expire_date;

        // 既存明細をクリアして AI 提案を流し込む
        if (itemList) itemList.innerHTML = '';

        (quote.items || []).forEach(function(it){
            var allowedTypes = ['product','install','shipping','other'];
            var type = allowedTypes.indexOf(it.type) >= 0 ? it.type : 'other';
            var row = buildItemRow(type);

            if (type === 'product') {
                var sel = row.querySelector('.qb-name-product');
                if (sel && it.product_id) {
                    var hasOpt = Array.prototype.some.call(sel.options, function(o){ return o.value === it.product_id; });
                    if (hasOpt) sel.value = it.product_id;
                }
            } else {
                var inp = row.querySelector('.qb-name');
                if (inp) inp.value = it.name || '';
            }
            row.querySelector('.qb-qty').value   = it.qty   || 1;
            row.querySelector('.qb-price').value = it.price || 0;
            itemList.appendChild(row);
        });
        recalcAll();
    }

    function qbAiSwitchToCreateTab() {
        if (document.querySelector('#panel-create.active')) return;
        var tab = document.querySelector('.st-tab[data-tab="create"]');
        if (tab) tab.click();
    }

    document.getElementById('qbAiSubmit').addEventListener('click', function(){
        var text = $qbAiInput.value.trim();
        if (!text) {
            $qbAiWarn.textContent = '指示文を入力してください';
            $qbAiWarn.style.display = '';
            return;
        }
        $qbAiWarn.style.display = 'none';
        $qbAiOverlay.classList.add('open');

        fetch('../api/quote-ai.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ csrf_token: CSRF, request_text: text })
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            $qbAiOverlay.classList.remove('open');
            if (!j.success) throw new Error(j.error || '生成失敗');
            var quote = j.data && j.data.quote;
            qbAiPopulate(quote);
            qbAiCloseModal();
            qbAiSwitchToCreateTab();

            var notes = (quote && quote.notes) || '';
            var rank  = (quote && quote.customer_rank) || '';
            var msg   = 'AI見積を反映しました' + (rank ? '（ランク' + rank + '適用）' : '');
            if (typeof showToast === 'function') {
                showToast(msg + (notes ? '\n' + notes : ''), 'success', 6000);
            } else if (notes) {
                alert(msg + '\n\n' + notes);
            }
        })
        .catch(function(e){
            $qbAiOverlay.classList.remove('open');
            $qbAiWarn.textContent = '生成失敗: ' + e.message;
            $qbAiWarn.style.display = '';
        });
    });
})();
</script>
