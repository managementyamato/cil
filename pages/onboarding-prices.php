<?php
/**
 * 新人向け価格表ガイド（オンボーディング）
 *
 * 新人が「業界用語の意味」と「人気製品の価格」を一度に学べるページ。
 * 用語解説 + クイック回答カード（F案）を組み合わせる。
 *
 * 権限: sales（全員閲覧可）
 */
require_once '../api/auth.php';
require_once '../functions/header.php';

$csrfToken = generateCsrfToken();

// 既存の製品定義を読込（価格表タブと共通）
$ppConfigRaw = @file_get_contents(__DIR__ . '/../config/sales-tools-products.json');
$ppConfig    = $ppConfigRaw ? json_decode($ppConfigRaw, true) : null;
if (!is_array($ppConfig)) $ppConfig = ['products' => [], 'common' => []];
$ppProductsForJs = [
    'products' => $ppConfig['products'] ?? [],
];
?>
<style<?= nonceAttr() ?>>
.ob-page { max-width: 1100px; margin: 0 auto; padding: 0 0 4rem; }
.ob-hero {
    background: linear-gradient(135deg, #1e40af 0%, #4338ca 100%);
    color: white;
    border-radius: 14px;
    padding: 1.5rem 1.75rem;
    margin-bottom: 1.5rem;
}
.ob-hero h2 { margin: 0 0 0.4rem; font-size: 1.3rem; }
.ob-hero p  { margin: 0; opacity: 0.92; font-size: 0.92rem; line-height: 1.6; }

/* 用語解説 */
.ob-glossary {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 1.4rem 1.6rem;
    margin-bottom: 1.5rem;
}
.ob-glossary h3 {
    margin: 0 0 1rem;
    font-size: 1rem;
    color: var(--gray-900);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.ob-glossary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
}
.ob-glossary-item {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 0.85rem 1rem;
}
.ob-glossary-key {
    display: inline-block;
    padding: 0.2rem 0.65rem;
    border-radius: 5px;
    font-weight: 700;
    font-size: 0.78rem;
    margin-bottom: 0.4rem;
}
.ob-glossary-key.k-S { background: #fef3c7; color: #b45309; }
.ob-glossary-key.k-A { background: #dbeafe; color: #1e40af; }
.ob-glossary-key.k-B { background: #d1fae5; color: #047857; }
.ob-glossary-key.k-rent1 { background: #fee2e2; color: #b91c1c; }
.ob-glossary-key.k-rent2 { background: #ffedd5; color: #c2410c; }
.ob-glossary-key.k-rent3 { background: #d1fae5; color: #047857; }
.ob-glossary-meaning { font-size: 0.86rem; color: var(--gray-700); line-height: 1.6; }
.ob-glossary-example {
    margin-top: 0.4rem;
    font-size: 0.75rem;
    color: var(--gray-500);
    font-style: italic;
}

/* CTA */
.ob-cta {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 1px solid #f59e0b;
    border-radius: 12px;
    padding: 1.1rem 1.4rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.ob-cta-text { color: #92400e; font-size: 0.92rem; }
.ob-cta-text strong { color: #78350f; }
.ob-cta a {
    background: #92400e;
    color: white;
    padding: 0.55rem 1.2rem;
    border-radius: 7px;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 700;
    transition: background 0.15s;
}
.ob-cta a:hover { background: #78350f; }

/* FAQカード（F案そのまま） */
.ob-section-title {
    font-size: 1rem;
    color: var(--gray-900);
    font-weight: 700;
    margin: 1.75rem 0 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.ob-loading {
    padding: 2rem;
    text-align: center;
    color: var(--gray-500);
    background: white;
    border: 1px dashed var(--gray-200);
    border-radius: 10px;
}
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
.faq-row { padding: 1rem 1.3rem; border-bottom: 1px solid var(--gray-100); }
.faq-row:last-child { border-bottom: none; }
.faq-q {
    font-size: 0.84rem;
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
.faq-a-tier { padding: 0.5rem 0.7rem; border-radius: 7px; }
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
.faq-a-tier.empty .faq-a-tier-amount { color: var(--gray-400); font-weight: 500; }
@media (max-width: 640px) {
    .faq-a { grid-template-columns: 1fr; padding-left: 0; }
}
</style>

<div class="ob-page">
    <div class="ob-hero">
        <h2>価格表の見方ガイド（新人向け）</h2>
        <p>営業の現場でよく出てくる「S層・A層・B層」「①月額〜③月額」が一目で分かる新人向け案内です。
        ここで用語を覚えたら、価格表タブの「さっと価格を調べる」ですぐ見積もりを試せます。</p>
    </div>

    <!-- 用語解説 -->
    <div class="ob-glossary">
        <h3>必須用語</h3>
        <h4 style="margin:0 0 0.5rem; color:var(--gray-700); font-size:0.85rem;">お客様のタイプ（層）</h4>
        <div class="ob-glossary-grid">
            <div class="ob-glossary-item">
                <span class="ob-glossary-key k-S">S層</span>
                <div class="ob-glossary-meaning"><strong>上位ディーラー</strong>: 売上上位のディーラー様向けの最も有利な価格</div>
                <div class="ob-glossary-example">例: 大興物産・レンタルニッケン</div>
            </div>
            <div class="ob-glossary-item">
                <span class="ob-glossary-key k-A">A層</span>
                <div class="ob-glossary-meaning"><strong>標準ディーラー</strong>: 一般的なディーラー様向けの標準価格</div>
                <div class="ob-glossary-example">例: それ以外の取引先ディーラー</div>
            </div>
            <div class="ob-glossary-item">
                <span class="ob-glossary-key k-B">B層</span>
                <div class="ob-glossary-meaning"><strong>新規開拓・直販</strong>: エンドユーザー直接 / 新規取引先向けの価格</div>
                <div class="ob-glossary-example">例: 初めてのお客様</div>
            </div>
        </div>

        <h4 style="margin:1.25rem 0 0.5rem; color:var(--gray-700); font-size:0.85rem;">レンタル月額の表記</h4>
        <div class="ob-glossary-grid">
            <div class="ob-glossary-item">
                <span class="ob-glossary-key k-rent1">①月額</span>
                <div class="ob-glossary-meaning"><strong>短期レンタル</strong>（1〜3ヶ月）の月額</div>
                <div class="ob-glossary-example">短いほど割高</div>
            </div>
            <div class="ob-glossary-item">
                <span class="ob-glossary-key k-rent2">②月額</span>
                <div class="ob-glossary-meaning"><strong>中期レンタル</strong>（3〜6ヶ月）の月額</div>
                <div class="ob-glossary-example">中間的な料金</div>
            </div>
            <div class="ob-glossary-item">
                <span class="ob-glossary-key k-rent3">③月額</span>
                <div class="ob-glossary-meaning"><strong>長期レンタル</strong>（6ヶ月〜）の月額</div>
                <div class="ob-glossary-example">長いほど割安</div>
            </div>
        </div>
    </div>

    <!-- 価格表タブへの導線 -->
    <div class="ob-cta">
        <div class="ob-cta-text">
            <strong>用語が分かったら</strong>: 価格表タブで「さっと価格を調べる」を使って実際に見積もってみよう。
        </div>
        <a href="/pages/sales-tools?tab=pricing">価格表タブを開く →</a>
    </div>

    <!-- 人気3製品のFAQ -->
    <div class="ob-section-title">人気3製品の早見表</div>
    <div id="obFaqList">
        <div class="ob-loading">読み込み中…</div>
    </div>
</div>

<script<?= nonceAttr() ?>>
(function(){
    'use strict';

    var PP_CONFIG_RAW = <?= json_encode($ppProductsForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function yen(n) { return '¥' + (n || 0).toLocaleString('ja-JP'); }
    function priceOf(row, group, label) {
        var p = (row.prices || []).find(function(p){ return p.group === group && p.label === label; });
        return p ? p.amount : null;
    }

    fetch('../api/price-list-get.php', { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success || !j.data || !j.data.available) {
                document.getElementById('obFaqList').innerHTML =
                    '<div class="ob-loading">価格表データがまだありません。管理者にインポートを依頼してください。</div>';
                return;
            }
            renderFAQ(j.data.sheets || []);
        })
        .catch(function(e){
            document.getElementById('obFaqList').innerHTML =
                '<div class="ob-loading">読み込みエラー: ' + escapeHtml(e.message) + '</div>';
        });

    function pickPopularRows(sheets) {
        // 製品定義の上位 3 製品それぞれから、最初の正規化行をピック
        var products = PP_CONFIG_RAW.products || [];
        var picks = [];
        for (var i = 0; i < products.length && picks.length < 3; i++) {
            var p = products[i];
            var regex;
            try { regex = new RegExp(p.match, p.flags || ''); } catch(e) { continue; }
            // マッチするシートを探す
            for (var j = 0; j < sheets.length; j++) {
                var s = sheets[j];
                if (!regex.test(s.title || '')) continue;
                if (s.normalized && s.normalized.rows && s.normalized.rows.length > 0) {
                    var row = s.normalized.rows[0];
                    picks.push({ product: p, sheet: s, row: row });
                    break;
                }
            }
        }
        return picks;
    }

    function renderFAQ(sheets) {
        var list = document.getElementById('obFaqList');
        var picks = pickPopularRows(sheets);
        if (picks.length === 0) {
            list.innerHTML = '<div class="ob-loading">表示可能な製品データがありません。</div>';
            return;
        }
        list.innerHTML = picks.map(function(item){
            var row = item.row;
            var specs = (row.attributes || [])
                .filter(function(a){ return a.label !== '製品シリーズ' && a.value; })
                .slice(0, 3)
                .map(function(a){ return escapeHtml(a.label) + ': ' + escapeHtml(a.value); }).join(' / ');

            function answerRow(num, qText, priceLabel, suffix) {
                var s = priceOf(row, 'S', priceLabel);
                var a = priceOf(row, 'A', priceLabel);
                var b = priceOf(row, 'B', priceLabel);
                function cell(amount, tier, label) {
                    var empty = amount === null;
                    return '<div class="faq-a-tier tier-' + tier + (empty ? ' empty' : '') + '">' +
                        '<div class="faq-a-tier-label">' + label + ' (' + tier + ')</div>' +
                        '<div class="faq-a-tier-amount">' + (empty ? '—' : yen(amount) + (suffix||'')) + '</div>' +
                    '</div>';
                }
                return '<div class="faq-row">' +
                    '<div class="faq-q"><span class="faq-q-mark">Q' + num + '</span>' + qText + '</div>' +
                    '<div class="faq-a">' +
                        cell(s, 'S', '上位ディーラー') +
                        cell(a, 'A', '標準ディーラー') +
                        cell(b, 'B', '新規開拓') +
                    '</div>' +
                '</div>';
            }

            return '<div class="faq-card">' +
                '<div class="faq-card-head">' +
                    '<h4 class="faq-card-title">' + escapeHtml(item.product.name) + ' / ' + escapeHtml(row.display_name) + '</h4>' +
                    '<div class="faq-card-specs">' + specs + '</div>' +
                '</div>' +
                answerRow(1, '販売する場合の価格は？', '販売価格', '') +
                answerRow(2, '短期レンタル(1〜3ヶ月)の月額は？', '①月額', '/月') +
                answerRow(3, '長期レンタル(6ヶ月〜)の月額は？', '③月額', '/月') +
            '</div>';
        }).join('');
    }
})();
</script>
