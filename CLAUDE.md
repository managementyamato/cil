# 開発ガイドライン

---

## 🚨 最重要: data.json は絶対に直接触らない

**data.json はシステム全体のデータベース。破損すると全データ消失・ログイン不可になる。（2026-02-11に実際に発生）**

### ⛔ 絶対禁止
- data.json を手動編集・削除・上書き
- `file_get_contents` / `file_put_contents` で直接読み書き
- AIがdata.jsonを一時的にでも上書きする（2026-02-16に実際に発生）

### ✅ 必須
- 必ず `getData()` / `saveData()` 経由でアクセス
- 復元が必要な場合: `php scripts/backup-data.php --restore=日時`

---

## 🔴 致命的な危険箇所（編集禁止レベル）

| ファイル | 行 | 触ると何が起きるか |
|----------|-----|-------------------|
| `data.json` | 全体 | 全データ消失（必ずgetData/saveData経由） |
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
