<?php /* 製品別 タブ */ ?>
    <div class="st-panel <?= $activeTab === 'products' ? 'active' : '' ?>" id="panel-products" role="tabpanel">
        <div class="st-product-grid" id="stProductGrid">
            <?php foreach ($products as $p): ?>
                <?php
                $hasUrl   = !empty($p['web_url']);
                $tagName  = $hasUrl ? 'a' : 'div';
                $extraAttr = $hasUrl
                    ? ' href="' . htmlspecialchars($p['web_url']) . '" target="_blank" rel="noopener" title="' . htmlspecialchars($p['name_ja']) . ' の製品Webサイトを新規タブで開く"'
                    : '';
                ?>
            <<?= $tagName ?> class="st-product-card<?= $hasUrl ? ' is-clickable' : '' ?>"
                 data-search-name="<?= htmlspecialchars(mb_strtolower($p['name_ja'] . ' ' . $p['name_en'] . ' ' . $p['category'] . ' ' . $p['description'])) ?>"
                 <?= $extraAttr ?>>
                <?php if ($hasUrl): ?>
                <span class="st-product-web-link" aria-hidden="true">
                    <?= renderLinkIcon($p['web_icon'] ?? 'globe', 16) ?>
                </span>
                <?php endif; ?>
                <div class="st-product-visual" aria-hidden="true">
                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2"/>
                        <line x1="8" y1="21" x2="16" y2="21"/>
                        <line x1="12" y1="17" x2="12" y2="21"/>
                    </svg>
                </div>
                <div>
                    <p class="st-product-name"><?= htmlspecialchars($p['name_ja']) ?></p>
                    <div class="st-product-name-en"><?= htmlspecialchars($p['name_en']) ?></div>
                </div>
                <p class="st-product-description"><?= htmlspecialchars($p['category']) ?> - <?= htmlspecialchars($p['description']) ?></p>
                <div class="st-product-tags">
                    <?php if (!empty($p['has_price'])): ?>
                    <span class="st-tag price">価格表</span>
                    <?php endif; ?>
                    <?php if ((int)$p['catalog_count'] > 0): ?>
                    <span class="st-tag catalog">カタログ <?= (int)$p['catalog_count'] ?>件</span>
                    <?php endif; ?>
                    <?php if ((int)$p['script_count'] > 0): ?>
                    <span class="st-tag script">スクリプト <?= (int)$p['script_count'] ?>件</span>
                    <?php endif; ?>
                </div>
            </<?= $tagName ?>>
            <?php endforeach; ?>
        </div>
        <div class="st-empty" id="stEmptyState" style="display: none;">
            <div class="empty-title">該当する製品がありません</div>
            <div>検索キーワードを変更してください</div>
        </div>
    </div>

    <!-- 価格表 -->
