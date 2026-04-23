# 変更履歴

> このファイルは CLAUDE.md の補足ドキュメントです。
> 機能追加・仕様変更を行った場合はここに記録してください。

---

## 記録する内容
- 日付
- 変更内容の概要
- 影響を受けるファイル（主要なもの）
- 関連ドキュメント（あれば）

---

## 変更履歴

| 日付 | 変更内容 | 主要ファイル | 関連ドキュメント |
|------|----------|--------------|------------------|
| 2026-02-05 | 権限システム実装（3段階権限、ページ別閲覧/編集権限） | `config/config.php`, `api/auth.php` | `docs/permission-system.md` |
| 2026-02-05 | 削除権限を管理部のみに制限 | `master.php`, `employees.php`, `masters.php`, `loans.php` | `docs/permission-system.md` |
| 2026-02-05 | ダッシュボード・サイドバーの権限制御 | `pages/index.php`, `functions/header.php` | `docs/permission-system.md` |
| 2026-02-05 | アルコールチェック：未紐付けレコードのsender_user_id照合対応 | `functions/photo-attendance-functions.php` | - |
| 2026-02-06 | CLAUDE.md拡充：危険箇所マップ、アーキテクチャ概要、機能追加パターン、テスト方法、失敗パターン、レビューチェックリスト追加 | `CLAUDE.md` | - |
| 2026-02-06 | セキュリティ強化：SSL検証有効化、自動ユーザー登録無効化、X-Forwarded-For信頼制限、CSP段階的厳格化、例外メッセージ秘匿、セッションタイムアウト環境変数化 | `api/mf-api.php`, `api/google-callback.php`, `functions/security.php`, `api/auth.php` | - |
| 2026-02-06 | 回帰防止テスト整備 | `tests/Unit/*.php`, `tests/bootstrap.php`, `pages/photo-attendance.php`, `pages/settings.php` | `CLAUDE.md` |
| 2026-02-09 | セキュリティ監査：.htaccess強化、OAuthのstateパラメータ実装、ヘルスチェック情報削減 | `.htaccess`, `api/google-oauth.php`, `api/google-callback.php`, `api/integration/health.php` | - |
| 2026-02-09 | 顧客データ暗号化：AES-256-GCMで顧客・担当者・パートナーのphone/email/addressを暗号化 | `functions/encryption.php`, `pages/customers.php`, `pages/masters.php` | - |
| 2026-02-09 | データ消失防止の多層防御：削除権限チェック修正、監査ログ追加、論理削除導入、saveDataの自動スナップショット | `config/config.php`, `functions/soft-delete.php`, `pages/customers.php` | - |
| 2026-02-09 | XSS脆弱性の緊急修正：6ファイル14箇所のinnerHTML使用箇所を修正 | `pages/photo-attendance.php`, `pages/loans.php`, `pages/finance.php` | - |
| 2026-02-09 | コードリファクタリング（レベル2）：重複CSS・JS・SVGアイコンを共通化 | `css/components.css`, `js/common-utils.js`, `js/icons.js` | - |
| 2026-02-09 | Next.js移行計画策定（レベル3） | `docs/NEXTJS-MIGRATION-PLAN.md` | `docs/NEXTJS-MIGRATION-PLAN.md` |
| 2026-02-09 | セキュリティ脆弱性修正（P0）：iconButton XSS修正、master.phpエスケープ適用 | `js/icons.js`, `pages/master.php`, `pages/customers.php` | - |
| 2026-02-09 | 情報漏洩対策（P0）：監査ログHMAC署名実装、MF APIキー環境変数化 | `functions/audit-log.php`, `api/mf-api.php`, `scripts/backup-encryption-key.php` | `docs/SECURITY-AUDIT-2026-02-09.md` |
| 2026-02-10 | 定期請求書作成機能追加 | `functions/recurring-invoice.php`, `api/recurring-invoices-api.php`, `pages/recurring-invoices.php` | - |
| 2026-02-11 | クライアントサイドページネーション追加：Paginatorクラス実装 | `js/common-utils.js`, `css/components.css`, `pages/employees.php`, `pages/troubles.php` | - |
| 2026-02-11 | 6機能追加：一括操作、横断検索、エクスポート強化、コメント機能、管理部隠しメッセージ、バグ修正 | `pages/master.php`, `pages/search.php`, `api/comments.php`, `api/export.php` | - |
| 2026-02-19 | CLAUDE.md分割・圧縮：詳細ドキュメントをdocs/に移動してコンテキスト消費を削減 | `CLAUDE.md`, `docs/patterns.md`, `docs/ng-patterns.md`, `docs/architecture.md`, `docs/testing.md`, `docs/changelog.md`, `docs/review-checklist.md` | - |
| 2026-02-25 | 社内公開前セキュリティ整備：テストアカウント削除 | `config/users.json` | - |
| 2026-02-25 | 名称変更：「製品管理部」→「製品技術部」全ファイル一括変更 | `CLAUDE.md`, `config/config.php`, `api/auth.php`, `pages/employees.php`, `functions/header.php`, `pages/user-permissions.php`, `docs/architecture.md`, `docs/manual-product.md`, `docs/manual-sales.md`, `docs/permission-system.md` | `docs/permission-system.md` |
| 2026-02-25 | ログイン画面リニューアル：favicon表示・"Yamato Gear"2色斜めテキスト・不要文言削除 | `pages/login.php` | - |
| 2026-02-25 | ルートindex.phpを刷新：古い"YA管理一覧"ページを廃止しpages/login.phpへリダイレクト化 | `index.php` | - |
| 2026-02-25 | www→非wwwリダイレクト追加 | `.htaccess` | - |
| 2026-02-25 | デプロイ安全化：PHP構文チェック・PHPUnit・diff確認・[y/N]確認プロンプトをデプロイ前チェックとして追加 | `auto-deploy.ps1` | - |
| 2026-02-25 | staging環境デプロイスクリプト新規作成 | `deploy-staging.ps1` | - |
| 2026-02-25 | マイワークスペース：メモのマークダウン対応を廃止しプレーンテキスト保存に変更 | `pages/my-workspace.php`, `api/tasks-memos.php` | - |
| 2026-02-25 | マイワークスペース：タスクに連絡先機能追加（チップUI選択・保存時メール通知・自分への連絡フィルター） | `pages/my-workspace.php`, `api/tasks-memos.php` | - |
| 2026-02-25 | タスク連絡先の選択元を users.json から従業員マスタ（data.json['employees']）に変更（退職者・削除済みは除外） | `pages/my-workspace.php`, `api/tasks-memos.php` | - |
| 2026-02-25 | タスク編集権限をメンション相手にも付与（作成者・admin・連絡先ユーザーが編集可） | `pages/my-workspace.php`, `api/tasks-memos.php` | - |
| 2026-02-25 | ログイン画面セッション切れ通知のスタイル修正（白背景カードに合わせた配色に変更） | `pages/login.php` | - |
| 2026-02-25 | スプシ同期ボタン・APIを管理部のみに制限（製品技術部から非表示化） | `pages/master.php`, `api/spreadsheet-projects.php` | - |
| 2026-02-25 | PJマスタ：案件番号の昇順/降順ソート追加（PHP側URLビルドで実装） | `pages/master.php` | - |
| 2026-02-25 | PJマスタ：chat_space_idが紐付いた案件番号をクリックでChatスペースに飛べるリンク追加 | `pages/master.php` | - |
| 2026-02-25 | スプシ同期：ソフトデリート済み案件がスプシに存在する場合に自動復元するよう修正 | `api/spreadsheet-projects.php` | - |
| 2026-02-25 | PJマスタ：レンタル・販売タグバッジに色付け（レンタル=青、販売=緑）。tagフィールド優先・現場名プレフィックスをフォールバックに統一 | `pages/master.php` | - |
| 2026-02-25 | スプシ同期：本番で手動変更したタイトルを保護する機能追加（synced_nameで前回同期値を記録し差分チェック） | `api/spreadsheet-projects.php` | - |
| 2026-02-25 | Chatスペース一覧取得API（get_spaces）を営業部でも利用可に変更。書き込み系アクションは個別にcanEdit()を維持 | `api/alcohol-chat-sync.php` | - |
| 2026-02-25 | PJマスタ新規登録のP番号自動採番をアクティブ案件のみ対象に修正（削除済みP番号が採番に影響しないよう改善） | `pages/master.php` | - |
| 2026-02-25 | マイワークスペース新規実装：タスク（共有・ステータス管理・サブタスク）＋メモ（プライベート・ピン留め・全文検索・タグ）＋連絡先メンション（メール通知） | `pages/my-workspace.php`, `api/tasks-memos.php`, `functions/data-schema.php`, `api/auth.php`, `functions/header.php` | - |
| 2026-04-10 | 値引き申請にPDF添付機能追加（Google Driveへ保存、メール添付対応） | `api/upload-discount-pdf.php`, `api/reports-hub-api.php`, `pages/reports-hub.php`, `functions/notification-functions.php`, `api/google-drive.php` | - |
| 2026-04-10 | 価格表ページ新規実装（顧客層タブ切替、製品別価格管理、一括保存） | `pages/price-list.php`, `api/price-list-api.php`, `functions/data-schema.php` | - |
| 2026-04-15 | 値引き申請にレンタル期間・販売額フィールド追加（メール通知にも表記） | `api/reports-hub-api.php`, `pages/reports-hub.php`, `api/discount-approval-action.php` | - |
| 2026-04-15 | 週報メールレイアウト変更：セクション行を1カラムに変更 | `api/reports-hub-api.php` | - |
| 2026-04-15 | 週報削除権限を本人のみに変更（管理部の他人削除を廃止） | `api/reports-hub-api.php`, `pages/reports-hub.php` | - |
| 2026-04-15 | 週報に画像・PDF添付機能追加（Google Drive連携、クライアント側画像圧縮、アップロード中送信ブロック） | `api/upload-weekly-image.php`, `api/serve-weekly-file.php`, `api/google-drive.php`, `pages/reports-hub.php` | - |
| 2026-04-15 | Google Drive保存先設定を設定ページに集約（報告ハブのモーダルを廃止） | `pages/settings.php`, `pages/reports-hub.php` | - |
| 2026-04-15 | 週報確認機能のDB対応：weekly_reportsテーブルにconfirmed_at等4カラムを追加。**⚠️ 教訓：DB_MODE=db環境では新フィールド追加時にALTER TABLEが必須。create-tables.sqlの更新だけでは本番に反映されない** | `scripts/create-tables.sql`, `api/migrate-weekly-confirm.php` | - |
| 2026-04-15 | 請求書確認ページ新規追加：MF同期済み請求書を月別・取引先別に一覧表示し確認チェックできる機能（明細展開表示、確認者記録、合計金額表示） | `pages/invoice-confirm.php`, `functions/data-schema.php`, `scripts/create-tables.sql`, `api/auth.php`, `functions/header.php` | - |
| 2026-04-20 | 指定請求書機能を一新：旧実装（HTMLテンプレート方式＋定期一括作成）を全面廃止し、ExcelテンプレートをPhpSpreadsheetでセル直書き込みする方式に刷新。アクティオ様式（営業所8拠点分）に対応。xlsx直出力＋LibreOffice経由のPDF変換対応（要soffice別途インストール）。 | `config/custom-invoice-config.php`, `functions/custom-invoice-generator.php`, `api/custom-invoice-api.php`, `pages/custom-invoice-create.php`, `uploads/invoice-templates/actio.xlsx`, `pages/mf-invoice-list.php`, `api/auth.php` | - |
| 2026-04-20 | 旧指定請求書関連ファイルを全削除（custom-invoice.php, invoice-templates.php, invoice-excel-templates.php, invoice-excel-download.php, excel-invoice-preview.php, invoice-template-viewer.php, recurring-invoices.php, invoice-templates-api.php, invoice-excel-api.php, generate-excel-invoice-api.php, download-excel-invoice.php, recurring-invoices-api.php, recurring-invoice.php, excel-invoice-generator.php, create-invoice-api.php, schedule-invoice-api.php, cron-recurring-invoices.php, test-tag-matching.php）、および設定画面の「定期請求書作成」メニューを削除 | `public_html/pages/*`, `public_html/api/*`, `public_html/functions/*`, `api/pages/settings-data.php`, `auto-deploy.ps1`, `deploy-staging.ps1`, `tests/Unit/PagePermissionTest.php` | - |
| 2026-04-20 | 指定請求書専用の一覧ページ新規作成。旧`mf-invoice-list.php`（MF請求書一覧・デバッグ機能混在）を削除し、`config/custom-invoice-config.php`に登録された取引先のMF請求書だけをクリーンに表示する`custom-invoice-list.php`に置き換え。サイドバー・設定画面のエントリも「指定請求書一覧」1つに集約 | `pages/custom-invoice-list.php`, `pages/mf-invoice-list.php`(削除), `functions/header.php`, `api/auth.php`, `public_html/api/auth.php`, `api/pages/settings-data.php`, `pages/settings.php`, `tests/Unit/PagePermissionTest.php`, `auto-deploy.ps1`, `deploy-staging.ps1` | - |
| 2026-04-20 | 指定請求書テンプレートを Google Drive 保管＋Excel「名前の定義」ベースに刷新。テンプレxlsxに `branch_name`/`billing_date_year`/`billing_date_month`/`billing_date_day`/`partner_code`/`items_table` などの名前付き範囲を設定するだけで、コード変更なしで新規取引先に対応可能に。営業所マスタは隠しシート `_branches` で管理。テンプレは設定→「指定請求書テンプレート保管先」でDriveフォルダIDを登録し、アップロードされたファイル名でMF請求書の partner_name と自動マッチング。`config/custom-invoice-config.php`（旧ハードコード設定）は削除 | `functions/custom-invoice-generator.php`, `api/custom-invoice-api.php`, `api/google-drive.php`, `pages/custom-invoice-list.php`, `pages/custom-invoice-create.php`, `pages/settings.php`, `scripts/add-named-ranges-actio.php`, `docs/custom-invoice-template-guide.md` | `docs/custom-invoice-template-guide.md` |
| 2026-04-21 | 指定請求書の複数MF請求書まとめ作成機能を追加。一覧ページにチェックボックス＋「選択した請求書を纏めて作成」ボタンを追加し、選択された複数MF請求書の明細を結合して1枚の指定請求書に出力。明細がテンプレの `items_table` 容量を超える場合は自動で分割し、複数xlsxをZIPにまとめてダウンロード。納入日は各明細ごとに元MF請求書の `sales_date` または `billing_date` を継承。営業所タグが全て一致すれば自動選択、不一致時は警告表示 | `pages/custom-invoice-list.php`, `pages/custom-invoice-create.php`, `api/custom-invoice-api.php`, `functions/custom-invoice-generator.php`, `api/google-drive.php` (共有ドライブ対応) | - |
| 2026-04-21 | MF請求書詳細取得を並列化（`curl_multi` 使用）。N件の請求書をまとめて作成する際、従来は順次取得でN倍の時間がかかっていたところ、並列実行で最も遅い1件分の時間に短縮。curl拡張が無い環境では順次取得に自動フォールバック、401時のトークンリフレッシュも対応 | `api/mf-api.php`（`getInvoiceDetailsBatch`追加）, `pages/custom-invoice-create.php`, `scripts/php.ini`（curl/libssh2/libsasl/nghttp2 有効化） | - |
| 2026-04-21 | 指定請求書テンプレートの「名前の定義」未設定を明確に検出するバリデーション追加、Driveファイル名をそのまま出力ファイル名に使用するよう改善、新規取引先テンプレ追加手順を管理者向けHTMLマニュアルページとして整備（サイドバー一覧ページと設定画面から遷移可能） | `pages/custom-invoice-manual.php`（新規）, `pages/custom-invoice-list.php`, `pages/custom-invoice-create.php`, `pages/settings.php`, `functions/custom-invoice-generator.php`, `api/custom-invoice-api.php`, `api/auth.php`, `public_html/api/auth.php`, `tests/Unit/PagePermissionTest.php` | `docs/custom-invoice-template-guide.md` |
| 2026-04-21 | 指定請求書テンプレートの「名前の定義」を不要に（自動検出を実装）。取引先から受け取った無加工のxlsxをそのままDriveに配置するだけで、ラベル文字列（納入部門名/請求日/取引先コード/品名・数量・単価・金額等）から入力セルを自動推測。年月日ラベル混在の分割日付パターン、数字ストリップによる取引先コード検出、結合セル対応済み。名前の定義が存在すればそれを優先使用。マニュアルも「基本的にExcel作業不要」に更新。Actio原本・名前定義済み両方で動作確認 | `functions/custom-invoice-generator.php`（`autoDetectTemplateRanges`等を追加）, `pages/custom-invoice-create.php`, `pages/custom-invoice-manual.php` | `docs/custom-invoice-template-guide.md` |
