# セッション引き継ぎドキュメント

このドキュメントを新しいClaude Codeセッションまたは別のAIアシスタントで使用して、プロジェクトの作業を継続できます。

---

# YA管理一覧システム - プロジェクト引き継ぎ

## プロジェクト概要

**プロジェクト名**: YA管理一覧システム
**リポジトリ**: https://github.com/managementyamato/cli
**作業ブランチ**: `claude/review-handoff-audit-cWcIL`
**本番環境**: https://cil.yamato-basic.com

トラブル管理と請求書作成を統合したWebベースの管理システム。MoneyForward Invoice APIと連携し、プロジェクトごとのトラブル記録から請求書作成までを一元管理します。

## 📍 現在の状態（最終更新: 2026/01/12）

### ✅ 完了している機能

#### 1. 基本機能
- ユーザー認証・管理（管理者/編集者/閲覧者）
- マスターデータ管理（顧客/パートナー/従業員/商品）
- トラブル管理（記録/一覧/編集/削除/CSVエクスポート）
- プロジェクト管理

#### 2. **MoneyForward連携（✅ 開発環境で動作確認済み）**
- OAuth2認証フロー実装（Authorization Code Grant）
- **SSL証明書検証バイパス設定済み（開発環境用）**
- アクセストークン自動リフレッシュ（5分前バッファ）
- POST body方式での認証（CLIENT_SECRET_POST）
- **過去3ヶ月分の請求書データ自動同期**
- プロジェクト名による自動照合

#### 3. **財務管理画面（最新）**
- 純利益表示・プロジェクト別収支管理
- **MF同期データの視覚表示（青色バッジ）**
- **MF同期済み案件数の統計カード表示**
- **更新日時の表示**

#### 4. 開発環境
- PHPビルトインサーバー対応
- Windows用バッチファイル（start-server.bat）
- Browser-sync自動リロード対応
- どこからでもGitHubから最新版を取得可能

### ❌ 未完了タスク

1. MoneyForward OAuth2認証の動作確認（本番環境）
2. MoneyForward請求書作成機能の実装
3. クラウド勤怠連携
4. テストの実装
5. データバックアップ機能

## 🔧 技術スタック

- **PHP 8.0+**: ビルトインサーバー使用（**Apache/MySQL不要**）
- **JavaScript**: Vanilla JS（フレームワーク不使用）
- **データ保存**: JSONファイル（data.json, users.json, mf-config.json）
- **外部API**: MoneyForward Invoice API v3
- **認証方式**: OAuth2 Authorization Code Grant
- **開発サーバー**: PHP組み込みサーバー、Browser-sync（オプション）

## 📂 重要なファイルと設定

### 1. mf-callback.php
**役割**: OAuth2認証コールバックハンドラー

**重要な設定**:
```php
// OAuth2エンドポイント（MoneyForward Business API）
define('MF_AUTH_URL', 'https://api.biz.moneyforward.com/authorize');
define('MF_TOKEN_URL', 'https://api.biz.moneyforward.com/token');

// SSL証明書の検証を無効化（開発環境のみ）
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// 認証パラメータ
$authParams = array(
    'client_id' => $config['client_id'],
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'mfc/invoice/data.write'  // 重要：このスコープが必要
);
```

**場所**: mf-callback.php (行14-15, 75-76, 140-145)

### 2. mf-api.php
**役割**: MoneyForward API クライアント

**重要な設定**:
```php
// エンドポイント
private $apiEndpoint = 'https://invoice.moneyforward.com/api/v3';
private $tokenUrl = 'https://api.biz.moneyforward.com/token';  // トークン更新用

// refreshAccessToken() メソッド内 - SSL検証バイパス
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// request() メソッド内 - SSL検証バイパス
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
```

**場所**: mf-api.php (行12-13, 82-83, 158-159)

### 3. finance.php
**役割**: 財務管理画面

**追加されたスタイル**（MF同期表示用）:
```css
.mf-sync-badge {
    display: inline-block;
    background: #3b82f6;  /* 青色 */
    color: white;
    font-size: 0.7rem;
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    margin-left: 0.5rem;
    font-weight: 500;
}

.sync-date {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-top: 0.2rem;
}
```

**MF同期済みカウント処理**:
```php
$mfSyncedCount = 0;
if (isset($data['finance']) && !empty($data['finance'])) {
    foreach ($data['finance'] as $finance) {
        if (isset($finance['mf_synced']) && $finance['mf_synced']) {
            $mfSyncedCount++;
        }
    }
}
```

**場所**: finance.php (行174-189, 202-213, 296-301)

### 4. mf-settings.php
**役割**: MoneyForward連携設定画面

**注意**: `office_id`フィールドは削除されています（GAS実装に合わせて不要）

## 🔑 MoneyForward設定情報

### アプリケーション設定（MoneyForwardクラウド会計側）

| 項目 | 値 |
|------|-----|
| **アプリケーション名** | トラブル対応 |
| **Client認証方法** | `CLIENT_SECRET_POST` |
| **リダイレクトURI（開発）** | `http://localhost:8000/mf-callback.php` |
| **リダイレクトURI（本番）** | `https://cil.yamato-basic.com/mf-callback.php` |
| **スコープ** | `mfc/invoice/data.write` |
| **Client ID** | 194812003555014 |

### mf-config.json構造

このファイルは.gitignoreに含まれています（秘密情報のため）。

```json
{
    "client_id": "194812003555014",
    "client_secret": "（シークレット - 各環境で設定）",
    "access_token": "（自動生成）",
    "refresh_token": "（自動生成）",
    "expires_in": 3600,
    "token_obtained_at": 1234567890,
    "updated_at": "2026-01-12 12:34:56"
}
```

## 🚀 セットアップ手順（どのPCからでも）

### 前提条件
- PHP 8.0+
- Git
- Node.js（ライブリロード使う場合のみ）

### 手順

```bash
# 1. リポジトリをクローン
git clone https://github.com/managementyamato/cli.git
cd cli

# 2. 作業ブランチに切り替え
git checkout claude/review-handoff-audit-cWcIL

# 3. 最新版を取得
git pull origin claude/review-handoff-audit-cWcIL

# 4. 開発サーバー起動
php -S localhost:8000

# Windowsの場合: start-server.bat をダブルクリックでも起動可能
```

### MoneyForward認証の初回設定

1. ブラウザで `http://localhost:8000/mf-settings.php` にアクセス
2. Client IDとClient Secretを入力して「認証情報を保存」
3. 「OAuth2認証を開始」ボタンをクリック
4. MoneyForwardのログイン画面で認証
5. 認証成功後、自動的に設定画面に戻る
6. 「認証に成功しました」と表示されればOK

### MFデータ同期の使い方

1. `http://localhost:8000/finance.php` にアクセス
2. 右上の「MFから同期」ボタンをクリック
3. 過去3ヶ月分の請求書データが自動的にプロジェクトと照合される
4. 同期されたプロジェクトには青い「MF同期」バッジが表示される
5. 統計カードに「MF同期済み」件数が表示される

## 🐛 トラブルシューティング

### 問題1: "トークンのリフレッシュに失敗しました (HTTP 0)"

**原因**: SSL証明書検証の問題またはトークンが期限切れ

**解決策**:
1. `mf-api.php`と`mf-callback.php`でSSL検証バイパスが設定されているか確認
   ```php
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
   curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
   ```
2. 上記が設定されていない場合は、GitHubから最新版をpull
3. それでもダメな場合は、もう一度OAuth2認証をやり直す（mf-settings.php）

### 問題2: 404 Not Found

**原因**: PHPサーバーが起動していない、または違うディレクトリで起動している

**解決策**:
```bash
# 正しいディレクトリにいるか確認
pwd
ls -la index.php  # index.phpがあるはず

# PHPサーバーを再起動
# Windowsの場合はCtrl+Cで停止してから
php -S localhost:8000
```

### 問題3: 古いバージョンのファイルが使われている

**原因**: GitHubから最新版をpullしていない

**解決策** - 方法1（既存フォルダを更新）:
```bash
cd cli
git stash  # 未保存の変更があれば一旦退避
git fetch origin
git checkout claude/review-handoff-audit-cWcIL
git reset --hard origin/claude/review-handoff-audit-cWcIL
```

**解決策** - 方法2（クリーンな状態で再取得・推奨）:
```bash
cd ..
mv cli cli-old  # バックアップ
git clone https://github.com/managementyamato/cli.git
cd cli
git checkout claude/review-handoff-audit-cWcIL
```

### 問題4: MF同期が表示されない

**原因**: まだMF同期を実行していない、またはプロジェクト名が一致しない

**解決策**:
1. finance.phpで「MFから同期」ボタンをクリック
2. プロジェクト名がMoneyForwardの請求書タイトルと部分一致するか確認
3. 一致しない場合は、プロジェクト名を調整するか、手動で財務データを入力

## 📊 重要な設計判断

### なぜSSL検証をバイパスしているのか？

**理由**: 開発環境でのSSL証明書検証エラーを回避するため

**注意**: 本番環境では必ず削除または条件分岐で無効化してください

```php
// 本番環境では以下のように条件分岐推奨
if (getenv('APP_ENV') === 'development') {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
}
```

### なぜClient認証方法がCLIENT_SECRET_POSTなのか？

**経緯**:
1. 当初はHTTP Basic認証を試したが403エラーで失敗
2. ユーザーが共有したGASの動作例がPOST body方式を使用
3. MoneyForward APIドキュメントで両方サポートされているが、POST body方式が確実に動作

### なぜデータベースを使わないのか？

**理由**:
- 小規模システムのためJSONファイルで十分な性能
- デプロイが簡単（ファイルコピーだけ）
- バックアップが容易
- 依存関係が最小限

**注意**: data.json/users.json/mf-config.jsonは.gitignoreされている

## 📦 Git管理

### プッシュ可能なブランチ
- ✅ `claude/review-handoff-audit-cWcIL` （現在の作業ブランチ）

### 最新のコミット履歴
```bash
9644dd0 README.mdを更新：最新の開発状況とMF認証完了を反映
b98379a 財務管理画面にMF同期データの視覚表示を追加
75d76c0 MF API クライアントを更新：トークンエンドポイントとSSL設定の修正
51a2972 SSL証明書検証を無効化（開発環境用）- HTTPコード0エラーの修正
8004c55 デバッグ情報を追加：MF OAuth2トークン取得エラーの詳細を表示
```

### コミット・プッシュ手順
```bash
# 変更をステージング
git add .

# コミット
git commit -m "変更内容の説明"

# プッシュ（必ずこのブランチに）
git push -u origin claude/review-handoff-audit-cWcIL
```

**注意**: セッションIDが一致しないブランチにはプッシュできません（403エラー）

## 🎯 次のアクション候補

### 優先度: 高
1. **本番環境でのMF OAuth2認証テスト**
   - 本番環境（https://cil.yamato-basic.com）でMF認証をテスト
   - リダイレクトURIを本番用に変更する必要あり
   - SSL検証バイパスを本番環境では無効化

2. **MoneyForward請求書作成機能の実装**
   - MF Invoice APIを使用
   - トラブルデータから請求書を自動生成
   - プロジェクト単位での請求書作成

### 優先度: 中
3. **データバックアップ機能**
   - data.json/users.jsonの自動バックアップ
   - 管理画面からバックアップ/リストア機能

4. **エラーハンドリングの強化**
   - MF API エラー時のリトライ機能
   - わかりやすいエラーメッセージ表示

### 優先度: 低
5. **クラウド勤怠連携**
   - ユーザーから詳細を聞く必要あり

## 📝 新しいセッションでの開始プロンプト

別のClaude会話やAIアシスタントでこのプロジェクトの作業を継続する場合、以下のプロンプトを使用してください：

```
以下のプロジェクトの作業を引き継ぎます。

【プロジェクト名】
YA管理一覧システム

【リポジトリ】
https://github.com/managementyamato/cli

【作業ブランチ】
claude/review-handoff-audit-cWcIL

【現在の状態】
- MoneyForward OAuth2認証が開発環境で動作確認済み
- 財務管理画面でMF同期データの視覚表示が実装済み（青色バッジ、統計カード）
- SSL証明書検証バイパス設定済み（開発環境用）
- 過去3ヶ月分の請求書データ自動同期機能が動作中

【技術スタック】
- PHP 8.0+（ビルトインサーバー、Apache/MySQL不要）
- データ保存: JSONファイル
- MoneyForward Invoice API v3（OAuth2認証）

【重要なファイル】
- mf-callback.php: OAuth2コールバック（SSL検証バイパス設定済み）
- mf-api.php: MF APIクライアント（SSL検証バイパス設定済み）
- finance.php: 財務管理画面（MF同期バッジ表示）
- mf-settings.php: MF連携設定UI

【詳細情報】
リポジトリ内のSESSION_HANDOFF.mdを参照してください。

【次のタスク（希望）】
（必要に応じて記載）
```

## 🔒 セキュリティ注意事項

### 重要
- **SSL検証バイパスは開発環境のみ**: 本番環境では必ず削除または条件分岐
- **mf-config.jsonは.gitignore済み**: 絶対にコミットしない
- **Client Secretは秘密情報**: 公開リポジトリにプッシュしない
- **本番環境のデータバックアップ**: デプロイ前に必ずバックアップ

### MoneyForward API制限
- **トークン有効期限**: 1時間（自動リフレッシュ機能実装済み）
- **リフレッシュバッファ**: トークン期限の5分前に自動リフレッシュ
- **同期対象期間**: 過去3ヶ月分の請求書データ

## 📚 参考ドキュメント

- **README.md**: プロジェクト全体の説明、技術スタック
- **SETUP-WINDOWS.md**: Windows環境の詳細セットアップガイド
- **MoneyForward Invoice API**: https://invoice.moneyforward.com/api/v3/docs

## 👤 ユーザー情報

### 環境
- **OS**: Windows
- **作業場所**: 複数の場所で作業する可能性あり
- **言語**: 日本語

### 特徴
- GASで動作している実装を参考にしたい
- シンプルな構成を好む（XAMPP不要、データベース不要）
- GitHubから最新版を取得して、どこでも開発できる環境を希望

---

**最終更新**: 2026/01/12
**作成者**: Claude (Anthropic)
**バージョン**: 2.0
