# セッション引き継ぎドキュメント

このドキュメントを新しいClaude Codeセッションの最初のメッセージとして使用してください。

---

# プロジェクト引き継ぎ

## プロジェクト概要

**プロジェクト名**: YA管理一覧システム
**リポジトリ**: https://github.com/managementyamato/cli
**作業ブランチ**: `claude/audit-dependencies-mk0uc1heu3tc90mg-wFx3f`
**本番環境**: https://cil.yamato-basic.com

トラブル管理と請求書作成を統合したWebベースの管理システム。MoneyForward Invoice APIと連携し、プロジェクトごとのトラブル記録から請求書作成までを一元管理します。

## 技術スタック

- **バックエンド**: PHP 8.0+ (組み込みサーバー使用、**Apache/MySQL不要**)
- **データ保存**: JSONファイル (data.json, users.json, mf-config.json)
- **外部API**: MoneyForward Invoice API v3 (OAuth2認証)
- **開発環境**: Node.js + Browser-sync (オプション)

## 現在の状態

### ✅ 実装完了

1. **基本機能**
   - ユーザー認証・管理（管理者/編集者/閲覧者）
   - マスターデータ管理（顧客/パートナー/従業員/商品）
   - トラブル管理（記録/一覧/編集/削除/CSVエクスポート）
   - 財務管理（純利益表示/プロジェクト別収支）

2. **MoneyForward連携**
   - OAuth2認証フロー実装（Authorization Code Grant）
   - アクセストークン自動リフレッシュ（5分前バッファ）
   - POST body方式での認証（client_id/client_secretをPOSTパラメータに含める）
   - リバースプロキシ対応のHTTPS検出
   - ファイル: `mf-callback.php`, `mf-api.php`, `mf-settings.php`

3. **開発環境**
   - Windowsセットアップガイド（SETUP-WINDOWS.md）
   - PHPビルトインサーバー対応
   - Browser-sync自動リロード対応

### ❌ 未完了タスク

1. **MoneyForward OAuth2認証の動作確認**（本番環境で未テスト）
2. MoneyForward請求書作成機能の実装
3. クラウド勤怠連携
4. テストの実装
5. データバックアップ機能

## 重要な設計判断と経緯

### 1. データベース不使用

- **理由**: 小規模システムのためJSONファイルで十分
- **メリット**: デプロイ簡単、バックアップ容易
- **注意**: data.json/users.jsonは.gitignoreされている

### 2. PHP組み込みサーバー使用

- **理由**: Apache/MySQLのセットアップ不要
- **開発**: `php -S localhost:8000`
- **本番**: 適切なWebサーバー推奨

### 3. MoneyForward OAuth2認証の変更履歴

#### 失敗した実装（過去）
```php
// HTTP Basic認証ヘッダー → 403エラーで失敗
$authHeader = 'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret);
```

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

**変更理由**:
- MoneyForward APIドキュメントで両方サポートされているが、POST body方式が確実
- ユーザーが共有したGASの動作例もPOST body方式を使用していた

### 4. HTTPS検出のリバースプロキシ対応

```php
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
           (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
```

**理由**: 本番環境がロードバランサー/リバースプロキシ経由のため

### 5. ファイル名変更: mf-oauth.php → mf-callback.php

**理由**: MoneyForward側のリダイレクトURI設定と一致させる必要があった

## 重要なファイル

### 認証・OAuth2関連
- `mf-callback.php` - OAuth2コールバック、トークン取得
- `mf-api.php` - MF APIクライアント、トークンリフレッシュ
- `mf-settings.php` - MF連携設定UI
- `mf-config.json` - MF設定（.gitignore、本番のみ存在）

### データファイル（.gitignore）
- `data.json` - トラブル・マスターデータ
- `users.json` - ユーザー情報
- `mf-config.json` - MoneyForward設定

### ドキュメント
- `README.md` - プロジェクト概要、技術スタック、設計判断
- `SETUP-WINDOWS.md` - 詳細なセットアップ手順

## 現在の問題・課題

### 1. 本番環境のデータが消えた問題（解決済み？）

**経緯**:
- ユーザーが本番環境にプッシュしたところ、登録データが消えた
- data.json/users.jsonは.gitignoreされているため、Gitには含まれない
- 本番環境で`git pull`を実行してもこれらのファイルは上書きされないはず

**原因（推測）**:
- ファイルの全上書き/再アップロードした可能性
- デプロイ方法が不明

**解決策**:
- 本番環境のデプロイ方法を確認する必要あり
- データバックアップ機能の実装が必要

### 2. MoneyForward OAuth2認証

**状態**: 実装完了、本番環境で未テスト

**設定手順**:
1. MoneyForwardでアプリケーション作成
2. リダイレクトURI設定: `https://cil.yamato-basic.com/mf-callback.php`
3. Client ID/Secretを取得
4. システムの「MF連携設定」から認証

### 3. 開発環境のプレビュー問題

**問題**: Claude Codeがリモート環境で動作しているため、localhostに直接アクセスできない

**試した方法**:
- `php -S localhost:8000` → ブラウザで接続不可
- `npm run dev` (Browser-sync) → 接続不可
- localtunnel → うまく動作せず

**推奨解決策**:
- 本番環境で直接テスト
- またはユーザーのローカルWindowsマシンでセットアップ（SETUP-WINDOWS.md参照）

## Git ブランチ戦略

### プッシュ可能
- ✅ `claude/audit-dependencies-mk0uc1heu3tc90mg-wFx3f` (現在の作業ブランチ)

### プッシュ不可（403エラー）
- ❌ `main` - 別セッションのブランチのためプッシュ不可
- ❌ `claude/setup-trouble-management-ersIX` - 別セッションのブランチ

**注意**: セッションIDが一致しないブランチにはプッシュできない

## 本番デプロイ方法（不明点あり）

**本番環境**: https://cil.yamato-basic.com

**推奨手順**（未確認）:
```bash
cd /path/to/project
git fetch origin
git checkout claude/audit-dependencies-mk0uc1heu3tc90mg-wFx3f
git pull origin claude/audit-dependencies-mk0uc1heu3tc90mg-wFx3f
```

**重要**: data.json/users.jsonはバックアップしてから操作すること

## 次のセッションで必要な情報

### ユーザーの開発環境
- **OS**: Windows
- **パソコン**: 作業場所が変わる可能性あり
- **インストール済み**: PHP、Git（環境変数設定に苦労した）

### ユーザーの要望・傾向
- 日本語でのコミュニケーション希望
- GASで動作している実装を参考にしたい
- シンプルな構成を好む（XAMPP不要、データベース不要）
- 本番環境で直接テストすることに抵抗なし

### よく使うコマンド
```bash
# 開発サーバー起動
php -S localhost:8000

# ライブリロード付き（Node.js必要）
npm run dev

# Git操作
git checkout claude/audit-dependencies-mk0uc1heu3tc90mg-wFx3f
git add .
git commit -m "message"
git push -u origin claude/audit-dependencies-mk0uc1heu3tc90mg-wFx3f
```

## 次のアクション候補

1. **MoneyForward OAuth2認証の動作確認**
   - 本番環境でテスト
   - エラーが出たらデバッグ

2. **請求書作成機能の実装**
   - MF Invoice APIを使用
   - トラブルデータから請求書を自動生成

3. **データバックアップ機能**
   - data.json/users.jsonの自動バックアップ
   - 管理画面からバックアップ/リストア

4. **クラウド勤怠連携**
   - ユーザーから詳細を聞く必要あり

## 参考情報

- **MoneyForward Invoice API**: https://invoice.moneyforward.com
- **PHP公式**: https://windows.php.net/download/
- **Git for Windows**: https://git-scm.com/download/win

---

## 新しいセッションでの開始方法

このドキュメントを読んだら、まずユーザーに以下を確認してください：

1. 「何に取り組みたいですか？」
2. 「MoneyForward OAuth2認証のテストを進めますか？」
3. 「本番環境の状態は確認できましたか？」
4. 「データは復旧しましたか？」

そして、ユーザーの要望に応じて適切なサポートを提供してください。
