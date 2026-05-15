# ローカル開発環境セットアップ

本番（DB_MODE=db, MySQL）と同じ条件でローカル開発するための手順。

E2E テスト（Playwright）もこの構成を前提とする。

---

## 必要なもの

- **PHP 8.2+** — `scripts/php.exe` が同梱
- **MySQL 5.7+ / MariaDB 10+** — XAMPP 経由（`C:/xampp/mysql/bin/mysql.exe`）が手軽
- **Node.js 18+** — Playwright 用

---

## 1. MySQL データベース作成

```powershell
"C:/xampp/mysql/bin/mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS adyamato_gear CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

XAMPP コントロールパネルから MySQL を Start しておくこと。

## 2. スキーマ流し込み

```powershell
"C:/xampp/mysql/bin/mysql.exe" -u root adyamato_gear < scripts/create-tables.sql
```

## 3. data.json から初期データ取り込み

```powershell
scripts/php.exe scripts/import-json-to-mysql.php
```

このスクリプトは:
- `data.json` を読み込み、id 重複を排除してから MySQL に流し込む
- DB_MODE 設定に関わらず常に MySQL モードで実行
- 完了後、主要テーブルの行数をレポート

## 4. `.env.local` の確認

```ini
APP_ENV=local
DB_MODE=dual       # ← MySQL と data.json の両方に書き込む
DB_HOST=localhost
DB_PORT=3306
DB_NAME=adyamato_gear
DB_USER=root
DB_PASS=
```

`DB_MODE` の選択肢:
- `json`: data.json のみ（MySQL 不要、ただし本番と挙動差あり）
- `dual`: 両方に書き込み（**推奨** — 本番再現度が高い）
- `db`: MySQL のみ（本番と同じ）

## 5. 開発サーバー起動

```powershell
scripts/php.exe -S localhost:8000 router.php
```

**重要**: `router.php` を必ず指定すること。これが `.htaccess` 相当の URL 書き換え（拡張子なし URL → `.php` ファイル）をするので、指定し忘れると `/pages/troubles` のようなクリーン URL がダッシュボードにフォールバックする。

## 6. ログイン

ブラウザで http://localhost:8000/ にアクセス → Google ログイン or テスト用バックドア：

```
http://localhost:8000/pages/test-login.php?email=managementsupport@yamato-agency.com
```

`pages/test-login.php` は `APP_ENV=local` 時のみ動作し、`auto-deploy.ps1` のデプロイ除外パターン `test-*.php` でデプロイ時に自動削除される。

---

## トラブルシューティング

### 「ボタンを押したらダッシュボードに飛ばされる」

`router.php` 無しで PHP サーバーが起動している可能性大。ターミナルで実行中のコマンドを確認し、`-S localhost:8000 router.php` の形になっていなかったら再起動。

### `SQLSTATE[HY000] [1049] Unknown database 'adyamato_gear'`

DB 作成手順（手順 1）を未実行。または XAMPP MySQL が起動していない。

### `Duplicate entry 'XXX' for key 'PRIMARY'` がインポートで出る

`scripts/import-json-to-mysql.php` は dedupe 処理を内蔵している。それでも出る場合は data.json のフォーマット異常を疑う。

### Playwright テストが「test-login failed」で落ちる

`APP_ENV=local` が `.env.local` に書かれているか確認。本番デプロイ環境では test-login.php は 403 を返す（意図された動作）。
