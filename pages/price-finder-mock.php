<?php
/**
 * 【モック】価格ファインダー（検索先出しフラット）
 *
 * 価格表の課題に対する作り替え案の試作:
 *   ① パッと見で作成できない    → 検索＋ランク選択の2アクションで単価が出る。各行に「コピー」
 *   ② どこに何の情報か分からない → 1画面のフラット表（製品/型番/サイズ＋販売/短期/中期/長期）
 *   ③ 1つ戻ると1から            → 画面遷移なし。行は「その場で展開」。検索/ランクは保持
 *   ④ 最初の選択がわかりにくい   → 入口を検索＋ランク切替に。製品カード選択ステップを廃止
 *
 * データは既存 data/product-prices.json をそのまま使用（読取専用）。本番にはデプロイしない。
 *
 * 閲覧: sales 以上（サイドバー未掲載・直URLのみ）
 */
require_once '../api/auth.php';
require_once '../functions/header.php';

$pfRaw = @file_get_contents(__DIR__ . '/../data/product-prices.json');
$pfData = $pfRaw ? json_decode($pfRaw, true) : null;
$pfSheets = [];
if (is_array($pfData) && !empty($pfData['sheets'])) {
    foreach ($pfData['sheets'] as $s) {
        $rows = $s['normalized']['rows'] ?? [];
        if (empty($rows)) continue;
        $pfSheets[] = [
            'title' => $s['title'] ?? '',
            'rows'  => array_map(fn($r) => [
                'name'   => $r['display_name'] ?? '',
                'attrs'  => $r['attributes'] ?? [],
                'prices' => $r['prices'] ?? [],
                'notes'  => $r['notes'] ?? null,
            ], $rows),
        ];
    }
}
$pfVer = $pfData['synced_at'] ?? '';
?>
<style<?= nonceAttr() ?>>
.pf-wrap { max-width: 1180px; margin: 0 auto; }
.pf-note { background:#fffbeb; border:1px solid #fde68a; color:#92400e; border-radius:8px; padding:0.5rem 0.8rem; font-size:0.82rem; margin-bottom:1rem; }
.pf-bar { position: sticky; top: 0; z-index: 5; background:#fff; border:1px solid var(--gray-200); border-radius:12px; padding:0.9rem 1rem; margin-bottom:1rem; box-shadow:0 1px 4px rgba(0,0,0,0.04); }
.pf-step { display:flex; align-items:center; gap:0.6rem; margin-bottom:0.6rem; }
.pf-step:last-child { margin-bottom:0; }
.pf-step-no { width:1.5rem; height:1.5rem; border-radius:50%; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.78rem; font-weight:700; flex-shrink:0; }
.pf-step-label { font-size:0.82rem; color:var(--gray-500); width:5.5rem; flex-shrink:0; }
.pf-search { flex:1; }
.pf-search input { width:100%; font-size:1rem; padding:0.6rem 0.9rem; }
.pf-rank-toggle { display:inline-flex; border:1px solid var(--gray-300); border-radius:8px; overflow:hidden; }
.pf-rank-btn { padding:0.5rem 1rem; background:#fff; border:none; cursor:pointer; font-size:0.88rem; color:var(--gray-700); border-right:1px solid var(--gray-200); }
.pf-rank-btn:last-child { border-right:none; }
.pf-rank-btn.active { color:#fff; font-weight:600; }
.pf-rank-btn.active[data-rank=S]{ background:#7c3aed; } .pf-rank-btn.active[data-rank=A]{ background:#2563eb; } .pf-rank-btn.active[data-rank=B]{ background:#059669; }
.pf-chips { display:flex; gap:0.4rem; flex-wrap:wrap; }
.pf-chip { padding:0.25rem 0.7rem; border:1px solid var(--gray-300); border-radius:9999px; background:#fff; cursor:pointer; font-size:0.78rem; color:var(--gray-600); }
.pf-chip.active { background:var(--gray-800); color:#fff; border-color:var(--gray-800); }
.pf-count { margin-left:auto; color:var(--gray-500); font-size:0.8rem; white-space:nowrap; }

.pf-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--gray-200); border-radius:10px; overflow:hidden; }
.pf-table th, .pf-table td { padding:0.55rem 0.8rem; border-bottom:1px solid var(--gray-100); font-size:0.88rem; }
.pf-table th { background:var(--gray-50); color:var(--gray-500); font-size:0.72rem; text-align:left; position:sticky; top:96px; z-index:1; }
.pf-table td.num, .pf-table th.num { text-align:right; font-variant-numeric:tabular-nums; }
.pf-table th.num.primary, .pf-table td.num.primary { background:rgba(37,99,235,0.06); font-weight:600; }
.pf-prod { font-weight:600; }
.pf-cat { color:var(--gray-400); font-size:0.72rem; }
.pf-trow { cursor:pointer; }
.pf-trow:hover { background:var(--gray-50); }
.pf-copybtn { padding:0.15rem 0.5rem; font-size:0.72rem; border:1px solid var(--gray-300); background:#fff; border-radius:6px; cursor:pointer; color:var(--gray-600); }
.pf-copybtn:hover { background:var(--gray-50); }
.pf-expand td { background:var(--gray-50); }
.pf-detail-grid { display:flex; flex-wrap:wrap; gap:1.2rem; align-items:flex-start; }
.pf-detail-block h5 { margin:0 0 0.3rem; font-size:0.72rem; }
.pf-tier-line { font-size:0.82rem; }
.pf-tier-line .t { display:inline-block; min-width:5.5rem; color:var(--gray-500); }
.pf-attrs { font-size:0.8rem; color:var(--gray-600); }
.pf-empty { text-align:center; color:var(--gray-400); padding:2rem; }
</style>

<div class="page-container pf-wrap">
    <div class="page-header">
        <div>
            <h2>価格ファインダー <span style="font-size:0.7rem; color:#92400e; background:#fef3c7; border-radius:6px; padding:0.1rem 0.5rem; vertical-align:middle;">モック</span></h2>
            <div style="color:var(--gray-600); font-size:0.85rem; margin-top:0.2rem;">検索 → ランク選択 → 単価がすぐ出る。社内の即時価格照会用の試作<?= $pfVer ? '（データ更新: ' . htmlspecialchars(substr($pfVer,0,10)) . '）' : '' ?></div>
        </div>
    </div>

    <div class="pf-note">これは方向性確認用のモックです（本番には出しません）。良ければ営業ツールの「価格表」タブをこの形に作り替えます。</div>

    <div class="pf-bar">
        <div class="pf-step">
            <span class="pf-step-no">1</span><span class="pf-step-label">製品を検索</span>
            <span class="pf-search"><input type="text" id="pfSearch" class="form-input" placeholder="型番・サイズ・製品名（例: P3 158 / UTM / モニ 90）" autocomplete="off"></span>
        </div>
        <div class="pf-step">
            <span class="pf-step-no">2</span><span class="pf-step-label">顧客ランク</span>
            <div class="pf-rank-toggle" id="pfRankToggle">
                <button type="button" class="pf-rank-btn" data-rank="S">S 上位ディーラー</button>
                <button type="button" class="pf-rank-btn active" data-rank="A">A 標準ディーラー</button>
                <button type="button" class="pf-rank-btn" data-rank="B">B 新規・直販</button>
            </div>
            <span class="pf-count" id="pfCount"></span>
        </div>
        <div class="pf-step">
            <span class="pf-step-no" style="background:var(--gray-300)">＋</span><span class="pf-step-label">絞り込み</span>
            <div class="pf-chips" id="pfChips"></div>
        </div>
    </div>

    <div id="pfResult"><div class="pf-empty">読み込み中…</div></div>
</div>

<script<?= nonceAttr() ?>>
(function() {
    const SHEETS = <?= json_encode($pfSheets, JSON_UNESCAPED_UNICODE) ?>;
    const RANK_LABELS = { S:'上位ディーラー', A:'標準ディーラー', B:'新規開拓・直販' };
    const LABEL_MAP = { '販売価格':'販売', '①月額':'短期(1-3M)', '②月額':'中期(3-6M)', '③月額':'長期(6M+)' };
    const COLS = ['販売', '短期(1-3M)', '中期(3-6M)', '長期(6M+)'];
    let rank = (function(){ try { const r=new URLSearchParams(location.search).get('rank'); return /^[SAB]$/.test(r||'')?r:'A'; } catch(e){ return 'A'; } })();
    let cat = '';

    // フラット化（②: 全シートの行を1配列に）
    const VARIANTS = [];
    SHEETS.forEach(s => {
        s.rows.forEach(r => {
            const attr = {};
            (r.attrs||[]).forEach(a => { attr[a.label] = a.value; });
            VARIANTS.push({
                cat: s.title,
                name: r.name,
                model: attr['型番'] || attr['型式'] || '',
                size: attr['インチ数'] ? (attr['インチ数']+'"') : (attr['画面サイズ']||attr['サイズ']||attr['仕様1']||''),
                attrs: r.attrs||[],
                prices: r.prices||[],
                notes: r.notes,
                hay: (r.name+' '+Object.values(attr).join(' ')).toLowerCase()
            });
        });
    });

    function yen(v){ return (v==null||v==='')?'—':'¥'+Number(v).toLocaleString('ja-JP'); }
    function priceFor(v, g, sysLabel){
        const p = v.prices.find(x => (x.group===g) && LABEL_MAP[x.label]===sysLabel);
        if (p) return p.amount;
        if (sysLabel==='販売') { const f=v.prices.find(x=>!x.group); if(f) return f.amount; }
        return null;
    }
    function hasRank(v, g){ return v.prices.some(p=>p.group===g); }
    function attrText(attrs){ return (attrs||[]).map(a=>a.label+': '+a.value).filter(Boolean).join(' ／ '); }

    function renderChips(){
        const cats = [...new Set(VARIANTS.map(v=>v.cat))];
        document.getElementById('pfChips').innerHTML =
            '<span class="pf-chip '+(cat===''?'active':'')+'" data-cat="">全</span>' +
            cats.map(c=>'<span class="pf-chip '+(cat===c?'active':'')+'" data-cat="'+escapeHtml(c)+'">'+escapeHtml(c)+'</span>').join('');
    }

    function render(){
        const q = document.getElementById('pfSearch').value.trim().toLowerCase();
        const terms = q.split(/\s+/).filter(Boolean);
        let list = VARIANTS.filter(v => (!cat || v.cat===cat) && terms.every(t => v.hay.indexOf(t) >= 0));
        document.getElementById('pfCount').textContent = list.length + '件';
        const result = document.getElementById('pfResult');
        if (!list.length){ result.innerHTML = '<div class="pf-empty">該当なし。検索語を変えてみてください</div>'; return; }
        const capped = list.slice(0, 300);
        const head = '<tr><th>製品 / 型番</th><th>サイズ</th>' +
            COLS.map((c,i)=>'<th class="num'+(i===0?' primary':'')+'">'+c+'</th>').join('') + '<th></th></tr>';
        const body = capped.map((v,i)=>{
            const cells = COLS.map((c,ci)=>'<td class="num'+(ci===0?' primary':'')+'">'+yen(priceFor(v,rank,c))+'</td>').join('');
            const dim = (hasRank(v,rank)||v.prices.some(p=>!p.group)) ? '' : ' style="opacity:.5"';
            return '<tr class="pf-trow" data-i="'+i+'"'+dim+'>' +
                '<td><div class="pf-prod">'+escapeHtml(v.model||v.name)+'</div><div class="pf-cat">'+escapeHtml(v.cat)+'</div></td>' +
                '<td>'+escapeHtml(v.size||'—')+'</td>'+cells+
                '<td><button type="button" class="pf-copybtn" data-copy="'+i+'">コピー</button></td></tr>';
        }).join('');
        result.innerHTML = '<table class="pf-table"><thead>'+head+'</thead><tbody>'+body+'</tbody></table>' +
            (list.length>capped.length ? '<div class="pf-empty">他 '+(list.length-capped.length)+' 件（絞り込んでください）</div>' : '');
        result.querySelectorAll('.pf-trow').forEach(tr=>{
            tr.addEventListener('click', e=>{ if(e.target.closest('.pf-copybtn'))return; toggleExpand(tr, capped[+tr.dataset.i]); });
        });
        result.querySelectorAll('.pf-copybtn').forEach(b=>{
            b.addEventListener('click', ()=>{ const v=capped[+b.dataset.copy]; const txt=(v.model||v.name)+(v.size?' '+v.size:'')+' / '+rank+'層 販売 '+yen(priceFor(v,rank,'販売')); navigator.clipboard.writeText(txt).then(()=>showToast('コピー: '+txt,'success')); });
        });
    }

    // ③: 行はその場で展開（画面遷移しない＝戻ると1から、が起きない）
    function toggleExpand(tr, v){
        const next = tr.nextElementSibling;
        if (next && next.classList.contains('pf-expand')){ next.remove(); return; }
        const ranks = ['S','A','B'].filter(g=>hasRank(v,g));
        let blocks;
        if (ranks.length) {
            blocks = ranks.map(g=>{
                const lines = COLS.map(c=>{ const a=priceFor(v,g,c); return a==null?'':'<div class="pf-tier-line"><span class="t">'+c+'</span>'+yen(a)+'</div>'; }).join('');
                return '<div class="pf-detail-block"><h5 style="color:'+({S:'#7c3aed',A:'#2563eb',B:'#059669'}[g])+'">'+g+'：'+RANK_LABELS[g]+'</h5>'+lines+'</div>';
            }).join('');
        } else {
            blocks = '<div class="pf-detail-block"><h5>価格</h5>'+(v.prices.map(p=>'<div class="pf-tier-line"><span class="t">'+escapeHtml(p.label||'価格')+'</span>'+yen(p.amount)+'</div>').join(''))+'</div>';
        }
        const notes = v.notes ? '<div class="pf-attrs" style="margin-top:0.4rem;">備考: '+escapeHtml(v.notes)+'</div>' : '';
        const row = document.createElement('tr');
        row.className = 'pf-expand';
        row.innerHTML = '<td colspan="7"><div class="pf-detail-grid">'+blocks+'</div><div class="pf-attrs" style="margin-top:0.5rem;">'+escapeHtml(attrText(v.attrs))+'</div>'+notes+'</td>';
        tr.after(row);
    }

    function syncRankBtns(){ document.querySelectorAll('.pf-rank-btn').forEach(b=>b.classList.toggle('active', b.dataset.rank===rank)); }
    document.getElementById('pfRankToggle').addEventListener('click', e=>{ const b=e.target.closest('.pf-rank-btn'); if(!b)return; rank=b.dataset.rank; syncRankBtns(); render(); });
    document.getElementById('pfChips').addEventListener('click', e=>{ const c=e.target.closest('.pf-chip'); if(!c)return; cat=c.dataset.cat; renderChips(); render(); });
    let t; document.getElementById('pfSearch').addEventListener('input', ()=>{ clearTimeout(t); t=setTimeout(render,180); });

    function pfInit(){ syncRankBtns(); renderChips(); render(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', pfInit); else pfInit();
})();
</script>

<?php require_once '../functions/footer.php'; ?>
