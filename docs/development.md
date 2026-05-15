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

1. **`templates/page-template.php` をコピーして `pages/xxx.php` を作成**
2. テンプレート内の `TODO` コメントを全て置換
3. `api/auth.php` の `$defaultPagePermissions` に権限を追加
4. `functions/header.php` のサイドバーに権限チェック付きでリンクを追加
5. `functions/data-schema.php` にエンティティを追加（必要に応じて）
6. テスト実行して確認

> コード例は `docs/patterns.md`「パターン1: 新規ページ追加」を参照
