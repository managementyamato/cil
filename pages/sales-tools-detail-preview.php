<?php
/**
 * 価格表「詳細ビュー」 デザイン比較プレビュー
 *
 * 同じ商品データ（モニたろう UTM-P4 81"）を 4 パターンで表示。
 * 採用するパターンを決めたら sales-tools.php の詳細ビューを差し替える想定。
 *
 *  A. ウィザード型     - 表を見せず 3問で価格を出す
 *  B. シンプル価格カード - 販売を主役、レンタルは折りたたみ
 *  C. ペルソナ切替    - 新人/営業/管理モードで表示を切替
 *  D. シナリオ型      - 販売/短期レンタル/長期レンタル等のシナリオタブ
 */
require_once '../api/auth.php';
require_once '../functions/header.php';

$canEditPage = canEdit();
$csrfToken   = generateCsrfToken();
?>
<style<?= nonceAttr() ?>>
.dv-page { max-width: 1280px; margin: 0 auto; padding: 0 0 4rem; }
.dv-hero {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 1px solid #f59e0b;
    border-radius: 14px;
    padding: 1.2rem 1.5rem;
    margin-bottom: 1.5rem;
}
.dv-hero h2 { margin: 0 0 0.3rem; font-size: 1.2rem; color: var(--gray-900); }
.dv-hero p  { margin: 0; color: var(--gray-700); font-size: 0.9rem; }
.dv-jump {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}
.dv-jump a {
    padding: 0.45rem 0.9rem;
    background: white;
    border: 1px solid var(--gray-300);
    border-radius: 7px;
    text-decoration: none;
    color: var(--gray-700);
    font-size: 0.85rem;
    transition: all 0.15s;
}
.dv-jump a:hover { border-color: var(--primary); color: var(--primary); }

/* セクション共通 */
.dv-section {
    margin-bottom: 2.5rem;
    padding: 1.5rem;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
}
.dv-section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 0.85rem;
    border-bottom: 1px dashed var(--gray-200);
    flex-wrap: wrap;
}
.dv-section-head h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--gray-900);
}
.dv-section-head .dv-section-sub {
    color: var(--gray-600);
    font-size: 0.85rem;
    margin-top: 0.3rem;
}
.dv-section-tag {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    background: var(--primary-light);
    color: var(--primary-dark);
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.05em;
}

/* 共通: 用語凡例 */
.dv-glossary {
    display: flex;
    gap: 0.85rem;
    flex-wrap: wrap;
    padding: 0.8rem 1rem;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    margin-bottom: 1rem;
    font-size: 0.78rem;
}
.dv-glossary-term {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.dv-glossary-key {
    font-weight: 700;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    background: white;
    border: 1px solid var(--gray-300);
}
.dv-glossary-key.k-S { color: #b45309; background: #fef3c7; border-color: #fde68a; }
.dv-glossary-key.k-A { color: #1e40af; background: #dbeafe; border-color: #bfdbfe; }
.dv-glossary-key.k-B { color: #047857; background: #d1fae5; border-color: #a7f3d0; }
.dv-glossary-meaning { color: var(--gray-700); }

/* ===== Pattern A: ウィザード ===== */
.wiz-frame {
    background: var(--gray-50);
    border-radius: 12px;
    padding: 1.5rem;
}
.wiz-step {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 1.1rem 1.3rem;
    margin-bottom: 0.85rem;
}
.wiz-step.completed { border-color: #10b981; background: #f0fdf4; }
.wiz-step-head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.7rem;
}
.wiz-num {
    width: 28px;
    height: 28px;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
}
.wiz-step.completed .wiz-num { background: #10b981; }
.wiz-step-title {
    font-weight: 700;
    color: var(--gray-900);
    font-size: 0.95rem;
}
.wiz-choices {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.6rem;
}
.wiz-choice {
    padding: 0.85rem 1rem;
    border: 2px solid var(--gray-200);
    border-radius: 10px;
    background: white;
    cursor: pointer;
    text-align: left;
    transition: all 0.15s;
    font-size: 0.88rem;
}
.wiz-choice:hover { border-color: var(--primary); }
.wiz-choice.active { border-color: var(--primary); background: var(--primary-light); }
.wiz-choice-title { font-weight: 700; color: var(--gray-900); }
.wiz-choice-sub { font-size: 0.74rem; color: var(--gray-500); margin-top: 0.2rem; }

.wiz-result {
    background: linear-gradient(135deg, #1e40af 0%, #4338ca 100%);
    color: white;
    border-radius: 12px;
    padding: 1.5rem 1.75rem;
    margin-top: 0.5rem;
}
.wiz-result-label { font-size: 0.78rem; opacity: 0.85; margin-bottom: 0.3rem; }
.wiz-result-price {
    font-size: 2.5rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
}
.wiz-result-explain {
    margin-top: 0.85rem;
    padding-top: 0.85rem;
    border-top: 1px solid rgba(255,255,255,0.2);
    font-size: 0.8rem;
    opacity: 0.92;
    line-height: 1.6;
}

/* ===== Pattern B: シンプル価格カード ===== */
.cardb-list { display: flex; flex-direction: column; gap: 0.85rem; }
.cardb {
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 1.2rem 1.4rem;
    background: white;
    transition: box-shadow 0.15s;
}
.cardb:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.cardb-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 1rem;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
}
.cardb-title { font-size: 1.05rem; font-weight: 700; color: var(--gray-900); margin: 0; }
.cardb-specs {
    font-size: 0.78rem;
    color: var(--gray-500);
    display: flex;
    gap: 0.85rem;
    flex-wrap: wrap;
}
.cardb-specs span::before { content: '・'; margin-right: 0.3rem; color: var(--gray-400); }
.cardb-specs span:first-child::before { content: ''; margin-right: 0; }
.cardb-sale-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.7rem;
    margin-top: 1rem;
}
.cardb-sale-cell {
    padding: 0.85rem 1rem;
    border-radius: 10px;
    background: var(--gray-50);
    text-align: center;
}
.cardb-sale-cell.tier-S { background: linear-gradient(180deg, #fef3c7 0%, #fde68a 100%); }
.cardb-sale-cell.tier-A { background: linear-gradient(180deg, #dbeafe 0%, #bfdbfe 100%); }
.cardb-sale-cell.tier-B { background: linear-gradient(180deg, #d1fae5 0%, #a7f3d0 100%); }
.cardb-sale-label { font-size: 0.75rem; color: var(--gray-600); font-weight: 600; margin-bottom: 0.3rem; }
.cardb-sale-amount {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--gray-900);
    font-variant-numeric: tabular-nums;
}
.cardb-toggle {
    margin-top: 0.85rem;
    background: transparent;
    border: none;
    color: var(--primary);
    cursor: pointer;
    font-size: 0.85rem;
    padding: 0.35rem 0;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.cardb-toggle svg { transition: transform 0.2s; }
.cardb-toggle.open svg { transform: rotate(180deg); }
.cardb-rental {
    display: none;
    margin-top: 0.85rem;
    padding-top: 0.85rem;
    border-top: 1px solid var(--gray-100);
}
.cardb-rental.open { display: block; }
.cardb-rental-grid {
    display: grid;
    grid-template-columns: auto repeat(3, 1fr);
    gap: 0.45rem 0.85rem;
    font-size: 0.8rem;
}
.cardb-rental-head {
    font-size: 0.7rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
}
.cardb-rental-tier {
    font-weight: 700;
    padding: 0.3rem 0.6rem;
    border-radius: 5px;
    text-align: center;
}
.cardb-rental-tier.tier-S { background: #fef3c7; color: #b45309; }
.cardb-rental-tier.tier-A { background: #dbeafe; color: #1e40af; }
.cardb-rental-tier.tier-B { background: #d1fae5; color: #047857; }
.cardb-rental-cell {
    text-align: right;
    font-variant-numeric: tabular-nums;
    color: var(--gray-900);
    font-weight: 600;
}

/* ===== Pattern C: ペルソナ切替 ===== */
.pers-bar {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding: 0.4rem;
    background: var(--gray-100);
    border-radius: 10px;
    width: fit-content;
}
.pers-btn {
    padding: 0.5rem 1.1rem;
    background: transparent;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.85rem;
    color: var(--gray-600);
    font-weight: 600;
    transition: all 0.15s;
}
.pers-btn:hover { color: var(--gray-900); }
.pers-btn.active {
    background: white;
    color: var(--gray-900);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.pers-view { display: none; }
.pers-view.active { display: block; }

/* 新人モード: シンプル */
.pers-newbie {
    padding: 1.5rem;
    background: linear-gradient(180deg, #f0fdf4 0%, white 70%);
    border: 1px solid #a7f3d0;
    border-radius: 12px;
}
.pers-newbie-title { font-size: 1.1rem; font-weight: 700; margin: 0 0 0.5rem; color: var(--gray-900); }
.pers-newbie-sub { color: var(--gray-600); font-size: 0.85rem; margin-bottom: 1rem; }
.pers-newbie-prices {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.85rem;
}
.pers-newbie-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 1rem 1.2rem;
}
.pers-newbie-customer { font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.4rem; }
.pers-newbie-customer .pers-newbie-pill {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    font-weight: 700;
    font-size: 0.7rem;
    margin-right: 0.4rem;
}
.pers-newbie-customer .pill-S { background: #fef3c7; color: #b45309; }
.pers-newbie-customer .pill-A { background: #dbeafe; color: #1e40af; }
.pers-newbie-customer .pill-B { background: #d1fae5; color: #047857; }
.pers-newbie-price {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--gray-900);
    font-variant-numeric: tabular-nums;
    margin: 0.3rem 0;
}
.pers-newbie-note { font-size: 0.75rem; color: var(--gray-500); }
.pers-newbie-help {
    margin-top: 1.25rem;
    padding: 0.85rem 1rem;
    background: white;
    border-left: 3px solid var(--primary);
    border-radius: 0 8px 8px 0;
    font-size: 0.8rem;
    color: var(--gray-700);
    line-height: 1.7;
}

/* 営業モード: 現行に近いフル表示 */
.pers-sales-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.pers-sales-table th, .pers-sales-table td {
    padding: 0.55rem 0.8rem;
    text-align: left;
    border-bottom: 1px solid var(--gray-100);
}
.pers-sales-table th {
    background: var(--gray-50);
    font-size: 0.72rem;
    text-transform: uppercase;
    color: var(--gray-600);
    font-weight: 700;
    letter-spacing: 0.04em;
}
.pers-sales-table td.num {
    text-align: right;
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}

/* 管理モード: 編集可能 */
.pers-admin-row {
    display: grid;
    grid-template-columns: 1fr 100px 100px 100px auto;
    gap: 0.5rem;
    align-items: center;
    padding: 0.55rem 0.7rem;
    border-bottom: 1px solid var(--gray-100);
}
.pers-admin-row:first-child {
    background: var(--gray-50);
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--gray-600);
    text-transform: uppercase;
}
.pers-admin-row input {
    width: 100%;
    padding: 0.35rem 0.5rem;
    border: 1px solid var(--gray-200);
    border-radius: 5px;
    font-size: 0.85rem;
    text-align: right;
    font-variant-numeric: tabular-nums;
}
.pers-admin-row input:focus { border-color: var(--primary); outline: none; }
.pers-admin-save {
    padding: 0.35rem 0.7rem;
    border: none;
    background: var(--primary);
    color: white;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.75rem;
    opacity: 0.5;
}
.pers-admin-save.dirty { opacity: 1; }

/* ===== Pattern D: シナリオ型 ===== */
.scn-tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}
.scn-tab {
    padding: 0.6rem 1.1rem;
    background: white;
    border: 2px solid var(--gray-200);
    border-radius: 10px;
    cursor: pointer;
    font-size: 0.88rem;
    color: var(--gray-700);
    font-weight: 600;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.scn-tab:hover { border-color: var(--gray-300); }
.scn-tab.active {
    border-color: var(--primary);
    background: var(--primary-light);
    color: var(--primary-dark);
}
.scn-tab-icon { font-size: 1.1rem; }
.scn-view { display: none; }
.scn-view.active { display: block; }

.scn-card {
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 1.1rem 1.3rem;
    margin-bottom: 0.7rem;
    background: white;
}
.scn-card-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 0.6rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.scn-card-title { font-size: 0.95rem; font-weight: 700; color: var(--gray-900); margin: 0; }
.scn-card-tier-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.55rem;
}
.scn-card-tier {
    padding: 0.7rem 0.85rem;
    border-radius: 8px;
    background: var(--gray-50);
}
.scn-card-tier.tier-S { background: #fef3c7; }
.scn-card-tier.tier-A { background: #dbeafe; }
.scn-card-tier.tier-B { background: #d1fae5; }
.scn-card-tier-label { font-size: 0.7rem; color: var(--gray-600); font-weight: 700; margin-bottom: 0.2rem; }
.scn-card-tier-amount {
    font-size: 1.1rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: var(--gray-900);
}
.scn-card-empty {
    padding: 1rem;
    color: var(--gray-500);
    text-align: center;
    font-size: 0.85rem;
}

/* ===== Pattern F: クイック回答カード ===== */
.faq-card {
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    background: white;
    margin-bottom: 1rem;
    overflow: hidden;
}
.faq-card-head {
    padding: 1rem 1.3rem;
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.faq-card-title { font-size: 1rem; font-weight: 700; color: var(--gray-900); margin: 0; }
.faq-card-specs { font-size: 0.78rem; color: var(--gray-500); }
.faq-row {
    padding: 1rem 1.3rem;
    border-bottom: 1px solid var(--gray-100);
}
.faq-row:last-child { border-bottom: none; }
.faq-q {
    font-size: 0.82rem;
    color: var(--gray-700);
    font-weight: 700;
    margin-bottom: 0.6rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.faq-q-mark {
    display: inline-flex;
    width: 22px; height: 22px;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 800;
}
.faq-a {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    padding-left: 1.8rem;
}
.faq-a-tier {
    padding: 0.5rem 0.7rem;
    border-radius: 7px;
    background: var(--gray-50);
}
.faq-a-tier.tier-S { background: #fef3c7; }
.faq-a-tier.tier-A { background: #dbeafe; }
.faq-a-tier.tier-B { background: #d1fae5; }
.faq-a-tier-label { font-size: 0.7rem; color: var(--gray-600); font-weight: 600; margin-bottom: 0.15rem; }
.faq-a-tier-amount {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--gray-900);
    font-variant-numeric: tabular-nums;
}

/* ===== Pattern G: ヒートマップ ===== */
.heat-wrap { overflow: auto; }
.heat-table {
    border-collapse: separate;
    border-spacing: 4px;
    font-size: 0.85rem;
}
.heat-table th, .heat-table td {
    padding: 0.5rem 0.8rem;
    border-radius: 6px;
    text-align: center;
}
.heat-table th {
    background: var(--gray-100);
    color: var(--gray-700);
    font-weight: 700;
    font-size: 0.78rem;
    white-space: nowrap;
}
.heat-table td.heat-row-label {
    background: var(--gray-50);
    color: var(--gray-700);
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}
.heat-cell {
    cursor: default;
    transition: transform 0.1s;
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    color: var(--gray-900);
    min-width: 90px;
}
.heat-cell:hover { transform: scale(1.08); z-index: 1; position: relative; }
.heat-cell.empty { background: white; color: var(--gray-300); }
.heat-legend {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.85rem;
    font-size: 0.78rem;
    color: var(--gray-600);
}
.heat-legend-bar {
    flex: 1;
    max-width: 240px;
    height: 14px;
    border-radius: 7px;
    background: linear-gradient(90deg, #ecfdf5 0%, #6ee7b7 35%, #f59e0b 70%, #b91c1c 100%);
}

/* ===== Pattern H: 電卓型 ===== */
.calc-wrap {
    background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
    border-radius: 14px;
    padding: 1.75rem;
}
.calc-inputs {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.calc-field {
    background: white;
    padding: 0.85rem 1rem;
    border-radius: 10px;
    border: 1px solid var(--gray-200);
}
.calc-field-label {
    font-size: 0.72rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 700;
    margin-bottom: 0.4rem;
}
.calc-field select, .calc-field input[type="number"] {
    width: 100%;
    border: 1px solid var(--gray-300);
    border-radius: 6px;
    padding: 0.45rem 0.6rem;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--gray-900);
    background: white;
}
.calc-period-slider {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-top: 0.3rem;
}
.calc-period-slider input[type="range"] { flex: 1; }
.calc-period-out {
    min-width: 70px;
    text-align: right;
    font-weight: 700;
    color: var(--primary);
    font-variant-numeric: tabular-nums;
}
.calc-result {
    background: white;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.calc-result-label { font-size: 0.78rem; color: var(--gray-500); margin-bottom: 0.3rem; }
.calc-result-amount {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--gray-900);
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
}
.calc-result-sub {
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: var(--gray-600);
}
.calc-result-total {
    margin-top: 0.85rem;
    padding-top: 0.85rem;
    border-top: 1px dashed var(--gray-200);
    color: var(--primary);
    font-weight: 700;
    font-size: 0.95rem;
}

/* ===== Pattern I: 顧客視点型 ===== */
.cust-section { margin-bottom: 1.5rem; }
.cust-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.65rem 1rem;
    border-radius: 10px 10px 0 0;
    color: white;
    font-weight: 700;
}
.cust-header.tier-S { background: linear-gradient(90deg, #b45309 0%, #d97706 100%); }
.cust-header.tier-A { background: linear-gradient(90deg, #1e40af 0%, #3b82f6 100%); }
.cust-header.tier-B { background: linear-gradient(90deg, #047857 0%, #10b981 100%); }
.cust-header-mark {
    display: inline-flex;
    width: 28px; height: 28px;
    background: rgba(255,255,255,0.3);
    border-radius: 50%;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}
.cust-body {
    border: 1px solid var(--gray-200);
    border-top: none;
    border-radius: 0 0 10px 10px;
    background: white;
}
.cust-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 0.5rem;
    padding: 0.7rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    align-items: center;
}
.cust-row:last-child { border-bottom: none; }
.cust-row-product {
    font-weight: 600;
    color: var(--gray-900);
    font-size: 0.88rem;
}
.cust-row-meta {
    font-size: 0.72rem;
    color: var(--gray-500);
    margin-top: 0.15rem;
}
.cust-row-price {
    text-align: right;
    font-variant-numeric: tabular-nums;
    font-weight: 700;
    color: var(--gray-900);
}
.cust-row-rental {
    text-align: right;
    font-variant-numeric: tabular-nums;
    color: var(--gray-700);
    font-size: 0.85rem;
}
.cust-row-empty { color: var(--gray-300); }

/* ===== Pattern J: タイムライン型 ===== */
.tl-wrap {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--gray-200);
}
.tl-product-select {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1.25rem;
}
.tl-product-select select {
    border: 1px solid var(--gray-300);
    border-radius: 6px;
    padding: 0.4rem 0.7rem;
    font-size: 0.9rem;
    font-weight: 600;
}
.tl-tier-tabs {
    display: flex;
    gap: 0.4rem;
    margin-bottom: 1rem;
}
.tl-tier-tab {
    padding: 0.4rem 0.9rem;
    border: 1px solid var(--gray-300);
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 700;
    font-size: 0.82rem;
    color: var(--gray-600);
}
.tl-tier-tab.active.tier-S { background: #fef3c7; border-color: #f59e0b; color: #b45309; }
.tl-tier-tab.active.tier-A { background: #dbeafe; border-color: #3b82f6; color: #1e40af; }
.tl-tier-tab.active.tier-B { background: #d1fae5; border-color: #10b981; color: #047857; }
.tl-bar {
    position: relative;
    height: 80px;
    display: flex;
    border-radius: 10px;
    overflow: hidden;
    margin: 1.5rem 0 1rem;
    border: 1px solid var(--gray-200);
}
.tl-seg {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    text-align: center;
    padding: 0.5rem;
    position: relative;
}
.tl-seg.seg-1 { background: linear-gradient(180deg, #ef4444 0%, #dc2626 100%); }
.tl-seg.seg-2 { background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%); }
.tl-seg.seg-3 { background: linear-gradient(180deg, #10b981 0%, #059669 100%); }
.tl-seg-period { font-size: 0.74rem; opacity: 0.92; }
.tl-seg-amount { font-size: 1.1rem; margin-top: 0.15rem; font-variant-numeric: tabular-nums; }
.tl-axis {
    display: flex;
    margin-top: -0.5rem;
    padding: 0 0.3rem;
}
.tl-axis-tick {
    flex: 1;
    text-align: center;
    font-size: 0.72rem;
    color: var(--gray-500);
}
.tl-axis-tick:first-child { text-align: left; }
.tl-axis-tick:last-child  { text-align: right; }
.tl-note {
    padding: 0.8rem 1rem;
    background: #f0fdf4;
    border-left: 3px solid #10b981;
    border-radius: 0 8px 8px 0;
    margin-top: 1rem;
    font-size: 0.82rem;
    color: var(--gray-700);
    line-height: 1.6;
}
.tl-note strong { color: #047857; }
</style>

<div class="dv-page">
    <div class="dv-hero">
        <h2>詳細ビュー デザイン比較プレビュー</h2>
        <p>同じ商品データ（モニたろう UTM-P4 81インチ）を 4 通りの見せ方で並べています。新入社員でも理解できるのはどれか比較してください。</p>
    </div>

    <nav class="dv-jump">
        <a href="#sec-a">A. ウィザード型</a>
        <a href="#sec-b">B. シンプル価格カード</a>
        <a href="#sec-c">C. ペルソナ切替</a>
        <a href="#sec-d">D. シナリオ型</a>
        <a href="#sec-f">F. クイック回答カード</a>
        <a href="#sec-g">G. 価格ヒートマップ</a>
        <a href="#sec-h">H. 電卓型</a>
        <a href="#sec-i">I. 顧客視点型</a>
        <a href="#sec-j">J. レンタルタイムライン</a>
    </nav>

    <!-- ========== A. ウィザード ========== -->
    <section class="dv-section" id="sec-a">
        <div class="dv-section-head">
            <div>
                <h3>A. 見積もりウィザード</h3>
                <div class="dv-section-sub">表を見せず、3問に答えるだけで価格を提示。新人にやさしいが、ベテランには遠回り。</div>
            </div>
            <span class="dv-section-tag">最も新人向け</span>
        </div>
        <div class="wiz-frame">
            <div class="wiz-step" id="wizStep1">
                <div class="wiz-step-head">
                    <div class="wiz-num">1</div>
                    <div class="wiz-step-title">お客様タイプを選んでください</div>
                </div>
                <div class="wiz-choices" id="wizCustomers">
                    <button class="wiz-choice" data-tier="S">
                        <div class="wiz-choice-title">上位ディーラー</div>
                        <div class="wiz-choice-sub">大興物産・レンタルニッケン等</div>
                    </button>
                    <button class="wiz-choice" data-tier="A">
                        <div class="wiz-choice-title">標準ディーラー</div>
                        <div class="wiz-choice-sub">それ以外のディーラー様</div>
                    </button>
                    <button class="wiz-choice" data-tier="B">
                        <div class="wiz-choice-title">新規開拓・直販</div>
                        <div class="wiz-choice-sub">エンドユーザー直接</div>
                    </button>
                </div>
            </div>

            <div class="wiz-step" id="wizStep2">
                <div class="wiz-step-head">
                    <div class="wiz-num">2</div>
                    <div class="wiz-step-title">取引形態を選んでください</div>
                </div>
                <div class="wiz-choices">
                    <button class="wiz-choice" data-mode="sale">
                        <div class="wiz-choice-title">販売</div>
                        <div class="wiz-choice-sub">買い切り</div>
                    </button>
                    <button class="wiz-choice" data-mode="rent-1">
                        <div class="wiz-choice-title">短期レンタル</div>
                        <div class="wiz-choice-sub">1〜3ヶ月（①月額）</div>
                    </button>
                    <button class="wiz-choice" data-mode="rent-2">
                        <div class="wiz-choice-title">中期レンタル</div>
                        <div class="wiz-choice-sub">3〜6ヶ月（②月額）</div>
                    </button>
                    <button class="wiz-choice" data-mode="rent-3">
                        <div class="wiz-choice-title">長期レンタル</div>
                        <div class="wiz-choice-sub">6ヶ月〜（③月額）</div>
                    </button>
                </div>
            </div>

            <div class="wiz-step" id="wizStep3">
                <div class="wiz-step-head">
                    <div class="wiz-num">3</div>
                    <div class="wiz-step-title">サイズ・型番を選んでください</div>
                </div>
                <select id="wizSize" class="form-input" style="max-width:280px;">
                    <option value="">読み込み中…</option>
                </select>
            </div>

            <div class="wiz-result" id="wizResult" style="display:none;">
                <div class="wiz-result-label">提示価格</div>
                <div class="wiz-result-price" id="wizPrice">¥-</div>
                <div class="wiz-result-explain" id="wizExplain"></div>
            </div>
        </div>
    </section>

    <!-- ========== B. シンプル価格カード ========== -->
    <section class="dv-section" id="sec-b">
        <div class="dv-section-head">
            <div>
                <h3>B. シンプル価格カード</h3>
                <div class="dv-section-sub">販売価格を主役に。レンタルは折りたたみ。「とりあえず販売価格だけ知りたい」シーンに最強。</div>
            </div>
            <span class="dv-section-tag">即視認性◎</span>
        </div>
        <div class="dv-glossary">
            <span class="dv-glossary-term"><span class="dv-glossary-key k-S">S</span><span class="dv-glossary-meaning">上位ディーラー</span></span>
            <span class="dv-glossary-term"><span class="dv-glossary-key k-A">A</span><span class="dv-glossary-meaning">標準ディーラー</span></span>
            <span class="dv-glossary-term"><span class="dv-glossary-key k-B">B</span><span class="dv-glossary-meaning">新規開拓</span></span>
        </div>
        <div class="cardb-list" id="cardbList"></div>
    </section>

    <!-- ========== C. ペルソナ切替 ========== -->
    <section class="dv-section" id="sec-c">
        <div class="dv-section-head">
            <div>
                <h3>C. ペルソナ切替</h3>
                <div class="dv-section-sub">新人/営業/管理者でビューを切替。1つの画面で複数の人物像に対応。</div>
            </div>
            <span class="dv-section-tag">柔軟性◎</span>
        </div>
        <div class="pers-bar">
            <button class="pers-btn active" data-pers="newbie">新人モード</button>
            <button class="pers-btn" data-pers="sales">営業モード</button>
            <button class="pers-btn" data-pers="admin">管理モード</button>
        </div>
        <div class="pers-view active" data-pers-view="newbie">
            <div class="pers-newbie">
                <h4 class="pers-newbie-title" id="persNewbieTitle">モニたろう UTM-P4 81インチ</h4>
                <div class="pers-newbie-sub" id="persNewbieSub">LEDビジョン / 画面 1600x1280 / 2.05㎡</div>
                <div class="pers-newbie-prices" id="persNewbiePrices"></div>
                <div class="pers-newbie-help">
                    <strong>はじめての方へ:</strong> 価格はお客様の取引区分で 3 段階あります。
                    <strong>上位</strong>=大手取引先（割引大）／<strong>標準</strong>=既存取引先／<strong>新規</strong>=エンドユーザー直接です。
                    分からなければ「標準」で見積もりを出しましょう。
                </div>
            </div>
        </div>
        <div class="pers-view" data-pers-view="sales">
            <table class="pers-sales-table" id="persSalesTable"><thead><tr></tr></thead><tbody></tbody></table>
        </div>
        <div class="pers-view" data-pers-view="admin">
            <div id="persAdminGrid"></div>
            <div style="margin-top:0.5rem; font-size:0.75rem; color:var(--gray-500);">
                <em>※ プレビュー: 編集ボタンは現状ダミーです（保存は未実装）</em>
            </div>
        </div>
    </section>

    <!-- ========== D. シナリオ型 ========== -->
    <section class="dv-section" id="sec-d">
        <div class="dv-section-head">
            <div>
                <h3>D. シナリオ型</h3>
                <div class="dv-section-sub">「何のための価格?」のタブから入り、関係ないカラムを隠す。情報過多にならない。</div>
            </div>
            <span class="dv-section-tag">情報絞り込み◎</span>
        </div>
        <div class="scn-tabs">
            <button class="scn-tab active" data-scn="sale">販売したい</button>
            <button class="scn-tab" data-scn="rent-short">短期レンタル（1-3ヶ月）</button>
            <button class="scn-tab" data-scn="rent-mid">中期レンタル（3-6ヶ月）</button>
            <button class="scn-tab" data-scn="rent-long">長期レンタル（6ヶ月〜）</button>
        </div>
        <div class="scn-view active" data-scn-view="sale" id="scnSale"></div>
        <div class="scn-view" data-scn-view="rent-short" id="scnShort"></div>
        <div class="scn-view" data-scn-view="rent-mid" id="scnMid"></div>
        <div class="scn-view" data-scn-view="rent-long" id="scnLong"></div>
    </section>

    <!-- ========== F. クイック回答カード ========== -->
    <section class="dv-section" id="sec-f">
        <div class="dv-section-head">
            <div>
                <h3>F. クイック回答カード</h3>
                <div class="dv-section-sub">「販売したい / 短期 / 長期」の 3 つの定番質問の答えを常に最初に出しておく。FAQ感覚で読める。</div>
            </div>
            <span class="dv-section-tag">スキャンしやすい</span>
        </div>
        <div id="faqList"></div>
    </section>

    <!-- ========== G. 価格ヒートマップ ========== -->
    <section class="dv-section" id="sec-g">
        <div class="dv-section-head">
            <div>
                <h3>G. 価格ヒートマップ</h3>
                <div class="dv-section-sub">色の濃さで価格の高低を視覚化。サイズ × 層 のマトリックスで「どこが高いか」が一目で分かる。</div>
            </div>
            <span class="dv-section-tag">価格カーブ可視化</span>
        </div>
        <div class="heat-wrap" id="heatWrap"></div>
        <div class="heat-legend">
            <span>安い</span>
            <div class="heat-legend-bar"></div>
            <span>高い</span>
        </div>
    </section>

    <!-- ========== H. 電卓型 ========== -->
    <section class="dv-section" id="sec-h">
        <div class="dv-section-head">
            <div>
                <h3>H. 電卓型</h3>
                <div class="dv-section-sub">サイズ・台数・期間・お客様を選ぶと、リアルタイムで価格が計算される。新人が「いじって覚える」設計。</div>
            </div>
            <span class="dv-section-tag">体験で覚える</span>
        </div>
        <div class="calc-wrap">
            <div class="calc-inputs">
                <div class="calc-field">
                    <div class="calc-field-label">サイズ・型番</div>
                    <select id="calcSize"></select>
                </div>
                <div class="calc-field">
                    <div class="calc-field-label">お客様タイプ</div>
                    <select id="calcTier">
                        <option value="S">上位ディーラー (S)</option>
                        <option value="A" selected>標準ディーラー (A)</option>
                        <option value="B">新規開拓 (B)</option>
                    </select>
                </div>
                <div class="calc-field">
                    <div class="calc-field-label">取引形態</div>
                    <select id="calcMode">
                        <option value="sale">販売（買い切り）</option>
                        <option value="rent-1" selected>短期レンタル(1〜3ヶ月)</option>
                        <option value="rent-2">中期レンタル(3〜6ヶ月)</option>
                        <option value="rent-3">長期レンタル(6ヶ月〜)</option>
                    </select>
                </div>
                <div class="calc-field">
                    <div class="calc-field-label">台数 / レンタル月数</div>
                    <div class="calc-period-slider">
                        <input type="number" id="calcQty" value="1" min="1" max="100" style="width:60px;"> 台
                        <span style="color:var(--gray-400);">×</span>
                        <input type="range" id="calcMonths" min="1" max="24" value="3">
                        <span class="calc-period-out"><span id="calcMonthsOut">3</span> ヶ月</span>
                    </div>
                </div>
            </div>
            <div class="calc-result">
                <div class="calc-result-label">単価</div>
                <div class="calc-result-amount" id="calcUnit">¥0</div>
                <div class="calc-result-sub" id="calcSub">—</div>
                <div class="calc-result-total" id="calcTotal">合計: ¥0</div>
            </div>
        </div>
    </section>

    <!-- ========== I. 顧客視点型 ========== -->
    <section class="dv-section" id="sec-i">
        <div class="dv-section-head">
            <div>
                <h3>I. 顧客視点型</h3>
                <div class="dv-section-sub">「製品ごと」ではなく「お客様タイプごと」に並べる。「このお客様にはこれくらいで提案できる」を一覧化。</div>
            </div>
            <span class="dv-section-tag">顧客マスタ連携向き</span>
        </div>
        <div id="custView"></div>
    </section>

    <!-- ========== J. レンタルタイムライン型 ========== -->
    <section class="dv-section" id="sec-j">
        <div class="dv-section-head">
            <div>
                <h3>J. レンタルタイムライン</h3>
                <div class="dv-section-sub">契約期間と月額の関係を時間軸で可視化。「長く借りるほど安くなる」をビジュアルで説明できる。</div>
            </div>
            <span class="dv-section-tag">顧客説明用</span>
        </div>
        <div class="tl-wrap">
            <div class="tl-product-select">
                <label style="font-weight:600; color:var(--gray-700);">対象製品:</label>
                <select id="tlProduct"></select>
            </div>
            <div class="tl-tier-tabs" id="tlTiers"></div>
            <div class="tl-bar" id="tlBar"></div>
            <div class="tl-axis">
                <div class="tl-axis-tick">契約日</div>
                <div class="tl-axis-tick">3ヶ月</div>
                <div class="tl-axis-tick">6ヶ月</div>
                <div class="tl-axis-tick">12ヶ月+</div>
            </div>
            <div class="tl-note">
                <strong>営業のポイント:</strong> 同じ製品でも、契約期間が長いほど月額は下がります。
                顧客に「6ヶ月以上なら ③月額(長期料金) が適用されます」と説明すると、長期契約への動機付けがしやすくなります。
            </div>
        </div>
    </section>

</div>

<script<?= nonceAttr() ?>>
(function(){
    'use strict';
    var SHEET_TITLE = 'モニたろうUTM・FA・RCM';

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function yen(n) { return '¥' + (n || 0).toLocaleString('ja-JP'); }

    // ----- price-list-get からデータ取得 -----
    var rows = [];
    fetch('../api/price-list-get.php?title=' + encodeURIComponent(SHEET_TITLE), { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success || !j.data || !j.data.sheet) throw new Error('データなし');
            var sheet = j.data.sheet;
            if (!sheet.normalized || !sheet.normalized.rows) throw new Error('正規化データなし');
            rows = sheet.normalized.rows;
            renderAll();
        })
        .catch(function(e){
            document.querySelectorAll('.dv-section').forEach(function(s){
                s.innerHTML += '<div style="padding:1rem; color:#b91c1c;">読み込みエラー: ' + escapeHtml(e.message) + '</div>';
            });
        });

    function renderAll() {
        renderWizard();
        renderCardB();
        renderPersona();
        renderScenario();
        renderFAQ();
        renderHeatmap();
        renderCalculator();
        renderCustomer();
        renderTimeline();
    }

    function priceOf(row, group, label) {
        var p = (row.prices || []).find(function(p){ return p.group === group && p.label === label; });
        return p ? p.amount : null;
    }
    function attrOf(row, label) {
        var a = (row.attributes || []).find(function(a){ return a.label === label; });
        return a ? a.value : '';
    }

    // ===== A. ウィザード =====
    var wizState = { tier: null, mode: null, rowIdx: null };
    function renderWizard() {
        // サイズプルダウン
        var sel = document.getElementById('wizSize');
        sel.innerHTML = '<option value="">サイズを選択…</option>' + rows.map(function(r, i){
            return '<option value="' + i + '">' + escapeHtml(r.display_name) + '</option>';
        }).join('');
        sel.addEventListener('change', function(){
            wizState.rowIdx = this.value === '' ? null : parseInt(this.value, 10);
            updateWizResult();
        });
        document.querySelectorAll('#wizCustomers .wiz-choice').forEach(function(b){
            b.addEventListener('click', function(){
                document.querySelectorAll('#wizCustomers .wiz-choice').forEach(function(x){ x.classList.remove('active'); });
                this.classList.add('active');
                wizState.tier = this.getAttribute('data-tier');
                document.getElementById('wizStep1').classList.add('completed');
                updateWizResult();
            });
        });
        document.querySelectorAll('#wizStep2 .wiz-choice').forEach(function(b){
            b.addEventListener('click', function(){
                document.querySelectorAll('#wizStep2 .wiz-choice').forEach(function(x){ x.classList.remove('active'); });
                this.classList.add('active');
                wizState.mode = this.getAttribute('data-mode');
                document.getElementById('wizStep2').classList.add('completed');
                updateWizResult();
            });
        });
    }
    function updateWizResult() {
        var resultEl = document.getElementById('wizResult');
        if (wizState.tier === null || wizState.mode === null || wizState.rowIdx === null) {
            resultEl.style.display = 'none';
            return;
        }
        var row = rows[wizState.rowIdx];
        var label = ({ 'sale': '販売価格', 'rent-1': '①月額', 'rent-2': '②月額', 'rent-3': '③月額' })[wizState.mode];
        var amount = priceOf(row, wizState.tier, label);
        document.getElementById('wizStep3').classList.add('completed');
        resultEl.style.display = '';
        if (amount === null) {
            document.getElementById('wizPrice').textContent = '価格なし';
            document.getElementById('wizExplain').textContent = '該当する組合せの価格は登録されていません。';
            return;
        }
        var tierLabel = { 'S': '上位ディーラー', 'A': '標準ディーラー', 'B': '新規開拓' }[wizState.tier];
        var modeLabel = { 'sale': '販売', 'rent-1': '短期レンタル(月額)', 'rent-2': '中期レンタル(月額)', 'rent-3': '長期レンタル(月額)' }[wizState.mode];
        document.getElementById('wizPrice').textContent = yen(amount) + (wizState.mode === 'sale' ? '' : ' / 月');
        document.getElementById('wizExplain').innerHTML =
            '対象: <strong>' + escapeHtml(row.display_name) + '</strong> / お客様: <strong>' + tierLabel + ' (' + wizState.tier + '層)</strong> / 取引: <strong>' + modeLabel + '</strong>';
    }

    // ===== B. シンプル価格カード =====
    function renderCardB() {
        var listEl = document.getElementById('cardbList');
        // 同じシリーズの中で最初の数件のみ表示
        var shown = rows.slice(0, 4);
        listEl.innerHTML = shown.map(function(row){
            var sale = { S: priceOf(row,'S','販売価格'), A: priceOf(row,'A','販売価格'), B: priceOf(row,'B','販売価格') };
            var specs = (row.attributes || [])
                .filter(function(a){ return a.label !== '製品シリーズ' && a.value; })
                .slice(0, 4)
                .map(function(a){ return '<span>' + escapeHtml(a.label) + ': ' + escapeHtml(a.value) + '</span>'; }).join('');
            var rentalRows = '';
            ['S','A','B'].forEach(function(g){
                rentalRows +=
                    '<div class="cardb-rental-tier tier-' + g + '">' + g + '層</div>' +
                    '<div class="cardb-rental-cell">' + (priceOf(row,g,'①月額') !== null ? yen(priceOf(row,g,'①月額')) : '—') + '</div>' +
                    '<div class="cardb-rental-cell">' + (priceOf(row,g,'②月額') !== null ? yen(priceOf(row,g,'②月額')) : '—') + '</div>' +
                    '<div class="cardb-rental-cell">' + (priceOf(row,g,'③月額') !== null ? yen(priceOf(row,g,'③月額')) : '—') + '</div>';
            });
            return '<div class="cardb">' +
                '<div class="cardb-head">' +
                    '<h4 class="cardb-title">' + escapeHtml(row.display_name) + '</h4>' +
                    '<div class="cardb-specs">' + specs + '</div>' +
                '</div>' +
                '<div class="cardb-sale-row">' +
                    '<div class="cardb-sale-cell tier-S"><div class="cardb-sale-label">上位ディーラー (S)</div><div class="cardb-sale-amount">' + (sale.S !== null ? yen(sale.S) : '—') + '</div></div>' +
                    '<div class="cardb-sale-cell tier-A"><div class="cardb-sale-label">標準ディーラー (A)</div><div class="cardb-sale-amount">' + (sale.A !== null ? yen(sale.A) : '—') + '</div></div>' +
                    '<div class="cardb-sale-cell tier-B"><div class="cardb-sale-label">新規開拓 (B)</div><div class="cardb-sale-amount">' + (sale.B !== null ? yen(sale.B) : '—') + '</div></div>' +
                '</div>' +
                '<button type="button" class="cardb-toggle">' +
                    'レンタル料金を見る' +
                    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>' +
                '</button>' +
                '<div class="cardb-rental">' +
                    '<div class="cardb-rental-grid">' +
                        '<div></div>' +
                        '<div class="cardb-rental-head">①月額 (1-3M)</div>' +
                        '<div class="cardb-rental-head">②月額 (3-6M)</div>' +
                        '<div class="cardb-rental-head">③月額 (6M+)</div>' +
                        rentalRows +
                    '</div>' +
                '</div>' +
            '</div>';
        }).join('');
        listEl.querySelectorAll('.cardb-toggle').forEach(function(btn){
            btn.addEventListener('click', function(){
                btn.classList.toggle('open');
                btn.nextElementSibling.classList.toggle('open');
            });
        });
    }

    // ===== C. ペルソナ切替 =====
    function renderPersona() {
        var row = rows[0]; // モニたろう UTM-P4 81"
        // 新人モード
        var newbieEl = document.getElementById('persNewbiePrices');
        var tiers = [
            { g: 'S', label: '上位ディーラーへの販売', sub: '大興物産・レンタルニッケン等' },
            { g: 'A', label: '標準ディーラーへの販売', sub: 'その他取引先' },
            { g: 'B', label: '新規開拓・直販', sub: 'エンドユーザー直接' },
        ];
        newbieEl.innerHTML = tiers.map(function(t){
            var p = priceOf(row, t.g, '販売価格');
            return '<div class="pers-newbie-card">' +
                '<div class="pers-newbie-customer"><span class="pers-newbie-pill pill-' + t.g + '">' + t.g + '層</span>' + escapeHtml(t.label) + '</div>' +
                '<div class="pers-newbie-price">' + (p !== null ? yen(p) : '—') + '</div>' +
                '<div class="pers-newbie-note">' + escapeHtml(t.sub) + '</div>' +
            '</div>';
        }).join('');

        // 営業モード: フルテーブル
        var saleTable = document.getElementById('persSalesTable');
        var headers = ['製品', 'インチ', 'サイズ', 'S層 ①月額', 'S層 ②月額', 'S層 ③月額', 'S層 販売', 'A層 ①月額', 'A層 ②月額', 'A層 ③月額', 'A層 販売', 'B層 ①月額', 'B層 ②月額', 'B層 ③月額', 'B層 販売'];
        saleTable.querySelector('thead tr').innerHTML = headers.map(function(h){ return '<th>' + h + '</th>'; }).join('');
        saleTable.querySelector('tbody').innerHTML = rows.slice(0, 6).map(function(r){
            var series = attrOf(r, '製品シリーズ');
            var inch = attrOf(r, 'インチ数');
            var size = attrOf(r, '画面サイズ');
            var cells = [series, inch, size];
            ['S','A','B'].forEach(function(g){
                ['①月額','②月額','③月額','販売価格'].forEach(function(l){
                    var p = priceOf(r, g, l);
                    cells.push(p !== null ? yen(p) : '—');
                });
            });
            return '<tr>' + cells.map(function(c, i){
                return i < 3 ? '<td>' + escapeHtml(c) + '</td>' : '<td class="num">' + escapeHtml(c) + '</td>';
            }).join('') + '</tr>';
        }).join('');

        // 管理モード: 編集UI
        var adminEl = document.getElementById('persAdminGrid');
        var adminHeader = '<div class="pers-admin-row"><div>製品</div><div>S層 販売</div><div>A層 販売</div><div>B層 販売</div><div></div></div>';
        var adminRows = rows.slice(0, 5).map(function(r, ri){
            return '<div class="pers-admin-row">' +
                '<div>' + escapeHtml(r.display_name) + '</div>' +
                ['S','A','B'].map(function(g){
                    var p = priceOf(r, g, '販売価格');
                    return '<div><input type="text" value="' + (p !== null ? p.toLocaleString('ja-JP') : '') + '" data-row="' + ri + '" data-tier="' + g + '"></div>';
                }).join('') +
                '<div><button type="button" class="pers-admin-save">保存</button></div>' +
            '</div>';
        }).join('');
        adminEl.innerHTML = adminHeader + adminRows;
        adminEl.querySelectorAll('input').forEach(function(inp){
            inp.addEventListener('input', function(){
                var saveBtn = inp.closest('.pers-admin-row').querySelector('.pers-admin-save');
                saveBtn.classList.add('dirty');
            });
        });

        // ペルソナ切替
        document.querySelectorAll('.pers-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                document.querySelectorAll('.pers-btn').forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
                var k = btn.getAttribute('data-pers');
                document.querySelectorAll('.pers-view').forEach(function(v){
                    v.classList.toggle('active', v.getAttribute('data-pers-view') === k);
                });
            });
        });
    }

    // ===== D. シナリオ =====
    function renderScenario() {
        var modes = {
            'sale':       { container: 'scnSale',  label: '販売価格', priceLabel: '販売価格' },
            'rent-short': { container: 'scnShort', label: '短期レンタル(①月額)', priceLabel: '①月額' },
            'rent-mid':   { container: 'scnMid',   label: '中期レンタル(②月額)', priceLabel: '②月額' },
            'rent-long':  { container: 'scnLong',  label: '長期レンタル(③月額)', priceLabel: '③月額' },
        };
        Object.keys(modes).forEach(function(k){
            var m = modes[k];
            var html = rows.slice(0, 6).map(function(row){
                var s = priceOf(row, 'S', m.priceLabel);
                var a = priceOf(row, 'A', m.priceLabel);
                var b = priceOf(row, 'B', m.priceLabel);
                if (s === null && a === null && b === null) {
                    return '<div class="scn-card"><h4 class="scn-card-title">' + escapeHtml(row.display_name) + '</h4><div class="scn-card-empty">この組合せの価格はありません</div></div>';
                }
                return '<div class="scn-card">' +
                    '<div class="scn-card-head"><h4 class="scn-card-title">' + escapeHtml(row.display_name) + '</h4></div>' +
                    '<div class="scn-card-tier-row">' +
                        '<div class="scn-card-tier tier-S"><div class="scn-card-tier-label">上位ディーラー</div><div class="scn-card-tier-amount">' + (s !== null ? yen(s) : '—') + '</div></div>' +
                        '<div class="scn-card-tier tier-A"><div class="scn-card-tier-label">標準ディーラー</div><div class="scn-card-tier-amount">' + (a !== null ? yen(a) : '—') + '</div></div>' +
                        '<div class="scn-card-tier tier-B"><div class="scn-card-tier-label">新規開拓</div><div class="scn-card-tier-amount">' + (b !== null ? yen(b) : '—') + '</div></div>' +
                    '</div>' +
                '</div>';
            }).join('');
            document.getElementById(m.container).innerHTML = html;
        });

        document.querySelectorAll('.scn-tab').forEach(function(t){
            t.addEventListener('click', function(){
                document.querySelectorAll('.scn-tab').forEach(function(x){ x.classList.remove('active'); });
                t.classList.add('active');
                var k = t.getAttribute('data-scn');
                document.querySelectorAll('.scn-view').forEach(function(v){
                    v.classList.toggle('active', v.getAttribute('data-scn-view') === k);
                });
            });
        });
    }

    // ===== F. クイック回答カード =====
    function renderFAQ() {
        var list = document.getElementById('faqList');
        var shown = rows.slice(0, 3);
        list.innerHTML = shown.map(function(row){
            var specs = (row.attributes || [])
                .filter(function(a){ return a.label !== '製品シリーズ' && a.value; })
                .slice(0, 3)
                .map(function(a){ return escapeHtml(a.label) + ': ' + escapeHtml(a.value); }).join(' / ');
            function answerRow(num, qText, priceLabel, suffix) {
                var s = priceOf(row, 'S', priceLabel);
                var a = priceOf(row, 'A', priceLabel);
                var b = priceOf(row, 'B', priceLabel);
                return '<div class="faq-row">' +
                    '<div class="faq-q"><span class="faq-q-mark">Q' + num + '</span>' + qText + '</div>' +
                    '<div class="faq-a">' +
                        '<div class="faq-a-tier tier-S"><div class="faq-a-tier-label">上位ディーラー (S)</div><div class="faq-a-tier-amount">' + (s !== null ? yen(s) + (suffix||'') : '—') + '</div></div>' +
                        '<div class="faq-a-tier tier-A"><div class="faq-a-tier-label">標準ディーラー (A)</div><div class="faq-a-tier-amount">' + (a !== null ? yen(a) + (suffix||'') : '—') + '</div></div>' +
                        '<div class="faq-a-tier tier-B"><div class="faq-a-tier-label">新規開拓 (B)</div><div class="faq-a-tier-amount">' + (b !== null ? yen(b) + (suffix||'') : '—') + '</div></div>' +
                    '</div>' +
                '</div>';
            }
            return '<div class="faq-card">' +
                '<div class="faq-card-head">' +
                    '<h4 class="faq-card-title">' + escapeHtml(row.display_name) + '</h4>' +
                    '<div class="faq-card-specs">' + specs + '</div>' +
                '</div>' +
                answerRow(1, '販売したい場合の価格は？', '販売価格', '') +
                answerRow(2, '短期レンタル(1〜3ヶ月)の月額は？', '①月額', '/月') +
                answerRow(3, '長期レンタル(6ヶ月〜)の月額は？', '③月額', '/月') +
            '</div>';
        }).join('');
    }

    // ===== G. 価格ヒートマップ =====
    function renderHeatmap() {
        var wrap = document.getElementById('heatWrap');
        var tiers = ['S', 'A', 'B'];
        // 最大価格（販売価格基準）を取得して色強度に使う
        var allPrices = [];
        rows.forEach(function(r){
            tiers.forEach(function(g){
                var p = priceOf(r, g, '販売価格');
                if (p) allPrices.push(p);
            });
        });
        var minP = Math.min.apply(null, allPrices);
        var maxP = Math.max.apply(null, allPrices);
        function colorFor(amount) {
            if (amount === null) return 'background: white; color: #d1d5db;';
            var t = (amount - minP) / (maxP - minP || 1);
            // 緑(120deg) → 黄(60deg) → 赤(0deg) のグラデーション
            var hue = 120 - t * 120;
            var sat = 70;
            var light = 90 - t * 40;
            return 'background: hsl(' + hue + ',' + sat + '%,' + light + '%);';
        }
        var html = '<table class="heat-table"><thead><tr><th></th>';
        tiers.forEach(function(g){ html += '<th>' + g + '層 販売</th>'; });
        html += '<th>S層 月額(短期)</th><th>A層 月額(短期)</th><th>B層 月額(短期)</th>';
        html += '</tr></thead><tbody>';
        rows.slice(0, 12).forEach(function(r){
            html += '<tr><td class="heat-row-label">' + escapeHtml(r.display_name) + '</td>';
            tiers.forEach(function(g){
                var p = priceOf(r, g, '販売価格');
                html += '<td class="heat-cell ' + (p === null ? 'empty' : '') + '" style="' + colorFor(p) + '" title="' + escapeHtml(r.display_name) + ' / ' + g + '層 販売">' +
                    (p !== null ? yen(p) : '—') + '</td>';
            });
            // 短期月額の比較
            tiers.forEach(function(g){
                var p = priceOf(r, g, '①月額');
                html += '<td class="heat-cell ' + (p === null ? 'empty' : '') + '" style="' + colorFor(p ? p * 25 : null) + '" title="' + g + '層 ①月額">' +
                    (p !== null ? yen(p) : '—') + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table>';
        wrap.innerHTML = html;
    }

    // ===== H. 電卓型 =====
    function renderCalculator() {
        var sizeSel = document.getElementById('calcSize');
        sizeSel.innerHTML = rows.map(function(r, i){
            return '<option value="' + i + '">' + escapeHtml(r.display_name) + '</option>';
        }).join('');

        function recompute() {
            var rowIdx = parseInt(sizeSel.value, 10) || 0;
            var row = rows[rowIdx];
            var tier = document.getElementById('calcTier').value;
            var mode = document.getElementById('calcMode').value;
            var qty  = parseInt(document.getElementById('calcQty').value, 10) || 1;
            var months = parseInt(document.getElementById('calcMonths').value, 10) || 1;
            document.getElementById('calcMonthsOut').textContent = months;

            var label = ({ 'sale': '販売価格', 'rent-1': '①月額', 'rent-2': '②月額', 'rent-3': '③月額' })[mode];
            var unit = priceOf(row, tier, label);
            var unitTxt = unit !== null ? yen(unit) : '価格なし';
            document.getElementById('calcUnit').textContent = unitTxt + (mode === 'sale' || unit === null ? '' : ' / 月');

            var modeLabel = { 'sale': '販売', 'rent-1': '短期レンタル', 'rent-2': '中期レンタル', 'rent-3': '長期レンタル' }[mode];
            var tierLabel = { 'S': '上位ディーラー', 'A': '標準ディーラー', 'B': '新規開拓' }[tier];
            document.getElementById('calcSub').textContent = '対象: ' + row.display_name + ' / ' + tierLabel + ' / ' + modeLabel;

            var total = 0;
            if (unit !== null) {
                total = mode === 'sale' ? unit * qty : unit * qty * months;
            }
            document.getElementById('calcTotal').textContent =
                '合計: ' + yen(total) + (mode === 'sale' ? ' (' + qty + '台)' : ' (' + qty + '台 × ' + months + 'ヶ月)');
        }
        ['change', 'input'].forEach(function(ev){
            ['calcSize', 'calcTier', 'calcMode', 'calcQty', 'calcMonths'].forEach(function(id){
                document.getElementById(id).addEventListener(ev, recompute);
            });
        });
        recompute();
    }

    // ===== I. 顧客視点型 =====
    function renderCustomer() {
        var view = document.getElementById('custView');
        var tiers = [
            { g: 'S', label: '上位ディーラー (S層)', desc: '大興物産・レンタルニッケン等' },
            { g: 'A', label: '標準ディーラー (A層)', desc: 'その他の取引先ディーラー' },
            { g: 'B', label: '新規開拓 (B層)',       desc: 'エンドユーザー直接' },
        ];
        view.innerHTML = tiers.map(function(t){
            var rowsHtml = rows.slice(0, 6).map(function(r){
                var sale  = priceOf(r, t.g, '販売価格');
                var rent1 = priceOf(r, t.g, '①月額');
                var rent3 = priceOf(r, t.g, '③月額');
                var meta = (function(){
                    var inch = attrOf(r, 'インチ数');
                    var size = attrOf(r, '画面サイズ');
                    var arr = [];
                    if (inch) arr.push(inch + '"');
                    if (size) arr.push(size);
                    return arr.join(' / ');
                })();
                return '<div class="cust-row">' +
                    '<div>' +
                        '<div class="cust-row-product">' + escapeHtml(r.display_name) + '</div>' +
                        (meta ? '<div class="cust-row-meta">' + escapeHtml(meta) + '</div>' : '') +
                    '</div>' +
                    '<div class="cust-row-price ' + (sale === null ? 'cust-row-empty' : '') + '">' + (sale !== null ? yen(sale) : '—') + '</div>' +
                    '<div class="cust-row-rental ' + (rent1 === null && rent3 === null ? 'cust-row-empty' : '') + '">' +
                        (rent1 !== null ? yen(rent1) + '〜' : '') +
                        (rent3 !== null ? yen(rent3) + '/月' : (rent1 !== null ? '/月' : '—')) +
                    '</div>' +
                '</div>';
            }).join('');
            return '<div class="cust-section">' +
                '<div class="cust-header tier-' + t.g + '">' +
                    '<span class="cust-header-mark">' + t.g + '</span>' +
                    '<span>' + escapeHtml(t.label) + '</span>' +
                    '<span style="opacity:0.85;font-weight:normal;font-size:0.8rem;margin-left:auto;">' + escapeHtml(t.desc) + '</span>' +
                '</div>' +
                '<div class="cust-body">' +
                    '<div class="cust-row" style="background:var(--gray-50);font-size:0.7rem;color:var(--gray-600);font-weight:700;text-transform:uppercase;">' +
                        '<div>製品</div><div style="text-align:right;">販売価格</div><div style="text-align:right;">月額(短期〜長期)</div>' +
                    '</div>' +
                    rowsHtml +
                '</div>' +
            '</div>';
        }).join('');
    }

    // ===== J. レンタルタイムライン型 =====
    var tlState = { rowIdx: 0, tier: 'A' };
    function renderTimeline() {
        var sel = document.getElementById('tlProduct');
        sel.innerHTML = rows.slice(0, 12).map(function(r, i){
            return '<option value="' + i + '">' + escapeHtml(r.display_name) + '</option>';
        }).join('');
        sel.addEventListener('change', function(){
            tlState.rowIdx = parseInt(this.value, 10);
            drawTimeline();
        });
        var tiersEl = document.getElementById('tlTiers');
        tiersEl.innerHTML = ['S', 'A', 'B'].map(function(g){
            var label = { S: '上位ディーラー', A: '標準ディーラー', B: '新規開拓' }[g];
            return '<button class="tl-tier-tab tier-' + g + ' ' + (g === tlState.tier ? 'active' : '') + '" data-tier="' + g + '">' + g + '層 / ' + label + '</button>';
        }).join('');
        tiersEl.querySelectorAll('.tl-tier-tab').forEach(function(btn){
            btn.addEventListener('click', function(){
                tlState.tier = btn.getAttribute('data-tier');
                tiersEl.querySelectorAll('.tl-tier-tab').forEach(function(b){
                    b.classList.toggle('active', b === btn);
                });
                drawTimeline();
            });
        });
        drawTimeline();
    }
    function drawTimeline() {
        var row = rows[tlState.rowIdx];
        var p1 = priceOf(row, tlState.tier, '①月額');
        var p2 = priceOf(row, tlState.tier, '②月額');
        var p3 = priceOf(row, tlState.tier, '③月額');
        var bar = document.getElementById('tlBar');
        bar.innerHTML =
            '<div class="tl-seg seg-1">' +
                '<div class="tl-seg-period">1〜3ヶ月 (①)</div>' +
                '<div class="tl-seg-amount">' + (p1 !== null ? yen(p1) + '/月' : '—') + '</div>' +
            '</div>' +
            '<div class="tl-seg seg-2">' +
                '<div class="tl-seg-period">3〜6ヶ月 (②)</div>' +
                '<div class="tl-seg-amount">' + (p2 !== null ? yen(p2) + '/月' : '—') + '</div>' +
            '</div>' +
            '<div class="tl-seg seg-3">' +
                '<div class="tl-seg-period">6ヶ月〜 (③)</div>' +
                '<div class="tl-seg-amount">' + (p3 !== null ? yen(p3) + '/月' : '—') + '</div>' +
            '</div>';
    }
})();
</script>
