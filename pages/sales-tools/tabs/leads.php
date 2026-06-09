<?php /* リード管理 タブ */ ?>
    <div class="st-panel <?= $activeTab === 'leads' ? 'active' : '' ?>" id="panel-leads" role="tabpanel">
        <div class="lead-wrap">

            <!-- サブタブ (Phase 2: 名刺 / リード) -->
            <nav class="lead-subtabs" role="tablist" aria-label="名刺・リード切替">
                <button type="button" class="lead-subtab" data-lead-subtab="cards" role="tab">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    名刺 <span class="lead-subtab-count" id="leadCardsCount">0</span>
                </button>
                <button type="button" class="lead-subtab active" data-lead-subtab="leads" role="tab">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                    リード <span class="lead-subtab-count" id="leadLeadsCount">0</span>
                </button>
            </nav>

            <!-- ════════════════════════════════════════════════════════════ -->
            <!--  名刺 サブパネル                                                -->
            <!-- ════════════════════════════════════════════════════════════ -->
            <div class="lead-subpanel" id="leadSubpanel-cards" role="tabpanel" hidden>
                <div class="lead-toolbar">
                    <div class="lead-toolbar-left">
                        <input type="text" id="leadCardSearch" class="form-input lead-search" placeholder="会社名・氏名・電話・メールで検索...">
                        <select id="leadCardPromotedFilter" class="form-input lead-status-filter">
                            <option value="">すべての名刺</option>
                            <option value="unpromoted">未昇格のみ</option>
                            <option value="promoted">昇格済のみ</option>
                        </select>
                    </div>
                    <div class="lead-toolbar-right">
                        <button type="button" class="qb-action-btn" id="leadCardAddBtn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            手動で追加
                        </button>
                        <button type="button" class="qb-action-btn primary" id="leadScanBtn" title="複数枚まとめて選択できます">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                <circle cx="12" cy="13" r="4"/>
                            </svg>
                            名刺をスキャン
                        </button>
                        <input type="file" id="leadScanInput" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" multiple style="display:none;">
                    </div>
                </div>

                <div class="bc-list" id="leadCardsList"></div>
                <div class="lead-empty" id="leadCardsEmpty" style="display:none;">
                    <div class="lead-empty-title">名刺がまだ登録されていません</div>
                    <div class="lead-empty-sub">「名刺を追加」から登録してください</div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════ -->
            <!--  リード サブパネル (既存)                                       -->
            <!-- ════════════════════════════════════════════════════════════ -->
            <div class="lead-subpanel active" id="leadSubpanel-leads" role="tabpanel">
                <!-- ツールバー -->
                <div class="lead-toolbar">
                    <div class="lead-toolbar-left">
                        <input type="text" id="leadSearch" class="form-input lead-search" placeholder="会社名・氏名・電話・メールで検索...">
                        <select id="leadStatusFilter" class="form-input lead-status-filter">
                            <option value="">すべてのステータス</option>
                            <option value="新規">新規</option>
                            <option value="接触済">接触済</option>
                            <option value="商談中">商談中</option>
                            <option value="成約">成約</option>
                            <option value="失注">失注</option>
                        </select>
                    </div>
                    <div class="lead-toolbar-right">
                        <button type="button" class="qb-action-btn" id="leadAddBtn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            手動で追加
                        </button>
                        <span style="color:var(--gray-500);font-size:0.78rem;">名刺スキャンは「名刺」タブから</span>
                    </div>
                </div>

                <!-- 一覧 (カード形式) -->
                <div class="lead-list" id="leadList"></div>
                <div class="lead-empty" id="leadEmpty" style="display:none;">
                    <div class="lead-empty-title">リードがまだ登録されていません</div>
                    <div class="lead-empty-sub">「名刺をスキャン」または「手動で追加」から登録してください</div>
                </div>
            </div>
        </div>
    </div>

    <!-- リード編集モーダル (標準 modal 規格) -->
    <div class="modal" id="leadModal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="leadModalTitle">リード登録</h3>
                <button type="button" class="modal-close" data-close-modal aria-label="閉じる">&times;</button>
            </div>
            <div class="modal-body">
                <div class="lead-modal-grid">
                    <div class="lead-modal-image" id="leadModalImageWrap" style="display:none;">
                        <img id="leadModalImage" alt="名刺画像">
                    </div>
                    <div class="lead-modal-fields">

                        <!-- 基本情報 -->
                        <div class="lead-section-title">基本情報</div>
                        <div class="qb-grid-2">
                            <div class="form-group">
                                <label for="leadFAm">担当者 (社内) <span class="lead-required">*</span></label>
                                <input type="text" id="leadFAm" class="form-input" placeholder="例: 鈴木 / 西井 / 浅井">
                            </div>
                            <div class="form-group">
                                <label for="leadFEmail">メール</label>
                                <input type="text" id="leadFEmail" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFCompany">ディーラー名 (仲介業者) <span class="lead-required">*</span></label>
                                <input type="text" id="leadFCompany" class="form-input" placeholder="例: セフテック株式会社">
                            </div>
                            <div class="form-group">
                                <label for="leadFDealerBranch">営業所名</label>
                                <input type="text" id="leadFDealerBranch" class="form-input" placeholder="例: 名古屋支店">
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="leadFPerson">担当者名 (ディーラー側) <span class="lead-required">*</span></label>
                                <input type="text" id="leadFPerson" class="form-input" placeholder="例: 大野 太郎">
                            </div>
                        </div>

                        <!-- 現場情報 -->
                        <div class="lead-section-title">現場情報</div>
                        <div class="qb-grid-2">
                            <div class="form-group">
                                <label for="leadFPrefecture">都道府県 <span class="lead-required">*</span></label>
                                <select id="leadFPrefecture" class="form-input">
                                    <option value="">選択してください</option>
                                    <?php
                                    $prefs = ['北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県',
                                              '茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県',
                                              '新潟県','富山県','石川県','福井県','山梨県','長野県',
                                              '岐阜県','静岡県','愛知県','三重県',
                                              '滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県',
                                              '鳥取県','島根県','岡山県','広島県','山口県',
                                              '徳島県','香川県','愛媛県','高知県',
                                              '福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県'];
                                    foreach ($prefs as $p) {
                                        echo '<option value="' . htmlspecialchars($p) . '">' . htmlspecialchars($p) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="leadFEndUser">ゼネコン名 (エンドユーザー)</label>
                                <input type="text" id="leadFEndUser" class="form-input" placeholder="例: 内藤建設">
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="leadFSite">現場名</label>
                                <input type="text" id="leadFSite" class="form-input" placeholder="例: ○○ビル新築工事">
                            </div>
                        </div>

                        <!-- 販売情報 -->
                        <div class="lead-section-title">販売情報</div>
                        <div class="qb-grid-2">
                            <div class="form-group">
                                <label for="leadFTxnType">販売/レンタル <span class="lead-required">*</span></label>
                                <select id="leadFTxnType" class="form-input">
                                    <option value="">選択してください</option>
                                    <option value="sale">販売</option>
                                    <option value="rental_12m">レンタル (12ヶ月)</option>
                                    <option value="rental_24m">レンタル (24ヶ月)</option>
                                    <option value="rental_long">レンタル (長期)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="leadFProductSize">サイズ</label>
                                <input type="text" id="leadFProductSize" class="form-input" placeholder="例: 65インチ">
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="leadFProductName">製品名 <span class="lead-required">*</span></label>
                                <input type="text" id="leadFProductName" class="form-input" placeholder="例: モニすけ OB3.0Lite">
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="leadFNotes">営業メモ</label>
                                <textarea id="leadFNotes" class="form-input" rows="2" placeholder="案件背景・特記事項など"></textarea>
                            </div>
                        </div>

                        <!-- 商談情報 -->
                        <div class="lead-section-title">商談情報</div>
                        <div class="qb-grid-3">
                            <div class="form-group">
                                <label for="leadFCloseDate">納期</label>
                                <input type="date" id="leadFCloseDate" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFConfidence">確度</label>
                                <select id="leadFConfidence" class="form-input">
                                    <option value="">未設定</option>
                                    <option value="1">1 (低)</option>
                                    <option value="2">2</option>
                                    <option value="3">3 (中)</option>
                                    <option value="4">4</option>
                                    <option value="5">5 (高)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="leadFQuoteStatus">見積書</label>
                                <select id="leadFQuoteStatus" class="form-input">
                                    <option value="">未</option>
                                    <option value="発行前">発行前</option>
                                    <option value="発行済">発行済</option>
                                    <option value="承認待ち">承認待ち</option>
                                </select>
                            </div>
                        </div>

                        <!-- ステータス -->
                        <div class="lead-section-title">ステータス</div>
                        <div class="form-group">
                            <label for="leadFStatus">ステータス</label>
                            <select id="leadFStatus" class="form-input">
                                <option value="新規">新規</option>
                                <option value="接触済">接触済</option>
                                <option value="商談中">商談中</option>
                                <option value="成約">成約</option>
                                <option value="失注">失注</option>
                            </select>
                        </div>

                    </div>
                </div>

                <!-- タイムライン (編集時のみ表示。新規作成時は非表示) -->
                <div id="leadTimelineSection" class="lead-timeline-section" style="display:none;">
                    <div class="lead-timeline-head">
                        <h4 class="lead-timeline-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:4px;">
                                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                            </svg>
                            タイムライン
                        </h4>
                        <span class="lead-timeline-hint">ステータス変更は自動記録されます。手動で進捗メモも追加できます。</span>
                    </div>
                    <div class="lead-timeline-add">
                        <select id="leadTlType" class="form-input lead-timeline-type">
                            <option value="manual_note">メモ</option>
                            <option value="meeting">商談・訪問</option>
                            <option value="quote">見積関連</option>
                        </select>
                        <textarea id="leadTlBody" class="form-input lead-timeline-body" rows="2" placeholder="進捗・気付きを入力 (Enter で改行、ボタンで送信)"></textarea>
                        <button type="button" class="qb-action-btn primary" id="leadTlAddBtn">追加</button>
                    </div>
                    <div id="leadTimelineList" class="lead-timeline-list">
                        <div class="lead-timeline-loading">読み込み中…</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="qb-action-btn" data-close-modal>キャンセル</button>
                <button type="button" class="qb-action-btn primary" id="leadSaveBtn">保存</button>
            </div>
        </div>
    </div>

    <!-- OCR解析中オーバーレイ -->
    <div class="lead-scan-overlay" id="leadScanOverlay" aria-hidden="true">
        <div class="lead-scan-spinner"></div>
        <div class="lead-scan-text" id="leadScanOverlayText">名刺を解析中…</div>
    </div>

    <!-- AI見積入力モーダル (標準 modal 規格) -->
    <div class="modal" id="qbAiModal" aria-hidden="true">
        <div class="modal-content" style="max-width: 640px;">
            <div class="modal-header">
                <h3>AIで見積を作成</h3>
                <button type="button" class="modal-close" data-close-ai-modal aria-label="閉じる">&times;</button>
            </div>
            <div class="modal-body">
                <p class="qb-ai-help">
                    顧客名・商品・数量・施工/配送の有無などを自然な日本語で入力してください。<br>
                    例:<br>
                    ・ <em>ニッケンさんの現場でモニたろう3台、設置工事と配送費込みで</em><br>
                    ・ <em>ヤマト食品 向け 電子黒板2台 + 屋外ディスプレイ1台、一式</em>
                </p>
                <textarea id="qbAiInput" class="form-input" rows="5" placeholder="例: ニッケン(株) の新宿現場 LEDビジョン 3台 設置工事込み" style="resize: vertical;"></textarea>
                <div class="qb-ai-warn" id="qbAiWarn" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="qb-action-btn" data-close-ai-modal>キャンセル</button>
                <button type="button" class="qb-action-btn primary" id="qbAiSubmit">生成して反映</button>
            </div>
        </div>
    </div>

    <!-- AI生成中オーバーレイ -->
    <div class="lead-scan-overlay" id="qbAiOverlay" aria-hidden="true">
        <div class="lead-scan-spinner"></div>
        <div class="lead-scan-text">AIが見積を作成中…</div>
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!--  名刺 編集モーダル (Phase 2 / 標準 modal 規格)                  -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div class="modal" id="leadCardModal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="leadCardModalTitle">名刺登録</h3>
                <button type="button" class="modal-close" data-close-card-modal aria-label="閉じる">&times;</button>
            </div>
            <div class="modal-body">
                <div class="lead-modal-image" id="leadCardModalImageWrap" style="display:none;margin-bottom:1rem;">
                    <img id="leadCardModalImage" alt="名刺画像" style="max-width:100%;border-radius:8px;border:1px solid var(--gray-200);">
                </div>

                <!-- 会社・担当者 -->
                <div class="lead-section-title">会社・担当者</div>
                <div class="qb-grid-2">
                    <div class="form-group">
                        <label for="leadCardFCompany">会社名 <span class="lead-required">*</span></label>
                        <input type="text" id="leadCardFCompany" class="form-input" placeholder="例: セフテック株式会社">
                    </div>
                    <div class="form-group">
                        <label for="leadCardFPerson">氏名 <span class="lead-required">*</span></label>
                        <input type="text" id="leadCardFPerson" class="form-input" placeholder="例: 大野 太郎">
                    </div>
                    <div class="form-group">
                        <label for="leadCardFTitle">役職</label>
                        <input type="text" id="leadCardFTitle" class="form-input" placeholder="例: 営業部長">
                    </div>
                    <div class="form-group">
                        <label for="leadCardFDept">部署</label>
                        <input type="text" id="leadCardFDept" class="form-input" placeholder="例: 営業第二課">
                    </div>
                </div>

                <!-- 連絡先 -->
                <div class="lead-section-title">連絡先</div>
                <div class="qb-grid-2">
                    <div class="form-group">
                        <label for="leadCardFPhone">電話</label>
                        <input type="text" id="leadCardFPhone" class="form-input" placeholder="例: 052-123-4567">
                    </div>
                    <div class="form-group">
                        <label for="leadCardFMobile">携帯</label>
                        <input type="text" id="leadCardFMobile" class="form-input" placeholder="例: 090-1234-5678">
                    </div>
                    <div class="form-group">
                        <label for="leadCardFEmail">メール</label>
                        <input type="text" id="leadCardFEmail" class="form-input" placeholder="例: ono@example.co.jp">
                    </div>
                    <div class="form-group">
                        <label for="leadCardFFax">FAX</label>
                        <input type="text" id="leadCardFFax" class="form-input" placeholder="例: 052-123-4568">
                    </div>
                    <div class="form-group">
                        <label for="leadCardFWebsite">Webサイト</label>
                        <input type="text" id="leadCardFWebsite" class="form-input" placeholder="例: https://example.co.jp">
                    </div>
                    <div class="form-group">
                        <label for="leadCardFExchanged">名刺交換日</label>
                        <input type="date" id="leadCardFExchanged" class="form-input">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="leadCardFAddress">住所</label>
                        <input type="text" id="leadCardFAddress" class="form-input" placeholder="例: 愛知県名古屋市中区栄1-1-1">
                    </div>
                </div>

                <!-- メモ -->
                <div class="lead-section-title">メモ</div>
                <div class="form-group">
                    <textarea id="leadCardFNotes" class="form-input" rows="2" placeholder="交換時の状況や備考など"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="qb-action-btn" data-close-card-modal>キャンセル</button>
                <button type="button" class="qb-action-btn primary" id="leadCardSaveBtn">保存</button>
            </div>
        </div>
    </div>

    <!-- 見積作成 -->
