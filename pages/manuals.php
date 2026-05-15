<?php
/**
 * マニュアル一覧（Google スライド等の外部マニュアルへのリンク集）
 *
 * 営業がトラブル対応中に検索キーワードで素早く該当マニュアルを見つけ、
 * クリックするとモーダル内 iframe で Google スライドを表示する。
 *
 * 権限:
 *   - sales:   閲覧・検索
 *   - product: 作成・編集
 *   - admin:   削除
 */
require_once '../api/auth.php';
require_once '../functions/header.php';

$canEditPage   = canEdit();
$canDeletePage = canDelete();
?>
<style<?= nonceAttr() ?>>
.manuals-page { max-width: 1200px; margin: 0 auto; }

.manuals-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1.25rem;
    gap: 1rem;
    flex-wrap: wrap;
}
.manuals-header h2 {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.manuals-header .hint {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-top: 0.2rem;
}

.search-hero {
    background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
    border: 1px solid var(--gray-200);
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.25rem;
}
.search-hero .search-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    background: white;
    border: 1px solid var(--gray-300);
    border-radius: 10px;
    padding: 0.65rem 0.9rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.search-hero .search-row svg { color: var(--gray-400); flex-shrink: 0; }
.search-hero input.search-input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 1rem;
    background: transparent;
    color: var(--gray-900);
}
.search-hero .clear-btn {
    border: none;
    background: var(--gray-100);
    color: var(--gray-500);
    border-radius: 6px;
    padding: 0.2rem 0.5rem;
    cursor: pointer;
    font-size: 0.8rem;
    display: none;
}
.search-hero .clear-btn.visible { display: inline-flex; }
.search-hero .result-count {
    font-size: 0.78rem;
    color: var(--gray-500);
    margin-top: 0.55rem;
}

.filter-section {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
    align-items: flex-start;
}
.filter-block {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 0.6rem 0.8rem;
    flex: 1;
    min-width: 260px;
}
.filter-block-title {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.4rem;
}
.chip-row { display: flex; gap: 0.35rem; flex-wrap: wrap; }
.chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border: 1px solid var(--gray-300);
    background: white;
    border-radius: 16px;
    padding: 0.22rem 0.7rem;
    font-size: 0.78rem;
    cursor: pointer;
    color: var(--gray-700);
    transition: all 0.12s;
}
.chip:hover { background: var(--gray-50); }
.chip.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}
.chip .count { font-size: 0.7rem; color: var(--gray-400); }
.chip.active .count { color: rgba(255,255,255,0.85); }

.manuals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 0.85rem;
}
.manual-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    cursor: pointer;
    transition: box-shadow 0.15s, transform 0.15s, border-color 0.15s;
}
.manual-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    transform: translateY(-2px);
    border-color: var(--primary);
}
.manual-card .card-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0;
    line-height: 1.4;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.manual-card .card-title svg {
    flex-shrink: 0;
    color: var(--gray-400);
}
.manual-card .card-desc {
    font-size: 0.82rem;
    color: var(--gray-600);
    line-height: 1.5;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.manual-card .card-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.2rem;
}
.badge-category {
    display: inline-flex;
    align-items: center;
    background: #e0f2fe;
    color: #075985;
    border-radius: 6px;
    padding: 0.15rem 0.5rem;
    font-size: 0.72rem;
    font-weight: 600;
}
.badge-tag {
    display: inline-flex;
    align-items: center;
    background: var(--gray-100);
    color: var(--gray-600);
    border-radius: 6px;
    padding: 0.12rem 0.45rem;
    font-size: 0.7rem;
}
.badge-restricted {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: #fef3c7;
    color: #92400e;
    border-radius: 6px;
    padding: 0.15rem 0.5rem;
    font-size: 0.72rem;
    font-weight: 600;
}
.badge-restricted-admin {
    background: #fee2e2;
    color: #991b1b;
}

/* ===================== ラジオグループ (公開範囲) ===================== */
.visibility-options {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.visibility-option {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    padding: 0.55rem 0.75rem;
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.12s;
}
.visibility-option:hover { background: var(--gray-50); }
.visibility-option input[type="radio"] {
    margin-top: 0.15rem;
    flex-shrink: 0;
}
.visibility-option.selected {
    border-color: var(--primary);
    background: #eff6ff;
}
.visibility-option .opt-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-900);
    display: block;
}
.visibility-option .opt-desc {
    font-size: 0.72rem;
    color: var(--gray-500);
    margin-top: 0.1rem;
    display: block;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem 2rem;
    color: var(--gray-400);
}
.empty-state svg { margin-bottom: 1rem; opacity: 0.5; }
.empty-state p { font-size: 0.95rem; margin: 0; }

/* ===================== 閲覧モーダル (iframe) ===================== */
.viewer-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9000;
    display: none;
    align-items: stretch;
    justify-content: center;
    padding: 1rem;
}
.viewer-overlay.active { display: flex; }
.viewer-modal {
    background: white;
    border-radius: 14px;
    width: 100%;
    max-width: 1100px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.2);
    display: flex;
    flex-direction: column;
    height: 100%;
    max-height: calc(100vh - 2rem);
}
.viewer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1.1rem;
    border-bottom: 1px solid var(--gray-200);
    gap: 0.5rem;
    flex-shrink: 0;
}
.viewer-header h3 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
    color: var(--gray-900);
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.viewer-header .header-actions {
    display: flex;
    gap: 0.35rem;
    align-items: center;
    flex-shrink: 0;
}
.viewer-iframe-wrap {
    flex: 1;
    overflow: hidden;
    background: var(--gray-100);
}
.viewer-iframe-wrap iframe {
    width: 100%;
    height: 100%;
    border: none;
}
.viewer-fallback {
    padding: 3rem 2rem;
    text-align: center;
    color: var(--gray-500);
}
.viewer-fallback a { color: var(--primary); font-weight: 600; }

/* ===================== 編集モーダル ===================== */
.form-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 9100;
    display: none;
    align-items: flex-start;
    justify-content: center;
    padding: 1.5rem;
    overflow-y: auto;
}
.form-modal-overlay.active { display: flex; }
.form-modal {
    background: white;
    border-radius: 14px;
    width: 100%;
    max-width: 640px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.2);
    display: flex;
    flex-direction: column;
    max-height: 92vh;
}
.form-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.3rem;
    border-bottom: 1px solid var(--gray-200);
    font-weight: 700;
    color: var(--gray-900);
}
.form-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.1rem 1.3rem;
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}
.form-modal-footer {
    padding: 0.85rem 1.3rem;
    border-top: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}
.form-group label {
    display: block;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.3rem;
}
.form-group .help-text {
    font-size: 0.72rem;
    color: var(--gray-500);
    margin-top: 0.25rem;
    line-height: 1.5;
}
.form-group .form-input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 0.55rem 0.75rem;
    border: 1px solid var(--gray-300);
    border-radius: 7px;
    font-size: 0.875rem;
    font-family: inherit;
    box-sizing: border-box;
    background: white;
}
.form-group textarea { resize: vertical; min-height: 70px; }

.tags-input-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    padding: 0.4rem 0.5rem;
    border: 1px solid var(--gray-300);
    border-radius: 7px;
    background: white;
    min-height: 38px;
    align-items: center;
}
.tags-input-wrap .tag-pill {
    background: var(--gray-100);
    color: var(--gray-700);
    border-radius: 4px;
    padding: 0.15rem 0.45rem;
    font-size: 0.78rem;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}
.tags-input-wrap .tag-pill button {
    border: none;
    background: transparent;
    color: var(--gray-500);
    cursor: pointer;
    font-size: 0.85rem;
    line-height: 1;
    padding: 0;
}
.tags-input-wrap input {
    border: none;
    outline: none;
    flex: 1;
    min-width: 120px;
    font-size: 0.85rem;
    padding: 0.2rem 0;
    background: transparent;
}
</style>

<div class="manuals-page">
    <div class="manuals-header">
        <div>
            <h2>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                マニュアル一覧
            </h2>
            <div class="hint">タイトル・症状・キーワードで検索 → クリックでマニュアルを開けます</div>
        </div>
        <?php if ($canEditPage): ?>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            <button class="btn btn-ghost" id="openImportBtn" title="Google スプレッドシートから一括取り込み">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                スプレッドシートから取り込み
            </button>
            <button class="btn btn-primary" id="openCreateBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                マニュアルを追加
            </button>
        </div>
        <?php endif; ?>
    </div>

    <div class="search-hero">
        <div class="search-row">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="searchInput" class="search-input" placeholder="タイトル・症状・キーワードで検索（例: 画面が映らない、音が出ない、再起動）" autocomplete="off">
            <button type="button" class="clear-btn" id="clearBtn">クリア</button>
        </div>
        <div class="result-count" id="resultCount">読み込み中...</div>
    </div>

    <div class="filter-section">
        <div class="filter-block">
            <div class="filter-block-title">カテゴリ</div>
            <div class="chip-row" id="categoryChips">
                <span class="chip active" data-category="">すべて</span>
            </div>
        </div>
        <div class="filter-block">
            <div class="filter-block-title">タグ</div>
            <div class="chip-row" id="tagChips">
                <span class="chip active" data-tag="">すべて</span>
            </div>
        </div>
    </div>

    <div class="manuals-grid" id="manualsGrid">
        <div class="empty-state"><p>読み込み中...</p></div>
    </div>
</div>

<!-- ===================== 閲覧モーダル (iframe) ===================== -->
<div class="viewer-overlay" id="viewerModal">
    <div class="viewer-modal">
        <div class="viewer-header">
            <h3 id="viewerTitle">マニュアル</h3>
            <div class="header-actions">
                <a id="openInTabBtn" class="btn btn-ghost btn-sm" href="#" target="_blank" rel="noopener" title="新しいタブで開く">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    新しいタブで開く
                </a>
                <?php if ($canEditPage): ?>
                <button id="editFromViewerBtn" class="btn btn-ghost btn-sm">編集</button>
                <?php endif; ?>
                <?php if ($canDeletePage): ?>
                <button id="deleteFromViewerBtn" class="btn btn-danger btn-sm">削除</button>
                <?php endif; ?>
                <button id="closeViewerBtn" class="btn btn-ghost" style="padding:0.25rem 0.5rem;">×</button>
            </div>
        </div>
        <div class="viewer-iframe-wrap" id="viewerIframeWrap">
            <iframe id="viewerIframe" src="about:blank" allowfullscreen referrerpolicy="no-referrer"></iframe>
        </div>
    </div>
</div>

<!-- ===================== スプシ取り込みモーダル ===================== -->
<?php if ($canEditPage): ?>
<div class="form-modal-overlay" id="importModal">
    <div class="form-modal" style="max-width:760px;">
        <div class="form-modal-header">
            <span>スプレッドシートから取り込み</span>
            <button class="btn btn-ghost" id="closeImportBtn" style="padding:0.25rem 0.5rem;">×</button>
        </div>
        <div class="form-modal-body">
            <div id="lastSyncBanner" style="display:none; padding:0.6rem 0.85rem; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; font-size:0.82rem; color:#1e3a8a;">
                <span id="lastSyncText"></span>
                <button id="resyncBtn" class="btn btn-primary btn-sm" style="margin-left:0.5rem;">同じ設定で再同期</button>
            </div>

            <div class="form-group">
                <label>スプレッドシート URL <span style="color:var(--danger)">*</span></label>
                <input type="url" id="impSheetUrl" class="form-input" placeholder="https://docs.google.com/spreadsheets/d/.../edit">
                <div class="help-text">Google スプレッドシート全体の URL を貼り付けてください。マニュアル管理者の Google アカウントでアクセス可能なシートが必要です。</div>
            </div>

            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <div class="form-group" style="flex:2; min-width:200px;">
                    <label>シート名（任意）</label>
                    <input type="text" id="impSheetName" class="form-input" placeholder="未指定の場合は先頭シートを使用">
                </div>
                <div class="form-group" style="flex:1; min-width:120px;">
                    <label>リンク列 <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="impLinkCol" class="form-input" value="L" maxlength="3" placeholder="L">
                </div>
                <div class="form-group" style="flex:1; min-width:120px;">
                    <label>目的列（任意）</label>
                    <input type="text" id="impDescCol" class="form-input" value="O" maxlength="3" placeholder="O">
                </div>
            </div>
            <div class="help-text" style="margin-top:-0.4rem;">
                リンク列にはスプシのハイパーリンク付きセル（表示テキスト＋URLのセル）を指定します。表示テキストは最初の半角/全角スペースで「カテゴリ + タイトル」に自動分割されます。スペースが無ければ全部タイトルになります。
            </div>

            <div class="form-group">
                <label>データ開始行</label>
                <input type="number" id="impStartRow" class="form-input" value="2" min="1" max="1000" style="max-width:120px;">
                <div class="help-text">何行目からデータが始まるか（1-indexed）。ヘッダーが3行ある場合は 4 を入力してください。</div>
            </div>

            <div class="form-group">
                <label>公開範囲（取り込むマニュアルすべてに適用）</label>
                <div class="visibility-options">
                    <label class="visibility-option">
                        <input type="radio" name="imp_visibility" value="all" checked>
                        <div>
                            <span class="opt-label">全員に公開</span>
                            <span class="opt-desc">営業部・製品技術部・管理部すべてが閲覧可能</span>
                        </div>
                    </label>
                    <label class="visibility-option">
                        <input type="radio" name="imp_visibility" value="product">
                        <div>
                            <span class="opt-label">製品技術部以上のみ</span>
                            <span class="opt-desc">営業部には表示されません</span>
                        </div>
                    </label>
                    <label class="visibility-option">
                        <input type="radio" name="imp_visibility" value="admin">
                        <div>
                            <span class="opt-label">管理部のみ</span>
                            <span class="opt-desc">管理部のメンバーだけが閲覧可能</span>
                        </div>
                    </label>
                </div>
            </div>

            <div id="impPreviewWrap" style="display:none; margin-top:0.5rem;">
                <div class="filter-block-title">プレビュー（先頭5件 / 取り込み対象 <span id="impPreviewTotal">0</span>件）</div>
                <div id="impPreviewList" style="display:flex; flex-direction:column; gap:0.4rem;"></div>
                <div id="impPreviewErrors" style="font-size:0.78rem; color:var(--gray-500); margin-top:0.4rem;"></div>
            </div>
        </div>
        <div class="form-modal-footer">
            <div id="impError" style="color:var(--danger); font-size:0.82rem; flex:1;"></div>
            <div style="display:flex; gap:0.5rem;">
                <button class="btn btn-ghost" id="impCancelBtn">閉じる</button>
                <button class="btn btn-ghost" id="impPreviewBtn">プレビュー</button>
                <button class="btn btn-primary" id="impRunBtn">取り込み実行</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===================== 編集モーダル ===================== -->
<?php if ($canEditPage): ?>
<div class="form-modal-overlay" id="formModal">
    <div class="form-modal">
        <div class="form-modal-header">
            <span id="formModalTitle">マニュアルを追加</span>
            <button class="btn btn-ghost" id="closeFormBtn" style="padding:0.25rem 0.5rem;">×</button>
        </div>
        <div class="form-modal-body">
            <input type="hidden" id="formManualId">

            <div class="form-group">
                <label>タイトル <span style="color:var(--danger)">*</span></label>
                <input type="text" id="formTitle" class="form-input" placeholder="例: LEDビジョン 画面が映らない時の確認手順" maxlength="200">
            </div>

            <div class="form-group">
                <label>マニュアル URL <span style="color:var(--danger)">*</span></label>
                <input type="url" id="formUrl" class="form-input" placeholder="https://docs.google.com/presentation/d/.../edit">
                <div class="help-text">Google スライド / Google ドキュメント / その他のリンクを貼り付けてください。Google 系は iframe で表示できます。</div>
            </div>

            <div class="form-group">
                <label>概要（カードに表示されます）</label>
                <textarea id="formDescription" placeholder="このマニュアルの内容を簡潔に説明（任意）"></textarea>
            </div>

            <div class="form-group">
                <label>検索キーワード（症状・別名・略語などを自由記述）</label>
                <textarea id="formKeywords" placeholder="例: 画面が真っ黒, ブラックアウト, 映らない, 表示されない, no signal, 起動しない"></textarea>
                <div class="help-text">ここに書いた語句は検索にヒットします。タイトルにない症状や言い回し、略語を入れておくと営業が見つけやすくなります。</div>
            </div>

            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <div class="form-group" style="flex:1; min-width:200px;">
                    <label>カテゴリ</label>
                    <input type="text" id="formCategory" class="form-input" placeholder="例: LEDビジョン / LCD / 音響" list="categoryList">
                    <datalist id="categoryList"></datalist>
                </div>
                <div class="form-group" style="flex:2; min-width:240px;">
                    <label>タグ（Enter で追加）</label>
                    <div class="tags-input-wrap" id="tagsWrap">
                        <input type="text" id="tagInput" placeholder="例: 緊急, 現場対応">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>公開範囲</label>
                <div class="visibility-options" id="visibilityOptions">
                    <label class="visibility-option">
                        <input type="radio" name="visibility" value="all" checked>
                        <div>
                            <span class="opt-label">全員に公開</span>
                            <span class="opt-desc">営業部・製品技術部・管理部すべてが閲覧可能（デフォルト）</span>
                        </div>
                    </label>
                    <label class="visibility-option">
                        <input type="radio" name="visibility" value="product">
                        <div>
                            <span class="opt-label">製品技術部以上のみ</span>
                            <span class="opt-desc">営業部には表示されません。製品技術部と管理部のみ閲覧可能</span>
                        </div>
                    </label>
                    <label class="visibility-option">
                        <input type="radio" name="visibility" value="admin">
                        <div>
                            <span class="opt-label">管理部のみ</span>
                            <span class="opt-desc">管理部のメンバーだけが閲覧可能</span>
                        </div>
                    </label>
                </div>
            </div>
        </div>
        <div class="form-modal-footer">
            <div id="formError" style="color:var(--danger); font-size:0.82rem; flex:1;"></div>
            <div style="display:flex; gap:0.5rem;">
                <button class="btn btn-ghost" id="cancelFormBtn">キャンセル</button>
                <button class="btn btn-primary" id="saveManualBtn">保存する</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script<?= nonceAttr() ?>>
(function(){
    var CSRF = '<?= generateCsrfToken() ?>';
    var CAN_EDIT   = <?= $canEditPage ? 'true' : 'false' ?>;
    var CAN_DELETE = <?= $canDeletePage ? 'true' : 'false' ?>;

    var manuals = [];
    var categories = {};
    var allTags = {};
    var activeCategory = '';
    var activeTag = '';
    var searchQuery = '';
    var currentManual = null;

    function $(id) { return document.getElementById(id); }
    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }
    function debounce(fn, wait) {
        var t;
        return function(){ var a=arguments, s=this; clearTimeout(t); t=setTimeout(function(){fn.apply(s,a);}, wait); };
    }

    // 公開範囲: 保存配列 → ラジオ値
    function visibilityArrayToValue(arr) {
        if (!Array.isArray(arr) || arr.length === 0) return 'all';
        if (arr.length === 1 && arr[0] === 'admin') return 'admin';
        if (arr.indexOf('admin') !== -1 && arr.indexOf('product') !== -1 && arr.indexOf('sales') === -1) return 'product';
        return 'all';
    }
    function renderVisibilityBadge(arr) {
        var v = visibilityArrayToValue(arr);
        if (v === 'admin') {
            return '<span class="badge-restricted badge-restricted-admin"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>管理部のみ</span>';
        }
        if (v === 'product') {
            return '<span class="badge-restricted"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>製品技術部以上</span>';
        }
        return '';
    }

    /**
     * 外部 URL を埋め込み表示用に変換する。
     * - Google スライド: /edit や末尾のクエリを /embed?start=false&loop=false&delayms=3000 に置き換え
     * - Google ドキュメント / スプレッドシート: /edit → /preview
     * - YouTube: /watch?v=ID → /embed/ID
     * - それ以外: そのまま返す（iframe 拒否されたらフォールバック表示）
     */
    function toEmbedUrl(url) {
        if (!url) return '';
        // Google Slides
        var m = url.match(/^(https:\/\/docs\.google\.com\/presentation\/d\/[^/]+)\/.*$/);
        if (m) return m[1] + '/embed?start=false&loop=false&delayms=3000';
        // Google Docs / Sheets
        if (/^https:\/\/docs\.google\.com\/(document|spreadsheets)\/d\//.test(url)) {
            return url.replace(/\/edit[^?#]*([?#].*)?$/, '/preview').replace(/\/view([?#].*)?$/, '/preview');
        }
        // YouTube
        var y = url.match(/^https?:\/\/(?:www\.)?youtube\.com\/watch\?v=([\w-]+)/);
        if (y) return 'https://www.youtube.com/embed/' + y[1];
        var ys = url.match(/^https?:\/\/youtu\.be\/([\w-]+)/);
        if (ys) return 'https://www.youtube.com/embed/' + ys[1];
        // Google Drive ファイル
        var d = url.match(/^(https:\/\/drive\.google\.com\/file\/d\/[^/]+)\/.*$/);
        if (d) return d[1] + '/preview';
        return url;
    }

    // ===================== データ取得 =====================
    async function loadManuals() {
        var params = new URLSearchParams();
        if (searchQuery) params.set('q', searchQuery);
        if (activeCategory) params.set('category', activeCategory);
        if (activeTag) params.set('tag', activeTag);
        try {
            var res = await fetch('/api/manuals-api.php?action=list&' + params.toString(), { credentials: 'same-origin' });
            var json = await res.json();
            if (!json.success) throw new Error(json.error || json.message || 'unknown');
            manuals    = json.data.manuals || [];
            categories = json.data.categories || {};
            allTags    = json.data.tags || {};
            renderChips();
            renderGrid();
            updateResultCount(json.data.total);
        } catch (e) {
            $('manualsGrid').innerHTML =
                '<div class="empty-state"><p style="color:var(--danger);">読み込み失敗: ' + escapeHtml(e.message) + '</p></div>';
        }
    }

    function updateResultCount(total) {
        $('resultCount').textContent = searchQuery
            ? '「' + searchQuery + '」の検索結果: ' + total + '件'
            : '全 ' + total + '件のマニュアル';
    }

    function renderChips() {
        var catBox = $('categoryChips');
        var html = '<span class="chip ' + (activeCategory === '' ? 'active' : '') + '" data-category="">すべて</span>';
        Object.keys(categories).forEach(function(c){
            html += '<span class="chip ' + (activeCategory === c ? 'active' : '') + '" data-category="' + escapeHtml(c) + '">' +
                escapeHtml(c) + ' <span class="count">' + categories[c] + '</span></span>';
        });
        catBox.innerHTML = html;

        var tagBox = $('tagChips');
        var thtml = '<span class="chip ' + (activeTag === '' ? 'active' : '') + '" data-tag="">すべて</span>';
        Object.keys(allTags).forEach(function(t){
            thtml += '<span class="chip ' + (activeTag === t ? 'active' : '') + '" data-tag="' + escapeHtml(t) + '">' +
                escapeHtml(t) + ' <span class="count">' + allTags[t] + '</span></span>';
        });
        tagBox.innerHTML = thtml;

        var dl = $('categoryList');
        if (dl) {
            dl.innerHTML = Object.keys(categories).map(function(c){
                return '<option value="' + escapeHtml(c) + '">';
            }).join('');
        }

        catBox.querySelectorAll('.chip').forEach(function(el){
            el.addEventListener('click', function(){ activeCategory = el.dataset.category; loadManuals(); });
        });
        tagBox.querySelectorAll('.chip').forEach(function(el){
            el.addEventListener('click', function(){ activeTag = el.dataset.tag; loadManuals(); });
        });
    }

    function renderGrid() {
        var grid = $('manualsGrid');
        if (!manuals.length) {
            grid.innerHTML = '<div class="empty-state">' +
                '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>' +
                '<p>該当するマニュアルがありません。検索条件を変えるか、新しいマニュアルを登録してください。</p></div>';
            return;
        }
        var html = '';
        manuals.forEach(function(m) {
            var tagsHtml = '';
            if (Array.isArray(m.tags)) {
                m.tags.slice(0, 4).forEach(function(t){
                    tagsHtml += '<span class="badge-tag">' + escapeHtml(t) + '</span>';
                });
            }
            var catHtml = m.category ? '<span class="badge-category">' + escapeHtml(m.category) + '</span>' : '';
            var visBadge = renderVisibilityBadge(m.visible_to);
            html += '<div class="manual-card" data-id="' + escapeHtml(m.id) + '">' +
                '<p class="card-title">' +
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>' +
                    escapeHtml(m.title) +
                '</p>' +
                (m.description ? '<div class="card-desc">' + escapeHtml(m.description) + '</div>' : '') +
                '<div class="card-meta">' + catHtml + tagsHtml + visBadge + '</div>' +
            '</div>';
        });
        grid.innerHTML = html;
        grid.querySelectorAll('.manual-card').forEach(function(card){
            card.addEventListener('click', function(){ openViewer(card.dataset.id); });
        });
    }

    // ===================== 検索 =====================
    var doSearch = debounce(function(){
        searchQuery = $('searchInput').value.trim();
        $('clearBtn').classList.toggle('visible', !!searchQuery);
        loadManuals();
    }, 220);
    $('searchInput').addEventListener('input', doSearch);
    $('clearBtn').addEventListener('click', function(){
        $('searchInput').value = '';
        searchQuery = '';
        $('clearBtn').classList.remove('visible');
        loadManuals();
        $('searchInput').focus();
    });

    // ===================== 閲覧モーダル =====================
    function openViewer(id) {
        var m = manuals.find(function(x){ return x.id === id; });
        if (!m) return;
        currentManual = m;
        $('viewerTitle').textContent = m.title;
        $('openInTabBtn').href = m.url;
        var embed = toEmbedUrl(m.url);
        $('viewerIframe').src = embed;
        $('viewerModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeViewer() {
        $('viewerModal').classList.remove('active');
        $('viewerIframe').src = 'about:blank';
        document.body.style.overflow = '';
        currentManual = null;
    }
    $('closeViewerBtn').addEventListener('click', closeViewer);

    // ===================== 編集モーダル =====================
    if (CAN_EDIT) {
        $('openCreateBtn').addEventListener('click', function(){ openForm(null); });
        $('editFromViewerBtn').addEventListener('click', function(){
            if (currentManual) { var m = currentManual; closeViewer(); openForm(m); }
        });
        $('closeFormBtn').addEventListener('click', closeForm);
        $('cancelFormBtn').addEventListener('click', closeForm);
        $('saveManualBtn').addEventListener('click', saveManual);

        $('tagInput').addEventListener('keydown', function(e){
            if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTagFromInput(); }
            else if (e.key === 'Backspace' && this.value === '') {
                var pills = $('tagsWrap').querySelectorAll('.tag-pill');
                if (pills.length) pills[pills.length - 1].remove();
            }
        });
        $('tagInput').addEventListener('blur', addTagFromInput);
    }
    if (CAN_DELETE) {
        $('deleteFromViewerBtn').addEventListener('click', deleteManual);
    }

    function setVisibilityRadio(value) {
        var radios = document.querySelectorAll('input[name="visibility"]');
        radios.forEach(function(r){
            r.checked = (r.value === value);
            var opt = r.closest('.visibility-option');
            if (opt) opt.classList.toggle('selected', r.checked);
        });
    }
    function getVisibilityRadio() {
        var checked = document.querySelector('input[name="visibility"]:checked');
        return checked ? checked.value : 'all';
    }
    // ラジオ変更時に見た目を更新
    document.querySelectorAll('input[name="visibility"]').forEach(function(r){
        r.addEventListener('change', function(){ setVisibilityRadio(r.value); });
    });

    function openForm(manual) {
        $('formError').textContent = '';
        if (manual) {
            $('formModalTitle').textContent = 'マニュアルを編集';
            $('formManualId').value = manual.id;
            $('formTitle').value = manual.title || '';
            $('formUrl').value = manual.url || '';
            $('formDescription').value = manual.description || '';
            $('formKeywords').value = manual.search_keywords || '';
            $('formCategory').value = manual.category || '';
            renderTagPills(manual.tags || []);
            setVisibilityRadio(visibilityArrayToValue(manual.visible_to));
        } else {
            $('formModalTitle').textContent = 'マニュアルを追加';
            $('formManualId').value = '';
            $('formTitle').value = '';
            $('formUrl').value = '';
            $('formDescription').value = '';
            $('formKeywords').value = '';
            $('formCategory').value = '';
            renderTagPills([]);
            setVisibilityRadio('all');
        }
        $('formModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(function(){ $('formTitle').focus(); }, 50);
    }
    function closeForm() {
        $('formModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function renderTagPills(tags) {
        var wrap = $('tagsWrap');
        var input = $('tagInput');
        wrap.querySelectorAll('.tag-pill').forEach(function(p){ p.remove(); });
        tags.forEach(function(t){
            var pill = document.createElement('span');
            pill.className = 'tag-pill';
            pill.dataset.value = t;
            pill.innerHTML = escapeHtml(t) + ' <button type="button" aria-label="削除">×</button>';
            pill.querySelector('button').addEventListener('click', function(){ pill.remove(); });
            wrap.insertBefore(pill, input);
        });
    }
    function addTagFromInput() {
        var inp = $('tagInput');
        var v = inp.value.trim();
        if (!v) return;
        var existing = collectTags();
        if (existing.indexOf(v) === -1) renderTagPills(existing.concat([v]));
        inp.value = '';
    }
    function collectTags() {
        return Array.from($('tagsWrap').querySelectorAll('.tag-pill')).map(function(p){ return p.dataset.value; });
    }

    async function saveManual() {
        $('formError').textContent = '';
        addTagFromInput();
        var payload = {
            action: $('formManualId').value ? 'update' : 'create',
            id: $('formManualId').value || undefined,
            title: $('formTitle').value.trim(),
            url: $('formUrl').value.trim(),
            description: $('formDescription').value.trim(),
            search_keywords: $('formKeywords').value.trim(),
            category: $('formCategory').value.trim(),
            tags: collectTags(),
            visibility: getVisibilityRadio(),
        };
        if (!payload.title) { $('formError').textContent = 'タイトルを入力してください'; return; }
        if (!payload.url)   { $('formError').textContent = 'URL を入力してください'; return; }
        if (!/^https?:\/\//i.test(payload.url)) { $('formError').textContent = 'URL は http:// または https:// で始めてください'; return; }

        var btn = $('saveManualBtn');
        btn.disabled = true;
        try {
            var res = await fetch('/api/manuals-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });
            var json = await res.json();
            if (!json.success) throw new Error(json.error || json.message || 'unknown');
            closeForm();
            await loadManuals();
        } catch (e) {
            $('formError').textContent = '保存失敗: ' + e.message;
        }
        btn.disabled = false;
    }

    async function deleteManual() {
        if (!currentManual) return;
        if (!confirm('「' + currentManual.title + '」を削除しますか？')) return;
        try {
            var res = await fetch('/api/manuals-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ action: 'delete', id: currentManual.id }),
                credentials: 'same-origin'
            });
            var json = await res.json();
            if (!json.success) throw new Error(json.error || json.message || 'unknown');
            closeViewer();
            await loadManuals();
        } catch (e) {
            alert('削除失敗: ' + e.message);
        }
    }

    // ===================== スプシ取り込み =====================
    if (CAN_EDIT) {
        var impEls = {
            open: $('openImportBtn'),
            modal: $('importModal'),
            close: $('closeImportBtn'),
            cancel: $('impCancelBtn'),
            preview: $('impPreviewBtn'),
            run: $('impRunBtn'),
            resync: $('resyncBtn'),
            banner: $('lastSyncBanner'),
            bannerText: $('lastSyncText'),
            url: $('impSheetUrl'),
            sheetName: $('impSheetName'),
            linkCol: $('impLinkCol'),
            descCol: $('impDescCol'),
            startRow: $('impStartRow'),
            previewWrap: $('impPreviewWrap'),
            previewList: $('impPreviewList'),
            previewTotal: $('impPreviewTotal'),
            previewErrors: $('impPreviewErrors'),
            error: $('impError'),
        };

        function openImport() {
            impEls.error.textContent = '';
            impEls.previewWrap.style.display = 'none';
            // 保存設定をロード
            fetch('/api/manuals-import-api.php?action=load-config', { credentials:'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(j){
                    if (j.success && j.data.config) {
                        var c = j.data.config;
                        impEls.url.value = c.sheet_url || '';
                        impEls.sheetName.value = c.sheet_name || '';
                        impEls.linkCol.value = c.link_col || 'L';
                        impEls.descCol.value = c.desc_col || '';
                        impEls.startRow.value = c.start_row || 2;
                        var radios = document.getElementsByName('imp_visibility');
                        radios.forEach(function(r){
                            r.checked = (r.value === (c.visibility || 'all'));
                            var opt = r.closest('.visibility-option');
                            if (opt) opt.classList.toggle('selected', r.checked);
                        });
                        if (c.last_synced_at) {
                            impEls.banner.style.display = '';
                            impEls.bannerText.textContent = '最終同期: ' + c.last_synced_at + ' (' + (c.last_synced_by || '') + ')';
                        } else {
                            impEls.banner.style.display = 'none';
                        }
                    }
                });
            impEls.modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeImport() {
            impEls.modal.classList.remove('active');
            document.body.style.overflow = '';
        }
        impEls.open.addEventListener('click', openImport);
        impEls.close.addEventListener('click', closeImport);
        impEls.cancel.addEventListener('click', closeImport);

        // ラジオの見た目連動
        document.getElementsByName('imp_visibility').forEach(function(r){
            r.addEventListener('change', function(){
                document.getElementsByName('imp_visibility').forEach(function(x){
                    var opt = x.closest('.visibility-option');
                    if (opt) opt.classList.toggle('selected', x.checked);
                });
            });
        });

        function gatherImportPayload() {
            var vis = 'all';
            var rs = document.getElementsByName('imp_visibility');
            rs.forEach(function(r){ if (r.checked) vis = r.value; });
            return {
                sheet_url:   impEls.url.value.trim(),
                sheet_name:  impEls.sheetName.value.trim(),
                link_col:    impEls.linkCol.value.trim().toUpperCase(),
                desc_col:    impEls.descCol.value.trim().toUpperCase(),
                start_row:   parseInt(impEls.startRow.value, 10) || 2,
                visibility:  vis,
            };
        }

        async function runPreview() {
            impEls.error.textContent = '';
            impEls.previewWrap.style.display = 'none';
            var payload = Object.assign({ action: 'preview' }, gatherImportPayload());
            if (!payload.sheet_url) { impEls.error.textContent = 'スプレッドシート URL を入力してください'; return; }
            if (!payload.link_col)  { impEls.error.textContent = 'リンク列を入力してください'; return; }
            impEls.preview.disabled = true;
            impEls.preview.textContent = '読み込み中...';
            try {
                var res = await fetch('/api/manuals-import-api.php', {
                    method:'POST',
                    headers:{ 'Content-Type':'application/json', 'X-CSRF-Token': CSRF },
                    body: JSON.stringify(payload),
                    credentials:'same-origin'
                });
                var json = await res.json();
                if (!json.success) throw new Error(json.error || json.message || 'unknown');
                var d = json.data;
                impEls.previewTotal.textContent = d.total_candidate;
                var html = '';
                d.samples.forEach(function(m, i){
                    html += '<div style="padding:0.55rem 0.7rem; background:var(--gray-50); border:1px solid var(--gray-200); border-radius:7px; font-size:0.82rem;">' +
                        '<div style="font-weight:600; color:var(--gray-900);">' +
                            (m.category ? '<span class="badge-category" style="margin-right:0.35rem;">' + escapeHtml(m.category) + '</span>' : '') +
                            escapeHtml(m.title) + '</div>' +
                        (m.url ? '<div style="color:var(--gray-500); font-size:0.72rem; margin-top:0.2rem; word-break:break-all;">' + escapeHtml(m.url) + '</div>' : '<div style="color:var(--danger); font-size:0.72rem;">URL なし - 取り込みでスキップされます</div>') +
                        (m.description ? '<div style="color:var(--gray-600); margin-top:0.25rem;">' + escapeHtml(m.description) + '</div>' : '') +
                    '</div>';
                });
                if (!html) html = '<div style="color:var(--gray-400); padding:1rem;">取り込み対象の行が見つかりませんでした。リンク列の設定とヘッダースキップを確認してください。</div>';
                impEls.previewList.innerHTML = html;
                impEls.previewWrap.style.display = '';
            } catch (e) {
                impEls.error.textContent = 'プレビュー失敗: ' + e.message;
            }
            impEls.preview.disabled = false;
            impEls.preview.textContent = 'プレビュー';
        }

        async function runImport(useResync) {
            impEls.error.textContent = '';
            var payload;
            if (useResync) {
                payload = { action: 'resync' };
            } else {
                payload = Object.assign({ action: 'import' }, gatherImportPayload());
                if (!payload.sheet_url) { impEls.error.textContent = 'スプレッドシート URL を入力してください'; return; }
                if (!payload.link_col)  { impEls.error.textContent = 'リンク列を入力してください'; return; }
            }
            if (!confirm('スプレッドシートから取り込みます。\n同じ URL が既にあるマニュアルは上書き更新されます。\nよろしいですか？')) return;

            impEls.run.disabled = true;
            impEls.run.textContent = '取り込み中...';
            try {
                var res = await fetch('/api/manuals-import-api.php', {
                    method:'POST',
                    headers:{ 'Content-Type':'application/json', 'X-CSRF-Token': CSRF },
                    body: JSON.stringify(payload),
                    credentials:'same-origin'
                });
                var json = await res.json();
                if (!json.success) throw new Error(json.error || json.message || 'unknown');
                var d = json.data;
                var msg = '取り込み完了: 新規 ' + d.created + ' / 更新 ' + d.updated + ' / スキップ ' + d.skipped;
                if (d.errors && d.errors.length) {
                    msg += '\n\n警告:\n' + d.errors.slice(0, 10).join('\n');
                    if (d.errors.length > 10) msg += '\n... 他 ' + (d.errors.length - 10) + '件';
                }
                alert(msg);
                closeImport();
                await loadManuals();
            } catch (e) {
                impEls.error.textContent = '取り込み失敗: ' + e.message;
            }
            impEls.run.disabled = false;
            impEls.run.textContent = '取り込み実行';
        }

        impEls.preview.addEventListener('click', runPreview);
        impEls.run.addEventListener('click', function(){ runImport(false); });
        if (impEls.resync) impEls.resync.addEventListener('click', function(){ runImport(true); });
    }

    loadManuals();
})();
</script>

<?php require_once '../functions/footer.php'; ?>
