# 開発環境・手順

> このファイルは CLAUDE.md の補足ドキュメントです。
> 開発サーバー・テスト・デプロイ・Worktreeに関する手順はここを参照してください。

---

## 開発サーバー

```
scripts\start-dev-server.bat
```
- PHP: `C:\xampp\php\php.exe`（php.iniは `lib/php.ini`）
- ポート: 8000 / URL: http://localhost:8000/

---

## テスト実行

機能追加・修正後は必ずテストを実行すること。失敗した場合はコミットしない。

```bash
cd C:\Claude\master
C:\xampp\php\php.exe vendor/bin/phpunit
```

> テストファイル一覧・カバレッジ詳細・手動テストチェックリストは `docs/testing.md` を参照

---

## デプロイ

```
powershell.exe -ExecutionPolicy Bypass -File "C:\Claude\master\auto-deploy.ps1"
```

## デプロイ後の本番確認（必須）

1. FTPで削除したファイルが本番サーバーに残っていないか確認
2. 本番MySQLにALTER TABLEが実行済みか確認（カラム追加時）
3. vendor/autoloadが本番に存在するか確認
4. 主要ページにアクセスして200が返るか確認

⚠️ ローカル.envの状態を本番状態として報告しない。必ず本番サーバーに対して直接確認すること。

---

## Worktreeでの作業ルール

- **メインリポジトリ**: `C:\Claude\master\`（開発サーバーはこちらを参照）
- **必須**: Worktreeでファイルを変更したら、メインリポジトリの同パスにも必ず同じ内容を書き込む

---

## 新ページ追加手順

1. **型に合った骨格テンプレートをコピーして `pages/xxx.php` を作成**
   - 一覧 / 検索 / CRUD 型 → `pages/_template-list.php`
   - 設定フォーム型 → `pages/_template-settings.php`
   - ハブ (タブ集約) 型 → `pages/_template-hub.php`
2. テンプレ先頭のコピー後手順コメントに従って `<NEW_PAGE_TITLE>` 等のプレースホルダと直接アクセス防止 die() ブロックを削除
3. `api/auth.php` の `$defaultPagePermissions` に権限を追加
4. `pages/user-permissions.php` のキーリストに新ページを追加 (タブ権限はサブキー `"<page>.php#<tab>"` で個別設定)
5. `functions/header.php` のサイドバーに権限チェック付きでリンクを追加
6. `functions/data-schema.php` にエンティティを追加 (必要に応じて)
7. テスト実行して確認

> コード例は `docs/patterns.md`「パターン1: 新規ページ追加」を参照
