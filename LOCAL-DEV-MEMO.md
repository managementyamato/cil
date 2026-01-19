# ローカル開発環境メモ

## 環境情報

- **OS**: Windows
- **プロジェクトパス**: `C:\Users\User\cil`
- **ブランチ**: `claude/fetch-latest-data-YTTlW`
- **開発サーバー**: `http://localhost:8000`

## クイックスタート

### 1. プロジェクトフォルダに移動

```powershell
# クローンしたフォルダに移動
cd C:\Users\User\cil

# 現在のディレクトリを確認
pwd
```

### 2. 最新の変更を取得

```powershell
git pull origin claude/fetch-latest-data-YTTlW
```

### 3. 初期セットアップ（初回のみ）

```powershell
php init-local.php
```

これで以下のファイルが作成されます：
- `data.json`
- `users.json`
- `mf-config.json`
- `.htaccess`

### 4. 開発サーバー起動

```powershell
php -S localhost:8000
```

または

```powershell
.\start-server.bat
```

### 5. ブラウザでアクセス

```
http://localhost:8000
```

## よく使うURL

- **トップページ**: http://localhost:8000
- **損益管理**: http://localhost:8000/finance.php
- **月別集計**: http://localhost:8000/mf-monthly.php
- **MF設定**: http://localhost:8000/mf-settings.php
- **PHP環境診断**: http://localhost:8000/check-php-config.php

## トラブルシューティング

### 損益ページが表示されない

1. `init-local.php` を実行して必要なファイルを作成
2. `check-php-config.php` で環境を確認

### OAuth認証エラー

1. `check-php-config.php` で以下を確認：
   - `allow_url_fopen` が有効か
   - OpenSSL拡張が有効か
   - HTTPS接続テストが成功するか

2. php.ini の修正が必要な場合：
   ```ini
   allow_url_fopen = On
   extension=openssl
   ```

3. PHPサーバーを再起動

### 最新データの取得

#### Webから（推奨）
```
http://localhost:8000/finance.php
→「MFから同期」ボタンをクリック
```

#### コマンドラインから
```powershell
php fetch-latest-data.php
```

## Git操作

### 最新の変更を取得
```powershell
git pull origin claude/fetch-latest-data-YTTlW
```

### 変更を確認
```powershell
git status
```

### ブランチを確認
```powershell
git branch
```

## 注意事項

- `data.json`, `users.json`, `mf-config.json` は `.gitignore` で除外されています
- これらのファイルは各環境で個別に作成・設定する必要があります
- MF連携を使用する場合は、`mf-config.json` にClient IDとClient Secretを設定してください

## 開発中の機能

現在 `claude/fetch-latest-data-YTTlW` ブランチで開発中：
- [x] 最新データ取得スクリプト（`fetch-latest-data.php`）
- [x] ローカル環境初期化（`init-local.php`）
- [x] PHP環境診断（`check-php-config.php`）
- [x] SSL接続エラー修正
- [x] 自動同期の無効化（ローカル環境）
