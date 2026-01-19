# マネーフォワードクラウド請求書 OAuth認証設定ガイド

## 概要

このガイドでは、マネーフォワードクラウド請求書APIとの連携を設定する方法を説明します。

## 前提条件

- マネーフォワードクラウド請求書のアカウント
- 管理者権限でのログイン
- PHPローカル開発環境が起動していること

## 設定手順

### 1. マネーフォワードクラウドでOAuthアプリケーションを登録

1. [マネーフォワードクラウド請求書](https://invoice.moneyforward.com/)にログイン

2. 画面右上の「各種設定」→「連携サービス設定」をクリック

3. 「API連携設定」タブを選択

4. 「OAuth認証アプリケーションを追加」ボタンをクリック

5. 以下の情報を入力:
   - **アプリケーション名**: 任意の名前（例: "勤怠管理システム"）
   - **リダイレクトURI**:
     ```
     http://localhost:8000/mf-callback.php
     ```
     または本番環境のURL（例: `https://yourdomain.com/mf-callback.php`）

6. 「登録」ボタンをクリック

7. 発行された **Client ID** と **Client Secret** をコピーして保存

### 2. ローカル環境で認証設定

1. ローカル開発サーバーを起動:
   ```bash
   ./start-server.bat
   ```

2. ブラウザで以下のURLにアクセス:
   ```
   http://localhost:8000/mf-settings.php
   ```

3. Client IDとClient Secretを入力

4. 「OAuth認証を開始」ボタンをクリック

5. マネーフォワードクラウドの認証画面にリダイレクトされます

6. 「許可する」ボタンをクリックして認証を承認

7. 自動的にコールバックURLにリダイレクトされ、トークンが保存されます

### 3. 認証状態の確認

コマンドラインで接続テストを実行:

```bash
./php.exe test-mf-connection.php
```

成功した場合の出力例:
```
=== マネーフォワードクラウドAPI 接続テスト ===

1. 設定ファイルの確認
✅ mf-config.json が見つかりました
   - Client ID: 設定済み
   - Client Secret: 設定済み
   - Access Token: 設定済み
   - Refresh Token: 設定済み

2. MFApiClientの初期化
✅ MFApiClientを初期化しました

3. 認証状態の確認
✅ OAuth認証が完了しています

4. APIテスト
   請求書一覧を取得中...
✅ APIリクエスト成功
   取得した請求書数: X 件

=== テスト完了 ===
```

## トラブルシューティング

### エラー: "トークン取得エラー: HTTPリクエストが失敗しました"

**原因**: リダイレクトURIが正しく設定されていない可能性があります。

**解決策**:
1. マネーフォワードクラウドで登録したリダイレクトURIを確認
2. `mf-settings.php`で表示されるリダイレクトURIと一致していることを確認
3. プロトコル（http/https）が一致していることを確認

### エラー: "アクセストークンがありません"

**原因**: OAuth認証が完了していません。

**解決策**:
1. ブラウザで `mf-settings.php` にアクセス
2. 「OAuth認証を開始」ボタンをクリック
3. マネーフォワードクラウドで認証を許可

### PHP警告: "Unable to load dynamic library 'curl'"

**原因**: cURL拡張が読み込めません（問題なし）。

**解決策**: この警告は無視できます。システムは`allow_url_fopen`を使用してHTTPリクエストを実行します。

## ファイル構成

- **mf-api.php** - マネーフォワードクラウドAPIクライアント
- **mf-settings.php** - OAuth認証設定画面
- **mf-callback.php** - OAuth認証コールバックハンドラー
- **mf-config.json** - 認証情報保存ファイル（自動生成）
- **test-mf-connection.php** - 接続テストスクリプト

## セキュリティに関する注意事項

1. **mf-config.json** には機密情報（Client Secret、Access Token）が含まれています
2. このファイルをGitにコミットしないでください（.gitignoreに追加済み）
3. 本番環境では、ファイルのパーミッションを適切に設定してください

## API利用例

```php
<?php
require_once 'mf-api.php';

// クライアントを初期化
$client = new MFApiClient();

// 請求書一覧を取得
$invoices = $client->getInvoices();

// 請求書を作成
$invoiceData = [
    'billing' => [
        'department_id' => 'DEPT001',
        'title' => '2024年1月分請求書',
        'billing_date' => '2024-01-31',
        // その他の請求書データ...
    ]
];
$result = $client->createInvoice($invoiceData);
?>
```

## サポート

問題が解決しない場合は、以下を確認してください:

1. [マネーフォワードクラウドAPI公式ドキュメント](https://invoice.moneyforward.com/api/index.html)
2. `mf-api-debug.json` - APIリクエストのデバッグログ
3. PHPのエラーログ

## 関連ドキュメント

- [ローカル開発環境セットアップ](./README-LOCAL-SETUP.md)
- [セッション引き継ぎガイド](./SESSION_HANDOFF.md)
