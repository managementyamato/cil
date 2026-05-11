# 環境設定（.env）の管理方針

## 概要

ローカル開発環境と本番環境で設定が異なるため、**Symfony/Laravel風の多段読み込み**で管理します。

---

## ファイル構成

| ファイル | 用途 | git管理 | デプロイ |
|---------|------|---------|---------|
| `.env.example` | テンプレート（参考用） | ✓ コミット | ✗ |
| `.env` | 共通デフォルト / 環境固有設定 | ✗ gitignore | ✗ |
| `.env.local` | ローカル開発専用の上書き | ✗ gitignore | ✗ |

### 各環境のファイル配置

#### ローカル開発
```
C:\Claude\master\
├── .env          ← 共通デフォルト
└── .env.local    ← ローカル専用の上書き
```

#### 本番（Xserver）
```
/.env          ← 本番固有設定（FTP で直接配置）
（.env.local は置かない）
```

---

## 読み込み順序

`config/config.php` が以下の順で読み込みます:

1. **`.env.local`**（最優先）
2. **`.env`**（フォールバック）

**先勝ち** 動作: 同じキーが両方にあれば先に読まれた方が勝ちます。

```
ローカル開発時:
  .env.local の DB_HOST=localhost → 採用
  .env の DB_HOST=mysql2301... → 無視（既にセット済み）

本番時:
  .env.local は存在しない
  .env の DB_HOST=mysql2301... → 採用
```

---

## APP_ENV による環境判定

`.env.local` で `APP_ENV=local` にすることでローカル動作モードに切り替わります。

### ヘルパー関数（config.php に定義）

```php
isProduction()    // APP_ENV === 'production'
isStaging()       // APP_ENV === 'staging'
isLocal()         // APP_ENV === 'local' | 'development' | 'dev'
isMailDisabled()  // MAIL_DISABLED=true または APP_ENV != production
```

### 自動的に切り替わる動作

| 動作 | 本番 (APP_ENV=production) | ローカル (APP_ENV=local) |
|------|-------------------------|-------------------------|
| メール送信 | 通常送信 | 抑止（ログのみ） |
| エラー詳細表示 | 隠す | 表示（将来実装可） |

---

## セットアップ手順

### 新規開発者がローカル環境を立ち上げる時

```bash
# 1. .env.local を作成（テンプレートからコピー）
cp .env.example .env.local

# 2. .env.local を編集（ローカル MySQL の設定など）
# 重要: APP_ENV=local にする
```

### 本番に新しい環境変数を追加する時

1. `.env.example` に追記（テンプレートとして）
2. 本番サーバーの `/.env` を FTP で取得 → 追記 → 戻す
3. ローカルの `.env.local` も必要に応じて追加

---

## デプロイ時の安全装置

`auto-deploy.ps1` が以下のファイルを **絶対にFTP同期しない** よう除外:

- `.env`
- `.env.local`
- `.env.example`
- `data.json`
- `users.json`
- `*.token.json`
- `mf-config.json`
- `google-config.json`

これにより、ローカルの設定が誤って本番を上書きする事故を防止。

---

## トラブルシューティング

### Q. ローカルで本番DBに接続したい
```env
# .env.local
DB_HOST=mysql2301.xserver.jp
DB_USER=adyamato_gear
DB_PASS=xxxxx
APP_ENV=local  # メール送信は抑止される
```
**注意**: 本番DBへの誤書き込みリスクがあるので推奨しない。テスト用に別DBを用意するのがベスト。

### Q. 本番 `.env` の DB_MODE を変えたい
1. FTP で本番 `/.env` を取得
2. ローカルで編集
3. FTP で書き戻す
4. （`auto-deploy.ps1` では `.env` を同期しないので安全）

### Q. `.env.local` の変更が反映されない
- `getenv()` は同一プロセス内でキャッシュされる
- PHP-FPM を使っている場合はリロード必要
- ブラウザを完全リロード（Ctrl+F5）

### Q. UPSERT モードで問題が起きた（過去事例）
本番の `.env` に1行追加:
```env
DB_SAVE_MODE=full_replace
```
→ 旧動作（DELETE-ALL + INSERT-ALL）に戻る。

---

## チェックリスト（新しい環境変数を追加する時）

- [ ] `.env.example` に追加
- [ ] `docs/env-management.md` のテーブルに追加（必要なら）
- [ ] ローカル `.env.local` に追加
- [ ] 本番 `/.env` に追加（FTP）
- [ ] 関連コードで `env('KEY', 'default')` を使う（直接 getenv 禁止）
