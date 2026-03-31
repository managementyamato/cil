# 開発ガイドライン

---

## 📝 現在の作業状況（クラッシュ後の再開用・都度更新）

- **作業中のファイル**: なし
- **やったこと**: 社内マニュアル（スライド）閲覧・確認システムを新規実装
- **次にやること**: なし（ユーザー確認待ち）
- **未解決の問題**: なし

> セッション開始時にここを確認し、作業終了・区切り時に必ず更新すること。

---

### ✅ 実装済み: マイワークスペース（2026-02-25）

**新規ファイル**
| ファイル | 内容 |
|---|---|
| `pages/my-workspace.php` | タスク＋メモ タブUI（メンション・連絡先セレクター含む） |
| `api/tasks-memos.php` | CRUD API（タスク・メモ・サブタスク・ピン留め・メンション通知） |

**変更ファイル**
| ファイル | 変更内容 |
|---|---|
| `functions/data-schema.php` | `tasks` / `memos` エンティティをスキーマに追加 |
| `api/auth.php` | `my-workspace.php` に `sales` 権限を追加 |
| `functions/header.php` | サイドバーに「マイワークスペース」リンクを追加 |

**機能概要**
- **タスク**: 全ユーザー共有、ステータス管理（未着手/進行中/完了）、期日設定、サブタスク（チェックリスト）
- **連絡先メンション**: タスク作成・編集時に社員を指定 → メール通知送信、「自分への連絡」フィルター
- **メモ**: 完全プライベート（user_emailで二重チェック）、Markdownプレビュー→プレーンテキストに変更、ピン留め、全文検索、タグ
- **権限**: 編集＝作成者・admin・メンション相手、削除＝adminのみ

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

## ⚠️ Worktreeでの作業ルール

- **作業ディレクトリ**: `C:\Claude\master\.claude\worktrees\strange-lumiere\`（ブランチ: `claude/strange-lumiere`）
- **メインリポジトリ**: `C:\Claude\master\`（開発サーバーはこちらを参照）
- **必須**: Worktreeでファイルを変更したら、メインリポジトリの同パスにも必ず同じ内容を書き込む

---

## 開発サーバー

```
scripts\start-dev-server.bat
```
- PHP: `C:\xampp\php\php.exe`（php.iniは `lib/php.ini`）
- ポート: 8000 / URL: http://localhost:8000/

---

## 必須ルール（実装時）

### CSRF保護
POSTを受ける全ページ・APIに必須。コード例は `docs/patterns.md` を参照。

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();  // 必ず最初に呼ぶ
}
```

### ファイルロック
JSONファイルの読み書きには必ずファイルロックを使う。`getData()` / `saveData()` を使えばロック済み。

### セキュリティ
- HTMLへの出力は必ず `htmlspecialchars()`
- JavaScriptの `innerHTML` は必ず `escapeHtml()`
- `iconButton()` では onclick 属性を使わない（data属性+イベントリスナー）
- 削除処理は `canDelete()`、物理削除ではなく論理削除（ソフトデリート）

### 新ページ追加時
1. `api/auth.php` の `$defaultPagePermissions` に権限を追加
2. `functions/header.php` のサイドバーに権限チェック付きでリンクを追加
3. POSTフォームがあればCSRF保護を入れる
4. 削除処理がある場合は `canDelete()` チェックを追加

> 機能追加パターンのコード例は `docs/patterns.md` を参照

### UIパターン統一（必須）

新規ページ・機能を実装する際は **必ず** `docs/patterns.md` の「UI統一パターン」セクションを確認して従うこと。

**入力フィールドのクラス名（特に重要）:**
- `<input>` / `<select>` / `<textarea>` には必ず `class="form-input"` を使う
- `class="form-control"` は **CSS未定義・使用禁止**（スタイルが当たらない）
- ラッパーは `<div class="form-group">` を使う

---

## テスト（必須）

**機能追加・修正後は必ずテストを実行すること。失敗した場合はコミットしない。**

```bash
cd C:\Claude\master
C:\xampp\php\php.exe vendor/bin/phpunit
```

> テストファイル一覧・カバレッジ詳細・手動テストチェックリストは `docs/testing.md` を参照

---

## 権限システム

- `sales`（営業部）: 閲覧のみ
- `product`（製品技術部）: 閲覧・編集
- `admin`（管理部）: 全機能（削除含む）

主要関数: `hasPermission()` / `isAdmin()` / `canEdit()` / `canDelete()` / `getPageViewPermission()` / `getPageEditPermission()`

> アーキテクチャ詳細・権限フロー・依存関係は `docs/architecture.md` を参照

---

## デプロイ

```
powershell.exe -ExecutionPolicy Bypass -File "C:\Claude\master\auto-deploy.ps1"
```

---

## 参照ドキュメント

| ドキュメント | 内容 |
|---|---|
| `docs/patterns.md` | 機能追加パターン・CSRF例・API例・**UI統一パターン**（新規ページ実装時は必読） |
| `docs/ng-patterns.md` | よくある失敗パターン（NG例0〜7） |
| `docs/architecture.md` | データフロー・認証フロー・危険箇所マップ（中〜高危険） |
| `docs/testing.md` | テスト実行・手動チェックリスト・カバレッジ現況 |
| `docs/changelog.md` | 変更履歴（機能追加・仕様変更時に記録） |
| `docs/review-checklist.md` | PRレビュー前の確認リスト |
| `docs/permission-system.md` | 権限システム詳細 |
| `docs/openapi.yaml` | API仕様書（OpenAPI 3.0） |
