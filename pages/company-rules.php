<?php
/**
 * 社内規則閲覧ページ
 * 閲覧: 全ユーザー（sales 以上）
 * 編集・削除: 管理部（admin）のみ
 */
require_once '../api/auth.php';
require_once '../functions/header.php';

$isAdmin   = isAdmin();
$csrfToken = generateCsrfToken();

// 章マスタ（固定）
$chapterMaster = [
    1  => '総則',
    2  => '採用',
    3  => '服務規律',
    4  => '勤務',
    5  => '休暇・休職',
    6  => '退職・解雇',
    7  => '表彰・懲戒',
    8  => '安全衛生',
    9  => '賃金',
    10 => '教育訓練',
    11 => '災害補償等',
    12 => '企業内人材育成推進機',
];

// 保存済みデータ読み込み
$data  = getData();
$rules = $data['company_rules'] ?? [];

// 削除済み除外 → 章番号をキーにした連想配列に変換
$savedRules = [];
foreach ($rules as $r) {
    if (empty($r['deleted_at'])) {
        $savedRules[(int)$r['chapter_number']] = $r;
    }
}
?>
<style<?= nonceAttr() ?>>
/* ===== レイアウト ===== */
.rules-wrap { display: flex; gap: 0; min-height: calc(100vh - 60px); }

/* 左サイドナビ（完全固定・枠囲み） */
body .main-content:has(.rules-wrap) { overflow-y: visible; overflow: visible; }
.rules-nav {
    width: 220px;
    background: #fff;
    border: 1px solid var(--gray-200); border-radius: 8px;
    padding: 0.75rem 0;
    position: fixed; top: 92px; left: calc(var(--sidebar-width) + 2rem);
    max-height: calc(100vh - 108px); overflow-y: auto;
    z-index: 10;
}
.sidebar.collapsed ~ .main-content .rules-nav { left: calc(var(--sidebar-collapsed-width) + 2rem); }
.rules-nav-title {
    font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em;
    color: var(--gray-400); text-transform: uppercase;
    padding: 0 1rem 0.5rem;
}
.rules-nav-item {
    display: flex; align-items: center; gap: 0.4rem;
    padding: 0.55rem 1rem;
    cursor: pointer; font-size: 0.85rem; color: #374151;
    border-left: 3px solid transparent;
    transition: background 0.1s, border-color 0.1s;
    text-decoration: none;
}
.rules-nav-item:hover { background: var(--gray-50); }
.rules-nav-item.active {
    background: #eff6ff;
    border-left-color: var(--primary);
    color: var(--primary); font-weight: 600;
}
.rules-nav-item .chapter-num {
    font-size: 0.7rem; color: var(--gray-400);
    min-width: 20px;
}
.rules-nav-item .has-content-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--primary); margin-left: auto; flex-shrink: 0;
}

/* メインコンテンツ */
.rules-main {
    flex: 1; min-width: 0;
    padding: 1.5rem 2rem;
    max-width: 860px;
    margin-left: 244px;
}

/* 検索バー */
.rules-search-bar {
    display: flex; gap: 0.5rem; align-items: center;
    margin-bottom: 1.5rem;
}
.rules-search-input {
    flex: 1; padding: 0.55rem 0.85rem;
    border: 1px solid var(--gray-200); border-radius: 8px;
    font-size: 0.9rem; outline: none;
    transition: border-color 0.15s;
}
.rules-search-input:focus { border-color: var(--primary); }
.rules-search-clear {
    background: none; border: none; cursor: pointer;
    color: var(--gray-400); font-size: 1.1rem; padding: 0 0.25rem;
    display: none;
}

/* ページヘッダー */
.rules-page-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;
}
.rules-page-header h2 { font-size: 1.4rem; font-weight: 700; }

/* 章コンテンツ */
.rules-chapter { display: none; }
.rules-chapter.active { display: block; }

.chapter-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 1rem; gap: 1rem;
}
.chapter-title-block {}
.chapter-label { font-size: 0.75rem; color: var(--gray-400); margin-bottom: 0.2rem; }
.chapter-title { font-size: 1.3rem; font-weight: 700; }
.chapter-updated { font-size: 0.75rem; color: var(--gray-400); margin-top: 0.2rem; }

.chapter-content-box {
    background: #fff;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    overflow: hidden;
    min-height: 200px;
}
.chapter-empty {
    color: var(--gray-400); font-style: italic; font-size: 0.9rem;
    text-align: center; padding: 3rem 0;
}
/* 前文（第1条より前のテキスト） */
.article-preamble {
    padding: 1rem 1.25rem;
    font-size: 0.88rem; line-height: 1.85; color: #374151;
    border-bottom: 1px solid var(--gray-100);
    white-space: pre-wrap; word-break: break-word;
}
/* 条アコーディオン */
.article-item { border-bottom: 1px solid var(--gray-100); }
.article-item:last-child { border-bottom: none; }
.article-heading {
    width: 100%; display: flex; justify-content: space-between; align-items: center;
    padding: 0.75rem 1.25rem;
    background: none; border: none; cursor: pointer; text-align: left;
    font-size: 0.88rem; font-weight: 600; color: #1f2937;
    transition: background 0.1s;
    gap: 0.75rem;
}
.article-heading:hover { background: var(--gray-50); }
.article-heading.open   { background: #f0fdf4; color: var(--primary); }
.article-toggle-icon {
    flex-shrink: 0; transition: transform 0.2s; color: var(--gray-400);
}
.article-heading.open .article-toggle-icon { transform: rotate(180deg); }
.article-body {
    padding: 0.75rem 1.5rem 1rem;
    font-size: 0.87rem; line-height: 1.9; color: #374151;
    background: #fafafa;
    white-space: pre-wrap; word-break: break-word;
    border-top: 1px solid var(--gray-100);
}

/* 条内画像 */
.article-img {
    max-width: 100%; height: auto;
    border-radius: 6px; margin: 0.75rem 0;
    display: block; border: 1px solid var(--gray-200);
}

/* 検索ハイライト */
.search-highlight { background: #fef08a; border-radius: 2px; }

/* 検索結果パネル */
.search-results { display: none; }
.search-result-item {
    background: #fff; border: 1px solid var(--gray-200);
    border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 0.75rem;
    cursor: pointer; transition: box-shadow 0.15s;
}
.search-result-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
.search-result-chapter { font-size: 0.75rem; color: var(--gray-400); margin-bottom: 0.3rem; }
.search-result-excerpt { font-size: 0.85rem; color: #374151; line-height: 1.6; }
.search-no-result { text-align: center; color: var(--gray-400); padding: 3rem 0; font-size: 0.9rem; }

/* 編集モーダル */
.rules-edit-modal {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.4); z-index: 1000;
    align-items: center; justify-content: center;
}
.rules-edit-modal.active { display: flex; }
.modal-box {
    background: #fff; border-radius: 12px;
    width: 90vw; max-width: 820px;
    max-height: 90vh; display: flex; flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--gray-200);
}
.modal-head h3 { font-size: 1.05rem; font-weight: 700; }
.modal-close {
    background: none; border: none; cursor: pointer;
    font-size: 1.4rem; color: var(--gray-400); line-height: 1;
    padding: 0 0.25rem;
}
.modal-body { padding: 1.25rem 1.5rem; overflow-y: auto; flex: 1; }
.modal-foot {
    padding: 1rem 1.5rem; border-top: 1px solid var(--gray-200);
    display: flex; justify-content: flex-end; gap: 0.75rem;
}
.form-label { font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 0.35rem; }
.form-group { margin-bottom: 1rem; }
textarea.form-input { min-height: 360px; resize: vertical; font-family: inherit; line-height: 1.8; }

@media (max-width: 700px) {
    .rules-wrap { flex-direction: column; }
    .rules-nav { width: 100%; height: auto; position: static; border-right: none; border-bottom: 1px solid var(--gray-200); display: flex; flex-wrap: wrap; padding: 0.5rem; gap: 0.25rem; }
    .rules-nav-title { display: none; }
    .rules-nav-item { border-left: none; border-bottom: 3px solid transparent; padding: 0.4rem 0.6rem; font-size: 0.78rem; }
    .rules-nav-item.active { border-bottom-color: var(--primary); background: none; }
    .rules-main { padding: 1rem; }
}
</style>

<div class="rules-wrap">
    <!-- 左ナビ -->
    <nav class="rules-nav" id="rulesNav">
        <div class="rules-nav-title">章一覧</div>
        <?php foreach ($chapterMaster as $num => $title): ?>
        <a class="rules-nav-item <?= $num === 1 ? 'active' : '' ?>" data-chapter="<?= $num ?>" href="#" id="navItem<?= $num ?>">
            <span class="chapter-num"><?= $num ?></span>
            <span><?= htmlspecialchars($title) ?></span>
            <?php if (isset($savedRules[$num]) && $savedRules[$num]['content'] !== ''): ?>
            <span class="has-content-dot"></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- メイン -->
    <div class="rules-main">
        <div class="rules-page-header">
            <h2>社内規則</h2>

        </div>

        <!-- 検索バー -->
        <div class="rules-search-bar">
            <input type="text" id="rulesSearchInput" class="rules-search-input" placeholder="キーワードで検索（全章対象）…" autocomplete="off">
            <button type="button" id="rulesSearchClear" class="rules-search-clear" title="クリア">×</button>
        </div>

        <!-- 検索結果 -->
        <div id="searchResultsPanel" class="search-results"></div>

        <!-- 章コンテンツ -->
        <div id="chaptersPanel">
            <?php foreach ($chapterMaster as $num => $title): ?>
            <div class="rules-chapter <?= $num === 1 ? 'active' : '' ?>" id="chapter<?= $num ?>">
                <div class="chapter-header">
                    <div class="chapter-title-block">
                        <div class="chapter-label">第<?= $num ?>章</div>
                        <div class="chapter-title"><?= htmlspecialchars($title) ?></div>
                        <?php if (isset($savedRules[$num])): ?>
                        <div class="chapter-updated">最終更新: <?= htmlspecialchars(substr($savedRules[$num]['updated_at'] ?? '', 0, 16)) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($isAdmin): ?>
                    <button type="button" class="btn btn-sm btn-outline edit-chapter-btn"
                        data-chapter-num="<?= $num ?>"
                        data-chapter-title="<?= htmlspecialchars($title) ?>"
                        data-rule-id="<?= htmlspecialchars($savedRules[$num]['id'] ?? '') ?>"
                        data-content="<?= htmlspecialchars($savedRules[$num]['content'] ?? '') ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        編集
                    </button>
                    <?php endif; ?>
                </div>

                <div class="chapter-content-box" id="chapterContent<?= $num ?>"></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- 編集モーダル -->
<div class="rules-edit-modal" id="editModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="editModalTitle">章を編集</h3>
            <button type="button" class="modal-close" id="editModalClose">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editRuleId">
            <input type="hidden" id="editChapterNum">
            <div class="form-group">
                <div class="form-label">章タイトル</div>
                <input type="text" id="editChapterTitle" class="form-input" readonly>
            </div>
            <div class="form-group">
                <div class="form-label" style="display:flex;justify-content:space-between;align-items:center;">
                    本文
                    <button type="button" id="insertImageBtn" class="btn btn-sm btn-outline" style="font-size:0.78rem;padding:0.25rem 0.6rem;">
                        📷 画像を挿入
                    </button>
                </div>
                <input type="file" id="imageFileInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                <div style="font-size:0.75rem;color:var(--gray-400);margin-bottom:0.4rem;">
                    画像を挿入したい箇所にカーソルを置いてから「画像を挿入」を押してください。<code style="background:#f3f4f6;padding:0 3px;border-radius:3px;">[IMG:ファイル名]</code> が自動挿入されます。
                </div>
                <textarea id="editContent" class="form-input" placeholder="本文を入力（条文テキストをそのまま貼り付けOK）"></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-outline" id="editModalCancel">キャンセル</button>
            <button type="button" class="btn btn-primary" id="editSaveBtn">保存</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script<?= nonceAttr() ?>>
const CSRF_TOKEN  = <?= json_encode($csrfToken) ?>;
const IS_ADMIN    = <?= $isAdmin ? 'true' : 'false' ?>;

// ===== ナビ切り替え =====
const navItems    = document.querySelectorAll('.rules-nav-item');
const chapters    = document.querySelectorAll('.rules-chapter');

function showChapter(num) {
    navItems.forEach(n => n.classList.toggle('active', n.dataset.chapter == num));
    chapters.forEach(c => c.classList.toggle('active', c.id === 'chapter' + num));
    // 検索パネルを隠す
    document.getElementById('searchResultsPanel').style.display = 'none';
    document.getElementById('chaptersPanel').style.display = '';
}

navItems.forEach(item => {
    item.addEventListener('click', e => {
        e.preventDefault();
        showChapter(item.dataset.chapter);
    });
});

// ===== 検索 =====
const searchInput  = document.getElementById('rulesSearchInput');
const searchClear  = document.getElementById('rulesSearchClear');
const searchPanel  = document.getElementById('searchResultsPanel');
const chaptersPanel = document.getElementById('chaptersPanel');

// 各章の本文データ（PHP から埋め込み）
const chapterData = <?php
    $jsData = [];
    foreach ($chapterMaster as $num => $title) {
        $jsData[] = [
            'num'     => $num,
            'title'   => $title,
            'content' => $savedRules[$num]['content'] ?? '',
        ];
    }
    echo json_encode($jsData, JSON_UNESCAPED_UNICODE);
?>;

function escapeHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function highlightText(text, keyword) {
    if (!keyword) return escapeHtml(text);
    const re = new RegExp('(' + keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    return escapeHtml(text).replace(re, '<mark class="search-highlight">$1</mark>');
}

function getExcerpt(text, keyword, len = 120) {
    const idx = text.toLowerCase().indexOf(keyword.toLowerCase());
    if (idx === -1) return text.slice(0, len) + (text.length > len ? '…' : '');
    const start = Math.max(0, idx - 30);
    const end   = Math.min(text.length, idx + len);
    return (start > 0 ? '…' : '') + text.slice(start, end) + (end < text.length ? '…' : '');
}

// ===== 条パース =====
// 「第○条」または「N. （タイトル）」で始まる行を区切りとして分割
// 戻り値: [{ extractedNum, titlePart, body[] }, ...] + _preamble プロパティ
function parseArticles(content) {
    const lines        = content.split('\n');
    const articles     = [];
    let current        = null;
    let preambleLines  = [];

    for (const line of lines) {
        const trimmed = line.trim();
        // パターン1: 「第○条（タイトル）」または「第○条　タイトル」
        const m1 = trimmed.match(/^第\s*([0-9０-９]+)\s*条\s*(.*)/);
        // パターン2: 「N. （タイトル）」形式（Google Docs エクスポート）
        const m2 = !m1 && trimmed.match(/^(\d+)\.\s+([（(].*)/);

        if (m1 || m2) {
            if (current) articles.push(current);
            if (m1) {
                // 全角数字を半角に変換
                const numStr = m1[1].replace(/[０-９]/g, d => String('０１２３４５６７８９'.indexOf(d)));
                current = { extractedNum: parseInt(numStr, 10), titlePart: m1[2].trim(), body: [] };
            } else {
                current = { extractedNum: parseInt(m2[1], 10), titlePart: m2[2].trim(), body: [] };
            }
        } else if (current) {
            current.body.push(line);
        } else {
            if (trimmed) preambleLines.push(line);
        }
    }
    if (current) articles.push(current);
    articles._preamble = preambleLines.length ? preambleLines : null;
    return articles;
}

// ===== 本文を HTML に変換（[IMG:ファイル名] を img タグに展開） =====
function renderBodyWithImages(text) {
    const parts = text.split(/\[IMG:([^\]]+)\]/g);
    let html = '';
    for (let i = 0; i < parts.length; i++) {
        if (i % 2 === 0) {
            html += escapeHtml(parts[i]);
        } else {
            // ファイル名のサニタイズ（パストラバーサル防止）
            const fname = parts[i].replace(/[^a-zA-Z0-9._-]/g, '');
            html += `<img class="article-img" src="/uploads/rules/${escapeHtml(fname)}" alt="添付画像">`;
        }
    }
    return html;
}

// ===== 章コンテンツをアコーディオンでレンダリング =====
function renderChapterContent(num, content, isAdmin) {
    const box = document.getElementById('chapterContent' + num);
    if (!box) return;

    if (!content || !content.trim()) {
        box.innerHTML = `<div class="chapter-empty">${isAdmin ? 'まだ内容が登録されていません。編集ボタンから本文を入力してください。' : '内容は準備中です。'}</div>`;
        return;
    }

    const articles = parseArticles(content);

    if (articles.length === 0) {
        // 条が1つも見つからない → プレーンテキスト表示
        box.style.padding    = '1.25rem';
        box.style.whiteSpace = 'pre-wrap';
        box.style.fontSize   = '0.9rem';
        box.style.lineHeight = '1.85';
        box.textContent = content;
        return;
    }

    // 前文（第1条より前）
    let html = '';
    if (articles._preamble && articles._preamble.length) {
        html += `<div class="article-preamble">${escapeHtml(articles._preamble.join('\n'))}</div>`;
    }

    // 自動採番: 前章までの条数合計 + 1 を開始番号とする（章をまたいで連番）
    const startNum = (chapterArticleOffset[num] || 0) + 1;

    html += articles.map((art, i) => {
        const displayNum = startNum + i;
        const heading    = `第${displayNum}条 ${art.titlePart}`.trim();
        const bodyText   = art.body.join('\n').trim();
        return `
        <div class="article-item">
            <button type="button" class="article-heading" data-idx="${i}">
                <span>${escapeHtml(heading)}</span>
                <svg class="article-toggle-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="article-body" style="display:none;">${renderBodyWithImages(bodyText)}</div>
        </div>`;
    }).join('');

    box.innerHTML = html;

    // アコーディオン開閉
    box.querySelectorAll('.article-heading').forEach(btn => {
        btn.addEventListener('click', () => {
            const body = btn.nextElementSibling;
            const isOpen = body.style.display !== 'none';
            body.style.display = isOpen ? 'none' : 'block';
            btn.classList.toggle('open', !isOpen);
        });
    });
}

// ===== 章ごとの条開始番号を事前計算（全章の条数を累積） =====
// 章をまたいで連番になるため、前の章の条数を足した値が各章のオフセット
const chapterArticleOffset = {};
let _cumulative = 0;
chapterData.forEach(ch => {
    chapterArticleOffset[ch.num] = _cumulative;
    _cumulative += parseArticles(ch.content).length;
});

// 全章レンダリング
const IS_ADMIN_BOOL = <?= $isAdmin ? 'true' : 'false' ?>;
chapterData.forEach(ch => renderChapterContent(ch.num, ch.content, IS_ADMIN_BOOL));

// ===== 検索（条単位でヒット） =====
function doSearch(keyword) {
    keyword = keyword.trim();
    searchClear.style.display = keyword ? 'block' : 'none';

    if (!keyword) {
        searchPanel.style.display = 'none';
        chaptersPanel.style.display = '';
        return;
    }

    searchPanel.style.display = 'block';
    chaptersPanel.style.display = 'none';

    const kw = keyword.toLowerCase();
    const hits = []; // { chapterNum, chapterTitle, articleHeading, excerpt }

    chapterData.forEach(ch => {
        if (!ch.content) return;
        const articles = parseArticles(ch.content);

        // 前文ヒット
        if (articles._preamble) {
            const text = articles._preamble.join('\n');
            if (text.toLowerCase().includes(kw)) {
                hits.push({ num: ch.num, title: ch.title, heading: '（前文）', excerpt: getExcerpt(text, keyword) });
            }
        }

        const startNum = (chapterArticleOffset[ch.num] || 0) + 1;
        articles.forEach((art, i) => {
            const displayNum = startNum + i;
            const heading    = `第${displayNum}条 ${art.titlePart}`.trim();
            const full       = heading + '\n' + art.body.join('\n');
            if (full.toLowerCase().includes(kw)) {
                hits.push({ num: ch.num, title: ch.title, heading, excerpt: getExcerpt(full, keyword) });
            }
        });
    });

    if (hits.length === 0) {
        searchPanel.innerHTML = '<div class="search-no-result">「' + escapeHtml(keyword) + '」に一致する内容は見つかりませんでした。</div>';
        return;
    }

    searchPanel.innerHTML = hits.map(h => `
        <div class="search-result-item" data-chapter="${h.num}">
            <div class="search-result-chapter">第${h.num}章 ${escapeHtml(h.title)} › ${escapeHtml(h.heading)}</div>
            <div class="search-result-excerpt">${highlightText(h.excerpt, keyword)}</div>
        </div>
    `).join('');

    searchPanel.querySelectorAll('.search-result-item').forEach(el => {
        el.addEventListener('click', () => {
            searchInput.value = '';
            searchClear.style.display = 'none';
            searchPanel.style.display = 'none';
            chaptersPanel.style.display = '';
            showChapter(el.dataset.chapter);
        });
    });
}

searchInput.addEventListener('input', () => doSearch(searchInput.value));
searchClear.addEventListener('click', () => {
    searchInput.value = '';
    doSearch('');
    searchInput.focus();
});

<?php if ($isAdmin): ?>
// ===== 編集モーダル =====
const editModal    = document.getElementById('editModal');
const editModalTitle = document.getElementById('editModalTitle');
const editRuleId   = document.getElementById('editRuleId');
const editChapterNum = document.getElementById('editChapterNum');
const editChapterTitle = document.getElementById('editChapterTitle');
const editContent  = document.getElementById('editContent');
const editSaveBtn  = document.getElementById('editSaveBtn');

function openEditModal(btn) {
    editModalTitle.textContent = '第' + btn.dataset.chapterNum + '章を編集';
    editRuleId.value       = btn.dataset.ruleId || '';
    editChapterNum.value   = btn.dataset.chapterNum;
    editChapterTitle.value = btn.dataset.chapterTitle;
    editContent.value      = btn.dataset.content || '';
    editModal.classList.add('active');
    setTimeout(() => editContent.focus(), 100);
}

document.querySelectorAll('.edit-chapter-btn').forEach(btn => {
    btn.addEventListener('click', () => openEditModal(btn));
});

function closeEditModal() { editModal.classList.remove('active'); }
document.getElementById('editModalClose').addEventListener('click', closeEditModal);
document.getElementById('editModalCancel').addEventListener('click', closeEditModal);
// 背景クリックでは閉じない（×ボタン・キャンセルのみ）

// ===== 画像アップロード =====
const insertImageBtn = document.getElementById('insertImageBtn');
const imageFileInput = document.getElementById('imageFileInput');

insertImageBtn.addEventListener('click', () => imageFileInput.click());

imageFileInput.addEventListener('change', async () => {
    const file = imageFileInput.files[0];
    if (!file) return;

    insertImageBtn.disabled    = true;
    insertImageBtn.textContent = 'アップロード中…';

    try {
        const formData = new FormData();
        formData.append('image', file);

        const res  = await fetch('/api/rules-image-upload.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            body: formData,
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'アップロード失敗');

        // カーソル位置にマーカーを挿入
        const ta     = editContent;
        const pos    = ta.selectionStart;
        const marker = `[IMG:${json.filename}]`;
        ta.value = ta.value.slice(0, pos) + marker + ta.value.slice(pos);
        ta.selectionStart = ta.selectionEnd = pos + marker.length;
        ta.focus();
    } catch (err) {
        alert('画像アップロードエラー: ' + err.message);
    } finally {
        insertImageBtn.disabled    = false;
        insertImageBtn.textContent = '📷 画像を挿入';
        imageFileInput.value       = '';
    }
});

editSaveBtn.addEventListener('click', async () => {
    const chapterNum = parseInt(editChapterNum.value);
    const content    = editContent.value;
    const ruleId     = editRuleId.value;

    editSaveBtn.disabled = true;
    editSaveBtn.textContent = '保存中…';

    try {
        const res = await fetch('/api/company-rules.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({
                action: 'save',
                id: ruleId,
                chapter_number: chapterNum,
                chapter_title: editChapterTitle.value,
                content: content,
            }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || '保存失敗');

        // 画面を更新（リロード）
        location.reload();
    } catch (err) {
        alert('エラー: ' + err.message);
        editSaveBtn.disabled = false;
        editSaveBtn.textContent = '保存';
    }
});
<?php endif; ?>
</script>
