<?php /* 価格表 タブ */ ?>
    <div class="st-panel <?= $activeTab === 'pricing' ? 'active' : '' ?>" id="panel-pricing" role="tabpanel">
        <div class="pp-wrap">

            <!-- 一覧ビュー -->
            <div class="pp-card" id="ppListView">
                <div class="pp-card-head">
                    <div>
                        <div class="pp-card-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="9" y1="13" x2="15" y2="13"/>
                                <line x1="9" y1="17" x2="15" y2="17"/>
                            </svg>
                            価格表一覧
                        </div>
                        <div class="pp-card-sub">クリックすると価格表をサイト内で表示します</div>
                    </div>
                    <div class="pp-head-actions">
                        <!-- さっと価格を調べる: ウィザードモーダル起動 -->
                        <button type="button" class="qb-action-btn primary" id="ppQuickQuoteBtn" title="3問に答えるだけで価格が出ます">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            さっと価格を調べる
                        </button>
                        <!-- 検索バー（一覧フィルタ用） -->
                        <div class="pp-search-wrap">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="ppListSearch" class="pp-search-input" placeholder="製品名で絞り込み…" autocomplete="off">
                        </div>
                        <span id="ppSyncStatus" class="pp-sync-status" style="display:none;"></span>
                        <?php if (isAdmin()): ?>
                        <a href="/pages/price-master.php" class="qb-action-btn" title="価格表マスタの編集ページへ" style="text-decoration:none;display:inline-flex;align-items:center;gap:0.3rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            マスタを編集
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 空状態（データなし） -->
                <div class="pp-empty-hero" id="ppEmptyHero" style="display:none;">
                    <div class="pp-empty-hero-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                    </div>
                    <div class="pp-empty-hero-title">価格表がまだありません</div>
                    <?php if (isAdmin()): ?>
                    <div class="pp-empty-hero-desc">価格表マスタ管理ページで登録してください。</div>
                    <a href="/pages/price-master.php" class="qb-action-btn primary pp-empty-hero-cta" style="text-decoration:none;display:inline-block;">マスタ管理ページを開く</a>
                    <?php else: ?>
                    <div class="pp-empty-hero-desc">管理部に価格登録を依頼してください。</div>
                    <?php endif; ?>
                </div>

                <div class="pp-product-list" id="ppProductList">
                    <!-- JS で動的描画 -->
                </div>
            </div>

            <!-- さっと価格を調べる: ウィザードモーダル -->
            <div class="pp-quote-modal" id="ppQuoteModal" style="display:none;">
                <div class="pp-quote-backdrop"></div>
                <div class="pp-quote-dialog">
                    <button type="button" class="pp-quote-close" id="ppQuoteClose" title="閉じる">×</button>
                    <h3 class="pp-quote-title">さっと価格を調べる</h3>
                    <p class="pp-quote-sub">3問に答えると価格が出ます。新人でも迷わない設計です。</p>

                    <div class="pp-quote-step">
                        <div class="pp-quote-step-head"><div class="pp-quote-num">1</div><div class="pp-quote-step-title">お客様タイプを選択</div></div>
                        <div class="pp-quote-choices" id="ppQqTiers">
                            <button class="pp-quote-choice" data-tier="S"><div class="pp-quote-choice-title">上位ディーラー</div><div class="pp-quote-choice-sub">大興物産・レンタルニッケン等</div></button>
                            <button class="pp-quote-choice" data-tier="A"><div class="pp-quote-choice-title">標準ディーラー</div><div class="pp-quote-choice-sub">それ以外のディーラー様</div></button>
                            <button class="pp-quote-choice" data-tier="B"><div class="pp-quote-choice-title">新規開拓・直販</div><div class="pp-quote-choice-sub">エンドユーザー直接</div></button>
                        </div>
                    </div>

                    <div class="pp-quote-step">
                        <div class="pp-quote-step-head"><div class="pp-quote-num">2</div><div class="pp-quote-step-title">製品と取引形態を選択</div></div>
                        <div class="pp-quote-row">
                            <select id="ppQqProduct" class="form-input pp-quote-select"><option value="">製品を選択…</option></select>
                            <select id="ppQqVariant" class="form-input pp-quote-select" disabled><option value="">サイズ・型番…</option></select>
                        </div>
                        <div class="pp-quote-choices" style="margin-top:0.6rem;" id="ppQqModes">
                            <button class="pp-quote-choice" data-mode="sale"><div class="pp-quote-choice-title">販売</div><div class="pp-quote-choice-sub">買い切り</div></button>
                            <button class="pp-quote-choice" data-mode="rent-1"><div class="pp-quote-choice-title">短期レンタル</div><div class="pp-quote-choice-sub">1〜3ヶ月（①月額）</div></button>
                            <button class="pp-quote-choice" data-mode="rent-2"><div class="pp-quote-choice-title">中期レンタル</div><div class="pp-quote-choice-sub">3〜6ヶ月（②月額）</div></button>
                            <button class="pp-quote-choice" data-mode="rent-3"><div class="pp-quote-choice-title">長期レンタル</div><div class="pp-quote-choice-sub">6ヶ月〜（③月額）</div></button>
                        </div>
                    </div>

                    <div class="pp-quote-result" id="ppQqResult" style="display:none;">
                        <div class="pp-quote-result-label">提示価格</div>
                        <div class="pp-quote-result-price" id="ppQqPrice">¥-</div>
                        <div class="pp-quote-result-explain" id="ppQqExplain"></div>
                        <div class="pp-quote-result-actions">
                            <button type="button" class="qb-action-btn" id="ppQqCopy">クリップボードにコピー</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 詳細ビュー（クリック後表示） -->
            <div class="pp-detail" id="ppDetailView" style="display:none;">
                <div class="pp-detail-head">
                    <button type="button" class="pp-back" id="ppBack">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="19" y1="12" x2="5" y2="12"/>
                            <polyline points="12 19 5 12 12 5"/>
                        </svg>
                        価格表一覧に戻る
                    </button>
                    <h3 class="pp-detail-title" id="ppDetailTitle">—</h3>
                    <div class="pp-search-wrap" style="margin-left:auto;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="ppDetailSearch" class="pp-search-input" placeholder="型番・サイズ・価格で絞り込み…" autocomplete="off">
                    </div>
                </div>
                <div class="pp-detail-body" id="ppDetailBody"></div>
            </div>

        </div>
    </div>

    <!-- カタログ -->
