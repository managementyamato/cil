# YA管理一覧システム

## 概要

トラブル管理と請求書作成を統合したWebベースの管理システム。MoneyForward Invoice APIと連携し、プロジェクトごとのトラブル記録から請求書作成までを一元管理します。

## 🚀 クイックスタート（どこからでもアクセス可能）

### 最新版を取得して起動（3ステップ）

```bash
# 1. リポジトリをクローン（または既存をpull）
git clone https://github.com/managementyamato/cli.git
cd cli
git checkout claude/review-handoff-audit-cWcIL

# 2. 開発サーバー起動
php -S localhost:8000

# 3. ブラウザでアクセス
# http://localhost:8000
```

**Windows**: `start-server.bat` をダブルクリックで即起動！

**重要**: どのPCからでもGitHubから最新版を取得すれば、同じ状態で開発できます。

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
- **MF同期データの視覚表示**（同期バッジ、更新日時表示）
- MF同期済み案件数の統計表示

### ✅ MoneyForward連携（✅ 動作確認済み）
- OAuth2認証フロー (Authorization Code Grant)
- アクセストークンの自動リフレッシュ（5分前バッファ）
- **file_get_contents()ベースのHTTPクライアント（cURL不要）**
- **請求書データの全件取得（ページネーション対応）**
- **タグからPJ番号と担当者名を自動抽出**
- **金額詳細の取得（小計、消費税、合計）**
- **月別集計機能（請求書を月ごとにグループ化）**
- **デバッグ機能（API応答の詳細確認）**
- リバースプロキシ対応のHTTPS検出

### ✅ 開発環境
- PHPビルトインサーバー対応
- Browser-sync自動リロード
- Windows環境セットアップガイド

## 次のタスク

### 完了済み
- [x] MoneyForward OAuth2認証の動作確認（開発環境）
- [x] MoneyForward請求書データ同期機能
- [x] cURLをfile_get_contents()に置き換え（ポータブル環境対応）
- [x] タグからPJ番号・担当者名の自動抽出
- [x] 金額詳細の取得と計算（小計、消費税）
- [x] 月別集計ページの実装
- [x] デバッグ機能の実装

### 未完了タスク
- [ ] MoneyForward OAuth2認証の動作確認（本番環境）
- [ ] MoneyForward請求書作成機能の実装
- [ ] プロジェクトとMF請求書の自動紐付け機能
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
├── mf-api.php             # MF API クライアント（file_get_contents実装）
├── mf-monthly.php         # MF請求書月別集計
├── mf-debug.php           # MFデバッグ情報表示
├── users.php              # ユーザー管理
├── config.php             # 設定ファイル
├── auth.php               # 認証処理
├── style.css              # スタイル
├── data.json              # データ（.gitignore）
├── users.json             # ユーザー（.gitignore）
├── mf-config.json         # MF設定（.gitignore）
├── mf-api-debug.json      # APIデバッグログ（.gitignore）
├── mf-sync-debug.json     # 同期デバッグログ（.gitignore）
├── package.json           # npm設定
├── README.md              # このファイル
└── SETUP-WINDOWS.md       # Windowsセットアップガイド
```

## ライセンス

ISC

## 開発履歴

- 2026/01/14: MF APIクライアントをcURLからfile_get_contents()に完全移行（ポータブル環境対応）
- 2026/01/14: タグからPJ番号・担当者名を自動抽出する機能を実装
- 2026/01/14: 金額詳細（小計、消費税、合計）の取得と計算機能を実装
- 2026/01/14: 月別集計ページ（mf-monthly.php）を新規作成
- 2026/01/14: デバッグ機能（mf-debug.php）を実装、API応答の詳細確認が可能に
- 2026/01/14: レスポンス構造の柔軟な対応（data/billingsキー両対応）
- 2026/01/12: 財務管理画面にMF同期データの視覚表示を追加（同期バッジ、統計カード）
- 2026/01/12: MF API クライアントを更新：トークンエンドポイントとSSL設定の修正
- 2026/01/12: SSL証明書検証を無効化（開発環境用）- HTTPコード0エラーの修正
- 2026/01/10: Windowsセットアップガイド作成、詳細なインストール手順追加
- 2026/01/09: MoneyForward OAuth2認証をPOST body方式に変更
- 2026/01/09: リバースプロキシ対応HTTPS検出追加（本番環境対応）
- 2026/01/09: mf-oauth.php を mf-callback.php にリネーム
- 2026/01: MoneyForward OAuth2連携実装
- 2026/01: トラブル管理システム基本機能実装

## 今回のセッションで実装した内容（2026/01/14）

### 問題
1. MF API認証が動作しない
2. cURL拡張がポータブルPHP環境で利用できない
3. 請求書データの取得方法が不明
4. タグ情報からPJ番号と担当者名を抽出する必要がある

### 解決策
1. **cURL依存の完全排除**
   - すべてのHTTPリクエストをfile_get_contents()に書き換え
   - stream_context_create()でヘッダーとメソッドを指定
   - ポータブルPHP環境で追加設定不要で動作

2. **API認証の修正**
   - OAuth2エンドポイントとスコープパラメータを修正
   - 正しいスコープ: `mfc/invoice/data.read mfc/invoice/data.write`
   - 正しいエンドポイント: `https://api.biz.moneyforward.com`

3. **請求書データの取得**
   - ページネーション対応で全件取得
   - レスポンス構造の柔軟な対応（dataキー/billingsキー）
   - デバッグ機能でAPI応答を詳細確認

4. **タグ情報の自動抽出**
   - 正規表現でPJ番号（P + 数字）を抽出
   - 日本語人名パターンで担当者名を抽出
   - 会社名や不要なタグを除外

5. **金額詳細の計算**
   - 明細（items）から小計を計算
   - 合計金額から消費税を逆算
   - 月別集計で視覚的に表示

### 新規ファイル
- `mf-monthly.php`: 月別集計ページ
- `mf-debug.php`: デバッグ情報表示ページ

### 修正ファイル
- `mf-api.php`: file_get_contents()実装、レスポンス構造対応
- `finance.php`: タグ抽出、金額計算、デバッグ機能
- `mf-callback.php`: file_get_contents()実装
- `.gitignore`: デバッグファイルを除外
