<?php
require_once '../api/auth.php';
require_once '../functions/header.php';

$searchQuery = trim($_GET['q'] ?? '');
$filterType = trim($_GET['type'] ?? '');
?>

<style<?= nonceAttr() ?>>
.search-page-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 2px solid var(--gray-300);
    border-radius: 8px;
    font-size: 1.1rem;
    transition: border-color 0.2s;
}
.search-page-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
}
.search-filters {
    display: flex;
    gap: 0.5rem;
    margin: 1rem 0;
    flex-wrap: wrap;
}
.search-filter-btn {
    padding: 0.4rem 1rem;
    border: 1px solid var(--gray-300);
    border-radius: 20px;
    background: white;
    cursor: pointer;
    font-size: 0.875rem;
    transition: all 0.2s;
}
.search-filter-btn:hover { background: var(--gray-50); }
.search-filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
.search-result-card {
    display: flex;
    align-items: flex-start;
    padding: 1rem;
    border-bottom: 1px solid var(--gray-100);
    gap: 1rem;
    text-decoration: none;
    color: inherit;
    transition: background 0.15s;
}
.search-result-card:hover { background: var(--gray-50); }
.result-type-badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-weight: 600;
    white-space: nowrap;
    margin-top: 2px;
}
.result-content { flex: 1; }
.result-title { font-weight: 500; margin-bottom: 0.25rem; }
.result-meta { font-size: 0.8rem; color: var(--gray-500); }
.export-section {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
    padding: 1rem;
    background: var(--gray-50);
    border-radius: 8px;
    margin-top: 1.5rem;
}
</style>

<div class="page-container">
    <div class="page-header">
        <h2>横断検索</h2>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET"  class="position-relative">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--gray-400)" stroke-width="2"        class="position-absolute left-075 top-50-translate">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" name="q" class="search-page-input" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="案件名、顧客名、従業員名、トラブル内容を検索..." autofocus>
                <?php if ($filterType): ?>
                <input type="hidden" name="type" value="<?= htmlspecialchars($filterType) ?>">
                <?php endif; ?>
            </form>

            <?php if ($searchQuery): ?>
            <div class="search-filters">
                <a href="?q=<?= urlencode($searchQuery) ?>" class="search-filter-btn <?= empty($filterType) ? 'active' : '' ?>">すべて</a>
                <a href="?q=<?= urlencode($searchQuery) ?>&type=project" class="search-filter-btn <?= $filterType === 'project' ? 'active' : '' ?>">案件</a>
                <a href="?q=<?= urlencode($searchQuery) ?>&type=trouble" class="search-filter-btn <?= $filterType === 'trouble' ? 'active' : '' ?>">トラブル</a>
                <a href="?q=<?= urlencode($searchQuery) ?>&type=customer" class="search-filter-btn <?= $filterType === 'customer' ? 'active' : '' ?>">顧客</a>
                <a href="?q=<?= urlencode($searchQuery) ?>&type=employee" class="search-filter-btn <?= $filterType === 'employee' ? 'active' : '' ?>">従業員</a>
            </div>
            <?php endif; ?>

            <div id="searchResultsArea">
                <?php if ($searchQuery): ?>
                <div        class="text-center p-2rem text-gray-400">
                    <div         class="spinner w-24 h-24 mb-05 mx-auto"></div>
                    検索中...
                </div>
                <?php else: ?>
                <div        class="text-center p-3rem" class="text-gray-400">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"  class="mb-2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <p>検索キーワードを入力してください</p>
                    <p   class="text-2xs">案件名、顧客名、従業員名、トラブル内容などを横断的に検索できます</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- データエクスポート -->
    <div   class="card mt-3">
        <div class="card-body">
            <h3     class="m-0-1">データエクスポート</h3>
            <p    class="mb-2 text-14 text-gray-500">各マスタデータをCSVまたはJSON形式でダウンロードできます。</p>

            <div        class="gap-2 grid grid-auto-280">
                <!-- 案件 -->
                <div      class="rounded-lg p-2 border-gray">
                    <div    class="d-flex align-center gap-1 mb-075">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                        <strong>案件データ</strong>
                    </div>
                    <div  class="d-flex gap-1">
                        <a href="/api/export.php?entity=projects&format=csv"       class="btn btn-outline text-2xs py-03 px-075">CSV</a>
                        <a href="/api/export.php?entity=projects&format=json"       class="btn btn-outline text-2xs py-03 px-075">JSON</a>
                    </div>
                </div>

                <!-- トラブル -->
                <div      class="rounded-lg p-2 border-gray">
                    <div    class="d-flex align-center gap-1 mb-075">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                        <strong>トラブルデータ</strong>
                    </div>
                    <div  class="d-flex gap-1">
                        <a href="/api/export.php?entity=troubles&format=csv"       class="btn btn-outline text-2xs py-03 px-075">CSV</a>
                        <a href="/api/export.php?entity=troubles&format=json"       class="btn btn-outline text-2xs py-03 px-075">JSON</a>
                    </div>
                </div>

                <!-- 顧客 -->
                <div      class="rounded-lg p-2 border-gray">
                    <div    class="d-flex align-center gap-1 mb-075">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        <strong>顧客データ</strong>
                    </div>
                    <div  class="d-flex gap-1">
                        <a href="/api/export.php?entity=customers&format=csv"       class="btn btn-outline text-2xs py-03 px-075">CSV</a>
                        <a href="/api/export.php?entity=customers&format=json"       class="btn btn-outline text-2xs py-03 px-075">JSON</a>
                    </div>
                </div>

                <!-- 従業員 -->
                <div      class="rounded-lg p-2 border-gray">
                    <div    class="d-flex align-center gap-1 mb-075">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <strong>従業員データ</strong>
                    </div>
                    <div  class="d-flex gap-1">
                        <a href="/api/export.php?entity=employees&format=csv"       class="btn btn-outline text-2xs py-03 px-075">CSV</a>
                        <a href="/api/export.php?entity=employees&format=json"       class="btn btn-outline text-2xs py-03 px-075">JSON</a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php if ($searchQuery): ?>
<script<?= nonceAttr() ?>>
(function() {
    const csrfToken = '<?= generateCsrfToken() ?>';
    const query = <?= json_encode($searchQuery) ?>;
    const filterType = <?= json_encode($filterType) ?>;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text || '');
        return div.innerHTML;
    }

    fetch('/api/search.php?q=' + encodeURIComponent(query) + '&limit=100')
        .then(r => r.json())
        .then(data => {
            const area = document.getElementById('searchResultsArea');
            if (!data.success) {
                area.innerHTML = '<div        class="text-center text-danger p-2rem">検索エラーが発生しました</div>';
                return;
            }

            let results = data.data.results || [];
            const total = data.data.total || 0;

            // タイプフィルター
            if (filterType) {
                results = results.filter(r => r.type === filterType);
            }

            if (results.length === 0) {
                area.innerHTML = '<div        class="text-center p-2rem" class="text-gray-400">「' + escapeHtml(query) + '」に一致する結果はありません</div>';
                return;
            }

            let html = '<div       class="text-14 text-gray-500 p-075-1 border-bottom-gray">'
                + results.length + '件の結果</div>';

            results.forEach(r => {
                const typeClass = {
                    project: 'search-type-project',
                    trouble: 'search-type-trouble',
                    customer: 'search-type-customer',
                    employee: 'search-type-employee',
                }[r.type] || '';

                html += '<a href="' + escapeHtml(r.url) + '" class="search-result-card">'
                    + '<span class="result-type-badge ' + typeClass + '">' + escapeHtml(r.type_label) + '</span>'
                    + '<div class="result-content">'
                    + '<div class="result-title">' + escapeHtml(r.title) + '</div>'
                    + '<div class="result-meta">'
                    + (r.subtitle ? escapeHtml(r.subtitle) : '')
                    + (r.status ? ' &middot; ' + escapeHtml(r.status) : '')
                    + '</div>'
                    + '</div>'
                    + '</a>';
            });

            area.innerHTML = html;
        })
        .catch(() => {
            document.getElementById('searchResultsArea').innerHTML = '<div        class="text-center text-danger p-2rem">検索エラーが発生しました</div>';
        });
})();
</script>
<?php endif; ?>

<style<?= nonceAttr() ?>>
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<?php require_once '../functions/footer.php'; ?>
