<?php /* リード管理 タブ */ ?>
    <div class="st-panel <?= $activeTab === 'leads' ? 'active' : '' ?>" id="panel-leads" role="tabpanel">
        <div class="lead-wrap">

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

            <!-- ステータス別件数 -->
            <div class="lead-status-summary" id="leadStatusSummary"></div>

            <!-- 一覧テーブル -->
            <div class="lead-table-wrap">
                <table class="lead-table" id="leadTable">
                    <thead>
                        <tr>
                            <th style="width: 28%;">会社・氏名</th>
                            <th style="width: 22%;">連絡先</th>
                            <th style="width: 18%;">役職・部署</th>
                            <th style="width: 10%;">ステータス</th>
                            <th style="width: 12%;">登録日</th>
                            <th style="width: 10%;">操作</th>
                        </tr>
                    </thead>
                    <tbody id="leadTbody"></tbody>
                </table>
                <div class="lead-empty" id="leadEmpty" style="display:none;">
                    <div class="lead-empty-title">リードがまだ登録されていません</div>
                    <div class="lead-empty-sub">「名刺をスキャン」または「手動で追加」から登録してください</div>
                </div>
            </div>
        </div>
    </div>

    <!-- リード編集モーダル -->
    <div class="lead-modal" id="leadModal" aria-hidden="true">
        <div class="lead-modal-backdrop" data-close-modal></div>
        <div class="lead-modal-dialog">
            <div class="lead-modal-head">
                <h3 id="leadModalTitle">リード登録</h3>
                <button type="button" class="lead-modal-close" data-close-modal aria-label="閉じる">×</button>
            </div>
            <div class="lead-modal-body">
                <div class="lead-modal-grid">
                    <div class="lead-modal-image" id="leadModalImageWrap" style="display:none;">
                        <img id="leadModalImage" alt="名刺画像">
                    </div>
                    <div class="lead-modal-fields">
                        <div class="qb-grid-2">
                            <div class="form-group">
                                <label for="leadFCompany">会社名 <span class="lead-required">*</span></label>
                                <input type="text" id="leadFCompany" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFPerson">氏名</label>
                                <input type="text" id="leadFPerson" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFTitle">役職</label>
                                <input type="text" id="leadFTitle" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFDept">部署</label>
                                <input type="text" id="leadFDept" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFPhone">電話</label>
                                <input type="text" id="leadFPhone" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFMobile">携帯</label>
                                <input type="text" id="leadFMobile" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFEmail">メール</label>
                                <input type="text" id="leadFEmail" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFFax">FAX</label>
                                <input type="text" id="leadFFax" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFWebsite">Webサイト</label>
                                <input type="text" id="leadFWebsite" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFAm">担当営業</label>
                                <input type="text" id="leadFAm" class="form-input">
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="leadFAddress">住所</label>
                                <input type="text" id="leadFAddress" class="form-input">
                            </div>
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
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="leadFNotes">メモ</label>
                                <textarea id="leadFNotes" class="form-input" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lead-modal-foot">
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

    <!-- AI見積入力モーダル -->
    <div class="lead-modal" id="qbAiModal" aria-hidden="true">
        <div class="lead-modal-backdrop" data-close-ai-modal></div>
        <div class="lead-modal-dialog" style="width: min(640px, calc(100vw - 2rem));">
            <div class="lead-modal-head">
                <h3>AIで見積を作成</h3>
                <button type="button" class="lead-modal-close" data-close-ai-modal aria-label="閉じる">×</button>
            </div>
            <div class="lead-modal-body">
                <p class="qb-ai-help">
                    顧客名・商品・数量・施工/配送の有無などを自然な日本語で入力してください。<br>
                    例:<br>
                    ・ <em>ニッケンさんの現場でモニたろう3台、設置工事と配送費込みで</em><br>
                    ・ <em>ヤマト食品 向け 電子黒板2台 + 屋外ディスプレイ1台、一式</em>
                </p>
                <textarea id="qbAiInput" class="form-input" rows="5" placeholder="例: ニッケン(株) の新宿現場 LEDビジョン 3台 設置工事込み" style="resize: vertical;"></textarea>
                <div class="qb-ai-warn" id="qbAiWarn" style="display:none;"></div>
            </div>
            <div class="lead-modal-foot">
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

    <!-- 見積作成 -->
