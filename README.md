# 現場トラブル管理システム

## セットアップ手順

### 1. リポジトリをクローン

```bash
git clone https://github.com/managementyamato/cli.git trouble-management
cd trouble-management
git checkout claude/setup-trouble-management-ersIX
```

### 2. 依存パッケージをインストール

```bash
npm install
```

### 3. 開発サーバーを起動

```bash
npm run dev
```

起動すると以下のように表示されます：

```
[Browsersync] Proxying: http://localhost:8000
[Browsersync] Access URLs:
 ---------------------------------------
       Local: http://localhost:3000
    External: http://192.168.x.x:3000
 ---------------------------------------
[PHP] Development Server started
```

### 4. ブラウザでアクセス

```
http://localhost:3000
```

**ファイルを編集すると自動的にブラウザがリロードされます！**

## 開発フロー

1. **ファイル編集** → エディタでPHP/CSS/JSファイルを編集
2. **保存** → 自動的にブラウザがリロード
3. **確認** → 即座にプレビュー確認

## コマンド

```bash
# 開発サーバー起動（自動リロード付き）
npm run dev

# PHPサーバーのみ起動
npm run server

# プレビュー（npm run dev と同じ）
npm run preview
```

## 技術スタック

- **PHP 7.4+**: バックエンド
- **Browser-sync**: 自動リロード・ブラウザ同期
- **Concurrently**: 複数プロセス同時実行

## 初回セットアップ

初回アクセス時は自動的に `setup.php` にリダイレクトされます。

1. 管理者アカウントを作成
   - メールアドレス
   - パスワード
   - 名前

2. ログイン

## 機能

- トラブル報告・管理
- プロジェクト管理
- 財務管理（純利益表示）
- マネーフォワード クラウド会計 API連携
- 顧客・パートナー・従業員・商品マスタ管理
- ユーザー管理（権限: 管理者/編集者/閲覧者）

## ディレクトリ構成

```
trouble-management/
├── index.php           # ダッシュボード
├── list.php            # トラブル一覧
├── report.php          # トラブル報告
├── master.php          # プロジェクト管理
├── finance.php         # 財務管理
├── mf-settings.php     # MF連携設定
├── users.php           # ユーザー管理
├── config.php          # 設定ファイル
├── auth.php            # 認証
├── style.css           # スタイル
├── data.json           # データ（除外）
├── users.json          # ユーザー（除外）
└── package.json        # npm設定
```

## ライセンス

ISC
