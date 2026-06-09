# 開発ガイドライン

---

## 🚨 最重要 #0: DB 保存モード（DB_SAVE_MODE）を絶対に変更しない

**過去 2 回 regression を起こした最も危険な設定。**

### ⛔ 絶対禁止
- 本番 `.env` の `DB_SAVE_MODE` を変更（特に `upsert` に戻す）
- `config/database.php` の `saveEntity()` 内の `env('DB_SAVE_MODE', 'full_replace')` のデフォルト値を変更
- UPSERT 関連コード (`saveEntityUpsert`) のロジック修正をテストなしで本番反映

### 過去の事故
- **2026-05-11**: UPSERT 化 → employees テーブル破損 → 全員ログイン不可 (1時間)
- **2026-05-12**: UPSERT 再投入 → weekly_reports 保存失敗 → 500 → 権限消失

### ✅ もし UPSERT を再有効化したい場合
1. ステージング環境（Cloudflare Tunnel + ローカル MySQL）を構築
2. 本番データのコピーで全エンティティの保存テスト
3. 1週間以上ステージングで運用検証
4. 失敗時の自動 full_replace フォールバックが動くことを確認
5. それでも本番では「営業時間外」かつ「即ロールバック可能体制」で投入

詳細: `docs/db-save-mode-history.md`

---

## data.json について（旧データソース・現在は廃止）

**現在の本番・ローカル開発はどちらも `DB_MODE=db`（MySQL専用）で運用。data.json は 2026-05-20 に退避済み。**

- 退避先: `backups/archived-data-json/data.json.2026-05-20_archived.json`
- データアクセスは `getData()` / `saveData()` 経由（内部で MySQL を読み書き）
- DB障害時の data.json fallback コード（[config.php:161-181](config/config.php)）は残っているが、対象ファイルが存在しないため `file_exists()` で false 落ちし、明示的に例外を投げる（=「壊れたデータで動き続ける」事故を防ぐ）
- fallback コード・関連スクリプト（`scripts/backup-data.php`, `scripts/import-json-to-mysql.php`, `scripts/lint-direct-file-ops.php`）の整理は別タスクで段階対応

---

## 🔴 致命的な危険箇所（編集禁止レベル）

| ファイル | 行 | 触ると何が起きるか |
|----------|-----|-------------------|
| `config/config.php` | 55-66 | 権限チェックが全ページで破綻 |
| `config/config.php` | 116-160 | データ消失・JSON破損 |
| `api/auth.php` | 36-52 | 認証バイパス・全ユーザーログイン不可 |
| `api/auth.php` | 82-117 | 新規ページの権限漏れ |
| `api/mf-api.php` | 88-91 | 本番環境でAPI連携が動かなくなる |

> 高危険・中危険の箇所は `docs/architecture.md` を参照

---

## AI作業許可

**以下は毎回許可を求めずに自動実行してよい:**
コードの編集・修正・追加、ファイルの作成・削除、ビルド・デプロイ・テストの実行、パッケージのインストール、開発サーバーの起動、データのインポート・エクスポート、設定ファイルの変更

**例外（明示的な許可が必要）:**
- 本番データの大量削除
- セキュリティ設定の大幅な変更
- FTP接続情報の変更
- **git checkout / git reset --hard / git rebase / git push --force / git branch -D などの破壊的Gitコマンド**

---

## 必須ルール（実装時）

### セキュリティ
- POSTを受ける全ページ・APIに `verifyCsrfToken()` 必須
- HTMLへの出力は必ず `htmlspecialchars()`
- JavaScriptの `innerHTML` は必ず `escapeHtml()`
- `iconButton()` では onclick 属性を使わない（data属性+イベントリスナー）
- 削除処理は `canDelete()`、物理削除ではなく論理削除（ソフトデリート）
- JSONファイルの読み書きには必ずファイルロックを使う（`getData()` / `saveData()` を使えばロック済み）

### UIパターン統一
- `<input>` / `<select>` / `<textarea>` には必ず `class="form-input"` を使う
- `class="form-control"` は **CSS未定義・使用禁止**
- ラッパーは `<div class="form-group">` を使う
- 新規ページは `pages/_template-list.php` / `_template-settings.php` / `_template-hub.php` を複製して始めること（`docs/patterns.md` パターン1 参照）
- モーダルは `.modal` + `.active` で開閉し、開閉には `openModal(id)` / `closeModal(id)` (`js/common-utils.js`) を使う
- **モーダルを背景クリック・Escキーで閉じるコードは禁止** — × ボタン / キャンセル / 保存の明示操作のみで閉じる（入力途中の誤操作消失を防ぐ）
- 独自モーダルクラス (`modal-backdrop` / `hub-modal` / `form-modal` 等) は新規禁止 (`docs/ui-legacy-classes.md` 参照)

### 新ページ追加・機能実装時
**実装前に必ず以下のドキュメントを読むこと:**
- `docs/development.md` — 手順（新ページ追加・開発サーバー・テスト・デプロイ）
- `docs/patterns.md` — コード例・APIパターン・UIパターン
- `docs/ng-patterns.md` — よくある失敗パターン（同じミスを繰り返さないために必読）

### テスト実行時
- `docs/testing.md` を読んでから実行すること（テストファイル一覧・カバレッジ・手動チェックリスト）

### コミット・PR作成前
- `docs/review-checklist.md` を読んで全項目を確認すること

### 機能追加・仕様変更後
- `docs/changelog.md` に変更内容を記録すること

### データフィールド追加時（⚠️ DB_MODE=db）
- `scripts/create-tables.sql` にカラムを追加するだけでは**本番に反映されない**
- **本番MySQLに ALTER TABLE を実行する**こと（マイグレーションスクリプト作成）
- 2026-04-15に `confirmed_at` 未追加でsaveDataが無言で失敗する事故が発生

### API実装・修正時
- `docs/openapi.yaml` を確認し、変更があれば仕様書も更新すること

---

## 権限システム

- `sales`（営業部）: 閲覧のみ
- `product`（製品技術部）: 閲覧・編集
- `admin`（管理部）: 全機能（削除含む）

主要関数: `hasPermission()` / `isAdmin()` / `canEdit()` / `canDelete()` / `getPageViewPermission()` / `getPageEditPermission()`

> 詳細は `docs/permission-system.md` を参照

---

## 参照ドキュメント

| ドキュメント | 内容 |
|---|---|
| `docs/development.md` | 開発サーバー・テスト・デプロイ・Worktree・新ページ追加手順 |
| `docs/patterns.md` | 機能追加パターン・CSRF例・API例・UIパターン |
| `docs/ng-patterns.md` | よくある失敗パターン（NG例0〜7） |
| `docs/architecture.md` | データフロー・認証フロー・危険箇所マップ |
| `docs/testing.md` | テスト実行・手動チェックリスト・カバレッジ現況 |
| `docs/changelog.md` | 変更履歴（機能追加・仕様変更時に記録） |
| `docs/review-checklist.md` | PRレビュー前の確認リスト |
| `docs/permission-system.md` | 権限システム詳細 |
| `docs/openapi.yaml` | API仕様書（OpenAPI 3.0） |
| `docs/ui-legacy-classes.md` | 既存ページの独自UIクラス棚卸し（段階移行対象） |
| `docs/feature-removal-checklist.md` | 機能削除時の手順とチェックリスト（並列調査→削除→デプロイ→FTP確認） |
| `docs/background-job-pattern.md` | バックグラウンドジョブのチャンク式ポーリングパターン (start+process設計) |
| `docs/cms-news-setup.md` | HP更新(CMS) 本番セットアップ手順 (GitHub PAT・接続テスト等) |
| `docs/local-setup.md` | ローカル開発環境セットアップ（MySQL・PHPサーバー・テストログイン手順） |
| `docs/e2e-tests.md` | Playwright E2E テスト（npm test）の使い方・テスト追加指針 |
