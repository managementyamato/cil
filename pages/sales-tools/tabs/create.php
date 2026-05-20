<?php /* 見積作成 タブ */ ?>
    <div class="st-panel <?= $activeTab === 'create' ? 'active' : '' ?>" id="panel-create" role="tabpanel">
        <div class="qb-wrap">

            <!-- AIで作成 -->
            <section class="qb-ai-row">
                <button type="button" class="qb-ai-btn" id="qbAiOpen">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L9 9l-7 1 5 5-1 7 6-3 6 3-1-7 5-5-7-1z"/>
                    </svg>
                    AIで見積を作成
                </button>
                <span class="qb-ai-hint">自然言語で指示すると、商品マスタと顧客ランクを参照して下のフォームに自動入力します</span>
            </section>

            <!-- 案件情報 -->
            <section class="qb-card">
                <h3 class="qb-card-title">案件情報</h3>
                <div class="qb-grid-2">
                    <div class="form-group">
                        <label for="qbSubject">件名</label>
                        <input type="text" id="qbSubject" class="form-input" placeholder="例: 〇〇現場 LEDビジョン設置一式">
                    </div>
                    <div class="form-group">
                        <label for="qbCustomer">顧客</label>
                        <input type="text" id="qbCustomer" class="form-input" placeholder="顧客名で検索..." autocomplete="off">
                        <div class="qb-rank-hint" id="qbRankHint" style="display:none;">
                            ランク: <span id="qbRankBadge"></span> / 主担当AM: <span id="qbAmName"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="qbIssueDate">見積日</label>
                        <input type="date" id="qbIssueDate" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="qbExpireDate">有効期限</label>
                        <input type="date" id="qbExpireDate" class="form-input">
                    </div>
                </div>
            </section>

            <!-- 見積明細 -->
            <section class="qb-card">
                <h3 class="qb-card-title">見積明細</h3>
                <p class="qb-card-sub">製品、施工費、配送費などを追加してください</p>

                <div class="qb-add-row">
                    <button type="button" class="qb-add-btn" data-add-type="product">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        製品を追加
                    </button>
                    <button type="button" class="qb-add-btn" data-add-type="install">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                        </svg>
                        施工費を追加
                    </button>
                    <button type="button" class="qb-add-btn" data-add-type="shipping">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                        </svg>
                        配送費を追加
                    </button>
                    <button type="button" class="qb-add-btn" data-add-type="other">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                        その他を追加
                    </button>
                </div>

                <!-- 明細リスト -->
                <div id="qbItemList"></div>

                <!-- 空状態 -->
                <div class="qb-empty" id="qbEmpty">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="4" y="2" width="16" height="20" rx="2"/>
                        <line x1="8" y1="6" x2="16" y2="6"/>
                        <line x1="8" y1="11" x2="10" y2="11"/>
                        <line x1="12" y1="11" x2="14" y2="11"/>
                        <line x1="14" y1="11" x2="16" y2="11"/>
                        <line x1="8" y1="15" x2="10" y2="15"/>
                        <line x1="12" y1="15" x2="14" y2="15"/>
                        <line x1="14" y1="15" x2="16" y2="15"/>
                        <line x1="8" y1="19" x2="10" y2="19"/>
                        <line x1="12" y1="19" x2="14" y2="19"/>
                    </svg>
                    <div class="qb-empty-title">明細がありません</div>
                    <div class="qb-empty-sub">上のボタンから項目を追加してください</div>
                </div>
            </section>

            <!-- 合計 -->
            <section class="qb-card qb-totals" id="qbTotals" style="display:none;">
                <div class="qb-total-row">
                    <span>小計</span>
                    <span id="qbSubtotal">0 円</span>
                </div>
                <div class="qb-total-row">
                    <span>消費税(10%)</span>
                    <span id="qbTax">0 円</span>
                </div>
                <div class="qb-total-row qb-total-grand">
                    <span>合計</span>
                    <span id="qbGrand">0 円</span>
                </div>
            </section>

            <!-- アクション -->
            <section class="qb-actions">
                <button type="button" class="qb-action-btn" id="qbResetBtn">クリア</button>
                <button type="button" class="qb-action-btn" id="qbPdfBtn">PDFダウンロード</button>
                <button type="button" class="qb-action-btn primary" id="qbSaveBtn">見積を保存</button>
            </section>

        </div>
    </div>

