/**
 * 共通ユーティリティ関数
 * ページ間で重複していたJavaScript関数を統合
 */

// ==========================================================================
// モーダル（Modal）ユーティリティ
// ==========================================================================

/**
 * モーダルを開く
 * @param {string} modalId - モーダルのID
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // スクロール防止
    }
}

/**
 * モーダルを閉じる
 * @param {string} modalId - モーダルのID
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = ''; // スクロール復元
    }
}

/**
 * モーダルの外側クリックで閉じる設定
 * @param {string} modalId - モーダルのID
 */
function setupModalClickOutside(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(modalId);
            }
        });
    }
}

// ==========================================================================
// セキュリティ（Security）ユーティリティ
// ==========================================================================

/**
 * HTMLエスケープ（XSS対策）
 * @param {string} text - エスケープするテキスト
 * @returns {string} エスケープされたテキスト
 */
function escapeHtml(text) {
    if (text === null || text === undefined) {
        return '';
    }
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

// ==========================================================================
// フォーム（Form）ユーティリティ
// ==========================================================================

/**
 * メールアドレスのバリデーション
 * @param {string} email - メールアドレス
 * @returns {boolean}
 */
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * 電話番号のバリデーション（日本）
 * @param {string} phone - 電話番号
 * @returns {boolean}
 */
function validatePhone(phone) {
    const re = /^(0\d{1,4}-?\d{1,4}-?\d{4}|0\d{9,10})$/;
    return re.test(phone.replace(/[\s()-]/g, ''));
}

/**
 * 必須項目のバリデーション
 * @param {string|number} value - 値
 * @returns {boolean}
 */
function validateRequired(value) {
    return value !== null && value !== undefined && String(value).trim() !== '';
}

/**
 * 数値のバリデーション
 * @param {string|number} value - 値
 * @returns {boolean}
 */
function validateNumeric(value) {
    return !isNaN(parseFloat(value)) && isFinite(value);
}

/**
 * フォームフィールドにエラーを表示
 * @param {string} fieldId - フィールドのID
 * @param {string} message - エラーメッセージ
 */
function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (!field) return;

    field.classList.add('error');

    // 既存のエラーメッセージを削除
    const existingError = field.parentElement.querySelector('.form-error');
    if (existingError) {
        existingError.remove();
    }

    // 新しいエラーメッセージを追加
    const errorDiv = document.createElement('span');
    errorDiv.className = 'form-error';
    errorDiv.textContent = message;
    field.parentElement.appendChild(errorDiv);
}

/**
 * フォームフィールドのエラーをクリア
 * @param {string} fieldId - フィールドのID
 */
function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;

    field.classList.remove('error');

    const errorDiv = field.parentElement.querySelector('.form-error');
    if (errorDiv) {
        errorDiv.remove();
    }
}

// ==========================================================================
// API（Fetch）ユーティリティ
// ==========================================================================

/**
 * APIリクエストのラッパー
 * @param {string} url - エンドポイントURL
 * @param {object} options - fetchオプション
 * @returns {Promise<object>}
 */
async function fetchAPI(url, options = {}) {
    try {
        const response = await fetch(url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

/**
 * POSTリクエストのヘルパー
 * @param {string} url - エンドポイントURL
 * @param {object} data - 送信データ
 * @param {string} csrfToken - CSRFトークン
 * @returns {Promise<object>}
 */
async function postAPI(url, data, csrfToken) {
    return fetchAPI(url, {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(data)
    });
}

/**
 * GETリクエストのヘルパー
 * @param {string} url - エンドポイントURL
 * @returns {Promise<object>}
 */
async function getAPI(url) {
    return fetchAPI(url, {
        method: 'GET'
    });
}

/**
 * 削除APIリクエスト
 * @param {string} url - エンドポイントURL
 * @param {string|number} itemId - 削除対象のID
 * @param {string} csrfToken - CSRFトークン
 * @returns {Promise<object>}
 */
async function deleteItem(url, itemId, csrfToken) {
    return postAPI(url, { id: itemId, action: 'delete' }, csrfToken);
}

// ==========================================================================
// テーブル（Table）ユーティリティ
// ==========================================================================

/**
 * テーブルをソート
 * @param {string} tableId - テーブルのID
 * @param {number} columnIndex - ソートする列のインデックス
 * @param {string} type - ソートタイプ ('string' | 'number' | 'date')
 */
function sortTable(tableId, columnIndex, type = 'string') {
    const table = document.getElementById(tableId);
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex]?.textContent.trim() || '';
        const bValue = b.cells[columnIndex]?.textContent.trim() || '';

        if (type === 'number') {
            return parseFloat(aValue) - parseFloat(bValue);
        } else if (type === 'date') {
            return new Date(aValue) - new Date(bValue);
        } else {
            return aValue.localeCompare(bValue, 'ja');
        }
    });

    // ソート後のテーブルを再構築
    rows.forEach(row => tbody.appendChild(row));
}

/**
 * テーブルをフィルタリング
 * @param {string} tableId - テーブルのID
 * @param {string} query - 検索クエリ
 * @param {number[]} columnIndexes - 検索対象の列インデックス（省略時は全列）
 */
function filterTable(tableId, query, columnIndexes = null) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    const lowerQuery = query.toLowerCase();

    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const searchCells = columnIndexes
            ? columnIndexes.map(i => cells[i])
            : Array.from(cells);

        const matchesQuery = searchCells.some(cell =>
            cell && cell.textContent.toLowerCase().includes(lowerQuery)
        );

        row.style.display = matchesQuery ? '' : 'none';
    });

    // ページネーション連携: フィルター変更を通知
    table.dispatchEvent(new CustomEvent('filter-changed'));
}

// ==========================================================================
// URL・パラメータユーティリティ
// ==========================================================================

/**
 * URLパラメータを取得
 * @param {string} key - パラメータ名
 * @returns {string|null}
 */
function getURLParam(key) {
    const params = new URLSearchParams(window.location.search);
    return params.get(key);
}

/**
 * URLパラメータを設定（リロードなし）
 * @param {string} key - パラメータ名
 * @param {string} value - パラメータ値
 */
function setURLParam(key, value) {
    const url = new URL(window.location);
    url.searchParams.set(key, value);
    window.history.replaceState({}, '', url);
}

/**
 * URLパラメータを削除（リロードなし）
 * @param {string} key - パラメータ名
 */
function removeURLParam(key) {
    const url = new URL(window.location);
    url.searchParams.delete(key);
    window.history.replaceState({}, '', url);
}

// ==========================================================================
// ユーティリティ関数
// ==========================================================================

/**
 * 日本語日付フォーマット
 * @param {Date|string} date - 日付
 * @returns {string}
 */
function formatDateJP(date) {
    const d = typeof date === 'string' ? new Date(date) : date;
    return d.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
}

/**
 * 日本語日時フォーマット
 * @param {Date|string} date - 日付
 * @returns {string}
 */
function formatDateTimeJP(date) {
    const d = typeof date === 'string' ? new Date(date) : date;
    return d.toLocaleString('ja-JP', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * 数値を3桁カンマ区切りでフォーマット
 * @param {number} num - 数値
 * @returns {string}
 */
function formatNumber(num) {
    return num.toLocaleString('ja-JP');
}

/**
 * 通貨フォーマット（円）
 * @param {number} amount - 金額
 * @returns {string}
 */
function formatCurrency(amount) {
    return amount.toLocaleString('ja-JP') + '円';
}

/**
 * デバウンス関数
 * @param {Function} func - 実行する関数
 * @param {number} delay - 遅延ミリ秒
 * @returns {Function}
 */
function debounce(func, delay = 300) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
}

/**
 * ローディング表示/非表示
 * @param {string} elementId - 要素ID
 * @param {boolean} show - 表示するか
 */
function toggleLoading(elementId, show) {
    const element = document.getElementById(elementId);
    if (!element) return;

    if (show) {
        element.disabled = true;
        element.innerHTML = '<span class="spinner"></span> 処理中...';
    } else {
        element.disabled = false;
    }
}

/**
 * トースト通知を表示
 * @param {string} message - メッセージ
 * @param {string} type - タイプ ('success' | 'danger' | 'warning' | 'info')
 * @param {number} duration - 表示時間（ミリ秒）
 */
function showToast(message, type = 'info', duration = 3000) {
    // 既存のトーストコンテナを取得または作成
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
        document.body.appendChild(container);
    }

    // トーストを作成
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.cssText = 'margin-bottom: 10px; min-width: 300px; animation: slideInRight 0.3s ease-out;';
    toast.textContent = message;

    container.appendChild(toast);

    // 自動削除
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ==========================================================================
// ページネーション
// ==========================================================================

/**
 * クライアントサイドページネーション
 * テーブルやdivリストに対してページ分割表示を行う
 */
class Paginator {
    /**
     * @param {Object} options
     * @param {string|HTMLElement} options.container - テーブルまたはコンテナ要素（セレクタまたはDOM要素）
     * @param {string} options.itemSelector - ページネーション対象のCSSセレクタ
     * @param {number} [options.perPage=50] - 1ページあたりの表示件数
     * @param {number[]} [options.perPageOptions=[20,50,100,0]] - 件数選択肢（0=全件）
     * @param {string|HTMLElement} [options.paginationTarget] - コントロール挿入先
     * @param {string} [options.urlParamPrefix=''] - URLパラメータの接頭辞
     * @param {string} [options.groupAttribute=null] - グループ化属性名（同グループを1単位として扱う）
     */
    constructor(options) {
        this.container = typeof options.container === 'string'
            ? document.querySelector(options.container) : options.container;
        if (!this.container) return;

        this.itemSelector = options.itemSelector || 'tbody tr';
        this.perPage = options.perPage || 50;
        this.perPageOptions = options.perPageOptions || [20, 50, 100, 0];
        this.urlParamPrefix = options.urlParamPrefix !== undefined ? options.urlParamPrefix : '';
        this.groupAttribute = options.groupAttribute || null;
        this.currentPage = 1;

        // ページネーションコントロールの挿入先
        if (options.paginationTarget) {
            this.paginationTarget = typeof options.paginationTarget === 'string'
                ? document.querySelector(options.paginationTarget) : options.paginationTarget;
        } else {
            this.paginationTarget = document.createElement('div');
            this.container.parentNode.insertBefore(this.paginationTarget, this.container.nextSibling);
        }

        // URLパラメータから初期値を復元
        if (this.urlParamPrefix !== null) {
            var savedPage = getURLParam(this.urlParamPrefix + 'page');
            var savedPerPage = getURLParam(this.urlParamPrefix + 'per_page');
        } else {
            var savedPage = null;
            var savedPerPage = null;
        }
        if (savedPage) this.currentPage = Math.max(1, parseInt(savedPage));
        if (savedPerPage) this.perPage = parseInt(savedPerPage) || this.perPage;

        // フィルター変更イベントをリスン
        this.container.addEventListener('filter-changed', () => {
            this.currentPage = 1;
            this.refresh();
        });

        this.refresh();
    }

    /** 全アイテムを取得 */
    _getAllItems() {
        return Array.from(this.container.querySelectorAll(this.itemSelector));
    }

    /** ページネーションによる非表示を一旦解除 */
    _restorePaginatedItems() {
        this.container.querySelectorAll('[data-paginated="hidden"]').forEach(el => {
            el.style.display = '';
            delete el.dataset.paginated;
        });
    }

    /** 表示可能なアイテム（フィルターで非表示でないもの）を取得 */
    _getVisibleItems(allItems) {
        return allItems.filter(item => item.style.display !== 'none');
    }

    /** グループ化されたアイテムリストを取得 */
    _getGroups(visibleItems) {
        if (!this.groupAttribute) {
            return visibleItems.map(item => [item]);
        }
        const groups = [];
        const groupMap = {};
        visibleItems.forEach(item => {
            const groupId = item.getAttribute(this.groupAttribute);
            if (groupId && groupMap[groupId] !== undefined) {
                groups[groupMap[groupId]].push(item);
            } else {
                groupMap[groupId || ('__solo_' + groups.length)] = groups.length;
                groups.push([item]);
            }
        });
        return groups;
    }

    /** 再計算＆再描画 */
    refresh() {
        // 1. ページネーションで隠したアイテムを一旦復元
        this._restorePaginatedItems();

        // 2. 全アイテムを取得し、フィルターで表示中のものを特定
        const allItems = this._getAllItems();
        const visibleItems = this._getVisibleItems(allItems);

        // 3. グループ化
        const groups = this._getGroups(visibleItems);
        const totalGroups = groups.length;

        // perPage=0 は全件表示
        const effectivePerPage = this.perPage === 0 ? totalGroups : this.perPage;
        const totalPages = Math.max(1, Math.ceil(totalGroups / effectivePerPage));
        this.currentPage = Math.max(1, Math.min(this.currentPage, totalPages));

        const startIdx = (this.currentPage - 1) * effectivePerPage;
        const endIdx = Math.min(startIdx + effectivePerPage, totalGroups);

        // 4. ページ外のアイテムを非表示にする
        groups.forEach((group, idx) => {
            const visible = idx >= startIdx && idx < endIdx;
            group.forEach(item => {
                if (!visible) {
                    item.style.display = 'none';
                    item.dataset.paginated = 'hidden';
                }
            });
        });

        // 5. コントロールを描画
        this._render(totalGroups, totalPages, startIdx, endIdx);

        // 6. URLパラメータを更新
        if (this.urlParamPrefix !== null) {
            setURLParam(this.urlParamPrefix + 'page', String(this.currentPage));
            if (this.perPage !== 50) {
                setURLParam(this.urlParamPrefix + 'per_page', String(this.perPage));
            }
        }
    }

    /** ページネーションUIを描画 */
    _render(total, totalPages, startIdx, endIdx) {
        if (!this.paginationTarget) return;

        // 件数が少なくページネーション不要な場合
        if (total <= Math.min(...this.perPageOptions.filter(n => n > 0))) {
            this.paginationTarget.innerHTML = '';
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'pagination-wrapper';

        // 件数情報
        const info = document.createElement('div');
        info.className = 'pagination-info';
        if (total === 0) {
            info.textContent = '0件';
        } else {
            info.innerHTML = '全<strong>' + total + '</strong>件中 <strong>' +
                (startIdx + 1) + '</strong>-<strong>' + endIdx + '</strong>件を表示';
        }
        wrapper.appendChild(info);

        // ページ番号（複数ページある場合のみ）
        if (totalPages > 1) {
            const nav = document.createElement('div');
            nav.className = 'pagination';

            // 前へ
            if (this.currentPage > 1) {
                nav.appendChild(this._createPageLink('\u00ab 前へ', this.currentPage - 1));
            } else {
                nav.appendChild(this._createSpan('\u00ab 前へ', 'disabled'));
            }

            // ページ番号（現在±3、省略記号付き）
            const range = this._getPageRange(this.currentPage, totalPages);
            range.forEach(p => {
                if (p === '...') {
                    nav.appendChild(this._createSpan('...', 'ellipsis'));
                } else if (p === this.currentPage) {
                    nav.appendChild(this._createSpan(String(p), 'current'));
                } else {
                    nav.appendChild(this._createPageLink(String(p), p));
                }
            });

            // 次へ
            if (this.currentPage < totalPages) {
                nav.appendChild(this._createPageLink('次へ \u00bb', this.currentPage + 1));
            } else {
                nav.appendChild(this._createSpan('次へ \u00bb', 'disabled'));
            }

            wrapper.appendChild(nav);
        }

        // 件数セレクタ
        const perPageDiv = document.createElement('div');
        perPageDiv.className = 'pagination-per-page';
        const label = document.createElement('span');
        label.textContent = '表示件数:';
        perPageDiv.appendChild(label);

        const select = document.createElement('select');
        this.perPageOptions.forEach(n => {
            const opt = document.createElement('option');
            opt.value = String(n);
            opt.textContent = n === 0 ? '全て' : n + '件';
            if (n === this.perPage) opt.selected = true;
            select.appendChild(opt);
        });
        select.addEventListener('change', (e) => {
            this.setPerPage(parseInt(e.target.value));
        });
        perPageDiv.appendChild(select);
        wrapper.appendChild(perPageDiv);

        this.paginationTarget.innerHTML = '';
        this.paginationTarget.appendChild(wrapper);
    }

    /** ページ番号範囲を計算（省略記号付き） */
    _getPageRange(current, total) {
        if (total <= 7) {
            return Array.from({length: total}, (_, i) => i + 1);
        }
        const pages = [];
        pages.push(1);
        const start = Math.max(2, current - 2);
        const end = Math.min(total - 1, current + 2);
        if (start > 2) pages.push('...');
        for (let i = start; i <= end; i++) pages.push(i);
        if (end < total - 1) pages.push('...');
        pages.push(total);
        return pages;
    }

    /** クリック可能なページリンクを作成 */
    _createPageLink(text, page) {
        const a = document.createElement('a');
        a.textContent = text;
        a.href = '#';
        a.addEventListener('click', (e) => {
            e.preventDefault();
            this.goToPage(page);
        });
        return a;
    }

    /** 非クリックのspanを作成 */
    _createSpan(text, className) {
        const span = document.createElement('span');
        span.textContent = text;
        span.className = className;
        return span;
    }

    /** 指定ページに移動 */
    goToPage(page) {
        this.currentPage = page;
        this.refresh();
        // ページ先頭にスクロール
        this.container.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    /** 1ページの表示件数を変更 */
    setPerPage(count) {
        this.perPage = count;
        this.currentPage = 1;
        this.refresh();
    }

    /** 現在の状態を取得 */
    getState() {
        const allItems = this._getAllItems();
        this._restorePaginatedItems();
        const visibleItems = this._getVisibleItems(allItems);
        const groups = this._getGroups(visibleItems);
        const total = groups.length;
        const effectivePerPage = this.perPage === 0 ? total : this.perPage;
        const totalPages = Math.max(1, Math.ceil(total / effectivePerPage));
        // re-apply pagination
        this.refresh();
        return {
            page: this.currentPage,
            perPage: this.perPage,
            totalItems: total,
            totalPages: totalPages
        };
    }

    /** Paginatorを破棄し、全アイテムを表示に戻す */
    destroy() {
        this._restorePaginatedItems();
        if (this.paginationTarget) {
            this.paginationTarget.innerHTML = '';
        }
    }
}

// ==========================================================================
// グローバルエクスポート（必要に応じて）
// ==========================================================================

// モダンなモジュールシステムを使用する場合
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        escapeHtml,
        openModal,
        closeModal,
        validateEmail,
        validatePhone,
        validateRequired,
        fetchAPI,
        postAPI,
        getAPI,
        deleteItem,
        sortTable,
        filterTable,
        formatDateJP,
        formatCurrency,
        showToast,
        Paginator
    };
}
