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

## 3. 初期データ取り込み

本番 MySQL のダンプを取得して流し込むか、または `scripts/create-tables.sql` 投入後に空 DB のままで動作確認する。

旧 `scripts/import-json-to-mysql.php`（data.json から流し込むスクリプト）は 2026-05-20 に `backups/archived-data-json/scripts/` へ退避済み。data.json 自体も退避済みのため、現在は使用しない。

## 4. `.env.local` の確認

```ini
APP_ENV=local
DB_MODE=db         # ← MySQL のみ（本番と完全に同じ挙動）
DB_HOST=localhost
DB_PORT=3306
DB_NAME=adyamato_gear
DB_USER=root
DB_PASS=
```

**重要**: 2026-05-20 以降、本番もローカルも `db` モード固定。`dual` / `json` モードは廃止済みで、コード内のフォールバックも撤去されている。

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

### Playwright テストが「test-login failed」で落ちる

`APP_ENV=local` が `.env.local` に書かれているか確認。本番デプロイ環境では test-login.php は 403 を返す（意図された動作）。
