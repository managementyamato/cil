# 設定必要リスト

デプロイ後に設定が必要な項目一覧

## 1. メール通知設定（未設定）

**ファイル:** `config/notification-config.json`

**設定項目:**
```json
{
    "smtp_host": "sv2304.xserver.jp",
    "smtp_port": 587,
    "smtp_username": "（メールアドレス）",
    "smtp_password": "（メールパスワード）",
    "smtp_from_email": "（送信元メールアドレス）",
    "smtp_from_name": "YA管理システム"
}
```

**手順:**
1. XSERVERサーバーパネル → メールアカウント設定
2. 新規メールアカウント作成（例: system@ad-yamato.xsrv.jp）
3. 上記設定ファイルに情報を入力
4. 管理画面の「設定」→「通知設定」からも変更可能

---

## 2. SSH鍵ペア設定（任意）

**目的:** git pullでのデプロイを可能にする

**手順:**
1. XSERVERサーバーパネル → SSH設定 → ON
2. 公開鍵認証用鍵ペアの生成
3. 秘密鍵をダウンロード
4. ローカルの `~/.ssh/xserver.key` に保存

---

## 3. Google OAuth設定（設定済みの場合はスキップ）

**ファイル:** `config/google-config.json`

**設定項目:**
- client_id
- client_secret
- redirect_uri

---

## 4. MF会計API設定（設定済みの場合はスキップ）

**ファイル:** `config/mf-config.json`

**設定項目:**
- client_id
- client_secret
- redirect_uri

---

## 5. Google Sheets API連携（任意）

**ファイル:** `config/credentials.json`（新規作成）

**目的:** Googleスプレッドシートとのデータ連携

**手順:**
1. Google Cloud Console（https://console.cloud.google.com）にアクセス
2. 新規プロジェクトを作成
3. 「APIとサービス」→「ライブラリ」→「Google Sheets API」を有効化
4. 「認証情報」→「サービスアカウント」を作成
5. サービスアカウントのキー（JSON）をダウンロード
6. `config/credentials.json`として保存
7. 連携したいスプレッドシートをサービスアカウントのメールアドレスに共有

**Composerインストール:**
```bash
composer require google/apiclient
```

---

## 6. データフォルダの権限（デプロイ後）

サーバー上で以下のフォルダに書き込み権限が必要:
- `data/`
- `tmp/`

```bash
chmod 755 data tmp
```

---

---

## TODO（後で対応）

- [ ] **借入金管理 GAS連携** - スプレッドシートとの自動同期設定
  - `docs/GAS-LOAN-INTEGRATION.md` にサンプルコードあり
  - 入金確認時にスプレッドシートのセルに色を塗る機能

---

## 更新履歴

- 2026-01-22: 初版作成
- 2026-01-22: Google Sheets API連携設定を追加
- 2026-01-23: 借入金管理機能を追加、GAS連携をTODOに追加
