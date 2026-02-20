# テスト方法・カバレッジ現況

> このファイルは CLAUDE.md の補足ドキュメントです。
> テスト実行・チェックリスト・カバレッジ詳細はここを参照してください。

---

## テスト実行コマンド

```bash
# 全テスト実行
cd C:\Claude\master
C:\xampp\php\php.exe vendor/bin/phpunit

# 特定のテスト
C:\xampp\php\php.exe vendor/bin/phpunit tests/Unit/PermissionTest.php

# カバレッジ
C:\xampp\php\php.exe vendor/bin/phpunit --coverage-html coverage/
```

---

## テストファイル一覧と検出内容

| テストファイル | 検出できる問題 |
|---|---|
| `PermissionTest.php` | 権限階層(sales/product/admin)の破綻、hasPermission/isAdmin/canEdit/canDeleteの動作変更 |
| `DataSchemaTest.php` | エンティティ削除、フィールド定義変更、ensureSchemaの不具合、既存データとの互換性破壊 |
| `DataPersistenceTest.php` | getData/saveDataの破損防止ロジック、env()ヘルパー、JSON整合性 |
| `CsrfTest.php` | CSRFトークン生成・検証の破綻 |
| `PagePermissionTest.php` | ページ権限定義のフォーマット不正、edit<viewの矛盾、管理ページの保護漏れ |
| `SecurityFunctionTest.php` | パスワードポリシー検証、ipInCidr判定、HTTPS検出 |
| `SecurityTest.php` | sanitizeInput関数の動作 |
| `RegressionGuardTest.php` | **auth.phpのinclude漏れ、CSRF検証漏れ、重要関数の削除、スキーマ必須フィールド欠落** |
| `ValidationTest.php` | 入力バリデーション関数の動作 |
| `LoggerTest.php` | Loggerクラスの動作 |
| `EncryptionTest.php` | AES-256-GCM暗号化・復号の往復テスト、二重暗号化防止、フィールド暗号化、マスク関数、鍵生成 |

---

## テストが自動検出する「よくある壊し方」

- ページに `require_once '../api/auth.php'` を入れ忘れ → `RegressionGuardTest` が失敗
- POSTハンドラに `verifyCsrfToken()` を入れ忘れ → `RegressionGuardTest` が失敗
- `$defaultPagePermissions` に新ページを追加し忘れ → `PagePermissionTest` が警告
- `DataSchema` からフィールドを削除 → `DataSchemaTest` が失敗
- 権限チェック関数の条件を変更 → `PermissionTest` が失敗
- `canDelete()` を `canEdit()` に変えてしまう → `RegressionGuardTest` が失敗

---

## 手動テストチェックリスト

### 認証・権限テスト
```
□ 未ログイン状態でページアクセス → ログインページにリダイレクトされる
□ sales権限でadmin専用ページ → index.phpにリダイレクトされる
□ product権限で削除ボタン → 「削除権限がありません」エラー
□ 8時間放置後 → セッションタイムアウト
```

### CSRF保護テスト
```
□ CSRFトークンなしでPOST → 403エラー
□ 別タブで取得したトークンでPOST → 403エラー
□ 正しいトークンでPOST → 成功
```

### データ整合性テスト
```
□ 同時に2ブラウザで同じデータを編集 → 楽観的ロックで競合検出
□ 保存中にブラウザを閉じる → data.jsonが壊れていない
□ 大量データ（1000件以上）で動作確認
```

### 権限テスト用アカウント
- `sales`権限: 閲覧のみ可能か確認
- `product`権限: 編集可能・削除不可か確認
- `admin`権限: 全操作可能か確認

---

## テストカバレッジ現況（2026-02-10 調査）

- **テストファイル数:** 12（ユニットテストのみ）
- **テストメソッド数:** 183
- **インテグレーションテスト:** 0
- **カバレッジ:** テスト可能な関数 約250個中、テスト済み 約50個（約20%）

### ✅ テスト済み領域

| ファイル | カバレッジ | テスト数 |
|----------|-----------|---------|
| `functions/validation.php` | ~90% | 18 |
| `functions/encryption.php` | ~90% | 30 |
| `functions/data-schema.php` | ~95% | 23 |
| `functions/soft-delete.php` | ~80% | 10 |
| `config/config.php`（権限） | ~85% | 19 |
| `config/config.php`（CSRF） | ~70% | 10 |
| `config/config.php`（データ） | ~50% | 13 |
| `functions/security.php`（一部） | ~40% | 20 |
| `functions/logger.php` | ~60% | 9 |
| `api/auth.php`（権限定義） | ~60% | 8 |
| `functions/api-middleware.php`（sanitizeのみ） | ~15% | 9 |
| （横断チェック） | — | 19 |

### ❌ テスト未実施（優先度順）

**P0（致命的）:**
- `functions/api-middleware.php` — initApi/getJsonInput/requireParams等
- `api/integration/api-auth.php` — 外部API認証
- `functions/security.php`（残り） — レート制限・セキュリティヘッダー
- `functions/audit-log.php` — 署名・検証ロジック

**P1（高）:**
- `functions/login-security.php` — セッション管理・ログイン追跡
- `functions/optimistic-lock.php` — 同時編集制御
- Google/MF APIクライアント全体

**P2（中）:**
- `functions/notification-functions.php` — メール通知
- `api/pdf-processor.php` — PDF処理
- `scripts/backup-data.php` — バックアップ・リストア

### テスト追加ロードマップ

**フェーズ1（1-2週間）:** ApiMiddlewareTest, ApiAuthIntegrationTest, AuditLogTest, RateLimitTest, IntegrationApiTest

**フェーズ2（2-4週間）:** LoginSecurityTest, OptimisticLockTest, MFAutoMapperTest, MFInvoiceSyncTest, Google/MF APIモックテスト

**フェーズ3（1-2ヶ月）:** NotificationTest, PhotoAttendanceTest, BackupRestoreTest, CRUDフロー統合テスト
