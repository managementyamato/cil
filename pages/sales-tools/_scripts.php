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
    var $leadList  = document.getElementById('leadList');
    var $leadEmpty = document.getElementById('leadEmpty');
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

    // 共通モーダル (js/common-utils.js の openModal/closeModal を使用 — class="modal" + .active)
    function leadOpenModal() {
        openModal('leadModal');
        $leadModal.setAttribute('aria-hidden', 'false');
        setTimeout(function(){ document.getElementById('leadFCompany').focus(); }, 50);
    }
    function leadCloseModal() {
        closeModal('leadModal');
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
    // 閉じるボタン (data-close-modal): リードモーダル内のみハンドル (他は別モーダル用ハンドラへ)
    document.querySelectorAll('#leadModal [data-close-modal]').forEach(function(el){
        el.addEventListener('click', leadHandleCancelClose);
    });
    document.addEventListener('keydown', function(e){
        if (e.key !== 'Escape') return;
        if ($leadModal.classList.contains('active')) { leadHandleCancelClose(); return; }
        var $cm = document.getElementById('leadCardModal');
        if ($cm && $cm.classList.contains('active')) { leadCardHandleCancelClose(); }
    });

    function leadResetForm() {
        ['leadFCompany','leadFPerson','leadFEmail','leadFAm',
         'leadFDealerBranch','leadFEndUser','leadFSite',
         'leadFProductSize','leadFProductName','leadFNotes']
        .forEach(function(id){ document.getElementById(id).value = ''; });
        document.getElementById('leadFStatus').value = '新規';
        document.getElementById('leadFPrefecture').value = '';
        document.getElementById('leadFTxnType').value = '';
        document.getElementById('leadFCloseDate').value = '';
        document.getElementById('leadFConfidence').value = '';
        document.getElementById('leadFQuoteStatus').value = '';
        $leadModalImageWrap.style.display = 'none';
        $leadModalImage.src = '';
        // タイムラインは新規作成時には不要 (リード保存後に編集モードで再オープンしたら表示)
        var tlSection = document.getElementById('leadTimelineSection');
        if (tlSection) tlSection.style.display = 'none';
        var tlBody = document.getElementById('leadTlBody');
        if (tlBody) tlBody.value = '';
    }

    function leadFillForm(lead) {
        // 基本情報
        document.getElementById('leadFAm').value           = lead.am || '';
        document.getElementById('leadFEmail').value         = lead.email || '';
        document.getElementById('leadFCompany').value       = lead.company_name || '';
        document.getElementById('leadFDealerBranch').value  = lead.dealer_branch || '';
        document.getElementById('leadFPerson').value        = lead.person_name || '';
        // 現場情報
        document.getElementById('leadFPrefecture').value    = lead.prefecture || '';
        document.getElementById('leadFEndUser').value       = lead.end_user_company || '';
        document.getElementById('leadFSite').value          = lead.site_name || '';
        // 販売情報
        document.getElementById('leadFTxnType').value       = lead.transaction_type || '';
        document.getElementById('leadFProductSize').value   = lead.product_size || '';
        document.getElementById('leadFProductName').value   = lead.product_name || '';
        document.getElementById('leadFNotes').value         = lead.notes || '';
        // 商談情報
        document.getElementById('leadFCloseDate').value     = lead.expected_close_date || '';
        document.getElementById('leadFConfidence').value    = lead.confidence || '';
        document.getElementById('leadFQuoteStatus').value   = lead.quote_status || '';
        // ステータス
        document.getElementById('leadFStatus').value        = lead.status || '新規';

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
            // 基本情報
            am:                  document.getElementById('leadFAm').value.trim(),
            email:               document.getElementById('leadFEmail').value.trim(),
            company_name:        document.getElementById('leadFCompany').value.trim(),
            dealer_branch:       document.getElementById('leadFDealerBranch').value.trim(),
            person_name:         document.getElementById('leadFPerson').value.trim(),
            // 現場情報
            prefecture:          document.getElementById('leadFPrefecture').value,
            end_user_company:    document.getElementById('leadFEndUser').value.trim(),
            site_name:           document.getElementById('leadFSite').value.trim(),
            // 販売情報
            transaction_type:    document.getElementById('leadFTxnType').value,
            product_size:        document.getElementById('leadFProductSize').value.trim(),
            product_name:        document.getElementById('leadFProductName').value.trim(),
            notes:               document.getElementById('leadFNotes').value.trim(),
            // 商談情報
            expected_close_date: document.getElementById('leadFCloseDate').value,
            confidence:          document.getElementById('leadFConfidence').value,
            quote_status:        document.getElementById('leadFQuoteStatus').value,
            // ステータス
            status:              document.getElementById('leadFStatus').value
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
                       (l.email||'') + ' ' + (l.product_name||'') + ' ' +
                       (l.site_name||'') + ' ' + (l.end_user_company||'') + ' ' +
                       (l.notes||'')).toLowerCase();
            return hay.indexOf(q) !== -1;
        });

        if (rows.length === 0) {
            $leadList.style.display = 'none';
            $leadEmpty.style.display = '';
        } else {
            $leadList.style.display = '';
            $leadEmpty.style.display = 'none';
        }

        // SVG アイコン定義
        var svgMail  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>';
        var svgPhone = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
        var svgEdit  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
        var svgTrash = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>';

        var html = rows.map(function(l){
            var sourceBadge = l.source === 'business_card'
                ? '<span class="lead-source-badge business_card" title="名刺OCR">名刺</span>'
                : '';

            // サブ情報 (製品 / 現場)
            var subParts = [];
            if (l.product_name) subParts.push(escapeHtml(l.product_name));
            if (l.product_size) subParts.push(escapeHtml(l.product_size));
            if (l.transaction_type) {
                var txnLabel = {'sale':'販売','rental_12m':'レンタル12M','rental_24m':'レンタル24M','rental_long':'レンタル長期'};
                subParts.push(escapeHtml(txnLabel[l.transaction_type] || l.transaction_type));
            }
            var sub = subParts.join(' / ');

            // メタ情報 (現場・都道府県・エンドユーザー)
            var metaParts = [];
            if (l.prefecture) metaParts.push(escapeHtml(l.prefecture));
            if (l.site_name) metaParts.push(escapeHtml(l.site_name));
            if (l.end_user_company) metaParts.push(escapeHtml(l.end_user_company));
            if (l.dealer_branch) metaParts.push(escapeHtml(l.dealer_branch));

            // 右側アクション
            var acts = '';
            if (l.email) {
                acts += '<a href="/pages/email.php?to=' + encodeURIComponent(l.email) + '&subject=' + encodeURIComponent('【' + (l.company || '') + '】') + '" class="bc-icon-btn" title="' + escapeHtml(l.email) + '">' + svgMail + '</a>';
            }
            var telNum = l.phone || l.mobile || '';
            if (telNum) {
                acts += '<a href="tel:' + escapeHtml(telNum) + '" class="bc-icon-btn" title="' + escapeHtml(telNum) + '">' + svgPhone + '</a>';
            }
            if (CAN_EDIT_LEAD) {
                acts += '<button type="button" class="bc-icon-btn" data-edit="' + escapeHtml(l.id) + '" title="編集">' + svgEdit + '</button>';
            }
            if (CAN_DELETE_LEAD) {
                acts += '<button type="button" class="bc-icon-btn bc-icon-danger" data-delete="' + escapeHtml(l.id) + '" title="削除">' + svgTrash + '</button>';
            }

            return '<div class="bc-card" data-view="' + escapeHtml(l.id) + '">' +
                '<div class="bc-card-left">' +
                    '<div class="bc-card-info">' +
                        '<div class="bc-card-name">' +
                            escapeHtml(l.company_name || '(無題)') + sourceBadge +
                            (l.person_name ? ' <span class="lead-card-person">' + escapeHtml(l.person_name) + '</span>' : '') +
                        '</div>' +
                        (sub ? '<div class="bc-card-sub">' + sub + '</div>' : '') +
                        (metaParts.length ? '<div class="bc-card-meta">' + metaParts.join('  ') + '</div>' : '') +
                    '</div>' +
                '</div>' +
                '<div class="bc-card-actions">' + acts + '</div>' +
            '</div>';
        }).join('');
        $leadList.innerHTML = html;

        // カードクリック → モーダルで詳細表示 (イベント委譲)
        $leadList.querySelectorAll('.bc-card[data-view]').forEach(function(card){
            card.addEventListener('click', function(e){
                // アクションボタン系がクリックされた場合はスキップ
                if (e.target.closest('.bc-icon-btn, a')) return;
                leadEdit(card.getAttribute('data-view'));
            });
        });
        // 行の編集・削除イベント
        $leadList.querySelectorAll('[data-edit]').forEach(function(btn){
            btn.addEventListener('click', function(e){ e.stopPropagation(); leadEdit(btn.getAttribute('data-edit')); });
        });
        $leadList.querySelectorAll('[data-delete]').forEach(function(btn){
            btn.addEventListener('click', function(e){ e.stopPropagation(); leadDelete(btn.getAttribute('data-delete')); });
        });
    }

    function leadFetch() {
        return fetch('../api/leads-api.php?action=list', { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(j){
                if (!j.success) throw new Error(j.error || '取得失敗');
                leadsCache = j.data.leads || [];
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
        // タイムラインセクションを表示してロード (編集モードのみ)
        var tlSection = document.getElementById('leadTimelineSection');
        if (tlSection) {
            tlSection.style.display = '';
            leadTimelineLoad(id);
        }
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
        if (!payload.company_name)    { leadShowToast('ディーラー名は必須です', 'error'); return; }
        if (!payload.person_name)     { leadShowToast('担当者名は必須です', 'error'); return; }
        if (!payload.prefecture)      { leadShowToast('都道府県を選択してください', 'error'); return; }
        if (!payload.transaction_type){ leadShowToast('販売/レンタルを選択してください', 'error'); return; }
        if (!payload.product_name)    { leadShowToast('製品名は必須です', 'error'); return; }

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
            // v2 Phase 2: スキャン結果は business_cards に保存する (リードではなく名刺扱い)
            leadCardEditingId = null;
            leadCardPendingImageDataUrl = j.data.image_data_url || '';
            var fields = j.data.fields || {};
            document.getElementById('leadCardModalTitle').textContent = leadScanTotal > 1
                ? ('名刺の解析結果 (' + idx + ' / ' + leadScanTotal + ')')
                : '名刺の解析結果（内容を確認して保存）';
            leadCardResetForm();
            // OCR 結果を名刺モーダルに pre-fill
            document.getElementById('leadCardFCompany').value = fields.company_name || '';
            document.getElementById('leadCardFPerson').value  = fields.person_name  || '';
            document.getElementById('leadCardFTitle').value   = fields.title        || '';
            document.getElementById('leadCardFDept').value    = fields.department   || '';
            document.getElementById('leadCardFPhone').value   = fields.phone        || '';
            document.getElementById('leadCardFMobile').value  = fields.mobile       || '';
            document.getElementById('leadCardFEmail').value   = fields.email        || '';
            document.getElementById('leadCardFFax').value     = fields.fax          || '';
            document.getElementById('leadCardFWebsite').value = fields.website      || '';
            document.getElementById('leadCardFAddress').value = fields.address      || '';
            document.getElementById('leadCardFExchanged').value = new Date().toISOString().substring(0, 10);
            if (leadCardPendingImageDataUrl) {
                document.getElementById('leadCardModalImage').src = leadCardPendingImageDataUrl;
                document.getElementById('leadCardModalImageWrap').style.display = '';
            }
            leadCardOpenModal();
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

    // ───────────────────────────────────────────────────────────
    //  タイムライン (リード管理 v2 Phase 1)
    // ───────────────────────────────────────────────────────────
    function leadTimelineLoad(leadId) {
        var $list = document.getElementById('leadTimelineList');
        if (!$list) return;
        $list.innerHTML = '<div class="lead-timeline-loading">読み込み中…</div>';
        fetch('../api/leads-api.php?action=list_activities&lead_id=' + encodeURIComponent(leadId), {
            credentials: 'same-origin'
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) throw new Error(j.error || '取得失敗');
            leadTimelineRender(j.data.items || []);
        })
        .catch(function(e){
            $list.innerHTML = '<div class="lead-timeline-empty" style="color:#dc2626;">読み込みエラー: ' + escapeHtml(e.message) + '</div>';
        });
    }

    function leadTimelineRender(items) {
        var $list = document.getElementById('leadTimelineList');
        if (!$list) return;
        if (!items.length) {
            $list.innerHTML = '<div class="lead-timeline-empty">タイムラインはまだありません。ステータスを変更するか、上のフォームから手動でメモを追加してください。</div>';
            return;
        }
        var typeLabel = {
            'status_change': 'ステータス',
            'manual_note':   'メモ',
            'meeting':       '商談',
            'quote':         '見積',
            'promotion':     '昇格',
            'system':        'システム'
        };
        $list.innerHTML = items.map(function(it){
            var when  = (it.occurred_at || '').replace(' ', ' ');
            var who   = escapeHtml(it.created_by_name || it.created_by || '');
            var label = typeLabel[it.type] || it.type;
            var bodyHtml = '';
            if (it.type === 'status_change') {
                bodyHtml = '<span class="lead-timeline-status-arrow">' +
                    escapeHtml(it.from_status || '未設定') + ' → ' + escapeHtml(it.to_status || '未設定') +
                '</span>';
                if (it.body) {
                    bodyHtml += '<div style="margin-top:0.3rem;color:var(--gray-600);">' + escapeHtml(it.body) + '</div>';
                }
            } else {
                if (it.title) bodyHtml += '<strong>' + escapeHtml(it.title) + '</strong><br>';
                bodyHtml += escapeHtml(it.body || '').replace(/\n/g, '<br>');
            }
            var delBtn = CAN_DELETE_LEAD
                ? '<button type="button" class="lead-timeline-delete" data-tl-delete="' + escapeHtml(it.id) + '" title="削除">削除</button>'
                : '';
            return '<div class="lead-timeline-item" data-type="' + escapeHtml(it.type) + '">' +
                '<div class="lead-timeline-dot"></div>' +
                '<div>' +
                    '<div class="lead-timeline-body-text">' + bodyHtml + '</div>' +
                    '<div class="lead-timeline-meta">' +
                        '<span class="lead-timeline-type-pill">' + escapeHtml(label) + '</span>' +
                        '<span>' + escapeHtml(when) + '</span>' +
                        (who ? '<span>' + who + '</span>' : '') +
                        delBtn +
                    '</div>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    function leadTimelineAdd() {
        if (!leadEditingId) { leadShowToast('リードを保存してから追加できます', 'error'); return; }
        var typeEl = document.getElementById('leadTlType');
        var bodyEl = document.getElementById('leadTlBody');
        var body = (bodyEl.value || '').trim();
        if (!body) { leadShowToast('メモを入力してください', 'error'); return; }
        var btn = document.getElementById('leadTlAddBtn');
        btn.disabled = true;
        fetch('../api/leads-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({
                action: 'add_activity',
                csrf_token: CSRF,
                lead_id: leadEditingId,
                type: typeEl.value || 'manual_note',
                body: body
            })
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) throw new Error(j.error || '追加失敗');
            bodyEl.value = '';
            leadTimelineLoad(leadEditingId);
        })
        .catch(function(e){ leadShowToast('追加失敗: ' + e.message, 'error'); })
        .then(function(){ btn.disabled = false; });
    }

    function leadTimelineDelete(activityId) {
        if (!confirm('このタイムラインエントリを削除しますか?')) return;
        fetch('../api/leads-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ action: 'delete_activity', csrf_token: CSRF, id: activityId })
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) throw new Error(j.error || '削除失敗');
            if (leadEditingId) leadTimelineLoad(leadEditingId);
        })
        .catch(function(e){ leadShowToast('削除失敗: ' + e.message, 'error'); });
    }

    // 追加ボタン
    var $leadTlAddBtn = document.getElementById('leadTlAddBtn');
    if ($leadTlAddBtn) $leadTlAddBtn.addEventListener('click', leadTimelineAdd);

    // タイムラインリスト内の削除ボタン (イベント委譲)
    var $leadTlList = document.getElementById('leadTimelineList');
    if ($leadTlList) {
        $leadTlList.addEventListener('click', function(e){
            var btn = e.target.closest('[data-tl-delete]');
            if (!btn) return;
            leadTimelineDelete(btn.getAttribute('data-tl-delete'));
        });
    }

    // ───────────────────────────────────────────────────────────
    //  名刺 サブタブ (リード管理 v2 Phase 2)
    // ───────────────────────────────────────────────────────────
    var leadCardsCache       = [];
    var leadCardsLoaded      = false;
    var leadCardEditingId    = null;
    var leadCardFilterPromoted = '';
    var leadCardFilterSearch   = '';
    var leadCardPendingImageDataUrl = ''; // OCR スキャン経由の画像 (data URL)

    function leadCardFetch() {
        var qs = [];
        if (leadCardFilterSearch)   qs.push('search=' + encodeURIComponent(leadCardFilterSearch));
        if (leadCardFilterPromoted === 'promoted')   qs.push('promoted_only=1');
        if (leadCardFilterPromoted === 'unpromoted') qs.push('unpromoted_only=1');
        fetch('../api/leads-api.php?action=list_cards' + (qs.length ? '&' + qs.join('&') : ''), {
            credentials: 'same-origin'
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) throw new Error(j.error || '取得失敗');
            leadCardsCache = j.data.items || [];
            leadCardRender();
            updateSubtabCounts();
        })
        .catch(function(e){ leadShowToast('名刺一覧の取得に失敗: ' + e.message, 'error'); });
    }

    function leadCardRender() {
        var $list  = document.getElementById('leadCardsList');
        var $empty = document.getElementById('leadCardsEmpty');
        if (!$list) return;
        if (!leadCardsCache.length) {
            $list.innerHTML = '';
            if ($empty) $empty.style.display = '';
            return;
        }
        if ($empty) $empty.style.display = 'none';

        // SVG アイコン定義
        var svgMail  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>';
        var svgPhone = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
        var svgEdit  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
        var svgTrash = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>';

        $list.innerHTML = leadCardsCache.map(function(c){
            var promoted = c.promoted_lead_id && c.promoted_lead_id !== '';
            // イニシャルバッジ
            var initial = (c.person_name || c.company_name || '?').substring(0, 2);
            // サブ情報
            var sub = escapeHtml(c.company_name || '');
            var titleDept = [c.title, c.department].filter(Boolean).map(escapeHtml).join(' / ');
            if (titleDept) sub += ' / ' + titleDept;
            var meta = [];
            if (c.exchanged_at) meta.push('名刺交換: ' + escapeHtml(c.exchanged_at.substring(0, 10)));
            if (c.registered_by) meta.push('登録: ' + escapeHtml(c.registered_by.split('@')[0]));

            // 右側アクション
            var acts = '';
            if (!promoted && CAN_EDIT_LEAD) {
                acts += '<button type="button" class="bc-action-btn bc-promote" data-card-promote="' + escapeHtml(c.id) + '">→ リード昇格</button>';
            }
            if (promoted) {
                acts += '<span class="bc-promoted-label">昇格済</span>';
            }
            if (c.email) {
                acts += '<a href="/pages/email.php?to=' + encodeURIComponent(c.email) + '&subject=' + encodeURIComponent('【' + (c.company || '') + '】') + '" class="bc-icon-btn" title="' + escapeHtml(c.email) + '">' + svgMail + '</a>';
            }
            var telNum = c.phone || c.mobile || '';
            if (telNum) {
                acts += '<a href="tel:' + escapeHtml(telNum) + '" class="bc-icon-btn" title="' + escapeHtml(telNum) + '">' + svgPhone + '</a>';
            }
            if (CAN_EDIT_LEAD) {
                acts += '<button type="button" class="bc-icon-btn" data-card-edit="' + escapeHtml(c.id) + '" title="編集">' + svgEdit + '</button>';
            }
            if (CAN_DELETE_LEAD) {
                acts += '<button type="button" class="bc-icon-btn bc-icon-danger" data-card-delete="' + escapeHtml(c.id) + '" title="削除">' + svgTrash + '</button>';
            }

            return '<div class="bc-card">' +
                '<div class="bc-card-left">' +
                    '<span class="bc-avatar">' + escapeHtml(initial) + '</span>' +
                    '<div class="bc-card-info">' +
                        '<div class="bc-card-name">' + escapeHtml(c.person_name || c.company_name || '-') + '</div>' +
                        '<div class="bc-card-sub">' + sub + '</div>' +
                        (meta.length ? '<div class="bc-card-meta">' + meta.join('  ') + '</div>' : '') +
                    '</div>' +
                '</div>' +
                '<div class="bc-card-actions">' + acts + '</div>' +
            '</div>';
        }).join('');
    }

    function leadCardResetForm() {
        ['leadCardFCompany','leadCardFPerson','leadCardFTitle','leadCardFDept','leadCardFPhone','leadCardFMobile',
         'leadCardFEmail','leadCardFFax','leadCardFWebsite','leadCardFExchanged','leadCardFAddress','leadCardFNotes']
        .forEach(function(id){ var el = document.getElementById(id); if (el) el.value = ''; });
    }
    function leadCardFillForm(card) {
        document.getElementById('leadCardFCompany').value   = card.company_name || '';
        document.getElementById('leadCardFPerson').value    = card.person_name  || '';
        document.getElementById('leadCardFTitle').value     = card.title        || '';
        document.getElementById('leadCardFDept').value      = card.department   || '';
        document.getElementById('leadCardFPhone').value     = card.phone        || '';
        document.getElementById('leadCardFMobile').value    = card.mobile       || '';
        document.getElementById('leadCardFEmail').value     = card.email        || '';
        document.getElementById('leadCardFFax').value       = card.fax          || '';
        document.getElementById('leadCardFWebsite').value   = card.website      || '';
        document.getElementById('leadCardFExchanged').value = (card.exchanged_at || '').substring(0, 10);
        document.getElementById('leadCardFAddress').value   = card.address      || '';
        document.getElementById('leadCardFNotes').value     = card.notes        || '';
    }
    function leadCardCollectForm() {
        return {
            company_name: document.getElementById('leadCardFCompany').value.trim(),
            person_name:  document.getElementById('leadCardFPerson').value.trim(),
            title:        document.getElementById('leadCardFTitle').value.trim(),
            department:   document.getElementById('leadCardFDept').value.trim(),
            phone:        document.getElementById('leadCardFPhone').value.trim(),
            mobile:       document.getElementById('leadCardFMobile').value.trim(),
            email:        document.getElementById('leadCardFEmail').value.trim(),
            fax:          document.getElementById('leadCardFFax').value.trim(),
            website:      document.getElementById('leadCardFWebsite').value.trim(),
            exchanged_at: document.getElementById('leadCardFExchanged').value || null,
            address:      document.getElementById('leadCardFAddress').value.trim(),
            notes:        document.getElementById('leadCardFNotes').value.trim()
        };
    }

    function leadCardOpenModal() {
        openModal('leadCardModal');
        var $m = document.getElementById('leadCardModal');
        if ($m) $m.setAttribute('aria-hidden', 'false');
    }
    function leadCardCloseModal() {
        closeModal('leadCardModal');
        var $m = document.getElementById('leadCardModal');
        if ($m) $m.setAttribute('aria-hidden', 'true');
        leadCardEditingId = null;
        leadCardPendingImageDataUrl = '';
        var iw = document.getElementById('leadCardModalImageWrap');
        var ig = document.getElementById('leadCardModalImage');
        if (iw) iw.style.display = 'none';
        if (ig) ig.src = '';
    }
    // モーダル閉じる: OCR キュー中は「スキップ」として次へ進める
    function leadCardHandleCancelClose() {
        var wasInQueue = leadInQueueMode;
        leadCardCloseModal();
        if (wasInQueue) leadQueueAdvance('skipped');
    }
    document.addEventListener('click', function(e){
        if (e.target.hasAttribute && e.target.hasAttribute('data-close-card-modal')) {
            leadCardHandleCancelClose();
        }
    });

    function leadCardAdd() {
        if (!CAN_EDIT_LEAD) { leadShowToast('権限がありません', 'error'); return; }
        leadCardEditingId = null;
        document.getElementById('leadCardModalTitle').textContent = '名刺登録';
        leadCardResetForm();
        // 名刺交換日は今日をデフォルト
        document.getElementById('leadCardFExchanged').value = new Date().toISOString().substring(0, 10);
        leadCardOpenModal();
    }
    function leadCardEdit(id) {
        var card = leadCardsCache.find(function(c){ return c.id === id; });
        if (!card) return;
        leadCardEditingId = id;
        document.getElementById('leadCardModalTitle').textContent = '名刺編集';
        leadCardResetForm();
        leadCardFillForm(card);
        leadCardOpenModal();
    }
    function leadCardDelete(id) {
        var card = leadCardsCache.find(function(c){ return c.id === id; });
        if (!card) return;
        if (!confirm('名刺「' + (card.company_name || '') + ' / ' + (card.person_name || '') + '」を削除しますか?')) return;
        fetch('../api/leads-api.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ action: 'delete_card', csrf_token: CSRF, id: id })
        }).then(function(r){ return r.json(); }).then(function(j){
            if (!j.success) throw new Error(j.error || '削除失敗');
            leadShowToast('名刺を削除しました', 'success'); leadCardFetch();
        }).catch(function(e){ leadShowToast('削除失敗: ' + e.message, 'error'); });
    }
    function leadCardSave() {
        if (!CAN_EDIT_LEAD) { leadShowToast('権限がありません', 'error'); return; }
        var payload = leadCardCollectForm();
        if (!payload.company_name) { leadShowToast('会社名は必須です', 'error'); return; }
        var body = Object.assign({ csrf_token: CSRF }, payload);
        if (leadCardEditingId) {
            body.action = 'update_card'; body.id = leadCardEditingId;
        } else {
            body.action = 'create_card';
            // OCR スキャン経由なら画像も送信して business_card_image_path に保存させる
            if (leadCardPendingImageDataUrl) {
                body.image_data_url = leadCardPendingImageDataUrl;
                body.ocr_source     = 'scan';
            }
        }
        fetch('../api/leads-api.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(body)
        }).then(function(r){ return r.json(); }).then(function(j){
            if (!j.success) throw new Error(j.error || '保存失敗');
            // キュー中は最後にまとめて表示、単発はその場でトースト
            if (!leadInQueueMode) {
                leadShowToast(leadCardEditingId ? '名刺を更新しました' : '名刺を登録しました', 'success');
            }
            var wasInQueue = leadInQueueMode;
            leadCardCloseModal(); leadCardFetch();
            if (wasInQueue) leadQueueAdvance('saved');
        }).catch(function(e){
            leadShowToast('保存失敗: ' + e.message, 'error');
            if (leadInQueueMode) { leadCardCloseModal(); leadQueueAdvance('error'); }
        });
    }
    function leadCardPromote(id) {
        var card = leadCardsCache.find(function(c){ return c.id === id; });
        if (!card) return;
        if (!confirm('名刺「' + (card.company_name || '') + ' / ' + (card.person_name || '') + '」をリードに昇格しますか?\n(名刺情報を初期値として新しいリードが作成されます)')) return;
        fetch('../api/leads-api.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ action: 'promote_card_to_lead', csrf_token: CSRF, id: id })
        }).then(function(r){ return r.json(); }).then(function(j){
            if (!j.success) throw new Error(j.error || '昇格失敗');
            leadShowToast('リードに昇格しました (リードタブで編集できます)', 'success');
            leadCardFetch();
            // リード側も同時に更新
            leadsLoaded = false;
            leadEnsureLoaded();
        }).catch(function(e){ leadShowToast('昇格失敗: ' + e.message, 'error'); });
    }

    // 名刺ボタン
    document.getElementById('leadCardAddBtn').addEventListener('click', leadCardAdd);
    document.getElementById('leadCardSaveBtn').addEventListener('click', leadCardSave);
    document.getElementById('leadCardSearch').addEventListener('input', function(){
        leadCardFilterSearch = this.value.trim(); leadCardFetch();
    });
    document.getElementById('leadCardPromotedFilter').addEventListener('change', function(){
        leadCardFilterPromoted = this.value; leadCardFetch();
    });

    // 名刺カードクリック委譲
    document.getElementById('leadCardsList').addEventListener('click', function(e){
        var b;
        if ((b = e.target.closest('[data-card-edit]')))    return leadCardEdit(b.getAttribute('data-card-edit'));
        if ((b = e.target.closest('[data-card-delete]')))  return leadCardDelete(b.getAttribute('data-card-delete'));
        if ((b = e.target.closest('[data-card-promote]'))) return leadCardPromote(b.getAttribute('data-card-promote'));
    });

    // ── サブタブ切替 ──
    function updateSubtabCounts() {
        var cc = document.getElementById('leadCardsCount');
        var lc = document.getElementById('leadLeadsCount');
        if (cc) cc.textContent = leadCardsCache.length;
        if (lc) lc.textContent = leadsCache ? leadsCache.length : 0;
    }
    function leadSwitchSubpanel(name) {
        document.querySelectorAll('.lead-subtab').forEach(function(t){
            t.classList.toggle('active', t.getAttribute('data-lead-subtab') === name);
        });
        ['cards','leads'].forEach(function(n){
            var p = document.getElementById('leadSubpanel-' + n);
            if (!p) return;
            var active = (n === name);
            p.classList.toggle('active', active);
            if (active) p.removeAttribute('hidden');
            else        p.setAttribute('hidden', '');
        });
        if (name === 'cards' && !leadCardsLoaded) { leadCardsLoaded = true; leadCardFetch(); }
    }
    document.querySelectorAll('.lead-subtab').forEach(function(t){
        t.addEventListener('click', function(){ leadSwitchSubpanel(t.getAttribute('data-lead-subtab')); });
    });

    // 初回フェッチ（リードタブがアクティブな時のみ即時実行・他タブからの切替時にも再取得）
    var leadsLoaded = false;
    function leadEnsureLoaded() {
        if (leadsLoaded) return;
        leadsLoaded = true;
        leadFetch();
        // 名刺カウントも初期表示用に取得 (バックグラウンド)
        if (!leadCardsLoaded) { leadCardsLoaded = true; leadCardFetch(); }
    }
    if (document.querySelector('#panel-leads.active')) leadEnsureLoaded();
    document.querySelectorAll('.st-tab[data-tab="leads"]').forEach(function(t){
        t.addEventListener('click', function(){ leadEnsureLoaded(); });
    });


    // ========== AI見積アシスタント ==========
    var $qbAiModal   = document.getElementById('qbAiModal');
    var $qbAiOverlay = document.getElementById('qbAiOverlay');
    var $qbAiInput   = document.getElementById('qbAiInput');
    var $qbAiWarn    = document.getElementById('qbAiWarn');

    function qbAiOpenModal() {
        $qbAiWarn.style.display = 'none';
        openModal('qbAiModal');
        $qbAiModal.setAttribute('aria-hidden', 'false');
        setTimeout(function(){ $qbAiInput.focus(); }, 50);
    }
    function qbAiCloseModal() {
        closeModal('qbAiModal');
        $qbAiModal.setAttribute('aria-hidden', 'true');
    }

    document.getElementById('qbAiOpen').addEventListener('click', qbAiOpenModal);
    document.querySelectorAll('[data-close-ai-modal]').forEach(function(el){
        el.addEventListener('click', qbAiCloseModal);
    });
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && $qbAiModal.classList.contains('active')) qbAiCloseModal();
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
