<?php
/**
 * 「スプシじゃない価格表」3スタイル比較プレビュー
 *
 * 同じデータ（モニたろう UTM-P4 81"）を 3 種類の見せ方で比較:
 *   ① 「答え」型 = 検索/AI 風（1質問 1答え、テーブルなし）
 *   ② ダッシュボード型 = グラフ・ヒートマップで可視化
 *   ③ 商品ページ型 = Amazon/Tesla 風（1製品 1ページ、価格は1つだけ目立つ）
 *
 * 採用案が決まったら sales-tools.php の価格表詳細ビューに移植。
 */
require_once '../api/auth.php';
require_once '../functions/header.php';
$csrfToken = generateCsrfToken();
?>
<style<?= nonceAttr() ?>>
.ps-page { max-width: 1280px; margin: 0 auto; padding: 0 0 4rem; }

/* イントロ */
.ps-intro {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 1px solid #f59e0b;
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
}
.ps-intro h2 { margin: 0 0 0.4rem; font-size: 1.2rem; color: #78350f; }
.ps-intro p  { margin: 0; color: #92400e; font-size: 0.9rem; line-height: 1.7; }

.ps-jump { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
.ps-jump a {
    padding: 0.5rem 1rem;
    background: white;
    border: 1px solid var(--gray-300);
    border-radius: 7px;
    text-decoration: none;
    color: var(--gray-700);
    font-size: 0.88rem;
    font-weight: 600;
}
.ps-jump a:hover { border-color: var(--primary); color: var(--primary); }

/* セクション共通 */
.ps-section {
    margin-bottom: 2.5rem;
    padding: 1.75rem;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 14px;
}
.ps-section-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 1rem;
    margin-bottom: 1.25rem;
    padding-bottom: 0.85rem;
    border-bottom: 1px solid var(--gray-200);
    flex-wrap: wrap;
}
.ps-section-head h3 { margin: 0; font-size: 1.15rem; color: var(--gray-900); }
.ps-section-head .ps-section-tag {
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.25rem 0.7rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}
.ps-section-sub { color: var(--gray-600); font-size: 0.85rem; margin-top: 0.3rem; }

/* ===== ① 答え型 (AI/検索風) ===== */
.ai-frame {
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    border-radius: 14px;
    padding: 1.5rem;
    min-height: 480px;
}
.ai-searchbar {
    display: flex;
    gap: 0.5rem;
    background: white;
    border: 2px solid var(--gray-300);
    border-radius: 14px;
    padding: 0.65rem 1rem;
    align-items: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: border-color 0.15s, box-shadow 0.15s;
}
.ai-searchbar:focus-within {
    border-color: var(--primary);
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.12);
}
.ai-searchbar svg { color: var(--gray-400); flex-shrink: 0; }
.ai-searchbar input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 1rem;
    background: transparent;
}
.ai-quick {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.85rem;
}
.ai-quick button {
    padding: 0.45rem 0.85rem;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 999px;
    font-size: 0.8rem;
    color: var(--gray-700);
    cursor: pointer;
    transition: all 0.15s;
}
.ai-quick button:hover { border-color: var(--primary); color: var(--primary); }

.ai-answer {
    background: white;
    border-radius: 14px;
    padding: 1.5rem 1.75rem;
    margin-top: 1.25rem;
    border: 1px solid var(--gray-200);
}
.ai-answer.empty {
    text-align: center;
    color: var(--gray-400);
    padding: 3rem 1rem;
}
.ai-answer-product { font-size: 0.85rem; color: var(--gray-500); margin-bottom: 0.4rem; }
.ai-answer-price {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--gray-900);
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
    margin-bottom: 0.85rem;
}
.ai-answer-explain {
    color: var(--gray-700);
    line-height: 1.7;
    font-size: 0.92rem;
    padding-top: 0.85rem;
    border-top: 1px dashed var(--gray-200);
}
.ai-answer-explain strong { color: var(--gray-900); }
.ai-answer-more {
    margin-top: 0.85rem;
    padding-top: 0.85rem;
    border-top: 1px dashed var(--gray-200);
    font-size: 0.78rem;
    color: var(--gray-500);
}

/* ===== ② ダッシュボード型 ===== */
.dash-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1rem;
}
@media (max-width: 900px) { .dash-grid { grid-template-columns: 1fr; } }
.dash-card {
    background: var(--gray-50);
    border-radius: 12px;
    padding: 1.1rem 1.25rem;
}
.dash-card-title {
    font-size: 0.82rem;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 700;
    margin-bottom: 0.85rem;
}
/* 棒グラフ: 価格カーブ */
.dash-bars { display: flex; flex-direction: column; gap: 0.5rem; }
.dash-bar-row { display: flex; align-items: center; gap: 0.85rem; font-size: 0.82rem; }
.dash-bar-label { width: 60px; flex-shrink: 0; color: var(--gray-700); font-weight: 600; }
.dash-bar-track {
    flex: 1;
    height: 28px;
    background: white;
    border-radius: 6px;
    overflow: hidden;
    position: relative;
}
.dash-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #60a5fa 0%, #3b82f6 100%);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 0 0.6rem;
    color: white;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    transition: width 0.3s;
}
.dash-bar-fill.tier-S { background: linear-gradient(90deg, #fbbf24 0%, #f59e0b 100%); }
.dash-bar-fill.tier-A { background: linear-gradient(90deg, #60a5fa 0%, #3b82f6 100%); }
.dash-bar-fill.tier-B { background: linear-gradient(90deg, #34d399 0%, #10b981 100%); }

/* KPI タイル */
.dash-kpi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; }
.dash-kpi {
    background: white;
    border-radius: 10px;
    padding: 0.85rem;
    text-align: center;
}
.dash-kpi-label { font-size: 0.7rem; color: var(--gray-500); margin-bottom: 0.25rem; }
.dash-kpi-value {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--gray-900);
    font-variant-numeric: tabular-nums;
}
.dash-kpi-sub { font-size: 0.68rem; color: var(--gray-500); margin-top: 0.2rem; }

/* ヒートマップ */
.dash-heatmap {
    display: grid;
    grid-template-columns: auto repeat(3, 1fr);
    gap: 4px;
    margin-top: 0.5rem;
}
.dash-heatmap-cell {
    padding: 0.55rem 0.7rem;
    border-radius: 5px;
    text-align: center;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--gray-900);
    font-variant-numeric: tabular-nums;
}
.dash-heatmap-cell.head { background: var(--gray-200); color: var(--gray-700); font-size: 0.7rem; }
.dash-heatmap-cell.label { background: white; color: var(--gray-600); font-weight: 700; font-size: 0.78rem; }

/* レンタル割引トレンド */
.dash-trend {
    height: 140px;
    position: relative;
    margin-top: 0.5rem;
    background: white;
    border-radius: 8px;
    padding: 0.5rem;
}
.dash-trend svg { width: 100%; height: 100%; }

/* ===== ③ 商品ページ型 ===== */
.prod-frame {
    background: white;
    border-radius: 14px;
    padding: 0;
    overflow: hidden;
}
.prod-grid {
    display: grid;
    grid-template-columns: 5fr 7fr;
    gap: 0;
}
@media (max-width: 900px) { .prod-grid { grid-template-columns: 1fr; } }
.prod-hero {
    background: linear-gradient(135deg, #1e40af 0%, #312e81 100%);
    color: white;
    padding: 2.5rem 2rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    min-height: 320px;
}
.prod-hero-icon {
    width: 90px; height: 90px;
    margin-bottom: 1.25rem;
    background: rgba(255,255,255,0.15);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}
.prod-hero-category {
    font-size: 0.78rem;
    opacity: 0.85;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin-bottom: 0.4rem;
}
.prod-hero-title {
    font-size: 1.6rem;
    font-weight: 800;
    margin: 0 0 0.5rem;
    line-height: 1.2;
}
.prod-hero-spec {
    font-size: 0.88rem;
    opacity: 0.85;
}

.prod-info { padding: 2rem 2.25rem; }
.prod-info-header { margin-bottom: 1.5rem; }
.prod-info-mode {
    display: inline-flex;
    background: var(--gray-100);
    border-radius: 999px;
    padding: 0.25rem;
    margin-bottom: 1.25rem;
}
.prod-info-mode button {
    border: none;
    padding: 0.45rem 1.1rem;
    background: transparent;
    border-radius: 999px;
    cursor: pointer;
    font-size: 0.85rem;
    color: var(--gray-600);
    font-weight: 600;
    transition: all 0.15s;
}
.prod-info-mode button.active {
    background: white;
    color: var(--gray-900);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.prod-price-hero { text-align: left; margin-bottom: 1.25rem; }
.prod-price-label {
    font-size: 0.78rem;
    color: var(--gray-500);
    font-weight: 600;
    margin-bottom: 0.3rem;
}
.prod-price-value {
    font-size: 3rem;
    font-weight: 800;
    color: var(--gray-900);
    line-height: 1.05;
    font-variant-numeric: tabular-nums;
}
.prod-price-suffix { font-size: 0.95rem; color: var(--gray-500); font-weight: 600; margin-left: 0.4rem; }

.prod-tier-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}
.prod-tier-btn {
    padding: 0.7rem 0.5rem;
    background: white;
    border: 2px solid var(--gray-200);
    border-radius: 10px;
    cursor: pointer;
    text-align: center;
    transition: all 0.15s;
}
.prod-tier-btn.active {
    border-color: var(--primary);
    background: var(--primary-light);
}
.prod-tier-btn-label { font-size: 0.72rem; color: var(--gray-600); font-weight: 600; }
.prod-tier-btn-price {
    font-size: 0.92rem;
    color: var(--gray-900);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    margin-top: 0.2rem;
}

.prod-specs {
    list-style: none;
    padding: 0;
    margin: 0;
    border-top: 1px solid var(--gray-200);
}
.prod-specs li {
    display: flex;
    justify-content: space-between;
    padding: 0.6rem 0;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.88rem;
}
.prod-specs li span:first-child { color: var(--gray-600); }
.prod-specs li span:last-child { color: var(--gray-900); font-weight: 600; }
.prod-cta {
    display: flex;
    gap: 0.6rem;
    margin-top: 1.25rem;
}
.prod-cta button {
    flex: 1;
    padding: 0.85rem 1.25rem;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
}
.prod-cta .primary {
    background: var(--primary);
    color: white;
    border: none;
}
.prod-cta .primary:hover { background: var(--primary-dark); }
.prod-cta .secondary {
    background: white;
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
}
.prod-cta .secondary:hover { border-color: var(--primary); color: var(--primary); }
</style>

<div class="ps-page">

    <div class="ps-intro">
        <h2>「スプシじゃない価格表」3スタイル比較</h2>
        <p>同じデータ（モニたろう UTM-P4 81インチ）を、テーブル表示を使わない3つの方向性で表現してみます。<br>
        どれが「使いたい」と思えるか比較してください。</p>
    </div>

    <nav class="ps-jump">
        <a href="#s1">① 答え型（AI/検索風）</a>
        <a href="#s2">② ダッシュボード型</a>
        <a href="#s3">③ 商品ページ型</a>
    </nav>

    <!-- ========== ① 答え型 ========== -->
    <section class="ps-section" id="s1">
        <div class="ps-section-head">
            <div>
                <h3>① 「答え」型（AI/検索風）</h3>
                <div class="ps-section-sub">質問を入力すると、答えが1つだけ大きく表示される。ChatGPT/Google検索のような体験。</div>
            </div>
            <span class="ps-section-tag">スプシ感ゼロ</span>
        </div>

        <div class="ai-frame">
            <div class="ai-searchbar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="aiQuery" placeholder="例: モニたろう81インチを大手ディーラーに販売したい">
            </div>

            <div class="ai-quick">
                <button data-q="モニたろう 81インチ 大手 販売">大手ディーラーに販売</button>
                <button data-q="モニたろう 81インチ 標準 短期レンタル">標準で短期レンタル</button>
                <button data-q="モニたろう 81インチ 新規 長期レンタル">新規開拓に長期レンタル</button>
                <button data-q="モニすけ 32インチ 大手 販売">モニすけ32"を大手販売</button>
            </div>

            <div class="ai-answer empty" id="aiAnswer">
                <div>上の検索ボックスに質問するか、サンプル質問をクリックしてください</div>
            </div>
        </div>
    </section>

    <!-- ========== ② ダッシュボード型 ========== -->
    <section class="ps-section" id="s2">
        <div class="ps-section-head">
            <div>
                <h3>② ダッシュボード型</h3>
                <div class="ps-section-sub">数字をグラフ・ヒートマップで可視化。一目で価格カーブとパターンが分かる。</div>
            </div>
            <span class="ps-section-tag">視覚優先</span>
        </div>

        <div class="dash-grid">
            <!-- 左: 棒グラフ -->
            <div class="dash-card">
                <div class="dash-card-title">サイズ別販売価格（標準ディーラー / A層）</div>
                <div class="dash-bars" id="dashBars"></div>
            </div>

            <!-- 右: KPI -->
            <div class="dash-card">
                <div class="dash-card-title">UTM-P4 81" 価格レンジ</div>
                <div class="dash-kpi-grid">
                    <div class="dash-kpi">
                        <div class="dash-kpi-label">最安レンタル</div>
                        <div class="dash-kpi-value" id="kpiMinRent">-</div>
                        <div class="dash-kpi-sub">/ 月</div>
                    </div>
                    <div class="dash-kpi">
                        <div class="dash-kpi-label">販売価格(中)</div>
                        <div class="dash-kpi-value" id="kpiSale">-</div>
                        <div class="dash-kpi-sub">A層</div>
                    </div>
                    <div class="dash-kpi">
                        <div class="dash-kpi-label">層差(最大)</div>
                        <div class="dash-kpi-value" id="kpiSpread">-</div>
                        <div class="dash-kpi-sub">S → B 差額</div>
                    </div>
                    <div class="dash-kpi">
                        <div class="dash-kpi-label">期間割引</div>
                        <div class="dash-kpi-value" id="kpiDiscount">-</div>
                        <div class="dash-kpi-sub">短期→長期</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dash-card" style="margin-top: 1rem;">
            <div class="dash-card-title">販売価格ヒートマップ（サイズ × 層）</div>
            <div class="dash-heatmap" id="dashHeat"></div>
        </div>

        <div class="dash-card" style="margin-top: 1rem;">
            <div class="dash-card-title">レンタル期間 → 月額（A層、UTM-P4 81"）</div>
            <div class="dash-trend">
                <svg viewBox="0 0 400 120" preserveAspectRatio="none">
                    <!-- 動的に line を描画 -->
                    <polyline id="trendLine" fill="none" stroke="#3b82f6" stroke-width="2"/>
                    <g id="trendDots"></g>
                    <g id="trendLabels" font-size="10" fill="#6b7280"></g>
                </svg>
            </div>
        </div>
    </section>

    <!-- ========== ③ 商品ページ型 ========== -->
    <section class="ps-section" id="s3" style="padding: 0; overflow: hidden;">
        <div class="ps-section-head" style="padding: 1.75rem 1.75rem 0.85rem;">
            <div>
                <h3>③ 商品ページ型（Amazon/Tesla風）</h3>
                <div class="ps-section-sub">1製品=1ページ。価格は1つだけ大きく、設定変更で値が変わる。顧客にも見せられる。</div>
            </div>
            <span class="ps-section-tag" style="margin: 1.75rem 1.75rem 0 0;">顧客提示可</span>
        </div>

        <div class="prod-frame">
            <div class="prod-grid">
                <div class="prod-hero">
                    <div class="prod-hero-icon">
                        <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="3" width="20" height="14" rx="2"/>
                            <line x1="8" y1="21" x2="16" y2="21"/>
                            <line x1="12" y1="17" x2="12" y2="21"/>
                        </svg>
                    </div>
                    <div class="prod-hero-category">LED ビジョン</div>
                    <h2 class="prod-hero-title">モニたろう UTM-P4</h2>
                    <div class="prod-hero-spec">81インチ / 1600×1280 / 2.05㎡</div>
                </div>

                <div class="prod-info">
                    <div class="prod-info-mode">
                        <button class="active" data-mode="sale">販売</button>
                        <button data-mode="rent-1">短期レンタル (1-3M)</button>
                        <button data-mode="rent-2">中期 (3-6M)</button>
                        <button data-mode="rent-3">長期 (6M+)</button>
                    </div>

                    <div class="prod-price-hero">
                        <div class="prod-price-label" id="prodPriceLabel">標準ディーラー(A層) への販売価格</div>
                        <div>
                            <span class="prod-price-value" id="prodPriceValue">¥-</span>
                            <span class="prod-price-suffix" id="prodPriceSuffix"></span>
                        </div>
                    </div>

                    <div class="prod-tier-row">
                        <button class="prod-tier-btn" data-tier="S"><div class="prod-tier-btn-label">上位ディーラー</div><div class="prod-tier-btn-price" id="prodTierS">-</div></button>
                        <button class="prod-tier-btn active" data-tier="A"><div class="prod-tier-btn-label">標準ディーラー</div><div class="prod-tier-btn-price" id="prodTierA">-</div></button>
                        <button class="prod-tier-btn" data-tier="B"><div class="prod-tier-btn-label">新規開拓</div><div class="prod-tier-btn-price" id="prodTierB">-</div></button>
                    </div>

                    <ul class="prod-specs">
                        <li><span>製品シリーズ</span><span>UTM-P4</span></li>
                        <li><span>インチ数</span><span>81 インチ</span></li>
                        <li><span>画面サイズ</span><span>1600 × 1280 px</span></li>
                        <li><span>平米数</span><span>2.05 ㎡</span></li>
                    </ul>

                    <div class="prod-cta">
                        <button class="primary">この内容で見積を作る</button>
                        <button class="secondary">プリント / PDF</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>

<script<?= nonceAttr() ?>>
(function(){
    'use strict';
    function escapeHtml(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function yen(n){ return '¥' + (n||0).toLocaleString('ja-JP'); }

    var rows = [];
    var sample = null; // モニたろう UTM-P4 81" 行

    fetch('/api/price-list-get.php?title=' + encodeURIComponent('モニたろうUTM・FA・RCM'), { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success || !j.data || !j.data.sheet) throw new Error('データなし');
            var sheet = j.data.sheet;
            if (!sheet.normalized || !sheet.normalized.rows) throw new Error('正規化データなし');
            rows = sheet.normalized.rows;
            sample = rows[0];
            renderAll();
        });

    function priceOf(row, g, label) {
        var p = (row && row.prices || []).find(function(x){ return x.group===g && x.label===label; });
        return p ? p.amount : null;
    }

    // ========== ① 答え型 ==========
    function answerQuery(q) {
        var tier = /大手|上位|S/.test(q) ? 'S' : /新規|B/.test(q) ? 'B' : 'A';
        var mode = /販売/.test(q) ? 'sale' : /短期|1-3/.test(q) ? 'rent-1' : /長期|6/.test(q) ? 'rent-3' : 'rent-2';
        var label = ({ 'sale':'販売価格', 'rent-1':'①月額', 'rent-2':'②月額', 'rent-3':'③月額' })[mode];
        // 簡易: モニたろう のみマッチ。それ以外は固定
        var row = (rows.find(function(r){ return /モニ/.test(r.display_name) && /81/.test(r.display_name); })) || rows[0];
        var p = priceOf(row, tier, label);
        var tierName = { S: '上位ディーラー', A: '標準ディーラー', B: '新規開拓' }[tier];
        var modeName = { 'sale': '販売', 'rent-1': '短期レンタル(1-3ヶ月)', 'rent-2': '中期レンタル(3-6ヶ月)', 'rent-3': '長期レンタル(6ヶ月〜)' }[mode];
        var suffix = mode === 'sale' ? '' : ' / 月';
        var el = document.getElementById('aiAnswer');
        if (p === null) {
            el.classList.remove('empty');
            el.innerHTML = '<div>該当する組合せの価格は登録されていません</div>';
            return;
        }
        el.classList.remove('empty');
        el.innerHTML =
            '<div class="ai-answer-product">' + escapeHtml((row.display_name || '')) + '</div>' +
            '<div class="ai-answer-price">' + yen(p) + '<span style="font-size:1rem;color:var(--gray-500);margin-left:0.4rem;">' + suffix + '</span></div>' +
            '<div class="ai-answer-explain">' +
                '<strong>' + escapeHtml(tierName) + '</strong> 向けの <strong>' + escapeHtml(modeName) + '</strong> 価格です。' +
                (mode === 'sale' ? ' 買い切りの場合の単価です。' : ' この金額が契約期間中、毎月発生します。') +
            '</div>' +
            '<div class="ai-answer-more">他のサイズや組合せを試したい場合は、検索ボックスから質問してください</div>';
    }
    document.querySelectorAll('.ai-quick button').forEach(function(b){
        b.addEventListener('click', function(){
            document.getElementById('aiQuery').value = b.getAttribute('data-q');
            answerQuery(b.getAttribute('data-q'));
        });
    });
    document.getElementById('aiQuery').addEventListener('keydown', function(e){
        if (e.key === 'Enter') answerQuery(this.value);
    });

    // ========== ② ダッシュボード型 ==========
    function renderAll() {
        // 棒グラフ: 各サイズの A層 販売価格
        var sizes = rows.slice(0, 8).map(function(r){
            var inch = (r.attributes || []).find(function(a){ return a.label === 'インチ数' || a.label === 'インチ'; });
            return {
                label: inch ? inch.value + '"' : (r.display_name || '?').substring(0, 8),
                price: priceOf(r, 'A', '販売価格') || 0
            };
        }).filter(function(x){ return x.price > 0; });
        var maxPrice = Math.max.apply(null, sizes.map(function(s){ return s.price; }));
        var barsEl = document.getElementById('dashBars');
        if (barsEl) {
            barsEl.innerHTML = sizes.map(function(s){
                var w = (s.price / maxPrice * 100).toFixed(0);
                return '<div class="dash-bar-row">' +
                    '<div class="dash-bar-label">' + escapeHtml(s.label) + '</div>' +
                    '<div class="dash-bar-track">' +
                        '<div class="dash-bar-fill tier-A" style="width: ' + w + '%;">' + yen(s.price) + '</div>' +
                    '</div>' +
                '</div>';
            }).join('');
        }

        // KPI タイル
        var s1 = priceOf(sample, 'A', '①月額'), s2 = priceOf(sample, 'A', '②月額'), s3 = priceOf(sample, 'A', '③月額');
        var saleS = priceOf(sample, 'S', '販売価格'), saleA = priceOf(sample, 'A', '販売価格'), saleB = priceOf(sample, 'B', '販売価格');
        document.getElementById('kpiMinRent').textContent = s3 ? yen(s3) : '-';
        document.getElementById('kpiSale').textContent = saleA ? yen(saleA) : '-';
        document.getElementById('kpiSpread').textContent = (saleS && saleB) ? yen(saleB - saleS) : '-';
        document.getElementById('kpiDiscount').textContent = (s1 && s3) ? Math.round((1 - s3/s1) * 100) + '%' : '-';

        // ヒートマップ
        var heatEl = document.getElementById('dashHeat');
        var heatSizes = sizes.slice(0, 5);
        var allPrices = [];
        heatSizes.forEach(function(s, i){
            ['S','A','B'].forEach(function(g){
                var r = rows[i]; if (!r) return;
                var p = priceOf(r, g, '販売価格');
                if (p) allPrices.push(p);
            });
        });
        var min = Math.min.apply(null, allPrices), max = Math.max.apply(null, allPrices);
        function heatColor(p) {
            if (!p) return 'background: white; color: #d1d5db;';
            var t = (p - min) / (max - min || 1);
            var hue = 120 - t * 120;
            return 'background: hsl(' + hue + ',70%,' + (90 - t*30) + '%);';
        }
        var html = '<div class="dash-heatmap-cell head"></div>' +
                   '<div class="dash-heatmap-cell head">S層</div>' +
                   '<div class="dash-heatmap-cell head">A層</div>' +
                   '<div class="dash-heatmap-cell head">B層</div>';
        heatSizes.forEach(function(s, i){
            html += '<div class="dash-heatmap-cell label">' + escapeHtml(s.label) + '</div>';
            ['S','A','B'].forEach(function(g){
                var r = rows[i]; if (!r) { html += '<div class="dash-heatmap-cell"></div>'; return; }
                var p = priceOf(r, g, '販売価格');
                html += '<div class="dash-heatmap-cell" style="' + heatColor(p) + '">' + (p ? yen(p) : '-') + '</div>';
            });
        });
        if (heatEl) heatEl.innerHTML = html;

        // トレンドライン (レンタル期間 → 月額)
        var trendPoints = [
            { x: 0,   label: '1ヶ月', price: s1 },
            { x: 100, label: '3ヶ月', price: s1 },
            { x: 200, label: '6ヶ月', price: s2 },
            { x: 400, label: '12ヶ月+', price: s3 }
        ].filter(function(p){ return p.price; });
        if (trendPoints.length > 0) {
            var maxR = Math.max.apply(null, trendPoints.map(function(p){ return p.price; }));
            var minR = Math.min.apply(null, trendPoints.map(function(p){ return p.price; }));
            function y(p) { return 110 - ((p - minR) / (maxR - minR || 1)) * 90; }
            var poly = trendPoints.map(function(p){ return p.x + ',' + y(p.price).toFixed(0); }).join(' ');
            document.getElementById('trendLine').setAttribute('points', poly);
            var dots = trendPoints.map(function(p){
                return '<circle cx="' + p.x + '" cy="' + y(p.price).toFixed(0) + '" r="4" fill="#3b82f6"/>';
            }).join('');
            document.getElementById('trendDots').innerHTML = dots;
            var labels = trendPoints.map(function(p){
                return '<text x="' + p.x + '" y="' + (y(p.price) - 8).toFixed(0) + '" text-anchor="middle" font-weight="700" fill="#1f2937">' + yen(p.price) + '</text>' +
                       '<text x="' + p.x + '" y="118" text-anchor="middle">' + p.label + '</text>';
            }).join('');
            document.getElementById('trendLabels').innerHTML = labels;
        }

        // ========== ③ 商品ページ型 ==========
        var prodState = { tier: 'A', mode: 'sale' };
        function updateProd() {
            var label = ({ 'sale':'販売価格', 'rent-1':'①月額', 'rent-2':'②月額', 'rent-3':'③月額' })[prodState.mode];
            var price = priceOf(sample, prodState.tier, label);
            var tierName = { S: '上位ディーラー', A: '標準ディーラー', B: '新規開拓' }[prodState.tier];
            var modeName = { 'sale': '販売価格', 'rent-1': '短期レンタル', 'rent-2': '中期レンタル', 'rent-3': '長期レンタル' }[prodState.mode];
            document.getElementById('prodPriceLabel').textContent = tierName + '(' + prodState.tier + '層) への ' + modeName;
            document.getElementById('prodPriceValue').textContent = price ? yen(price) : '価格なし';
            document.getElementById('prodPriceSuffix').textContent = (prodState.mode === 'sale' || !price) ? '' : '/ 月';
            // tier ボタンの価格表示
            ['S','A','B'].forEach(function(g){
                var p = priceOf(sample, g, label);
                document.getElementById('prodTier' + g).textContent = p ? yen(p) : '-';
            });
        }
        document.querySelectorAll('.prod-info-mode button').forEach(function(b){
            b.addEventListener('click', function(){
                document.querySelectorAll('.prod-info-mode button').forEach(function(x){ x.classList.remove('active'); });
                b.classList.add('active');
                prodState.mode = b.getAttribute('data-mode');
                updateProd();
            });
        });
        document.querySelectorAll('.prod-tier-btn').forEach(function(b){
            b.addEventListener('click', function(){
                document.querySelectorAll('.prod-tier-btn').forEach(function(x){ x.classList.remove('active'); });
                b.classList.add('active');
                prodState.tier = b.getAttribute('data-tier');
                updateProd();
            });
        });
        updateProd();
    }
})();
</script>
