<?php
/**
 * 価格表ビュー サンプル（デザイン比較用・preview）
 *
 * 同じ同期済みデータを使い、複数の表示パターンを並べて見せる。
 * 採用するパターンを決めたら sales-tools.php の価格表タブを差し替える想定。
 */
require_once '../api/auth.php';
require_once '../functions/header.php';

$canEditPage   = canEdit();
$canDeletePage = canDelete();
$csrfToken     = generateCsrfToken();
?>
<style<?= nonceAttr() ?>>
.pv-page { max-width: 1280px; margin: 0 auto; padding: 0 0 3rem; }

/* ヒーロー */
.pv-hero {
    background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
    border: 1px solid var(--gray-200);
    border-radius: 14px;
    padding: 1.2rem 1.5rem;
    margin-bottom: 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
.pv-hero h2 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0 0 0.3rem;
}
.pv-hero p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--gray-600);
}
.pv-meta {
    display: flex; gap: 1rem; flex-wrap: wrap;
    font-size: 0.85rem; color: var(--gray-700);
}
.pv-meta span b { color: var(--gray-900); }

/* ビュー切替 */
.pv-switcher {
    display: flex;
    gap: 0.4rem;
    padding: 0.4rem;
    background: var(--gray-100);
    border-radius: 10px;
    margin-bottom: 1.25rem;
    overflow-x: auto;
}
.pv-switcher button {
    padding: 0.55rem 1rem;
    border: none;
    background: transparent;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--gray-700);
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s, color 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.pv-switcher button:hover { color: var(--gray-900); }
.pv-switcher button.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    font-weight: 700;
}

/* 共通: ビュー本体 */
.pv-view { display: none; }
.pv-view.active { display: block; }

/* ============ パターン1: カテゴリカード（ダッシュボード型） ============ */
.cv1-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.cv1-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 1.1rem 1.25rem;
    cursor: pointer;
    transition: box-shadow 0.15s, transform 0.15s, border-color 0.15s;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.cv1-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    transform: translateY(-2px);
    border-color: var(--primary);
}
.cv1-card.active {
    border-color: var(--primary);
    background: var(--primary-light);
}
.cv1-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.cv1-icon.c-blue   { background: #dbeafe; color: #1d4ed8; }
.cv1-icon.c-orange { background: #ffedd5; color: #c2410c; }
.cv1-icon.c-green  { background: #d1fae5; color: #047857; }
.cv1-icon.c-purple { background: #ede9fe; color: #6d28d9; }
.cv1-icon.c-gray   { background: var(--gray-100); color: var(--gray-600); }
.cv1-icon.c-red    { background: #fee2e2; color: #b91c1c; }
.cv1-card h3 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0;
}
.cv1-card p {
    margin: 0;
    font-size: 0.825rem;
    color: var(--gray-600);
    line-height: 1.5;
}
.cv1-card .cv1-count {
    margin-top: auto;
    font-size: 0.75rem;
    color: var(--gray-500);
    padding-top: 0.4rem;
    border-top: 1px solid var(--gray-100);
}
.cv1-card .cv1-count b { color: var(--gray-900); font-size: 0.85rem; }

.cv1-detail {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    overflow: hidden;
    display: none;
}
.cv1-detail.open { display: block; }
.cv1-detail-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--gray-200);
    background: var(--gray-50);
}
.cv1-detail-head h3 { font-size: 1rem; font-weight: 700; margin: 0; }
.cv1-detail-sub {
    display: flex; gap: 0.5rem; padding: 0.6rem 1.25rem;
    border-bottom: 1px solid var(--gray-200);
    overflow-x: auto;
}
.cv1-subtab {
    padding: 0.35rem 0.7rem;
    border-radius: 999px;
    border: 1px solid var(--gray-300);
    background: white;
    color: var(--gray-700);
    font-size: 0.8rem;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.12s;
}
.cv1-subtab:hover { background: var(--gray-50); }
.cv1-subtab.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

/* ============ パターン2: カテゴリタブ（横タブ） ============ */
.cv2-tabs {
    display: flex; gap: 0.25rem;
    border-bottom: 1px solid var(--gray-200);
    margin-bottom: 1rem;
    overflow-x: auto;
}
.cv2-tab {
    padding: 0.75rem 1.1rem;
    border: none;
    background: transparent;
    border-bottom: 2px solid transparent;
    color: var(--gray-700);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.12s;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.cv2-tab:hover { color: var(--gray-900); background: var(--gray-50); }
.cv2-tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    font-weight: 700;
}
.cv2-tab .cv2-count {
    font-size: 0.7rem; padding: 1px 6px;
    background: var(--gray-100); color: var(--gray-700);
    border-radius: 8px;
}
.cv2-tab.active .cv2-count {
    background: var(--primary-light);
    color: var(--primary-dark);
}
.cv2-content {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 1rem;
    min-height: 480px;
}
.cv2-list {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 0.6rem;
    display: flex; flex-direction: column; gap: 2px;
    max-height: 600px; overflow-y: auto;
}
.cv2-item {
    padding: 0.45rem 0.7rem;
    border-radius: 6px;
    font-size: 0.85rem;
    color: var(--gray-800);
    cursor: pointer;
    border: 1px solid transparent;
    background: transparent;
    text-align: left;
    transition: all 0.12s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.cv2-item:hover { background: var(--gray-50); }
.cv2-item.active {
    background: var(--primary-light);
    color: var(--primary-dark);
    border-color: var(--primary);
    font-weight: 600;
}
.cv2-item small { font-size: 0.7rem; color: var(--gray-500); }
.cv2-panel {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 1rem 1.25rem;
}
.cv2-panel-title { font-weight: 700; margin: 0 0 0.6rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--gray-200); }

/* ============ パターン3: アコーディオン ============ */
.cv3-section {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    margin-bottom: 0.6rem;
    overflow: hidden;
}
.cv3-head {
    padding: 0.85rem 1.1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    background: var(--gray-50);
    transition: background 0.12s;
    gap: 0.75rem;
}
.cv3-head:hover { background: var(--gray-100); }
.cv3-head h3 { margin: 0; font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.cv3-head .cv3-meta { font-size: 0.78rem; color: var(--gray-500); }
.cv3-chevron { transition: transform 0.2s; color: var(--gray-500); }
.cv3-section.open .cv3-chevron { transform: rotate(180deg); }
.cv3-body { display: none; padding: 1rem 1.1rem; border-top: 1px solid var(--gray-200); }
.cv3-section.open .cv3-body { display: block; }
.cv3-subitems {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.cv3-subitem {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.825rem;
    color: var(--gray-800);
    cursor: pointer;
    background: white;
    text-align: left;
    transition: all 0.12s;
}
.cv3-subitem:hover { border-color: var(--primary); background: var(--primary-light); }
.cv3-subitem.active { border-color: var(--primary); background: var(--primary-light); color: var(--primary-dark); font-weight: 600; }

/* ============ 共通 テーブル表示 ============ */
.pv-data-table-wrap {
    overflow: auto;
    max-height: 540px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    background: white;
}
.pv-data-table {
    border-collapse: collapse;
    font-size: 0.8125rem;
    width: max-content;
    min-width: 100%;
}
.pv-data-table td {
    padding: 0.4rem 0.65rem;
    border: 1px solid var(--gray-200);
    white-space: pre-wrap;
    vertical-align: top;
    min-width: 60px;
    max-width: 240px;
    word-break: break-word;
}
.pv-data-table tr:first-child td {
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-800);
    position: sticky;
    top: 0;
    z-index: 1;
}
.pv-empty {
    padding: 2rem 1rem;
    text-align: center;
    color: var(--gray-500);
    font-size: 0.9rem;
}
.pv-loading {
    padding: 4rem 1rem;
    text-align: center;
    color: var(--gray-500);
}
.pv-note {
    background: #fef3c7;
    border-left: 4px solid var(--warning);
    border-radius: 6px;
    padding: 0.7rem 1rem;
    font-size: 0.8125rem;
    color: #92400e;
    margin-bottom: 1.25rem;
}

/* ============ 顧客ランク定義 専用ビュー ============ */
.rank-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}
.rank-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.rank-card-head {
    padding: 0.75rem 1rem;
    display: flex; align-items: center; gap: 0.6rem;
    color: white;
    font-weight: 700;
}
.rank-card.rank-A .rank-card-head { background: linear-gradient(135deg, #1d4ed8, #2563eb); }
.rank-card.rank-B .rank-card-head { background: linear-gradient(135deg, #047857, #10b981); }
.rank-card.rank-C .rank-card-head { background: linear-gradient(135deg, #c2410c, #f97316); }
.rank-card.rank-D .rank-card-head { background: linear-gradient(135deg, #b91c1c, #ef4444); }
.rank-badge {
    background: rgba(255,255,255,0.25);
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 800;
}
.rank-deal-type { font-size: 0.875rem; opacity: 0.95; }

.rank-card-body { padding: 1rem; flex: 1; display: flex; flex-direction: column; gap: 0.75rem; }
.rank-condition {
    font-size: 0.8125rem;
    color: var(--gray-700);
    background: var(--gray-50);
    border-left: 3px solid var(--gray-300);
    padding: 0.5rem 0.7rem;
    border-radius: 0 6px 6px 0;
    line-height: 1.5;
}
.rank-companies-title {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.rank-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.rank-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    font-size: 0.78rem;
    color: var(--gray-800);
    background: var(--gray-100);
    border: 1px solid var(--gray-200);
}
.rank-card.rank-A .rank-chip { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
.rank-card.rank-B .rank-chip { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.rank-card.rank-C .rank-chip { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }
.rank-card.rank-D .rank-chip { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.rank-empty {
    font-size: 0.8125rem;
    color: var(--gray-500);
    font-style: italic;
}
.rank-notes {
    background: #fef9c3;
    border-left: 4px solid #eab308;
    border-radius: 6px;
    padding: 0.6rem 0.9rem;
    font-size: 0.8125rem;
    color: #713f12;
}
.rank-notes b { display: block; margin-bottom: 0.25rem; }

/* 編集UI拡張 */
.rank-card .rank-edit-input {
    border: 1px dashed rgba(255,255,255,0.4);
    background: rgba(255,255,255,0.12);
    color: white;
    font-size: 0.875rem;
    padding: 0.2rem 0.45rem;
    border-radius: 5px;
    outline: none;
    flex: 1;
    min-width: 0;
}
.rank-card .rank-edit-input::placeholder { color: rgba(255,255,255,0.6); }
.rank-card .rank-edit-input:focus { background: rgba(255,255,255,0.22); border-style: solid; }

.rank-edit-condition {
    width: 100%;
    border: 1px solid var(--gray-200);
    background: white;
    padding: 0.5rem 0.7rem;
    font-size: 0.85rem;
    color: var(--gray-900);
    border-radius: 6px;
    outline: none;
    transition: border-color 0.12s;
    box-sizing: border-box;
}
.rank-edit-condition:focus { border-color: var(--primary); }

.rank-chip-editable {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.35rem 0.2rem 0.65rem;
    border-radius: 999px;
    font-size: 0.78rem;
    background: var(--gray-100);
    color: var(--gray-800);
    border: 1px solid var(--gray-200);
}
.rank-card.rank-A .rank-chip-editable { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
.rank-card.rank-B .rank-chip-editable { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.rank-card.rank-C .rank-chip-editable { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }
.rank-card.rank-D .rank-chip-editable { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.rank-chip-del {
    border: none;
    background: rgba(0,0,0,0.06);
    color: inherit;
    width: 18px; height: 18px;
    border-radius: 50%;
    line-height: 1;
    font-size: 0.85rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.rank-chip-del:hover { background: rgba(0,0,0,0.15); }
.rank-chip-add {
    border: 1px dashed var(--gray-400);
    background: white;
    color: var(--gray-600);
    border-radius: 999px;
    padding: 0.18rem 0.6rem;
    font-size: 0.78rem;
    outline: none;
    min-width: 110px;
}
.rank-chip-add:focus { border-color: var(--primary); border-style: solid; color: var(--gray-900); }

.rank-row-actions {
    display: flex; gap: 0.4rem; margin-top: 0.75rem;
    justify-content: flex-end;
}
.rank-icon-btn {
    background: rgba(255,255,255,0.2);
    color: white;
    border: none;
    width: 28px; height: 28px;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.12s;
}
.rank-icon-btn:hover { background: rgba(255,255,255,0.35); }

.rank-add-card {
    background: var(--gray-50);
    border: 2px dashed var(--gray-300);
    border-radius: 12px;
    padding: 2rem 1rem;
    text-align: center;
    color: var(--gray-600);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.15s;
    min-height: 180px;
    justify-content: center;
}
.rank-add-card:hover {
    border-color: var(--primary);
    background: var(--primary-light);
    color: var(--primary-dark);
}

.rank-saved-flash {
    position: fixed;
    bottom: 1.5rem; right: 1.5rem;
    background: var(--success);
    color: white;
    padding: 0.6rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    z-index: 1000;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.2s;
    pointer-events: none;
}
.rank-saved-flash.show { opacity: 1; transform: translateY(0); }

.rank-loading-bar {
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--primary), transparent);
    background-size: 200% 100%;
    animation: rankLoad 1s linear infinite;
}
@keyframes rankLoad {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>

<div class="pv-page">

    <div class="pv-note">
        <strong>プレビューページ</strong> ― 価格表の表示パターン比較用です。気に入った形を採用予定。
    </div>

    <div class="pv-hero">
        <div>
            <h2>価格表 表示パターン プレビュー</h2>
            <p>同じデータを 3 パターンで表示しています</p>
        </div>
        <div class="pv-meta" id="pvMeta">
            <span>読み込み中…</span>
        </div>
    </div>

    <div class="pv-switcher" role="tablist">
        <button type="button" class="active" data-view="v1">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            パターン1: カテゴリカード
        </button>
        <button type="button" data-view="v2">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
            パターン2: カテゴリタブ
        </button>
        <button type="button" data-view="v3">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            パターン3: アコーディオン
        </button>
    </div>

    <div id="pvBoot" class="pv-loading">価格表データを読み込んでいます...</div>

    <!-- パターン1 -->
    <section class="pv-view active" id="view-v1">
        <div class="cv1-grid" id="cv1Grid"></div>
        <div class="cv1-detail" id="cv1Detail">
            <div class="cv1-detail-head">
                <h3 id="cv1Title">—</h3>
                <button type="button" class="qb-action-btn" id="cv1Close" style="padding: 0.3rem 0.7rem; font-size: 0.8rem;">閉じる</button>
            </div>
            <div class="cv1-detail-sub" id="cv1SubTabs"></div>
            <div style="padding: 1rem 1.25rem;" id="cv1Body"></div>
        </div>
    </section>

    <!-- パターン2 -->
    <section class="pv-view" id="view-v2">
        <div class="cv2-tabs" id="cv2Tabs"></div>
        <div class="cv2-content">
            <div class="cv2-list" id="cv2List"></div>
            <div class="cv2-panel">
                <h3 class="cv2-panel-title" id="cv2Title">項目を選択してください</h3>
                <div id="cv2Body"></div>
            </div>
        </div>
    </section>

    <!-- パターン3 -->
    <section class="pv-view" id="view-v3">
        <div id="cv3Sections"></div>
    </section>

</div>

<script<?= nonceAttr() ?>>
(function(){
    var PV_CSRF = <?= json_encode($csrfToken) ?>;
    var PV_CAN_DELETE = <?= $canDeletePage ? 'true' : 'false' ?>;
    var ranksCache = [];

    // ===== カテゴリ定義 =====
    var CATEGORIES = [
        {
            key: 'products', label: '商品価格', color: 'blue',
            desc: '製品別の販売価格（インチ・型番別）',
            iconPath: '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
            match: function(t){ return /モニ|メッシュ|ゲンバルジャー|屋内用液晶|屋外用プロジェクター|新規開拓|miniモニ/.test(t) && !/原価|計算|売価\/|【旧】/.test(t); }
        },
        {
            key: 'shipping', label: '運搬費', color: 'orange',
            desc: '地域別・サイズ別の配送料金',
            iconPath: '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
            match: function(t){ return /運搬/.test(t); }
        },
        {
            key: 'install', label: '設置・調整費', color: 'green',
            desc: '現場設置・調整作業の料金',
            iconPath: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
            match: function(t){ return /設置|調整/.test(t); }
        },
        {
            key: 'customers', label: '顧客ランク定義', color: 'purple',
            desc: 'A/B/C/D 層の判定基準',
            iconPath: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            match: function(t){ return /顧客定義/.test(t); }
        },
        {
            key: 'used', label: '中古・在庫', color: 'gray',
            desc: '中古品・故障品の在庫',
            iconPath: '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
            match: function(t){ return /中古|在庫/.test(t); }
        },
        {
            key: 'internal', label: '原価・計算（内部用）', color: 'red',
            desc: '原価・計算式・内部資料',
            iconPath: '<rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="16" y1="14" x2="16" y2="18"/><line x1="12" y1="14" x2="12" y2="18"/><line x1="8" y1="14" x2="8" y2="18"/><line x1="8" y1="10" x2="16" y2="10"/>',
            match: function(t){ return /原価|計算|売価\/|【旧】/.test(t); }
        },
    ];

    function escapeHtml(s){
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }
    function categorize(items) {
        var groups = {};
        CATEGORIES.forEach(function(c){ groups[c.key] = []; });
        items.forEach(function(item){
            var found = false;
            for (var i = 0; i < CATEGORIES.length; i++) {
                if (CATEGORIES[i].match(item.title)) {
                    groups[CATEGORIES[i].key].push(item);
                    found = true;
                    break;
                }
            }
            if (!found) groups['internal'].push(item);
        });
        return groups;
    }
    function svgIcon(path, size) {
        size = size || 22;
        return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + path + '</svg>';
    }
    function renderTable(values) {
        if (!values || !values.length) return '<div class="pv-empty">データなし</div>';
        var cols = 0; values.forEach(function(r){ if (r && r.length > cols) cols = r.length; });
        var html = '<div class="pv-data-table-wrap"><table class="pv-data-table"><tbody>';
        values.forEach(function(row){
            html += '<tr>';
            for (var c = 0; c < cols; c++) {
                html += '<td>' + escapeHtml(row && row[c] != null ? row[c] : '') + '</td>';
            }
            html += '</tr>';
        });
        return html + '</tbody></table></div>';
    }

    // 顧客ランク定義 = 編集可能カード（API連動）
    function renderCustomerRanks(/* values は使わない: API から取得 */) {
        var holderId = 'crHolder_' + Math.random().toString(36).slice(2, 9);
        setTimeout(function(){ fetchAndRenderRanks(holderId); }, 0);
        return '<div id="' + holderId + '"></div>';
    }

    function fetchAndRenderRanks(holderId) {
        var holder = document.getElementById(holderId);
        if (!holder) return;
        holder.innerHTML = '<div class="rank-loading-bar"></div><div style="text-align:center; padding:1rem; color:var(--gray-500); font-size:0.85rem;">読み込み中…</div>';
        fetch('../api/customer-ranks-api.php?action=list', { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(j){
                if (!j.success) throw new Error(j.error || '取得失敗');
                ranksCache = j.data.ranks || [];
                holder.innerHTML = renderRankGridHtml(ranksCache);
                bindRankEvents(holder, holderId);
            })
            .catch(function(e){
                holder.innerHTML = '<div class="pv-empty">読み込みエラー: ' + escapeHtml(e.message) + '</div>';
            });
    }

    function renderRankGridHtml(ranks) {
        var html = '<div class="rank-grid">';
        ranks.forEach(function(r){ html += renderEditableRankCard(r); });
        html += '<div class="rank-add-card" data-action="add-rank">' +
            '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' +
            '<div style="font-weight:600;">新しいランクを追加</div>' +
            '<div style="font-size:0.75rem;">クリックで作成</div>' +
        '</div>';
        html += '</div>';
        return html;
    }

    function renderEditableRankCard(r) {
        var rank = String(r.rank || 'A').toUpperCase();
        var html = '<div class="rank-card rank-' + escapeHtml(rank) + '" data-id="' + escapeHtml(r.id || '') + '">';
        html += '<div class="rank-card-head">';
        html +=   '<select class="rank-edit-input rank-edit-rank" style="flex:0 0 80px;">';
        ['A','B','C','D'].forEach(function(opt){
            html += '<option value="' + opt + '"' + (opt === rank ? ' selected' : '') + '>' + opt + '層</option>';
        });
        html +=   '</select>';
        html +=   '<input type="text" class="rank-edit-input rank-edit-deal-type" value="' + escapeHtml(r.deal_type || '') + '" placeholder="取引タイプ（例: ディーラー販売）">';
        if (PV_CAN_DELETE) {
            html += '<button type="button" class="rank-icon-btn" data-action="delete-rank" title="このランクを削除">' +
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>' +
                '</button>';
        }
        html += '</div>';
        html += '<div class="rank-card-body">';
        html +=   '<div><div class="rank-companies-title">該当条件</div>';
        html +=   '<textarea class="rank-edit-condition" rows="2" placeholder="例: 売上上位3社ディーラー">' + escapeHtml(r.condition || '') + '</textarea></div>';
        html +=   '<div><div class="rank-companies-title">該当先</div><div class="rank-chips">';
        (r.companies || []).forEach(function(c, i){
            html += '<span class="rank-chip-editable" data-idx="' + i + '">' + escapeHtml(c) +
                '<button type="button" class="rank-chip-del" data-action="del-company" data-idx="' + i + '" aria-label="削除">×</button></span>';
        });
        html +=   '<input type="text" class="rank-chip-add" placeholder="+ 企業を追加">';
        html +=   '</div></div>';
        html += '</div></div>';
        return html;
    }

    function bindRankEvents(holder, holderId) {
        holder.querySelectorAll('.rank-card').forEach(function(card){
            var id = card.dataset.id;
            var rankSelect = card.querySelector('.rank-edit-rank');
            var dealInput  = card.querySelector('.rank-edit-deal-type');
            var condInput  = card.querySelector('.rank-edit-condition');
            var addInput   = card.querySelector('.rank-chip-add');

            if (rankSelect) rankSelect.addEventListener('change', function(){ saveRank(id, holderId); });
            if (dealInput)  dealInput.addEventListener('blur', function(){ saveRank(id, holderId); });
            if (condInput)  condInput.addEventListener('blur', function(){ saveRank(id, holderId); });

            if (addInput) {
                addInput.addEventListener('keydown', function(e){
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        var v = addInput.value.trim();
                        if (v) addCompany(id, v, holderId);
                    }
                });
                addInput.addEventListener('blur', function(){
                    var v = addInput.value.trim();
                    if (v) addCompany(id, v, holderId);
                });
            }

            card.querySelectorAll('[data-action="del-company"]').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var idx = parseInt(btn.dataset.idx, 10);
                    removeCompany(id, idx, holderId);
                });
            });
            card.querySelectorAll('[data-action="delete-rank"]').forEach(function(btn){
                btn.addEventListener('click', function(){
                    if (!confirm('このランクを削除しますか?')) return;
                    deleteRank(id, holderId);
                });
            });
        });
        holder.querySelectorAll('[data-action="add-rank"]').forEach(function(btn){
            btn.addEventListener('click', function(){
                upsertRank({ id: '', rank: 'A', deal_type: '', condition: '', companies: [] }, holderId, true);
            });
        });
    }

    function findRank(id) { return ranksCache.find(function(r){ return r.id === id; }); }

    function saveRank(id, holderId) {
        var card = document.querySelector('[data-id="' + id + '"]');
        if (!card) return;
        var r = findRank(id);
        if (!r) return;
        var payload = {
            id: id,
            rank:      card.querySelector('.rank-edit-rank').value,
            deal_type: card.querySelector('.rank-edit-deal-type').value.trim(),
            condition: card.querySelector('.rank-edit-condition').value.trim(),
            companies: r.companies || [],
            note:      r.note || '',
        };
        upsertRank(payload, holderId, false);
    }

    function upsertRank(payload, holderId, rerender) {
        fetch('../api/customer-ranks-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': PV_CSRF },
            body: JSON.stringify(Object.assign({ csrf_token: PV_CSRF, action: 'upsert' }, payload))
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) throw new Error(j.error || '保存失敗');
            flashSaved('保存しました');
            if (rerender) fetchAndRenderRanks(holderId);
            else {
                // キャッシュだけ更新
                if (j.data && j.data.rank) {
                    var idx = ranksCache.findIndex(function(r){ return r.id === j.data.rank.id; });
                    if (idx >= 0) ranksCache[idx] = j.data.rank;
                }
            }
        })
        .catch(function(e){ alert('保存失敗: ' + e.message); });
    }

    function addCompany(id, value, holderId) {
        var r = findRank(id);
        if (!r) return;
        if (!Array.isArray(r.companies)) r.companies = [];
        r.companies.push(value);
        upsertRank({
            id: id, rank: r.rank, deal_type: r.deal_type,
            condition: r.condition, companies: r.companies, note: r.note || ''
        }, holderId, true);
    }

    function removeCompany(id, idx, holderId) {
        var r = findRank(id);
        if (!r || !Array.isArray(r.companies)) return;
        r.companies.splice(idx, 1);
        upsertRank({
            id: id, rank: r.rank, deal_type: r.deal_type,
            condition: r.condition, companies: r.companies, note: r.note || ''
        }, holderId, true);
    }

    function deleteRank(id, holderId) {
        fetch('../api/customer-ranks-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': PV_CSRF },
            body: JSON.stringify({ csrf_token: PV_CSRF, action: 'delete', id: id })
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) throw new Error(j.error || '削除失敗');
            flashSaved('削除しました');
            fetchAndRenderRanks(holderId);
        })
        .catch(function(e){ alert('削除失敗: ' + e.message); });
    }

    var _rankFlashTimer = null;
    function flashSaved(text) {
        var el = document.getElementById('rankSavedFlash');
        if (!el) {
            el = document.createElement('div');
            el.id = 'rankSavedFlash';
            el.className = 'rank-saved-flash';
            document.body.appendChild(el);
        }
        el.textContent = text || '保存しました';
        el.classList.add('show');
        clearTimeout(_rankFlashTimer);
        _rankFlashTimer = setTimeout(function(){ el.classList.remove('show'); }, 1200);
    }

    // タイトル → 専用レンダラー振り分け
    function renderSheet(title, values) {
        if (title === '顧客定義') return renderCustomerRanks();
        return renderTable(values);
    }

    // ===== データ取得 =====
    var allItems = []; // [{title, values}]
    var groups = {};

    fetch('../api/price-list-get.php', { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(j){
            document.getElementById('pvBoot').style.display = 'none';
            if (!j.success || !j.data.available) {
                document.getElementById('pvBoot').textContent = '価格表が同期されていません。先に sales-tools.php → 価格表タブから同期してください。';
                document.getElementById('pvBoot').style.display = '';
                return;
            }
            allItems = (j.data.sheets || []).map(function(s){
                return { title: s.title, values: s.values || [] };
            });
            groups = categorize(allItems);
            document.getElementById('pvMeta').innerHTML =
                '<span>最終同期 <b>' + escapeHtml(j.data.synced_at || '—') + '</b></span>' +
                '<span>項目数 <b>' + allItems.length + '</b></span>';

            renderV1();
            renderV2();
            renderV3();
        })
        .catch(function(e){
            document.getElementById('pvBoot').textContent = '読み込みエラー: ' + e.message;
        });

    // ===== ビュー切替 =====
    document.querySelectorAll('.pv-switcher button').forEach(function(b){
        b.addEventListener('click', function(){
            document.querySelectorAll('.pv-switcher button').forEach(function(x){ x.classList.remove('active'); });
            b.classList.add('active');
            var v = b.getAttribute('data-view');
            document.querySelectorAll('.pv-view').forEach(function(x){ x.classList.remove('active'); });
            document.getElementById('view-' + v).classList.add('active');
        });
    });

    // ============ パターン1: カテゴリカード ============
    function renderV1() {
        var grid = document.getElementById('cv1Grid');
        grid.innerHTML = CATEGORIES.map(function(c){
            var items = groups[c.key] || [];
            return '<div class="cv1-card" data-cat="' + c.key + '">' +
                '<div class="cv1-icon c-' + c.color + '">' + svgIcon(c.iconPath, 22) + '</div>' +
                '<h3>' + escapeHtml(c.label) + '</h3>' +
                '<p>' + escapeHtml(c.desc) + '</p>' +
                '<div class="cv1-count"><b>' + items.length + '</b> 項目</div>' +
            '</div>';
        }).join('');
        grid.querySelectorAll('.cv1-card').forEach(function(card){
            card.addEventListener('click', function(){
                grid.querySelectorAll('.cv1-card').forEach(function(x){ x.classList.remove('active'); });
                card.classList.add('active');
                cv1Open(card.getAttribute('data-cat'));
            });
        });
        document.getElementById('cv1Close').addEventListener('click', function(){
            document.getElementById('cv1Detail').classList.remove('open');
            grid.querySelectorAll('.cv1-card').forEach(function(x){ x.classList.remove('active'); });
        });
    }
    function cv1Open(catKey) {
        var cat = CATEGORIES.find(function(c){ return c.key === catKey; });
        var items = groups[catKey] || [];
        var detail = document.getElementById('cv1Detail');
        document.getElementById('cv1Title').textContent = cat.label + '（' + items.length + '項目）';
        var subTabsEl = document.getElementById('cv1SubTabs');
        var body = document.getElementById('cv1Body');
        if (items.length === 0) {
            subTabsEl.innerHTML = '';
            body.innerHTML = '<div class="pv-empty">該当する項目はありません</div>';
        } else {
            subTabsEl.innerHTML = items.map(function(it, i){
                return '<button type="button" class="cv1-subtab' + (i === 0 ? ' active' : '') + '" data-idx="' + i + '">' + escapeHtml(it.title) + '</button>';
            }).join('');
            body.innerHTML = renderSheet(items[0].title, items[0].values);
            subTabsEl.querySelectorAll('.cv1-subtab').forEach(function(t){
                t.addEventListener('click', function(){
                    subTabsEl.querySelectorAll('.cv1-subtab').forEach(function(x){ x.classList.remove('active'); });
                    t.classList.add('active');
                    var pickIt = items[parseInt(t.getAttribute('data-idx'),10)];
                    body.innerHTML = renderSheet(pickIt.title, pickIt.values);
                });
            });
        }
        detail.classList.add('open');
        detail.scrollIntoView({behavior:'smooth', block:'start'});
    }

    // ============ パターン2: カテゴリタブ ============
    function renderV2() {
        var tabs = document.getElementById('cv2Tabs');
        var list = document.getElementById('cv2List');
        var titleEl = document.getElementById('cv2Title');
        var bodyEl = document.getElementById('cv2Body');

        tabs.innerHTML = CATEGORIES.map(function(c, i){
            var items = groups[c.key] || [];
            return '<button type="button" class="cv2-tab' + (i === 0 ? ' active' : '') + '" data-cat="' + c.key + '">' +
                svgIcon(c.iconPath, 14) +
                escapeHtml(c.label) +
                '<span class="cv2-count">' + items.length + '</span>' +
            '</button>';
        }).join('');

        function showCategory(catKey) {
            var items = groups[catKey] || [];
            if (items.length === 0) {
                list.innerHTML = '<div style="padding:1rem; color:var(--gray-500); font-size:0.85rem;">該当なし</div>';
                titleEl.textContent = '—';
                bodyEl.innerHTML = '<div class="pv-empty">このカテゴリには項目がありません</div>';
                return;
            }
            list.innerHTML = items.map(function(it, i){
                return '<button type="button" class="cv2-item' + (i === 0 ? ' active' : '') + '" data-idx="' + i + '">' +
                    '<span>' + escapeHtml(it.title) + '</span>' +
                    '<small>' + it.values.length + '行</small>' +
                '</button>';
            }).join('');
            titleEl.textContent = items[0].title;
            bodyEl.innerHTML = renderSheet(items[0].title, items[0].values);
            list.querySelectorAll('.cv2-item').forEach(function(b){
                b.addEventListener('click', function(){
                    list.querySelectorAll('.cv2-item').forEach(function(x){ x.classList.remove('active'); });
                    b.classList.add('active');
                    var it = items[parseInt(b.getAttribute('data-idx'),10)];
                    titleEl.textContent = it.title;
                    bodyEl.innerHTML = renderSheet(it.title, it.values);
                });
            });
        }
        tabs.querySelectorAll('.cv2-tab').forEach(function(t){
            t.addEventListener('click', function(){
                tabs.querySelectorAll('.cv2-tab').forEach(function(x){ x.classList.remove('active'); });
                t.classList.add('active');
                showCategory(t.getAttribute('data-cat'));
            });
        });
        if (CATEGORIES.length > 0) showCategory(CATEGORIES[0].key);
    }

    // ============ パターン3: アコーディオン ============
    function renderV3() {
        var container = document.getElementById('cv3Sections');
        container.innerHTML = CATEGORIES.map(function(c, i){
            var items = groups[c.key] || [];
            return '<div class="cv3-section' + (i === 0 ? ' open' : '') + '" data-cat="' + c.key + '">' +
                '<div class="cv3-head">' +
                    '<h3><span class="cv1-icon c-' + c.color + '" style="width:30px;height:30px;border-radius:7px;">' + svgIcon(c.iconPath, 15) + '</span>' + escapeHtml(c.label) + '</h3>' +
                    '<div style="display:flex;align-items:center;gap:0.75rem;">' +
                        '<span class="cv3-meta">' + items.length + ' 項目</span>' +
                        '<span class="cv3-chevron"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>' +
                    '</div>' +
                '</div>' +
                '<div class="cv3-body">' +
                    (items.length ? '<div class="cv3-subitems">' + items.map(function(it,i){
                        return '<button type="button" class="cv3-subitem' + (i === 0 ? ' active' : '') + '" data-idx="' + i + '">' + escapeHtml(it.title) + '</button>';
                    }).join('') + '</div><div class="cv3-table"></div>' : '<div class="pv-empty">該当なし</div>') +
                '</div>' +
            '</div>';
        }).join('');

        container.querySelectorAll('.cv3-section').forEach(function(section){
            var catKey = section.getAttribute('data-cat');
            var items = groups[catKey] || [];
            var head = section.querySelector('.cv3-head');
            var tableEl = section.querySelector('.cv3-table');
            if (tableEl && items.length) {
                tableEl.innerHTML = renderSheet(items[0].title, items[0].values);
            }
            head.addEventListener('click', function(){ section.classList.toggle('open'); });
            section.querySelectorAll('.cv3-subitem').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.stopPropagation();
                    section.querySelectorAll('.cv3-subitem').forEach(function(x){ x.classList.remove('active'); });
                    btn.classList.add('active');
                    var it = items[parseInt(btn.getAttribute('data-idx'),10)];
                    tableEl.innerHTML = renderSheet(it.title, it.values);
                });
            });
        });
    }
})();
</script>

<?php require_once '../functions/footer.php'; ?>
