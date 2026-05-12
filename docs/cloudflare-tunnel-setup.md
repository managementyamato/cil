# Cloudflare Tunnel テスト環境セットアップメモ

> 作成日: 2026-05-11
> 目的: ローカルXAMPP環境を Cloudflare Tunnel で社外URLとして公開し、デプロイ前にチームでテスト確認できるようにする
> 状態: **準備完了・未稼働**（実施時はこのメモの手順通り進める）

---

## 全体像

```
あなたのPC (XAMPP)              Cloudflare              チームメンバー
┌──────────────────┐           ┌─────────┐           ┌──────────┐
│ Apache:8080      │ ←Tunnel→  │ HTTPS化  │ ←───→     │ ブラウザ  │
│ PHP 8.2.12       │           │ 配信     │           │ 確認     │
│ MySQL            │           │          │           │          │
│ yamato_gear_test │           │          │           │          │
└──────────────────┘           └─────────┘           └──────────┘
                               trycloudflare.com の一時URL
                               (毎起動ごとに変わる)
```

---

## 既に作成・準備済みのもの

| 項目 | 状態 | 場所 |
|------|------|------|
| cloudflared CLI | インストール済み 2025.8.1 | (winget でインストール) |
| 起動スクリプト | 作成済み | `C:\Claude\master\start-test-tunnel.bat` |
| MySQL インポートスクリプト | 作成済み | `C:\Claude\master\scripts\setup-local-mysql.bat` |
| Apache vhost (port 8080) | 設定済み | `C:\xampp\apache\conf\extra\httpd-vhosts.conf` |
| MAIL_DISABLED ガード | 実装済み | `C:\Claude\master\api\google-gmail.php` |
| .env 本番上書き防止 | 実装済み | `C:\Claude\master\auto-deploy.ps1` ※注意 |

---

## 実施時の手順

### Step 1: 本番MySQLのダンプを取得

1. https://www.xserver.ne.jp/login_server.php → サーバーパネル
2. データベース → **phpMyAdmin** を選択
3. ログイン情報:
   - サーバー: mysql2301.xserver.jp
   - ユーザー: adyamato_gear
   - パスワード: Adyamato8010_
4. `adyamato_gear` データベースを選択 → **エクスポート** タブ
5. 形式: SQL → 「実行」
6. ダウンロードした `.sql` ファイルを以下に保存:
   ```
   C:\Claude\master\backups\mysql-dump\yamato_gear.sql
   ```
   フォルダがなければ作成

### Step 2: テスト用 .env を準備

ローカル `.env` を以下に書き換える（本番運用時は元に戻すか分ける）:

```env
APP_ENV=staging
SESSION_TIMEOUT_HOURS=8
SSL_VERIFY_PEER=true
CSP_MODE=legacy
AUDIT_LOG_SIGNING_KEY=

# テスト環境では空にして誤送信防止
MF_CLIENT_ID=
MF_CLIENT_SECRET=
MF_ACCESS_TOKEN=
MF_REFRESH_TOKEN=
FRONTEND_URL=

# メール送信抑止（true でログのみ・送信しない）
MAIL_DISABLED=true

# DB はローカル MySQL のテスト DB
DB_MODE=db
DB_HOST=localhost
DB_PORT=3306
DB_NAME=yamato_gear_test
DB_USER=root
DB_PASS=
```

そして `public_html` にもコピー（Apache が読むのはこちら）:
```cmd
copy C:\Claude\master\.env C:\Claude\master\public_html\.env
```

### Step 3: ローカルMySQLにインポート

XAMPP の MySQL を起動済みの状態で:

```cmd
C:\Claude\master\scripts\setup-local-mysql.bat
```

→ 「ローカルDB セットアップ完了」が出ればOK

### Step 4: テスト環境を起動

```cmd
C:\Claude\master\start-test-tunnel.bat
```

→ コンソールに以下が表示:
```
+------------------------------------------------------------+
|  Your quick Tunnel has been created! Visit it at:          |
|  https://xxxx-xxxx-xxxx-xxxx.trycloudflare.com             |
+------------------------------------------------------------+
```

→ この URL を社内メンバーに共有

---

## テスト環境の特徴・安全装置

| 項目 | 動作 |
|------|------|
| データソース | ローカル `yamato_gear_test` DB（本番に一切影響なし） |
| MoneyForward API | 無効化（誤って本番MFに請求書送信される事故を防止） |
| Gmail送信 | 抑止（メール飛ばず、ログに記録のみ） |
| Google Sheets | 読み込み可（書き込み機能を使うと本番Sheetsに反映するので注意） |
| ファイルアップロード | ローカル `uploads/` に保存 |
| URL | trycloudflare.com の一時URL（起動ごとに変わる） |

---

## 注意事項・トラブルシューティング

### A. デプロイで本番 .env が上書きされないようにする

`auto-deploy.ps1` のWinSCP synchronizeで `.env` を除外済み（実施済み）:
```powershell
synchronize remote -filemask="|.env;data.json;..."
```
**もし auto-deploy.ps1 が壊れていたら、本番 .env を上書きする危険があるので必ず確認**

### B. 本番DBに誤接続するリスク

ローカルテスト用 `.env` の `DB_HOST` を `mysql2301.xserver.jp` にしないこと。
`localhost` だけにしておけば本番に届かない。

### C. 終了処理

`start-test-tunnel.bat` のコンソールで `Ctrl+C` でTunnel停止。
XAMPPはタスクマネージャー or `C:\xampp\xampp-control.exe` で停止。

### D. trycloudflare URL が変わる問題

毎起動で URL が変わる。固定したい場合は:
1. ドメインを Cloudflare に登録（Cloudflareをネームサーバーに）
2. `cloudflared tunnel login` でブラウザ認証
3. `cloudflared tunnel create yamato-test` でトンネル作成
4. `cloudflared tunnel route dns yamato-test test.your-domain.com`
5. `~/.cloudflared/config.yml` で設定保存
6. `cloudflared tunnel run yamato-test` で起動

### E. アクセス制限（社員のみに）

Cloudflare Access（Zero Trust）で Googleアカウント認証を必須にできる:
1. Cloudflareダッシュボード → Zero Trust → Access → Applications
2. 「Add an application」→ Self-hosted
3. Application domain に test.your-domain.com を設定
4. Policy で「emails ending in @yamato-mgt.com」等を設定

---

## 今後の選択肢

| 段階 | 内容 | 設定難易度 |
|------|------|----------|
| 1 | trycloudflare 一時URL（クイック共有） | ★（このメモ通り） |
| 2 | 固定URL（自社ドメイン+Tunnel） | ★★ |
| 3 | 認証付き固定URL（Cloudflare Access） | ★★ |
| 4 | 常時稼働化（Windowsサービス化 or 別PC） | ★★★ |
| 5 | Xserver 上にテスト用サブドメイン+DB | ★★（Cloudflare不要、別ルート） |

---

## 関連ファイル

- `C:\Claude\master\start-test-tunnel.bat` — 起動スクリプト
- `C:\Claude\master\scripts\setup-local-mysql.bat` — DB初期化スクリプト
- `C:\Claude\master\auto-deploy.ps1` — 本番デプロイ（.env除外済み）
- `C:\Claude\master\api\google-gmail.php` — MAIL_DISABLED ガード
- `C:\xampp\apache\conf\extra\httpd-vhosts.conf` — port 8080 vhost
