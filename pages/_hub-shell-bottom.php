<?php
/**
 * ハブ共通シェル (ボトム部分)
 * _hub-shell-top.php とペアで使用する。
 */
?>
    </div><!-- /.hub-content -->
</div><!-- /.hub-page -->

<script<?= function_exists('nonceAttr') ? nonceAttr() : '' ?>>
/* ハブタブの AJAX 切替 (営業ツール同等の「ページ遷移しない」UX)。
   白フラッシュを完全に消すために .hub-page の中身だけ差し替える。
   子ページのインライン JS を安全に再実行するため、以下の対策を入れている:
     - 各 <script> 本体を IIFE でラップ (top-level let/const/var の二重宣言を回避)
     - IIFE 内で document.addEventListener('DOMContentLoaded', ...) を shim し即時実行
     - <script>/<style nonce="..."> は現行ページの nonce に貼り替え (CSP 適合)
     - data-no-ajax を持つリンクは AJAX 対象外 (通常遷移)
   失敗時は window.location で通常遷移にフォールバック。
   タグ backup-before-hub-ajax 時点が AJAX 切替前の状態。 */
(function() {
    const hubPage = document.querySelector('.hub-page');
    if (!hubPage) return;

    const currentNonce = (document.currentScript && document.currentScript.nonce) || '';

    function wrapScriptForSafeReplay(src) {
        // top-level let/const/var を関数スコープに閉じ込め、DOMContentLoaded を即時実行 shim する。
        return ';(function(){\n' +
               '  var __addOrig=document.addEventListener.bind(document);\n' +
               '  document.addEventListener=function(t,fn,o){\n' +
               '    if(t===\'DOMContentLoaded\'){ try{ setTimeout(function(){ try{fn({type:\'DOMContentLoaded\'});}catch(e){console.warn(\'[hub-ajax] DOMContentLoaded handler error\',e);} },0);}catch(e){} return; }\n' +
               '    return __addOrig(t,fn,o);\n' +
               '  };\n' +
               '  try{\n' + src + '\n  }catch(e){console.warn(\'[hub-ajax] script error\',e);}\n' +
               '  finally{ document.addEventListener=__addOrig; }\n' +
               '})();';
    }

    function adoptInjectedNodes(root) {
        // <style nonce="..."> は再生成して現行ページの nonce を付与 (CSP のため)
        root.querySelectorAll('style[nonce]').forEach(function(oldStyle) {
            const ns = document.createElement('style');
            Array.from(oldStyle.attributes).forEach(function(attr) {
                if (attr.name === 'nonce') return;
                ns.setAttribute(attr.name, attr.value);
            });
            if (currentNonce) ns.setAttribute('nonce', currentNonce);
            ns.textContent = oldStyle.textContent;
            oldStyle.parentNode.replaceChild(ns, oldStyle);
        });
        // <script> は IIFE ラップ + nonce 付与で再生成 (innerHTML 由来は実行されないため)
        root.querySelectorAll('script').forEach(function(oldScript) {
            const ns = document.createElement('script');
            Array.from(oldScript.attributes).forEach(function(attr) {
                if (attr.name === 'nonce') return;
                ns.setAttribute(attr.name, attr.value);
            });
            if (currentNonce) ns.setAttribute('nonce', currentNonce);
            if (oldScript.src) {
                ns.src = oldScript.src;
            } else {
                ns.textContent = wrapScriptForSafeReplay(oldScript.textContent || '');
            }
            oldScript.parentNode.replaceChild(ns, oldScript);
        });
    }

    let lastReqId = 0;
    async function navigateAjax(url, pushState) {
        const myReqId = ++lastReqId;
        try {
            hubPage.style.opacity = '0.55';
            hubPage.style.transition = 'opacity 0.12s ease';
            const r = await fetch(url, { credentials: 'same-origin', headers: { 'X-Hub-Ajax': '1' } });
            if (myReqId !== lastReqId) return;
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const html = await r.text();
            if (myReqId !== lastReqId) return;
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const newHubPage = doc.querySelector('.hub-page');
            if (!newHubPage) {
                window.location.href = url;
                return;
            }
            hubPage.innerHTML = newHubPage.innerHTML;
            adoptInjectedNodes(hubPage);
            const newTitle = doc.querySelector('title');
            if (newTitle) document.title = newTitle.textContent;
            if (pushState) history.pushState({ hubAjax: true }, '', url);
            hubPage.style.opacity = '1';
            window.scrollTo(0, 0);
            document.dispatchEvent(new CustomEvent('hub:contentLoaded', { detail: { url: url } }));
        } catch (err) {
            console.warn('[hub-ajax] fallback (full reload):', err && err.message);
            window.location.href = url;
        }
    }

    // クリック (.hub-tab-2 のみ対象)
    document.addEventListener('click', function(e) {
        const a = e.target.closest && e.target.closest('a.hub-tab-2');
        if (!a) return;
        if (a.target === '_blank') return;
        if (a.hasAttribute('data-no-ajax')) return;
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
        const href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#') return;
        try {
            const u = new URL(a.href, location.href);
            if (u.origin !== location.origin) return;
        } catch (_) { return; }
        e.preventDefault();
        // 即時に active を切替 (体感速度向上)
        document.querySelectorAll('a.hub-tab-2').forEach(function(t) {
            t.classList.remove('active');
            t.setAttribute('aria-current', 'false');
        });
        a.classList.add('active');
        a.setAttribute('aria-current', 'page');
        navigateAjax(a.href, true);
    });

    // ブラウザバック/フォワード
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.hubAjax) {
            navigateAjax(window.location.href, false);
        }
    });

    // ホバー時に次ページを prefetch (バックアップ策・ネットワーク時間を短縮)
    const prefetched = new Set();
    function prefetch(url) {
        if (!url || prefetched.has(url)) return;
        prefetched.add(url);
        try {
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = url;
            link.as = 'document';
            document.head.appendChild(link);
        } catch (_) {}
    }
    document.addEventListener('mouseover', function(e) {
        const a = e.target.closest && e.target.closest('a.hub-tab-2');
        if (!a || a.target === '_blank' || a.hasAttribute('data-no-ajax')) return;
        try {
            const u = new URL(a.href, location.href);
            if (u.origin === location.origin) prefetch(a.href);
        } catch (_) {}
    });
})();
</script>

<?php require_once __DIR__ . '/../functions/footer.php'; ?>
