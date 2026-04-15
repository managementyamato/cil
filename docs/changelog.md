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
