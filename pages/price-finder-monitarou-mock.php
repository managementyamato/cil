<?php
/**
 * 【モック】モニたろう 製品表（ランク選択＋バリアント一覧）
 *
 * 価格表作り替えの第一弾。モニたろう1製品に絞った価格表ビュー。
 *   - 上部でランク(S/A/B)を選ぶ → 型番/サイズごとに 販売/短期/中期/長期 を一覧
 *   - 行クリックで全ランド(S/A/B)を その場で展開（画面遷移しない）
 *   - データは data/product-prices.json のモニたろう系シート（重複はdisplay_nameで排除）
 *
 * 本番にはデプロイしない（*-mock.php は auto-deploy で除外）。閲覧: sales 以上。
 */
require_once '../api/auth.php';
require_once '../functions/header.php';

// モニたろう系シートからバリアントを収集（display_name で重複排除）
$pfRaw = @file_get_contents(__DIR__ . '/../data/product-prices.json');
$pfData = $pfRaw ? json_decode($pfRaw, true) : null;
$variants = [];
$seen = [];
$verPrefix = '/^(モニたろう|miniモニたろう)/u';
if (is_array($pfData) && !empty($pfData['sheets'])) {
    foreach ($pfData['sheets'] as $s) {
        $title = $s['title'] ?? '';
        if (!preg_match($verPrefix, $title)) continue;       // モニたろう系のみ
        foreach ($s['normalized']['rows'] ?? [] as $r) {
            $dn = $r['display_name'] ?? '';
            if ($dn === '' || isset($seen[$dn])) continue;    // 重複排除
            $seen[$dn] = true;
            $attr = [];
            foreach ($r['attributes'] ?? [] as $a) { $attr[$a['label']] = $a['value']; }
            $variants[] = [
                'name'    => $dn,
                'series'  => $attr['製品シリーズ'] ?? ($attr['型番'] ?? ''),
                'inch'    => $attr['インチ数'] ?? '',
                'screen'  => $attr['画面サイズ'] ?? '',
                'sqm'     => $attr['平米数'] ?? '',
                'mini'    => (strpos($title, 'mini') !== false || strpos($dn, 'mini') !== false),
                'prices'  => $r['prices'] ?? [],
                'notes'   => $r['notes'] ?? null,
            ];
        }
    }
}
// インチ昇順 → シリーズ名 で並べ替え
usort($variants, function($a, $b){
    $ia = (float)preg_replace('/[^\d.]/', '', (string)$a['inch']);
    $ib = (float)preg_replace('/[^\d.]/', '', (string)$b['inch']);
    if ($ia !== $ib) return $ia <=> $ib;
    return strcmp($a['series'], $b['series']);
});
?>
<style<?= nonceAttr() ?>>
.mt-wrap { max-width: 1080px; margin: 0 auto; }
.mt-note { background:#fffbeb; border:1px solid #fde68a; color:#92400e; border-radius:8px; padding:0.5rem 0.8rem; font-size:0.82rem; margin-bottom:1rem; }
.mt-bar { position: sticky; top: 0; z-index: 5; background:#fff; border:1px solid var(--gray-200); border-radius:12px; padding:0.9rem 1rem; margin-bottom:1rem; display:flex; align-items:center; gap:0.8rem; flex-wrap:wrap; box-shadow:0 1px 4px rgba(0,0,0,0.04); }
.mt-bar .lbl { font-size:0.82rem; color:var(--gray-500); }
.mt-rank-toggle { display:inline-flex; border:1px solid var(--gray-300); border-radius:8px; overflow:hidden; }
.mt-rank-btn { padding:0.5rem 1rem; background:#fff; border:none; cursor:pointer; font-size:0.88rem; color:var(--gray-700); border-right:1px solid var(--gray-200); }
.mt-rank-btn:last-child { border-right:none; }
.mt-rank-btn.active { color:#fff; font-weight:600; }
.mt-rank-btn.active[data-rank=S]{ background:#7c3aed; } .mt-rank-btn.active[data-rank=A]{ background:#2563eb; } .mt-rank-btn.active[data-rank=B]{ background:#059669; }
.mt-search input { padding:0.45rem 0.8rem; }
.mt-count { margin-left:auto; color:var(--gray-500); font-size:0.8rem; }

.mt-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--gray-200); border-radius:10px; overflow:hidden; }
.mt-table th, .mt-table td { padding:0.55rem 0.8rem; border-bottom:1px solid var(--gray-100); font-size:0.9rem; }
.mt-table th { background:var(--gray-50); color:var(--gray-500); font-size:0.72rem; text-align:left; position:sticky; top:70px; z-index:1; }
.mt-table td.num, .mt-table th.num { text-align:right; font-variant-numeric:tabular-nums; }
.mt-table th.num.primary, .mt-table td.num.primary { background:rgba(37,99,235,0.06); font-weight:600; }
.mt-series { font-weight:600; }
.mt-mini { font-size:0.68rem; color:#7c3aed; background:#ede9fe; border-radius:4px; padding:0 0.3rem; margin-left:0.3rem; vertical-align:middle; }
.mt-group td { background:var(--gray-100); font-weight:700; color:var(--gray-700); font-size:0.85rem; border-top:2px solid var(--gray-200); }
.mt-group .cnt { font-weight:400; color:var(--gray-500); font-size:0.78rem; margin-left:0.5rem; }
.mt-trow { cursor:pointer; }
.mt-trow:hover { background:var(--gray-50); }
.mt-expand td { background:var(--gray-50); }
.mt-detail { display:flex; flex-wrap:wrap; gap:1.4rem; align-items:flex-start; }
.mt-detail h5 { margin:0 0 0.3rem; font-size:0.72rem; }
.mt-line { font-size:0.82rem; } .mt-line .t { display:inline-block; min-width:5.5rem; color:var(--gray-500); }
.mt-attrs { font-size:0.8rem; color:var(--gray-600); margin-top:0.5rem; }
.mt-empty { text-align:center; color:var(--gray-400); padding:2rem; }
</style>

<div class="page-container mt-wrap">
    <div class="page-header">
        <div>
            <h2>モニたろう 製品表 <span style="font-size:0.7rem; color:#92400e; background:#fef3c7; border-radius:6px; padding:0.1rem 0.5rem; vertical-align:middle;">モック</span></h2>
            <div style="color:var(--gray-600); font-size:0.85rem; margin-top:0.2rem;">ランクを選ぶと、型番・サイズごとの単価が一覧で出ます。行をクリックすると S/A/B 全層を展開。</div>
        </div>
    </div>
    <div class="mt-note">モニたろう1製品の試作です（本番には出しません）。これでOKなら他製品にも展開します。</div>

    <div class="mt-bar">
        <span class="lbl">顧客ランク</span>
        <div class="mt-rank-toggle" id="mtRank">
            <button type="button" class="mt-rank-btn" data-rank="S">S 上位ディーラー</button>
            <button type="button" class="mt-rank-btn active" data-rank="A">A 標準ディーラー</button>
            <button type="button" class="mt-rank-btn" data-rank="B">B 新規・直販</button>
        </div>
        <span class="mt-search"><input type="text" id="mtSearch" class="form-input" placeholder="型番・サイズで絞り込み（例: UTM / 158）" autocomplete="off"></span>
        <span class="mt-count" id="mtCount"></span>
    </div>

    <div id="mtResult"><div class="mt-empty">読み込み中…</div></div>
</div>

<script<?= nonceAttr() ?>>
(function() {
    const VARIANTS = <?= json_encode($variants, JSON_UNESCAPED_UNICODE) ?>;
    const RANK_LABELS = { S:'上位ディーラー', A:'標準ディーラー', B:'新規開拓・直販' };
    const LABEL_MAP = { '販売価格':'販売', '①月額':'短期(1-3M)', '②月額':'中期(3-6M)', '③月額':'長期(6M+)' };
    const COLS = ['販売', '短期(1-3M)', '中期(3-6M)', '長期(6M+)'];
    let rank = (function(){ try { const r=new URLSearchParams(location.search).get('rank'); return /^[SAB]$/.test(r||'')?r:'A'; } catch(e){ return 'A'; } })();

    VARIANTS.forEach(v => { v.hay = ((v.series||'')+' '+(v.inch||'')+' '+(v.screen||'')+' '+(v.name||'')).toLowerCase(); });

    function yen(x){ return (x==null||x==='')?'—':'¥'+Number(x).toLocaleString('ja-JP'); }
    function priceFor(v, g, sysLabel){ const p=v.prices.find(x=>x.group===g && LABEL_MAP[x.label]===sysLabel); return p?p.amount:null; }
    function hasRank(v, g){ return v.prices.some(p=>p.group===g); }
    function sizeText(v){ return (v.inch?v.inch+'"':'') + (v.screen?'（'+v.screen+'）':''); }

    function render(){
        const q = document.getElementById('mtSearch').value.trim().toLowerCase();
        const terms = q.split(/\s+/).filter(Boolean);
        const list = VARIANTS.filter(v => terms.every(t => v.hay.indexOf(t)>=0));
        document.getElementById('mtCount').textContent = list.length + '件';
        const el = document.getElementById('mtResult');
        if (!list.length){ el.innerHTML = '<div class="mt-empty">該当なし</div>'; return; }
        // インチでグループ化（昇順、未定/miniは末尾）
        const groups = [];
        const gmap = {};
        list.forEach((v, i) => {
            v._i = i;
            const inchNum = parseFloat(String(v.inch).replace(/[^\d.]/g, ''));
            const key = (v.inch && !isNaN(inchNum)) ? String(inchNum) : '__other__';
            if (!gmap[key]) { gmap[key] = { key, inchNum: isNaN(inchNum) ? Infinity : inchNum, label: (v.inch && !isNaN(inchNum)) ? (v.inch + 'インチ') : '個別見積（mini等）', items: [] }; groups.push(gmap[key]); }
            gmap[key].items.push(v);
        });
        groups.sort((a, b) => a.inchNum - b.inchNum);

        const head = '<tr><th>製品（シリーズ）</th><th>画面サイズ</th>' + COLS.map((c,i)=>'<th class="num'+(i===0?' primary':'')+'">'+c+'</th>').join('') + '</tr>';
        let body = '';
        groups.forEach(g => {
            body += '<tr class="mt-group"><td colspan="6">' + escapeHtml(g.label) + '<span class="cnt">' + g.items.length + 'タイプ</span></td></tr>';
            g.items.forEach(v => {
                const cells = COLS.map((c,ci)=>'<td class="num'+(ci===0?' primary':'')+'">'+yen(priceFor(v,rank,c))+'</td>').join('');
                const dim = hasRank(v,rank) ? '' : ' style="opacity:.5"';
                body += '<tr class="mt-trow" data-i="'+v._i+'"'+dim+'>' +
                    '<td><span class="mt-series">'+escapeHtml(v.series||v.name)+'</span>'+(v.mini?'<span class="mt-mini">mini</span>':'')+'</td>' +
                    '<td>'+escapeHtml(v.screen||'—')+'</td>'+cells+'</tr>';
            });
        });
        el.innerHTML = '<table class="mt-table"><thead>'+head+'</thead><tbody>'+body+'</tbody></table>';
        el.querySelectorAll('.mt-trow').forEach(tr=>tr.addEventListener('click', ()=>toggleExpand(tr, list[+tr.dataset.i])));
    }

    function toggleExpand(tr, v){
        const next = tr.nextElementSibling;
        if (next && next.classList.contains('mt-expand')){ next.remove(); return; }
        const ranks = ['S','A','B'].filter(g=>hasRank(v,g));
        const blocks = ranks.map(g=>{
            const lines = COLS.map(c=>{ const a=priceFor(v,g,c); return a==null?'':'<div class="mt-line"><span class="t">'+c+'</span>'+yen(a)+'</div>'; }).join('');
            return '<div><h5 style="color:'+({S:'#7c3aed',A:'#2563eb',B:'#059669'}[g])+'">'+g+'：'+RANK_LABELS[g]+'</h5>'+lines+'</div>';
        }).join('');
        const attrs = [v.screen?'画面 '+v.screen:'', v.sqm?'平米 '+v.sqm:''].filter(Boolean).join(' ／ ');
        const notes = v.notes ? '<div class="mt-attrs">備考: '+escapeHtml(v.notes)+'</div>' : '';
        const row = document.createElement('tr'); row.className = 'mt-expand';
        row.innerHTML = '<td colspan="6"><div class="mt-detail">'+blocks+'</div>'+(attrs?'<div class="mt-attrs">'+escapeHtml(attrs)+'</div>':'')+notes+'</td>';
        tr.after(row);
    }

    document.getElementById('mtRank').addEventListener('click', e=>{ const b=e.target.closest('.mt-rank-btn'); if(!b)return; rank=b.dataset.rank; document.querySelectorAll('.mt-rank-btn').forEach(x=>x.classList.toggle('active', x.dataset.rank===rank)); render(); });
    let t; document.getElementById('mtSearch').addEventListener('input', ()=>{ clearTimeout(t); t=setTimeout(render,150); });

    function init(){ document.querySelectorAll('.mt-rank-btn').forEach(x=>x.classList.toggle('active', x.dataset.rank===rank)); render(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>

<?php require_once '../functions/footer.php'; ?>
