# 本番デプロイチェックリスト - 2026-02-09

**デプロイ日時**: 2026-02-09 23:41
**対象環境**: 本番（yamato-mgt.com）
**デプロイ内容**: P0セキュリティ修正 + XSS脆弱性修正

---

## ✅ デプロイ完了ファイル

### 修正されたファイル（7ファイル）
- ✅ `api/mf-api.php` - MF APIキー環境変数対応
- ✅ `functions/audit-log.php` - HMAC-SHA256署名実装
- ✅ `js/icons.js` - XSS脆弱性修正（onclick属性削除）
- ✅ `pages/customers.php` - 営業所削除の論理削除化
- ✅ `pages/master.php` - 案件詳細モーダルのXSS修正

### バックアップファイル
- ✅ `pages/loans.php.backup`
- ✅ `pages/troubles.php.backup`

---

## 🔧 デプロイ後の必須設定

### ⚠️ 重要：以下の設定を必ず実施してください

#### 1. 環境変数の設定（本番サーバー）

本番サーバーの`.env`ファイルに以下を追加：

```bash
# SSH接続
ssh <本番サーバー>

# .envファイルを編集
cd /path/to/yamato-mgt.com/public_html
nano .env
```

追加する内容：
```bash
# 監査ログ署名キー（必須）
# 以下のコマンドで生成:
# php -r "echo bin2hex(random_bytes(32));"
AUDIT_LOG_SIGNING_KEY=<生成したキーをここに貼り付け>

# MF APIキー（既にMF連携を使用している場合）
# config/mf-config.json から移行
MF_CLIENT_ID=<既存のClient IDをここに>
MF_CLIENT_SECRET=<既存のClient Secretをここに>
MF_ACCESS_TOKEN=<既存のAccess Tokenをここに>
MF_REFRESH_TOKEN=<既存のRefresh Tokenをここに>
```

#### 2. 暗号化キーのバックアップ（本番サーバー）

```bash
# 初回バックアップ実行
php scripts/backup-encryption-key.php --backup

# 自動バックアップ設定（毎日午前3時）
bash scripts/setup-key-backup-cron.sh
```

#### 3. 監査ログの整合性検証

```bash
# 既存ログの署名は後方互換で動作するため、
# 次回のログ記録から署名が付与されます

# 検証スクリプト作成（オプション）
php -r "
require 'functions/audit-log.php';
\$result = verifyAuditLogIntegrity();
echo 'Total: ' . \$result['total'] . PHP_EOL;
echo 'Verified: ' . \$result['verified'] . PHP_EOL;
echo 'Unsigned: ' . \$result['unsigned'] . PHP_EOL;
echo 'Failed: ' . \$result['failed'] . PHP_EOL;
"
```

---

## ✅ 動作確認手順

### 1. 基本動作確認

- [ ] ログイン画面にアクセス
- [ ] Google OAuth認証が正常に動作
- [ ] ダッシュボードが表示される

### 2. セキュリティ機能確認

#### 監査ログ署名（新規ログのみ）
- [ ] 任意の操作（例：顧客編集）を実行
- [ ] `data/audit-log.json`を確認し、最新エントリに`signature`フィールドがあることを確認

#### MF API環境変数（既に連携済みの場合のみ）
- [ ] MF請求書同期を実行
- [ ] エラーなく動作することを確認

### 3. XSS修正の確認

#### iconButton関数
- [ ] 顧客一覧ページで編集・削除ボタンが正常に動作
- [ ] ブラウザの開発者ツールで、ボタンに`data-action`属性があり、`onclick`属性がないことを確認

#### 案件詳細モーダル（master.php）
- [ ] 案件一覧から案件をクリック
- [ ] 詳細モーダルが表示され、特殊文字（`<script>`など）が正しくエスケープされることを確認

### 4. 論理削除の確認

#### 営業所削除
- [ ] 顧客ページで営業所を削除
- [ ] 削除後、営業所が表示されなくなることを確認
- [ ] ゴミ箱ページ（`/pages/trash.php`）で削除済み営業所が表示されることを確認（admin権限のみ）

---

## 🚨 トラブルシューティング

### 問題1: 監査ログ署名エラー

**症状**: 「監査ログ署名キーが設定されていません」エラー

**対処**:
1. `.env`ファイルに`AUDIT_LOG_SIGNING_KEY`が設定されているか確認
2. `config/encryption.key`が存在するか確認（代替キーとして使用）
3. PHPから環境変数が読み込めるか確認: `php -r "echo getenv('AUDIT_LOG_SIGNING_KEY');"`

### 問題2: MF API連携エラー

**症状**: MF請求書同期が失敗

**対処**:
1. `.env`に`MF_CLIENT_ID`等が設定されているか確認
2. または`config/mf-config.json`が存在するか確認（後方互換）
3. エラーログ（`logs/error_*.log`）を確認

### 問題3: ボタンが動作しない（iconButton）

**症状**: 編集・削除ボタンをクリックしても反応しない

**原因**: `iconButton()`の第3引数（旧`onClick`）を使用しているコード

**対処**:
1. 該当ページのJavaScriptコードを確認
2. `iconButton()`呼び出しで`onClick`を渡している箇所を修正
3. イベントリスナーで登録するように変更

---

## 📊 デプロイ前後の比較

| 項目 | デプロイ前 | デプロイ後 |
|-----|----------|----------|
| **監査ログ改竄防止** | ❌ なし | ✅ HMAC-SHA256署名 |
| **MF APIキー管理** | ⚠️ 平文ファイル | ✅ 環境変数優先 |
| **XSS脆弱性（iconButton）** | 🔴 onclick属性 | ✅ data属性 |
| **XSS脆弱性（master.php）** | 🔴 エスケープ漏れ | ✅ escapeHtml適用 |
| **営業所削除** | ⚠️ 物理削除 | ✅ 論理削除 |
| **暗号化キーバックアップ** | ❌ なし | ✅ スクリプト実装 |

---

## 📝 ロールバック手順（緊急時のみ）

バックアップから復元する場合：

```bash
# バックアップディレクトリ確認
ls backups/20260209_234103/

# ファイルを個別に復元
cp backups/20260209_234103/mf-api.php api/
cp backups/20260209_234103/audit-log.php functions/
cp backups/20260209_234103/icons.js js/
cp backups/20260209_234103/customers.php pages/
cp backups/20260209_234103/master.php pages/
```

---

## ✅ 完了確認

デプロイ担当者は以下を確認してサインしてください：

- [ ] 全ファイルが正常にアップロードされた
- [ ] `.env`ファイルに署名キーを設定した
- [ ] 暗号化キーの初回バックアップを実行した
- [ ] 基本動作確認が完了した
- [ ] セキュリティ機能確認が完了した
- [ ] XSS修正の確認が完了した

**担当者**: ___________________
**確認日時**: ___________________
**サイン**: ___________________

---

## 📚 関連ドキュメント

- [セキュリティ監査報告書](./SECURITY-AUDIT-2026-02-09.md)
- [P0セキュリティ修正完了報告](./P0-SECURITY-FIX-SUMMARY.md)
- [変更履歴](../CLAUDE.md#変更履歴)

---

**作成者**: Claude Sonnet 4.5
**最終更新**: 2026-02-09 23:41
