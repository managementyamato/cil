<?php
/**
 * UI 共通コンポーネント
 *
 * 各ページで個別実装されていたボタン・検索入力等を統一する。
 * これらを使えば、文言・配置・スタイル・挙動が全ページで揃う。
 *
 * 使い方:
 *   require_once __DIR__ . '/ui-components.php';
 *
 *   echo uiNewButton('新規登録', ['onclick' => 'openModal()']);
 *   echo uiBackButton('cancel');  // モーダル下部 → 「キャンセル」
 *   echo uiBackButton('list', ['href' => 'troubles.php']);  // 「← 一覧に戻る」
 *   echo uiSearchInput(['id' => 'mySearch', 'placeholder' => '製品名で検索...']);
 *   echo uiSyncButton('MF', ['id' => 'btnSyncMf']);
 *
 * 設計方針:
 * - すべて関数戻り値は HTML 文字列 (echo して使う)
 * - inline style は最小限、見た目はクラスベース
 * - 既存の `.btn-primary` `.btn-secondary` クラス資産を活用
 * - 値のエスケープは内部で処理 (呼び出し側は生文字列で渡してOK)
 */

if (!function_exists('h')) {
    function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/**
 * 新規作成ボタン
 *
 * 全システム共通の「新規登録」ボタン。文言は原則「新規登録」(プロジェクト・トラブル・顧客等の "登録")。
 * 文書/資料の "作成" 場合のみ第1引数で別文言を渡せるが、推奨は固定。
 *
 * @param string $label  通常は '新規登録' (デフォルト)。「新規作成」「+ 新規追加」等の表記揺れを避ける。
 * @param array  $opts   [
 *     'id' => string,      // ボタンID
 *     'href' => string,    // 指定すると <a> でリンク化、未指定なら <button>
 *     'onclick' => string, // ボタンクリック時の JS (※ CSP nonce適用ページでは data-action を推奨)
 *     'class' => string,   // 追加クラス
 *     'attrs' => string,   // 追加属性 (例: 'data-action="show-add"')
 *     'icon' => bool,      // true で先頭に + アイコンを表示 (デフォルト true)
 * ]
 */
function uiNewButton(string $label = '新規登録', array $opts = []): string {
    $id      = $opts['id']      ?? '';
    $href    = $opts['href']    ?? '';
    $onclick = $opts['onclick'] ?? '';
    $cls     = trim('btn btn-primary ' . ($opts['class'] ?? ''));
    $attrs   = $opts['attrs'] ?? '';
    $icon    = $opts['icon'] ?? true;
    $idAttr  = $id ? ' id="' . h($id) . '"' : '';
    $onAttr  = $onclick ? ' onclick="' . h($onclick) . '"' : '';

    $iconSvg = $icon ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right:0.35rem;vertical-align:-2px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' : '';

    if ($href !== '') {
        return '<a href="' . h($href) . '" class="' . h($cls) . '"' . $idAttr . ' ' . $attrs . '>' . $iconSvg . h($label) . '</a>';
    }
    return '<button type="button" class="' . h($cls) . '"' . $idAttr . $onAttr . ' ' . $attrs . '>' . $iconSvg . h($label) . '</button>';
}

/**
 * 戻る・キャンセル系ボタン
 *
 * 種別:
 *   'cancel'      → モーダル下部の「キャンセル」 (btn-secondary)
 *   'close'       → モーダル下部の「閉じる」 (btn-secondary) - 読み取り専用モーダル用
 *   'list'        → ページ上部の「← 一覧に戻る」 (btn-secondary btn-sm)
 *   'modal-x'     → モーダル右上の「×」アイコンボタン
 *
 * @param string $kind   'cancel' | 'close' | 'list' | 'modal-x'
 * @param array  $opts   [
 *     'href' => string,    // 'list' 時のリンク先
 *     'onclick' => string,
 *     'attrs' => string,   // data-close-modal 等
 *     'label' => string,   // 'list' のラベル上書き (例: 'お知らせ一覧に戻る')
 *     'class' => string,
 *     'id' => string,
 * ]
 */
function uiBackButton(string $kind = 'cancel', array $opts = []): string {
    $href    = $opts['href']    ?? '';
    $onclick = $opts['onclick'] ?? '';
    $attrs   = $opts['attrs']   ?? '';
    $extra   = $opts['class']   ?? '';
    $id      = $opts['id']      ?? '';
    $idAttr  = $id ? ' id="' . h($id) . '"' : '';
    $onAttr  = $onclick ? ' onclick="' . h($onclick) . '"' : '';

    if ($kind === 'modal-x') {
        $cls = trim('modal-close ' . $extra);
        return '<button type="button" class="' . h($cls) . '"' . $idAttr . $onAttr . ' ' . $attrs . ' aria-label="閉じる" title="閉じる">&times;</button>';
    }

    if ($kind === 'list') {
        $label = $opts['label'] ?? '一覧に戻る';
        $cls = trim('btn btn-secondary btn-sm ' . $extra);
        $back = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right:0.3rem;vertical-align:-2px;"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>';
        if ($href !== '') {
            return '<a href="' . h($href) . '" class="' . h($cls) . '"' . $idAttr . ' ' . $attrs . '>' . $back . h($label) . '</a>';
        }
        return '<button type="button" class="' . h($cls) . '"' . $idAttr . $onAttr . ' ' . $attrs . '>' . $back . h($label) . '</button>';
    }

    // cancel / close
    $label = ($kind === 'close') ? '閉じる' : 'キャンセル';
    $cls = trim('btn btn-secondary ' . $extra);
    if ($href !== '') {
        return '<a href="' . h($href) . '" class="' . h($cls) . '"' . $idAttr . ' ' . $attrs . '>' . h($label) . '</a>';
    }
    return '<button type="button" class="' . h($cls) . '"' . $idAttr . $onAttr . ' ' . $attrs . '>' . h($label) . '</button>';
}

/**
 * 検索入力フィールド
 *
 * 全システムで挙動を統一: input + 200ms debounce → DOMフィルタが基本。
 * サーバ検索ページ (form submit) は trigger='form' を指定。
 *
 * @param array $opts [
 *     'id' => string,                // input id (必須)
 *     'placeholder' => string,       // プレースホルダー (推奨: '〇〇で検索...')
 *     'value' => string,             // 初期値 (form 検索時に GET から復元)
 *     'name' => string,              // 'form' トリガー時の name 属性
 *     'trigger' => 'input'|'form',   // 'input' (即時 debounce) or 'form' (Enter送信) (default: 'input')
 *     'debounce' => int,             // debounce ms (default: 200)
 *     'callback' => string,          // input トリガー時のJSコールバック関数名 (受け取る: value)
 *                                     // 例: 'myFilterFn' → myFilterFn(searchValue) が呼ばれる
 *     'class' => string,             // 追加CSSクラス (default: form-input)
 *     'width' => string,             // 幅 (例: '320px')
 *     'wrapClass' => string,         // ラッパーdivに追加するクラス
 *     'autofocus' => bool,           // autofocus
 * ]
 */
function uiSearchInput(array $opts = []): string {
    $id          = $opts['id']          ?? 'uiSearch_' . substr(md5((string)mt_rand()), 0, 8);
    $placeholder = $opts['placeholder'] ?? 'キーワードで検索...';
    $value       = $opts['value']       ?? '';
    $name        = $opts['name']        ?? '';
    $trigger     = $opts['trigger']     ?? 'input';
    $debounceMs  = (int)($opts['debounce'] ?? 200);
    $callback    = $opts['callback']    ?? '';
    $cls         = trim('ui-search-input form-input ' . ($opts['class'] ?? ''));
    $width       = $opts['width']       ?? '';
    $wrapClass   = trim('ui-search-wrap ' . ($opts['wrapClass'] ?? ''));
    $autofocus   = !empty($opts['autofocus']) ? ' autofocus' : '';
    $nameAttr    = $name ? ' name="' . h($name) . '"' : '';
    $styleAttr   = $width ? ' style="width:' . h($width) . ';"' : '';

    $nonce = function_exists('nonceAttr') ? nonceAttr() : '';

    // 共通アイコン (虫眼鏡)
    $icon = '<svg class="ui-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';

    // ラッパー
    $html = '<div class="' . h($wrapClass) . '">';
    $html .= $icon;
    $html .= '<input type="text" id="' . h($id) . '"' . $nameAttr
           . ' class="' . h($cls) . '"'
           . ' placeholder="' . h($placeholder) . '"'
           . ' value="' . h($value) . '"'
           . ' autocomplete="off"'
           . $autofocus
           . $styleAttr . '>';
    $html .= '</div>';

    // input トリガー時のみ JS bind を一緒に出力 (CSP nonce 対応)
    if ($trigger === 'input' && $callback !== '') {
        $callbackJs = json_encode($callback);
        $html .= '<script' . $nonce . '>'
              . '(function(){var el=document.getElementById(' . json_encode($id) . ');if(!el)return;'
              . 'var t=null;el.addEventListener("input",function(){clearTimeout(t);t=setTimeout(function(){'
              . 'var fn=window[' . $callbackJs . '];if(typeof fn==="function")fn(el.value);'
              . '},' . $debounceMs . ');});'
              . '})();'
              . '</script>';
    }
    return $html;
}

/**
 * 検索入力フィールド用の共通スタイルを1回だけ出力する。
 * header.php に組み込んでもよいし、ページ側で呼んでもよい。
 */
function uiSearchInputStyles(): string {
    static $emitted = false;
    if ($emitted) return '';
    $emitted = true;
    $nonce = function_exists('nonceAttr') ? nonceAttr() : '';
    return '<style' . $nonce . '>'
         . '.ui-search-wrap{position:relative;display:inline-flex;align-items:center;}'
         . '.ui-search-wrap .ui-search-icon{position:absolute;left:0.65rem;color:var(--gray-400);pointer-events:none;}'
         . '.ui-search-wrap .ui-search-input{padding-left:2rem;}'
         . '</style>';
}

/**
 * 同期ボタン
 *
 * 文言は「<source>から同期」で統一 (例: 'MFから同期', 'Sheetsから同期', 'GitHubから同期')
 *
 * @param string $source 同期元の名称 (例: 'MF', 'Sheets', 'GitHub')
 * @param array  $opts   [
 *     'id' => string,
 *     'onclick' => string,
 *     'class' => string,    // 追加クラス
 *     'attrs' => string,
 *     'busy_label' => string, // 同期中の文言 (default: '同期中…')
 *     'icon' => bool,       // 矢印循環アイコン (default: true)
 * ]
 */
function uiSyncButton(string $source, array $opts = []): string {
    $label   = $source . 'から同期';
    $id      = $opts['id']      ?? '';
    $onclick = $opts['onclick'] ?? '';
    // variant: 'primary' (デフォルト, メインアクション) / 'secondary' (セカンダリ)
    $variant = $opts['variant'] ?? 'primary';
    $baseBtn = ($variant === 'secondary') ? 'btn btn-secondary' : 'btn btn-primary';
    $cls     = trim($baseBtn . ' ' . ($opts['class'] ?? ''));
    $attrs   = $opts['attrs']   ?? '';
    $icon    = $opts['icon']    ?? true;
    $idAttr  = $id ? ' id="' . h($id) . '"' : '';
    $onAttr  = $onclick ? ' onclick="' . h($onclick) . '"' : '';

    $iconSvg = $icon
        ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right:0.35rem;vertical-align:-2px;"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>'
        : '';

    return '<button type="button" class="' . h($cls) . '"' . $idAttr . $onAttr . ' ' . $attrs
         . ' data-sync-label="' . h($label) . '"'
         . ' data-sync-busy="' . h($opts['busy_label'] ?? '同期中…') . '">'
         . $iconSvg . h($label) . '</button>';
}

/**
 * 同期ステータス表示用の inline 要素
 * 例: 「最終同期 2026-05-19 16:30」
 */
function uiSyncStatus(string $id = 'syncStatus', string $initial = ''): string {
    return '<span class="ui-sync-status" id="' . h($id) . '" style="font-size:0.78rem;color:var(--gray-500);">' . h($initial) . '</span>';
}
