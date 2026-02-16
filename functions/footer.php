        </main>
    </div>
    <div class="toast" id="toast"></div>
    <!-- トップへ戻るボタン -->
    <button id="backToTop" class="back-to-top-btn">&#9650;</button>
    <script<?= nonceAttr() ?>>
    (function(){var btn=document.getElementById('backToTop');if(!btn)return;btn.addEventListener('click',function(){window.scrollTo({top:0,behavior:'smooth'});});btn.addEventListener('mouseover',function(){this.style.opacity='1';this.style.transform='translateY(-2px)';});btn.addEventListener('mouseout',function(){this.style.opacity='0.8';this.style.transform='none';});window.addEventListener('scroll',function(){btn.style.display=window.scrollY>400?'block':'none';},{passive:true});})();
    </script>
    <!-- グローバル検索 -->
    <script<?= nonceAttr() ?>>
    (function() {
        const searchInput = document.getElementById('globalSearchInput');
        const searchResults = document.getElementById('globalSearchResults');
        if (!searchInput || !searchResults) return;

        let searchTimer = null;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        // Ctrl+K で検索にフォーカス
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
            if (e.key === 'Escape' && searchResults.style.display !== 'none') {
                searchResults.style.display = 'none';
                searchInput.blur();
            }
        });

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            const query = this.value.trim();
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            searchTimer = setTimeout(function() {
                performSearch(query);
            }, 300);
        });

        searchInput.addEventListener('focus', function() {
            if (this.value.trim().length >= 2 && searchResults.innerHTML) {
                searchResults.style.display = 'block';
            }
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.global-search-wrapper')) {
                searchResults.style.display = 'none';
            }
        });

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = String(text || '');
            return div.innerHTML;
        }

        function performSearch(query) {
            fetch('/api/search.php?q=' + encodeURIComponent(query) + '&limit=10')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        searchResults.innerHTML = '<div class="search-no-results">検索エラー</div>';
                        searchResults.style.display = 'block';
                        return;
                    }
                    var results = data.data.results || [];
                    if (results.length === 0) {
                        searchResults.innerHTML = '<div class="search-no-results">「' + escapeHtml(query) + '」の結果はありません</div>';
                        searchResults.style.display = 'block';
                        return;
                    }
                    var html = '';
                    results.forEach(function(r) {
                        var typeClass = 'search-type-' + r.type;
                        html += '<a href="' + escapeHtml(r.url) + '" class="search-result-item">'
                            + '<span class="search-result-type ' + typeClass + '">' + escapeHtml(r.type_label) + '</span>'
                            + '<div class="search-result-info">'
                            + '<div class="search-result-title">' + escapeHtml(r.title) + '</div>'
                            + (r.subtitle ? '<div class="search-result-subtitle">' + escapeHtml(r.subtitle) + '</div>' : '')
                            + '</div>'
                            + (r.status ? '<span class="search-result-status">' + escapeHtml(r.status) + '</span>' : '')
                            + '</a>';
                    });
                    if (data.data.total > 10) {
                        html += '<div class="search-footer"><a href="/pages/search.php?q=' + encodeURIComponent(query) + '">全 ' + data.data.total + ' 件を表示</a></div>';
                    }
                    searchResults.innerHTML = html;
                    searchResults.style.display = 'block';
                })
                .catch(function() {
                    searchResults.innerHTML = '<div class="search-no-results">検索エラー</div>';
                    searchResults.style.display = 'block';
                });
        }
    })();
    </script>
</body>
</html>
