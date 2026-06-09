<?php
/**
 * 申請・報告ハブ 共有スタイル
 *   - 3 タブ (報告 / 値引き / リード) の共通要素
 *   - shell-top.php の .hub-* に追加するハブ固有の装飾
 */
?>
<style<?= function_exists('nonceAttr') ? nonceAttr() : '' ?>>
/* ── ヘッダー固有 (申請・報告ハブのみ赤系アイコン) ── */
.hub-header-icon { background: var(--danger-light); color: var(--danger); }

/* ── 共通カード ── */
.hub-card{background:#fff;border:1px solid var(--gray-200);border-radius:12px;padding:1rem 1.25rem;margin-bottom:0.75rem;transition:box-shadow .15s;}
.hub-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.06);}
.hub-card-header{display:flex;align-items:center;justify-content:space-between;gap:0.5rem;margin-bottom:0.5rem;}
.hub-card-title{font-weight:600;font-size:0.95rem;color:var(--gray-800);}
.hub-card-meta{font-size:0.78rem;color:var(--gray-500);}

/* ── ステータスバッジ ── */
.status-badge{display:inline-block;padding:2px 10px;border-radius:9px;font-size:0.73rem;font-weight:600;}
.status-badge.pending{background:#fff3e0;color:#e65100;}
.status-badge.approved{background:#e8f5e9;color:#2e7d32;}
.status-badge.rejected{background:#fce4ec;color:#c62828;}
.status-badge.draft{background:var(--gray-100);color:var(--gray-500);}
.status-badge.submitted{background:#e3f2fd;color:#1565c0;}
.status-badge.confirmed{background:#e8f5e9;color:#2e7d32;}

/* ── フィルタバー ── */
.filter-bar{display:flex;gap:6px;margin-bottom:1rem;flex-wrap:wrap;}
.filter-btn{padding:4px 14px;border:1px solid var(--gray-300);border-radius:8px;font-size:0.78rem;background:#fff;cursor:pointer;color:var(--gray-600);transition:all .15s;}
.filter-btn:hover{border-color:var(--primary);}
.filter-btn.active{background:var(--primary);color:#fff;border-color:var(--primary);}

/* ── サマリーカード ── */
.summary-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0.75rem;margin-bottom:1.25rem;}
.summary-card{background:#fff;border:1px solid var(--gray-200);border-radius:10px;padding:0.75rem 1rem;text-align:center;}
.summary-card .num{font-size:1.4rem;font-weight:700;color:var(--gray-800);}
.summary-card .label{font-size:0.73rem;color:var(--gray-500);margin-top:2px;}

/* ── モーダル ── */
.hub-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:10001;align-items:center;justify-content:center;}
.hub-modal.active{display:flex;}
.hub-modal-content{background:#fff;border-radius:14px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);}
.hub-modal-header{padding:1.25rem 1.5rem;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;}
.hub-modal-header h3{margin:0;font-size:1.1rem;}
.hub-modal-body{padding:1.25rem 1.5rem;}
.hub-modal-footer{padding:1rem 1.5rem;border-top:1px solid var(--gray-200);display:flex;justify-content:flex-end;gap:0.75rem;}
.hub-modal-close{background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--gray-400);line-height:1;}
.hub-modal-close:hover{color:var(--gray-700);}

/* ── レスポンシブ ── */
@media(max-width:768px){
    .summary-row{grid-template-columns:repeat(2,1fr);}
}
</style>

<script<?= function_exists('nonceAttr') ? nonceAttr() : '' ?>>
/**
 * 申請・報告ハブ 共通 JS ヘルパー (window.ReportsHub)
 * 各タブの IIFE から window.ReportsHub.* として参照する。
 * shell-bottom.php の AJAX 切替で同一スクリプトが複数回評価されても安全な再代入式で定義する。
 */
(function(){
    'use strict';
    if (window.ReportsHub && window.ReportsHub.__initialized) return;

    const RH = window.ReportsHub = window.ReportsHub || {};
    RH.__initialized = true;

    RH.esc = function(s){ const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
    RH.fmt = function(n){ return Number(n||0).toLocaleString(); };
    RH.dateShort = function(s){ return s ? String(s).substring(0, 10) : ''; };

    RH.showAlert = function(msg, type){
        const el = document.createElement('div');
        el.className = 'alert alert-' + (type || 'success');
        el.textContent = msg;
        el.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:10000;padding:0.75rem 1.25rem;border-radius:8px;font-size:0.85rem;box-shadow:0 4px 12px rgba(0,0,0,.15);';
        if (type === 'success') { el.style.background = '#e8f5e9'; el.style.color = '#2e7d32'; }
        else { el.style.background = '#fce4ec'; el.style.color = '#c62828'; }
        document.body.appendChild(el);
        setTimeout(function(){ el.remove(); }, 3000);
    };

    RH.openModal  = function(id){ const el = document.getElementById(id); if (el) el.classList.add('active'); };
    RH.closeModal = function(id){ const el = document.getElementById(id); if (el) el.classList.remove('active'); };

    RH.apiGet = async function(api, type, action, extra){
        try {
            const r = await fetch(api + '?type=' + type + '&action=' + action + (extra || ''));
            if (!r.ok) return { items: [], error: 'HTTP ' + r.status };
            const json = await r.json();
            return json.data || json;
        } catch (e) {
            console.error('apiGet error:', e);
            return { items: [], error: e.message };
        }
    };

    RH.apiPost = async function(api, csrf, params){
        try {
            const fd = new FormData();
            fd.append('csrf_token', csrf);
            for (const k in params) { if (Object.prototype.hasOwnProperty.call(params, k)) fd.append(k, params[k]); }
            const r = await fetch(api, { method: 'POST', body: fd });
            if (!r.ok) { const t = await r.text(); return { error: 'HTTP ' + r.status + ': ' + t.substring(0, 200) }; }
            const json = await r.json();
            if (!json.success) return { error: json.error || '不明なエラー' };
            return json.data || json;
        } catch (e) {
            console.error('apiPost error:', e);
            return { error: e.message };
        }
    };

    // モーダルの×ボタン/キャンセル統一処理 (背景クリックでは閉じない)
    if (!RH.__modalCloseHooked) {
        RH.__modalCloseHooked = true;
        document.addEventListener('click', function(e){
            if (e.target.hasAttribute && e.target.hasAttribute('data-close-hub-modal')) {
                const m = e.target.closest('.hub-modal');
                if (m) m.classList.remove('active');
            }
        });
    }
})();
</script>
