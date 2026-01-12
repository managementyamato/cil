# YA管理一覧システム

## 概要

トラブル管理と請求書作成を統合したWebベースの管理システム。MoneyForward Invoice APIと連携し、プロジェクトごとのトラブル記録から請求書作成までを一元管理します。

## 技術スタック

- **バックエンド**: PHP 8.0+ (組み込みサーバー使用、Apache/MySQL不要)
- **フロントエンド**: HTML, CSS, JavaScript (Vanilla)
- **データ保存**: JSONファイル (data.json, users.json)
- **外部API**: MoneyForward Invoice API v3 (OAuth2認証)
- **開発環境**: Node.js + Browser-sync (オプション - ライブリロード用)

## 実装済み機能

### ✅ 認証・ユーザー管理
- ユーザー登録・ログイン機能
- セッション管理
- 管理者・編集者・閲覧者の権限管理

### ✅ マスターデータ管理
- 顧客マスター
- パートナーマスター
- 従業員マスター
- 商品マスター

### ✅ トラブル管理
- プロジェクトごとのトラブル記録・報告
- トラブル一覧・検索・フィルタリング
- トラブル詳細の編集・削除
- CSVエクスポート機能

### ✅ 財務管理
- 純利益表示
- プロジェクト別収支管理

### ✅ MoneyForward連携
- OAuth2認証フロー (Authorization Code Grant)
- アクセストークンの自動リフレッシュ（5分前バッファ）
- POST body方式での認証（client_id/client_secret）
- リバースプロキシ対応のHTTPS検出
- 請求書作成API連携準備完了

### ✅ 開発環境
- PHPビルトインサーバー対応
- Browser-sync自動リロード
- Windows環境セットアップガイド

## 次のタスク

### 未完了タスク
- [ ] MoneyForward OAuth2認証の動作確認（本番環境）
- [ ] MoneyForward請求書作成機能の実装
- [ ] クラウド勤怠連携
- [ ] テストの実装
- [ ] エラーハンドリングの強化
- [ ] データバックアップ機能

## 重要な設計判断

### なぜデータベースを使わないのか
- **理由**: 小規模システムのため、JSONファイルで十分な性能を確保できる
- **メリット**:
  - インストール・デプロイが非常に簡単
  - バックアップが容易（ファイルコピーだけ）
  - 依存関係が少ない
- **制限**: 大量の同時書き込みは想定していない（小規模チーム向け）

### なぜPHP組み込みサーバーを使うのか
- **理由**: Apache/MySQLのセットアップ不要でシンプルに動作
- **メリット**:
  - 開発環境の構築が極めて簡単
  - PHPコマンド1つで起動可能
  - 依存関係が最小限
- **注意**: 本番環境では適切なWebサーバー（nginx、Apache等）を推奨

### MoneyForward OAuth2の認証方式の変更

#### 当初の実装（失敗）
```php
// HTTP Basic認証ヘッダーで送信
$authHeader = 'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret);
```
- **結果**: 403エラーで認証失敗

#### 現在の実装（成功）
```php
// POST bodyにclient_id/client_secretを含める
$postData = array(
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirectUri,
    'client_id' => $config['client_id'],
    'client_secret' => $config['client_secret']
);
```
- **理由**:
  - MoneyForward APIの公式ドキュメントで両方サポートされている
  - POST body方式の方が確実に動作する
  - GASの動作例もPOST body方式を使用

### HTTPS検出のリバースプロキシ対応

```php
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
           (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
```

- **理由**: 本番環境（https://cil.yamato-basic.com）でロードバランサー/リバースプロキシ経由のため
- **メリット**:
  - プロキシ背後でも正しくHTTPSを検出
  - OAuth2リダイレクトURIが正しく生成される
  - 本番環境で確実に動作

### ファイル命名の変更: mf-oauth.php → mf-callback.php

- **理由**: MoneyForward側のリダイレクトURI設定と一致させる必要があった
- **経緯**: 初回実装時にリダイレクトURIミスマッチエラーが発生
- **教訓**: 外部API連携時はファイル名も含めて仕様を確認する

## セットアップ

詳細は [SETUP-WINDOWS.md](SETUP-WINDOWS.md) を参照してください。

### 🚀 最短セットアップ（3ステップ）

#### 方法1: バッチファイルで起動（Windows - 最も簡単！）

```bash
# 1. PHP と Git をインストール（初回のみ）

# 2. リポジトリをクローン
git clone https://github.com/managementyamato/cli.git
cd cli
git checkout claude/review-handoff-audit-cWcIL

# 3. バッチファイルをダブルクリック
# エクスプローラーで start-server.bat をダブルクリック
# （またはコマンドプロンプトで start-server.bat を実行）
```

**ブラウザ自動起動版**: `start-server-with-browser.bat` をダブルクリック

#### 方法2: コマンドで起動

```bash
# 1. PHP と Git をインストール

# 2. リポジトリをクローン
git clone https://github.com/managementyamato/cli.git
cd cli
git checkout claude/review-handoff-audit-cWcIL

# 3. 開発サーバー起動
php -S localhost:8000

# ブラウザで http://localhost:8000 を開く
```

### ライブリロード付き開発環境（オプション）

```bash
# Node.js依存パッケージをインストール
npm install

# 開発サーバー起動（ファイル変更時に自動リロード）
npm run dev

# ブラウザで http://localhost:3000 を開く
```

### 📌 開発ポートの違いと注意点

| 起動方法 | ポート | リロード方法 | MoneyForward OAuth2 |
|---------|-------|------------|-------------------|
| `php -S localhost:8000` | 8000 | **手動**（F5キー） | ✅ 推奨（設定不要） |
| `npm run dev` | 3000 | **自動**（ファイル保存時） | ⚠️ リダイレクトURI設定が必要 |

**MoneyForward OAuth2認証を使用する場合：**
- **ポート8000を推奨**：MoneyForward側のリダイレクトURI設定が `http://localhost:8000/mf-callback.php` で済む
- ポート3000を使う場合：MoneyForward側に `http://localhost:3000/mf-callback.php` も追加設定が必要

**開発時のワークフロー：**
- ポート8000：ファイル編集 → ブラウザでF5キー → 変更確認
- ポート3000：ファイル編集 → 自動でブラウザリロード → 変更確認

## デプロイ

### 本番環境（https://cil.yamato-basic.com）

```bash
# SSH接続後
cd /path/to/project
git fetch origin
git checkout claude/audit-dependencies-mk0uc1heu3tc90mg-wFx3f
git pull origin claude/audit-dependencies-mk0uc1heu3tc90mg-wFx3f
```

## MoneyForward連携設定

1. [MoneyForward Invoice](https://invoice.moneyforward.com) にログイン
2. 「設定」→「API連携」→「アプリケーションを作成」
3. リダイレクトURIに本番URLを設定:
   - 本番: `https://cil.yamato-basic.com/mf-callback.php`
   - 開発: `http://localhost:8000/mf-callback.php`
4. Client IDとClient Secretを取得
5. システムの「MF連携設定」から認証

## ディレクトリ構成

```
cli/
├── index.php              # ダッシュボード
├── list.php               # トラブル一覧
├── report.php             # トラブル報告
├── master.php             # プロジェクト管理
├── finance.php            # 財務管理
├── mf-settings.php        # MF連携設定UI
├── mf-callback.php        # MF OAuth2コールバック
├── mf-api.php             # MF API クライアント
├── users.php              # ユーザー管理
├── config.php             # 設定ファイル
├── auth.php               # 認証処理
├── style.css              # スタイル
├── data.json              # データ（.gitignore）
├── users.json             # ユーザー（.gitignore）
├── mf-config.json         # MF設定（.gitignore）
├── package.json           # npm設定
├── README.md              # このファイル
└── SETUP-WINDOWS.md       # Windowsセットアップガイド
```

## ライセンス

ISC

## 開発履歴

- 2026/01/10: Windowsセットアップガイド作成、詳細なインストール手順追加
- 2026/01/09: MoneyForward OAuth2認証をPOST body方式に変更
- 2026/01/09: リバースプロキシ対応HTTPS検出追加（本番環境対応）
- 2026/01/09: mf-oauth.php を mf-callback.php にリネーム
- 2026/01: MoneyForward OAuth2連携実装
- 2026/01: トラブル管理システム基本機能実装
