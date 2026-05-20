<?php
/**
 * ハブ共通シェル (ボトム部分)
 * _hub-shell-top.php とペアで使用する。
 */
?>
    </div><!-- /.hub-content -->
</div><!-- /.hub-page -->

<script<?= function_exists('nonceAttr') ? nonceAttr() : '' ?>>
/* ハブタブのホバー時に次ページを prefetch しておく。
   PHP レンダリングを事前完了させることで、クリック後の白フラッシュ時間を短縮する。
   View Transitions API (style.css の @view-transition) と併用する補助策。 */
(function() {
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
        if (!a || a.target === '_blank') return;
        try {
            const u = new URL(a.href, location.href);
            if (u.origin === location.origin) prefetch(a.href);
        } catch (_) {}
    });
    document.addEventListener('touchstart', function(e) {
        const a = e.target.closest && e.target.closest('a.hub-tab-2');
        if (a) prefetch(a.href);
    }, { passive: true });
})();
</script>

<?php require_once __DIR__ . '/../functions/footer.php'; ?>
