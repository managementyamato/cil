# OAuth リダイレクトURI 設定メモ

## 問題の概要

`www.yamato-mgt.com` でアクセスした場合、コードが `HTTP_HOST` を使って動的にリダイレクトURIを生成するため、OAuth認証が失敗する可能性がある。

---

## 1. マネーフォワード（MF）

### 現在の登録状況（MF管理画面）
- `https://yamato-mgt.com/pages/mf-callback.php` ✓
- `http://localhost:8000/pages/mf-callback.php` ✓
- `https://www.yamato-mgt.com/pages/mf-callback` ✓（.phpなし）
- `https://www.yamato-mgt.com/mf-callback.php` ✓
- `https://yamato-mgt.com/pages/mf-callback` ✓（.phpなし）
- `https://www.yamato-mgt.com/pages/mf-callback` ✓（.phpなし）

### 未登録（追加が必要）
- `https://www.yamato-mgt.com/pages/mf-callback.php` ❌

### 関連ファイル
- `pages/mf-settings.php:32-33` - リダイレクトURI生成
- `pages/mf-callback.php:26-27` - コールバック処理

---

## 2. Google OAuth（ログイン認証）

### 登録が必要なURI（Google Cloud Console）
- `http://localhost:8000/api/google-callback.php` （ローカル開発）
- `https://yamato-mgt.com/api/google-callback.php`
- `https://www.yamato-mgt.com/api/google-callback.php`

### 関連ファイル
- `api/google-oauth.php:30-37` - 動的にリダイレクトURI生成
- `pages/google-oauth-settings.php:30-32` - 設定画面でのURI生成

---

## 3. Google Chat OAuth

### 登録が必要なURI（Google Cloud Console）
- `http://localhost:8000/api/google-chat-callback.php` （ローカル開発）
- `https://yamato-mgt.com/api/google-chat-callback.php`
- `https://www.yamato-mgt.com/api/google-chat-callback.php`

### 問題点
`api/google-chat.php:62` で設定ファイルのURIを文字列置換している：
```php
str_replace('google-callback.php', 'google-chat-callback.php', $this->redirectUri)
```
設定ファイルが `localhost` の場合、本番環境では動作しない。

### 関連ファイル
- `api/google-chat.php:62` - 認証URL生成
- `api/google-chat-callback.php` - コールバック処理

---

## 4. Google Drive OAuth

Drive認証は `api/google-callback.php` に統合済み（`state=drive_connect` パラメータで識別）。
専用の `google-drive-callback.php` は削除済み。

### 関連ファイル
- `api/google-drive.php` - Driveクライアント
- `api/google-callback.php` - コールバック処理（Drive含む）

---

## 5. Google Calendar OAuth

### 登録が必要なURI（Google Cloud Console）
- `http://localhost:8000/api/google-calendar-callback.php` （ローカル開発）
- `https://yamato-mgt.com/api/google-calendar-callback.php`
- `https://www.yamato-mgt.com/api/google-calendar-callback.php`

### 問題点
設定ファイルの `redirect_uri` を直接使用しているため、`localhost` 固定では本番で動作しない。

### 関連ファイル
- `api/google-calendar.php:94` - 設定読み込み
- `api/google-calendar-callback.php` - コールバック処理

---

## 設定ファイルの現状

### config/google-config.json
```json
{
    "redirect_uri": "http://localhost:8000/api/google-callback.php"
}
```
※ ローカル開発用のURIが固定されている

---

## 推奨対応

### 方法1: 各プロバイダに全パターンのURIを登録
- `www` あり/なし
- `.php` あり/なし（.htaccessでリライトしている場合）
- `localhost`（開発用）

### 方法2: コード修正でURIを正規化
- `www` を常に除去する、または常に付与する
- 本番ドメインを固定値として使用する

### 方法3: 環境変数で管理
- `.env` にリダイレクトURIのベースURLを設定
- コードで環境に応じて切り替え

---

## チェックリスト

### MF（マネーフォワード）
- [ ] `https://www.yamato-mgt.com/pages/mf-callback.php` を登録

### Google Cloud Console
- [ ] `https://yamato-mgt.com/api/google-callback.php` を確認/登録
- [ ] `https://www.yamato-mgt.com/api/google-callback.php` を確認/登録
- [ ] `https://yamato-mgt.com/api/google-chat-callback.php` を確認/登録
- [ ] `https://www.yamato-mgt.com/api/google-chat-callback.php` を確認/登録
- [ ] Drive認証は `google-callback.php` に統合済み（state=drive_connect）
- [ ] `https://yamato-mgt.com/api/google-calendar-callback.php` を確認/登録
- [ ] `https://www.yamato-mgt.com/api/google-calendar-callback.php` を確認/登録

---

作成日: 2026-02-04
