# MF開発環境用フォルダ

このフォルダは、ローカル開発環境でMFクラウド請求書のOAuth認証とAPIテストを行うためのものです。

## セットアップ手順

1. **MFクラウド請求書の設定**
   - https://invoice.moneyforward.com/settings/oauth にアクセス
   - リダイレクトURIに `http://localhost/mf-dev/mf-callback.php` を追加

2. **OAuth認証**
   - http://localhost/mf-dev/mf-settings.php にアクセス
   - 「OAuth認証を開始」ボタンをクリック
   - MFにログインして認証を許可

3. **APIテスト**
   - http://localhost/mf-dev/test.php にアクセス
   - 「今月の請求書を取得」ボタンをクリック

## ファイル構成

- `mf-config.json` - 開発環境用のMF設定ファイル（認証情報を保存）
- `mf-settings.php` - OAuth認証設定ページ
- `mf-callback.php` - OAuthコールバックページ
- `test.php` - API動作確認用テストページ
- `README.md` - このファイル

## 注意事項

- このフォルダは開発環境専用です
- 本番環境にはデプロイしないでください
- `mf-config.json` には認証トークンが含まれるため、Gitにコミットしないでください
