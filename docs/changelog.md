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
