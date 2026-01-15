# Google OAuth 簡単セットアップガイド

このガイドは、Google OAuth認証を最速で設定するための手順です。

## 🚀 クイックスタート（5分で完了）

### 1. Google Cloud Consoleでの設定（3分）

1. https://console.cloud.google.com/ にアクセス
2. 新しいプロジェクトを作成（例: YA管理システム）
3. 左メニューから「APIとサービス」→「OAuth同意画面」を選択
   - 「外部」を選択して「作成」
   - アプリ名: YA管理一覧
   - ユーザーサポートメール: あなたのメールアドレス
   - デベロッパーの連絡先情報: あなたのメールアドレス
   - 「保存して次へ」を3回クリック
4. 「APIとサービス」→「認証情報」を選択
   - 「認証情報を作成」→「OAuth クライアント ID」
   - アプリケーションの種類: **ウェブ アプリケーション**
   - 名前: YA管理システム
   - 承認済みのリダイレクト URI: `http://localhost:8000/google-callback.php`
   - 「作成」をクリック
5. 表示された**クライアントID**と**クライアントシークレット**をコピー

### 2. システムでの設定（1分）

1. `google-config.json` ファイルを作成
   ```bash
   cp google-config.json.template google-config.json
   ```

2. コピーした情報を貼り付け
   ```json
   {
     "client_id": "ここに先ほどコピーしたクライアントIDを貼り付け",
     "client_secret": "ここに先ほどコピーしたクライアントシークレットを貼り付け",
     "redirect_uri": "http://localhost:8000/google-callback.php"
   }
   ```

### 3. テストユーザーの追加（1分）

1. Google Cloud Consoleに戻る
2. 「APIとサービス」→「OAuth同意画面」
3. 「テストユーザーを追加」をクリック
4. ログインに使用するGoogleアカウントのメールアドレスを追加
5. **重要**: このメールアドレスは、従業員マスタに登録されている必要があります

### 4. 動作確認

1. PHPサーバーを起動
   ```bash
   php -S localhost:8000
   ```

2. ブラウザで http://localhost:8000/login.php にアクセス

3. 「Googleでログイン」ボタンが表示されていることを確認

4. ボタンをクリックしてログインテスト

## ✅ チェックリスト

設定が正しく完了したか確認：

- [ ] Google Cloud Consoleでプロジェクトを作成した
- [ ] OAuth 2.0クライアントIDを作成した
- [ ] クライアントIDとシークレットをコピーした
- [ ] google-config.json を作成した
- [ ] リダイレクトURIを正しく設定した（http://localhost:8000/google-callback.php）
- [ ] テストユーザーを追加した
- [ ] テストユーザーのメールアドレスが従業員マスタに登録されている
- [ ] ログイン画面に「Googleでログイン」ボタンが表示される

## ⚠️ トラブルシューティング

### 「Googleでログイン」ボタンが表示されない
- `google-config.json` が正しく作成されているか確認
- ファイル内のJSON形式が正しいか確認（カンマ、クォーテーションなど）

### 「このアプリは確認されていません」と表示される
- テストモードでは正常な動作です
- 「詳細」→「（アプリ名）に移動（安全ではないページ）」をクリック

### 「このGoogleアカウントは登録されていません」と表示される
- 従業員マスタ（employees.php）にメールアドレスが登録されているか確認
- テストユーザーとして追加したGoogleアカウントと一致しているか確認

### 「アクセスがブロックされました」と表示される
- Google Cloud Consoleでテストユーザーを追加したか確認
- 追加したメールアドレスが正しいか確認

## 📚 詳細情報

より詳しい説明が必要な場合は、`GOOGLE-OAUTH-SETUP.md` を参照してください。

## 💡 本番環境での使用

本番環境で使用する場合は、以下を変更してください：

1. `google-config.json` の `redirect_uri` を実際のドメインに変更
   ```json
   "redirect_uri": "https://your-domain.com/google-callback.php"
   ```

2. Google Cloud Consoleの「承認済みのリダイレクト URI」も同様に変更

3. 100人以上のユーザーが使用する場合は、OAuth同意画面を公開申請
