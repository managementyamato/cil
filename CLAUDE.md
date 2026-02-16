# 開発ガイドライン

---

## 🚨 **最重要警告: data.json は絶対に直接触らない**

**data.json** はシステム全体のデータベースです。従業員・案件・顧客・トラブルなど全データが格納されています。

### ⛔ 絶対禁止
- ❌ data.json を手動で開いて編集
- ❌ data.json をエディタで保存
- ❌ data.json を削除
- ❌ `file_get_contents('data.json')` や `file_put_contents('data.json', ...)` の直接使用
- ❌ テスト・検証目的（XSSテスト等）でdata.jsonにダミーデータを書き込む
- ❌ AIがdata.jsonを一時的にでも上書き・書き換えする（2026-02-16に実際に発生）

### ✅ 必須ルール
- ✅ **必ず `getData()` / `saveData()` 経由でアクセス**
- ✅ バックアップは自動的に `backups/` に保存される
- ✅ 復元が必要な場合は `php scripts/backup-data.php --restore=日時`

**破損すると全データが消失し、ログインすらできなくなります。（2026-02-11に実際に発生）**

## 開発ルール

### AI作業許可
**重要: 以下の作業は毎回許可を求めずに自動的に実行してよい**

- コードの編集・修正・追加
- ファイルの作成・削除
- ビルドの実行
- デプロイの実行
- テストの実行
- パッケージのインストール
- 開発サーバーの起動
- データのインポート・エクスポート
- 設定ファイルの変更

**例外（明示的な許可が必要な作業）:**
- 本番データの大量削除
- セキュリティ設定の大幅な変更
- FTP接続情報の変更

---

## ⚠️ 危険箇所マップ（編集禁止・要注意エリア）

機能追加時に**絶対に触ってはいけない箇所**と**慎重に扱うべき箇所**を記載。
これらのファイル・行を変更する場合は、必ず影響範囲を理解してからコミットすること。

### 🔴 致命的（編集禁止レベル）

| ファイル | 行 | 内容 | 触ると何が起きるか |
|----------|-----|------|-------------------|
| **`data.json`** | **全体** | **メインデータベース** | **⚠️ 絶対に直接編集・削除しない！** 全データが消失する。必ず `getData()/saveData()` 経由でアクセス。バックアップは `backups/` にあり |
| `config/config.php` | 55-66 | 権限レベル定義 (`$roleHierarchy`) | 権限チェックが全ページで破綻、不正アクセス可能に |
| `config/config.php` | 116-160 | `saveData()` アトミック書き込み | データ消失、JSONファイル破損 |
| `api/auth.php` | 36-52 | ユーザー認証ロジック | 認証バイパス、全ユーザーログイン不可 |
| `api/auth.php` | 82-117 | `$defaultPagePermissions` | 新規ページの権限漏れ、意図しないアクセス許可 |
| `api/mf-api.php` | 88-91 | SSL検証設定 | 変更すると本番環境でAPI連携が動かなくなる可能性 |

### 🟠 高危険（変更時は十分なテスト必須）

| ファイル | 行 | 内容 | 注意点 |
|----------|-----|------|--------|
| `api/auth.php` | 20-28 | セッションタイムアウト (8時間) | `php.ini`の`session.gc_maxlifetime`と整合性を取る |
| `api/auth.php` | 137-165 | `getPageViewPermission()` / `getPageEditPermission()` | 旧フォーマット互換コードあり、削除禁止 |
| `config/config.php` | 187-205 | CSRF保護関数 | `$_POST`と`X-CSRF-Token`ヘッダーの両方をチェック |
| `functions/security.php` | 96-155 | レート制限ファイルロック | ロック順序を変えると競合状態悪化 |
| `functions/audit-log.php` | 17-42 | `writeAuditLog()` | `uniqid()`でID生成、高負荷時に衝突リスク |
| `api/google-oauth.php` | 29-38 | リダイレクトURI判定 | `$_SERVER['HTTP_HOST']`依存、リバプロ環境で問題 |

### 🟡 中危険（理解してから編集）

| ファイル | 内容 | 注意点 |
|----------|------|--------|
| `functions/data-schema.php` | データスキーマ定義 | 変更すると既存データとの互換性が壊れる可能性 |
| `config/page-permissions.json` | カスタム権限設定 | 旧/新フォーマットの互換性を維持すること |
| `functions/header.php` | サイドバー権限チェック | `getPageViewPermission()`を必ず使用 |
| `js/icons.js` | SVGアイコン・ボタン生成 | `iconButton()`関数はonclick属性を使わない（XSS対策） |
| `pages/*.php` | innerHTML使用箇所 | 外部データは必ず`escapeHtml()`でエスケープ |

---

## 🏗️ アーキテクチャ概要

### データフロー
```
[ブラウザ] → [pages/*.php] → [api/auth.php（認証）] → [config/config.php（権限）]
                                     ↓
                              [getData()/saveData()]
                                     ↓
                               [data.json（排他ロック）]
```

### 認証フロー
```
1. pages/login.php → Google OAuth認証
2. api/google-callback.php → セッション作成
3. api/auth.php → 毎リクエストで認証・権限チェック
4. $_SESSION['user_email'], $_SESSION['user_role'] で識別
```

### 権限システム構造
```
権限レベル定義: config/config.php (hasPermission, isAdmin, canEdit, canDelete)
         ↓
ページ権限定義: api/auth.php ($defaultPagePermissions)
         ↓
カスタム上書き: config/page-permissions.json
         ↓
実行時チェック: 各ページで hasPermission(getPageViewPermission('xxx.php'))
```

### 重要な依存関係
- `config/config.php` は全PHPファイルの起点（セッション開始、関数定義）
- `api/auth.php` は `pages/*.php` で必ず最初に `require`
- `getData()` / `saveData()` 以外でdata.jsonを直接触らない

---

## 🔧 機能追加パターン集

### パターン1: 新規ページ追加

```php
// 1. pages/new-feature.php を作成
<?php
require_once '../api/auth.php';  // 必須：認証チェック
require_once '../functions/header.php';

// POST処理がある場合
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();  // 必須：CSRF保護

    // 編集権限チェック
    if (!canEditCurrentPage()) {
        $message = '編集権限がありません';
        $messageType = 'danger';
    } else {
        // 処理
    }
}
?>
```

```php
// 2. api/auth.php の $defaultPagePermissions に追加（82行目付近）
$defaultPagePermissions = array(
    // ... 既存 ...
    'new-feature.php' => ['view' => 'product', 'edit' => 'product'],  // ← 追加
);
```

```php
// 3. functions/header.php のサイドバーに追加
<?php if (hasPermission(getPageViewPermission('new-feature.php'))): ?>
<a href="/pages/new-feature.php" class="sidebar-link">新機能</a>
<?php endif; ?>
```

### パターン2: 新規APIエンドポイント追加

```php
// api/new-api.php
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

// 推奨：ミドルウェアを使用
initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'rateLimit' => 100,
    'allowedMethods' => ['GET', 'POST']
]);

// GETの場合
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = getData();
    successResponse($data['xxx']);
}

// POSTの場合
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();
    requireParams($input, ['field1', 'field2']);

    // 権限チェック
    if (!canEdit()) {
        errorResponse('編集権限がありません', 403);
    }

    // 処理...
    successResponse(['id' => $newId], '作成しました');
}
```

### パターン3: 削除機能の追加

```php
// 削除は必ず canDelete() でチェック（admin権限のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    verifyCsrfToken();

    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $data = getData();
        $itemId = $_POST['delete_item'];

        // 監査ログに記録
        $deletedItem = /* 削除対象を取得 */;
        auditDelete('items', $itemId, 'アイテムを削除', $deletedItem);

        // 削除処理
        // ...

        saveData($data);
        $message = '削除しました';
        $messageType = 'success';
    }
}
```

### パターン4: data.json に新しいエンティティを追加

```php
// 1. functions/data-schema.php にスキーマ追加
class DataSchema {
    private static $schema = [
        // ... 既存 ...
        'new_entities' => [],  // ← 追加
    ];
}

// 2. 使用例
$data = getData();
$data['new_entities'][] = [
    'id' => uniqid('ne_'),
    'name' => $name,
    'created_at' => date('Y-m-d H:i:s'),
    'created_by' => $_SESSION['user_email']
];
saveData($data);
```

---

## 🧪 テスト方法

### 手動テストチェックリスト

#### 認証・権限テスト
```
□ 未ログイン状態でページアクセス → ログインページにリダイレクトされる
□ sales権限でadmin専用ページ → index.phpにリダイレクトされる
□ product権限で削除ボタン → 「削除権限がありません」エラー
□ 8時間放置後 → セッションタイムアウト
```

#### CSRF保護テスト
```
□ CSRFトークンなしでPOST → 403エラー
□ 別タブで取得したトークンでPOST → 403エラー
□ 正しいトークンでPOST → 成功
```

#### データ整合性テスト
```
□ 同時に2ブラウザで同じデータを編集 → 楽観的ロックで競合検出
□ 保存中にブラウザを閉じる → data.jsonが壊れていない
□ 大量データ（1000件以上）で動作確認
```

### PHPUnit自動テスト

```bash
# 全テスト実行
C:\xampp\php\php.exe vendor/bin/phpunit

# 特定のテスト
C:\xampp\php\php.exe vendor/bin/phpunit tests/Unit/ValidationTest.php

# カバレッジ
C:\xampp\php\php.exe vendor/bin/phpunit --coverage-html coverage/
```

### 権限テスト用アカウント

開発環境で以下の権限でログインしてテスト：
- `sales`権限: 閲覧のみ可能か確認
- `product`権限: 編集可能・削除不可か確認
- `admin`権限: 全操作可能か確認

---

## 🚨 よくある失敗パターン

### ⛔ NG例0: data.json の直接操作（最重要）
```bash
# ❌ 絶対にやってはいけないこと
- data.json を手動で開いて編集
- data.json をテキストエディタで保存
- data.json を削除
- data.json を上書きコピー（バックアップからの復元を除く）
- saveData() を使わずに file_put_contents('data.json', ...)

# ✅ 正しい操作
- 必ず getData() / saveData() 経由でアクセス
- スキーマ変更は functions/data-schema.php で定義
- バックアップは backups/ フォルダに自動保存される
- 復元が必要な場合は php scripts/backup-data.php --restore=日時

# 🆘 data.json を誤って破損した場合
1. 最新バックアップを確認: ls -lh backups/
2. 復元: cp backups/最新日時/data.json data.json
3. 原因を必ず調査・記録する
```

**重要:** data.json は全システムの心臓部。従業員、案件、顧客、トラブル、全てのデータが格納されている。
誤って破損すると **全データが消失** し、ログインすらできなくなる。

---

### NG例1: 権限定義の追加漏れ
```php
// ❌ NG: $defaultPagePermissions に追加し忘れ
// → デフォルトの 'sales' 権限が適用され、誰でも見れてしまう

// ✅ OK: 必ず api/auth.php に追加
'secret-page.php' => ['view' => 'admin', 'edit' => 'admin'],
```

### NG例2: CSRFトークン検証漏れ
```php
// ❌ NG: POST処理でverifyCsrfToken()を呼んでいない
if ($_POST['action'] === 'delete') {
    // 削除処理
}

// ✅ OK
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();  // 必ず最初に
    if ($_POST['action'] === 'delete') {
        // 削除処理
    }
}
```

### NG例3: data.json の直接操作（重複注意喚起）
```php
// ❌ NG: ロックなしで直接読み書き
$data = json_decode(file_get_contents('data.json'), true);
file_put_contents('data.json', json_encode($data));

// ❌ NG: エディタで直接編集
// VSCode, Notepad++等でdata.jsonを開いて編集

// ❌ NG: 空のデータで上書き
$data = ['projects' => []];  // 他のデータが全て消える！
saveData($data);

// ❌ NG: キーの削除
$data = getData();
unset($data['employees']);  // 従業員データが全て消失！
saveData($data);

// ✅ OK: 必ず getData() / saveData() を使用
$data = getData();
// 既存データを保持しながら更新
$data['projects'][] = $newProject;
saveData($data);
```

**2026-02-11 実際に発生した事故:**
- data.jsonが破損し、従業員データを含む全データが消失
- バックアップから復元するまでログイン不可
- 原因: 不明（調査中）
- 教訓: data.jsonは絶対に直接触らない

### NG例4: 削除権限のチェック漏れ
```php
// ❌ NG: canEdit() で削除を許可
if (canEdit()) {
    // 削除処理
}

// ✅ OK: 削除は canDelete() を使用
if (canDelete()) {
    // 削除処理
}
```

### NG例5: HTMLエスケープ漏れ（PHP出力）
```php
// ❌ NG: XSS脆弱性
echo "<p>名前: " . $name . "</p>";

// ✅ OK
echo "<p>名前: " . htmlspecialchars($name) . "</p>";
```

### NG例6: JavaScriptでのinnerHTML使用時のエスケープ漏れ
```javascript
// ❌ NG: XSS脆弱性
const name = response.name; // APIレスポンス
element.innerHTML = `<div>${name}</div>`;

// ✅ OK: escapeHtml() 関数を使用
element.innerHTML = `<div>${escapeHtml(name)}</div>`;

// ✅ さらに安全: textContent を使用（HTMLタグが不要な場合）
element.textContent = name;
```

### NG例7: iconButton関数でのonclick属性使用
```javascript
// ❌ NG: XSS脆弱性（過去の実装）
iconButton('delete', 'btn-icon', "deleteItem('" + id + "')", '削除');

// ✅ OK: data属性を使い、イベントリスナーで登録
const btn = iconButton('delete', 'btn-icon', '', '削除');
btn.setAttribute('data-id', id);
btn.addEventListener('click', () => deleteItem(id));
```

---

## 📋 コードレビューチェックリスト

PRを出す前に以下を確認：

### セキュリティ
- [ ] POSTを受けるページで `verifyCsrfToken()` を呼んでいる
- [ ] HTMLに出力する変数は `htmlspecialchars()` でエスケープ
- [ ] JavaScriptの `innerHTML` 使用時は `escapeHtml()` でエスケープ
- [ ] `iconButton()` 関数で onclick 属性を使っていない（data属性+イベントリスナーを使用）
- [ ] 削除処理は `canDelete()` でチェック
- [ ] 削除処理は物理削除ではなく論理削除（ソフトデリート）を使用
- [ ] 新規ページは `$defaultPagePermissions` に追加済み

### データ整合性
- [ ] data.json は `getData()` / `saveData()` 経由でのみアクセス
- [ ] 重要な操作は監査ログ (`auditCreate`, `auditUpdate`, `auditDelete`) を記録

### 保守性
- [ ] マジックナンバーを使っていない（定数化推奨）
- [ ] エラーメッセージは日本語で分かりやすく
- [ ] 複雑なロジックにはコメントを追加

---

## 開発サーバーの起動

```
scripts\start-dev-server.bat
```
- PHP: `C:\xampp\php\php.exe`（php.iniは `lib/php.ini` を使用）
- ポート: 8000
- URL: http://localhost:8000/
- Google OAuth redirect_uri: `http://localhost:8000/api/google-callback.php`
- 終了: Ctrl+C

※ このスクリプト以外でサーバーを起動しないこと

## CSRF保護（必須）

新しいPOSTフォームやAJAX POST呼び出しを追加する際は、必ずCSRFトークンを含めること。

### HTMLフォームの場合
```php
<form method="POST">
    <?= csrfTokenField() ?>
    <!-- フォーム内容 -->
</form>
```

### PHP側のPOST処理の場合
ページ先頭（POST処理の前）に以下を追加:
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}
```

### JavaScript fetch POSTの場合
```javascript
const csrfToken = '<?= generateCsrfToken() ?>';

fetch('/api/xxx.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify(data)
});
```

### API側（fetchの受け側）の場合
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}
```

## ファイルロック（必須）

JSONファイルの読み書きには必ずファイルロックを使う。
- 読み込み: `flock($fp, LOCK_SH)` （共有ロック）
- 書き込み: `flock($fp, LOCK_EX)` （排他ロック）
- メインデータは `getData()` / `saveData()` を使えばロック済み

## 新ページ追加時

1. `api/auth.php` の `$defaultPagePermissions` に権限を追加
2. `functions/header.php` のサイドバーに権限チェック付きでリンクを追加
3. POSTフォームがあればCSRF保護を入れる
4. 削除処理がある場合は `canDelete()` チェックを追加
5. ダッシュボードに表示する場合は `pages/index.php` に権限チェックを追加

## 権限システム

詳細は `docs/permission-system.md` を参照。

### 権限レベル
- `sales`（営業部）: 基本的な閲覧のみ
- `product`（製品管理部）: データの閲覧・編集
- `admin`（管理部）: 全機能（削除含む）

### 権限チェック関数
```php
hasPermission($role)           // 指定レベル以上か判定
isAdmin()                      // 管理部のみtrue
canEdit()                      // 製品管理部以上でtrue
canDelete()                    // 管理部のみtrue（削除操作に使用）
getPageViewPermission($page)   // ページの閲覧権限を取得
getPageEditPermission($page)   // ページの編集権限を取得
```

### サイドバーの権限制御
```php
<?php if (hasPermission(getPageViewPermission('xxx.php'))): ?>
<a href="/pages/xxx.php">メニュー名</a>
<?php endif; ?>
```

### 削除処理の権限制御
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_xxx'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        // 削除処理
    }
}
```

## デプロイ

```
powershell.exe -ExecutionPolicy Bypass -File "C:\Claude\master\auto-deploy.ps1"
```

※ ポータブル版WinSCPを使用（GUIインストール不要）

### デプロイ時の注意
- `-filemask` にサーバー専用ファイルを追加（必要に応じて）
- `.gitignore` に機密ファイルを追加（必要に応じて）
- `scripts/backup-data.php` の `$targetFiles` にバックアップ対象を追加（必要に応じて）

## セキュリティ注意事項

- HTMLへの出力は必ず `htmlspecialchars()` を使う
- パスワードは `password_hash()` / `password_verify()` を使う（現在bcrypt済み）
- config/*.json, data.json, *.key はgit管理対象外
- セッションタイムアウト: 環境変数 `SESSION_TIMEOUT_HOURS` で設定（デフォルト8時間）
- SSL証明書検証: 環境変数 `SSL_VERIFY_PEER` で制御（本番ではtrue必須）
- CSPモード: 環境変数 `CSP_MODE` で制御（strict/report/legacy）
- X-Forwarded-For: `config/security-config.json` の `trusted_proxies` に設定したプロキシからのみ信頼

## 入力バリデーション

`functions/validation.php` を使用して入力値を検証する。

### 個別関数
```php
validateEmail($email);      // メールアドレス
validatePhone($phone);      // 電話番号（日本）
validateDate($date);        // 日付（Y-m-d形式）
validateRequired($value);   // 必須項目
validateNumeric($value);    // 数値
validateUrl($url);          // URL
validatePostalCode($code);  // 郵便番号
```

### Validatorクラス（複数項目をまとめて検証）
```php
$validator = new Validator();
$validator->required('name', $name, '名前')
          ->email('email', $email, 'メール')
          ->phone('phone', $phone, '電話番号')
          ->date('date', $date, '日付');

if ($validator->hasErrors()) {
    respondValidationError($validator); // JSON形式でエラーを返して終了
}
```

## ログ記録

`functions/logger.php` を使用してログを記録する。

```php
logInfo('ユーザーがログイン', ['user' => $email]);
logWarning('処理に時間がかかっています', ['duration' => $sec]);
logError('エラー発生', ['error' => $message]);
logException($e, 'API呼び出し失敗');
```

ログファイルは `logs/` ディレクトリに日付別で保存される。

## レート制限

`functions/security.php` を使用してAPIのレート制限を行う。

```php
// IPアドレスベース（1分間に100リクエストまで）
checkIpRateLimit(100, 60);

// ユーザーベース
checkUserRateLimit($userId, 60, 60);

// ログイン試行回数制限
$result = checkLoginAttempts($email, 5, 15); // 5回失敗で15分ロック
if (!$result['allowed']) {
    echo $result['message'];
    exit;
}
```

## セキュリティヘッダー

ページ出力前に `setSecurityHeaders()` を呼び出すとセキュリティヘッダーが設定される。
（CSP, HSTS, X-Frame-Options, X-Content-Type-Options 等）

## パスワードポリシー

```php
$result = validatePasswordPolicy($password, $username);
if (!$result['valid']) {
    // $result['errors'] にエラーメッセージ配列
}

// 強度スコア（0-100）
$score = calculatePasswordStrength($password);
```

設定は `config/security-config.json` で変更可能。

## ヘルスチェック

本番環境の稼働確認:
```
https://yamato-mgt.com/api/integration/health.php
```

## バックアップ

```bash
# 通常バックアップ（フォルダ形式）
php scripts/backup-data.php

# ZIP形式
php scripts/backup-data.php --zip

# 一覧表示
php scripts/backup-data.php --list

# 検証
php scripts/backup-data.php --verify

# リストア
php scripts/backup-data.php --restore=20260130_120000
```

## 環境変数

`.env.example` をコピーして `.env` を作成。
```php
// コード内で環境変数を取得
$value = env('APP_ENV', 'production');
```

## API仕様書

`docs/openapi.yaml` に OpenAPI 3.0 形式で記載。
Swagger UI等で閲覧可能。

## セッション管理

ユーザーはヘッダーの🔐アイコンから `/pages/sessions.php` にアクセス可能。
- アクティブなセッション一覧表示
- 他のセッションを強制ログアウト
- ログイン履歴の確認

新しいIPからのログイン時はメール通知が送信される。

## エラーハンドリング

エラーログは `logs/error_YYYY-MM-DD.log` に保存される。
- 本番環境: エラー詳細は非表示、ログに記録
- 開発環境: 詳細なエラー情報を表示

## APIミドルウェア

新しいAPIエンドポイントでは `functions/api-middleware.php` を使用する。

```php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

// API初期化（認証・CSRF検証・レート制限を自動適用）
initApi([
    'requireAuth' => true,    // 認証必須
    'requireCsrf' => true,    // CSRF検証（POST時）
    'rateLimit' => 100,       // レート制限
    'allowedMethods' => ['GET', 'POST']
]);

// JSONリクエストボディを取得
$input = getJsonInput();

// パラメータ検証
requireParams($input, ['name', 'email']);
$name = sanitizeInput($input['name'], 'string');
$email = sanitizeInput($input['email'], 'email');

// レスポンス
successResponse(['id' => $newId], '作成しました');
// または
errorResponse('エラーメッセージ', 400);
```

## 楽観的ロック（同時編集対策）

`functions/optimistic-lock.php` を使用して同時編集の競合を検出する。

```php
require_once __DIR__ . '/../functions/optimistic-lock.php';

// 編集開始時：ロックを取得
$lock = acquireEditLock('projects', $projectId, $userId);
if (!$lock['success']) {
    respondLockError($lock['lockedBy']); // 他のユーザーが編集中
}

// 保存時：バージョンをチェック
$versionCheck = checkVersion('projects', $projectId, $clientVersion);
if ($versionCheck['conflict']) {
    respondConflictError($versionCheck['currentVersion']); // 競合発生
}

// 保存後：バージョンを更新
$newVersion = updateEntityVersion('projects', $projectId, $newData);

// 編集終了時：ロックを解放
releaseEditLock('projects', $projectId, $userId);
```

## 監査ログ

全ての重要操作は `functions/audit-log.php` で記録される。

```php
// ヘルパー関数
auditCreate('projects', $projectId, '案件を作成', $newData);
auditUpdate('projects', $projectId, '案件を更新', $oldData, $newData);
auditDelete('projects', $projectId, '案件を削除', $deletedData);
auditLogin(true, $email);
auditLogout();
auditExport('projects', 'csv', 100);

// カスタムログ
writeAuditLog('custom_action', 'target', '説明', ['detail' => 'value']);

// 整合性検証（改竄検知）
$result = verifyAuditLogIntegrity();
if (!$result['valid']) {
    // 改竄の可能性あり
}
```

## データ暗号化

個人情報は `functions/encryption.php` で暗号化して保存する。

```php
require_once __DIR__ . '/../functions/encryption.php';

// 単一値の暗号化・復号化
$encrypted = encryptData($phoneNumber);
$decrypted = decryptData($encrypted);

// 配列の複数フィールドを暗号化
$employee = encryptFields($employee, ['phone', 'address', 'my_number']);
$employee = decryptFields($employee, ['phone', 'address', 'my_number']);

// マスク表示（一部を隠す）
echo maskPhone('03-1234-5678');  // 03-****-5678
echo maskEmail('user@example.com');  // u**r@example.com
```

## テスト実行（必須）

**機能追加・修正後は必ずテストを実行すること。** テストが失敗した場合はコミットしない。

```bash
# 全テスト実行
cd C:\Claude\master
C:\xampp\php\php.exe vendor/bin/phpunit

# 特定のテストファイル
C:\xampp\php\php.exe vendor/bin/phpunit tests/Unit/PermissionTest.php

# カバレッジレポート
C:\xampp\php\php.exe vendor/bin/phpunit --coverage-html coverage/
```

### テストファイル一覧と検出内容

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

### テストが自動検出する「よくある壊し方」

- ページに `require_once '../api/auth.php'` を入れ忘れ → `RegressionGuardTest` が失敗
- POSTハンドラに `verifyCsrfToken()` を入れ忘れ → `RegressionGuardTest` が失敗
- `$defaultPagePermissions` に新ページを追加し忘れ → `PagePermissionTest` が警告
- `DataSchema` からフィールドを削除 → `DataSchemaTest` が失敗
- 権限チェック関数の条件を変更 → `PermissionTest` が失敗
- `canDelete()` を `canEdit()` に変えてしまう → `RegressionGuardTest` が失敗

---

## 変更履歴の記録ルール

機能追加・仕様変更を行った場合は、このセクションに記録すること。

### 記録する内容
- 日付
- 変更内容の概要
- 影響を受けるファイル（主要なもの）
- 関連ドキュメント（あれば）

### 変更履歴

| 日付 | 変更内容 | 主要ファイル | 関連ドキュメント |
|------|----------|--------------|------------------|
| 2026-02-05 | 権限システム実装（3段階権限、ページ別閲覧/編集権限） | `config/config.php`, `api/auth.php` | `docs/permission-system.md` |
| 2026-02-05 | 削除権限を管理部のみに制限 | `master.php`, `employees.php`, `masters.php`, `loans.php` | `docs/permission-system.md` |
| 2026-02-05 | ダッシュボード・サイドバーの権限制御 | `pages/index.php`, `functions/header.php` | `docs/permission-system.md` |
| 2026-02-05 | アルコールチェック：未紐付けレコードのsender_user_id照合対応 | `functions/photo-attendance-functions.php` | - |
| 2026-02-06 | CLAUDE.md拡充：危険箇所マップ、アーキテクチャ概要、機能追加パターン、テスト方法、失敗パターン、レビューチェックリスト追加 | `CLAUDE.md` | - |
| 2026-02-06 | セキュリティ強化：SSL検証有効化、自動ユーザー登録無効化、X-Forwarded-For信頼制限、CSP段階的厳格化、例外メッセージ秘匿、セッションタイムアウト環境変数化 | `api/mf-api.php`, `api/google-callback.php`, `functions/security.php`, `api/auth.php` | - |
| 2026-02-06 | 回帰防止テスト整備：権限・データスキーマ・CSRF・ページ権限・セキュリティ関数・認証チェック漏れ検出の自動テスト追加。テストが検出したバグ（photo-attendance.php, settings.phpのauth.php未読込）を修正。既存テスト（LoggerTest, SecurityTest）も修正 | `tests/Unit/*.php`, `tests/bootstrap.php`, `pages/photo-attendance.php`, `pages/settings.php` | `CLAUDE.md` |
| 2026-02-09 | セキュリティ監査：.htaccess強化、OAuthのstateパラメータ実装、ヘルスチェック情報削減、mf-debugページのadmin制限、XSSエスケープ修正 | `.htaccess`, `api/google-oauth.php`, `api/google-callback.php`, `api/google-calendar*.php`, `api/google-chat*.php`, `api/integration/health.php`, `pages/mf-debug.php`, `pages/integration-settings.php` | - |
| 2026-02-09 | 顧客データ暗号化：AES-256-GCMで顧客・担当者・パートナーのphone/email/addressを暗号化。enc:プレフィックスで平文との後方互換対応。マイグレーションスクリプト・テスト追加 | `functions/encryption.php`, `pages/customers.php`, `pages/masters.php`, `api/sync-partners.php`, `api/integration/customers.php`, `scripts/migrate-encrypt-data.php`, `tests/Unit/EncryptionTest.php` | - |
| 2026-02-09 | データ消失防止の多層防御：(1)削除権限チェック漏れ修正(customers.php全4箇所、master.php担当者削除、bulk-pdf-match.php)、(2)全削除処理に監査ログ追加、(3)employees.phpのsaveData()漏れ修正、(4)論理削除(ソフトデリート)導入で物理削除を廃止、(5)saveData()の自動スナップショット機能(data/snapshots/に5分間隔・50世代保持)、(6)管理者向けゴミ箱ページ(trash.php)追加、(7)RegressionGuardTestに削除権限・監査ログの自動検出テスト追加 | `config/config.php`, `functions/soft-delete.php`, `pages/customers.php`, `pages/master.php`, `pages/masters.php`, `pages/employees.php`, `pages/troubles.php`, `pages/trash.php`, `api/loans-api.php`, `tests/Unit/RegressionGuardTest.php`, `tests/Unit/SoftDeleteTest.php` | - |
| 2026-02-09 | XSS脆弱性の緊急修正：6ファイル14箇所のinnerHTML使用箇所でAPIレスポンス・ユーザー入力を直接埋め込んでいた脆弱性を修正。全ファイルに`escapeHtml()`関数を追加し、外部データを必ずエスケープする安全な実装に変更。全テスト通過確認済み | `pages/photo-attendance.php`, `pages/loans.php`, `pages/finance.php`, `pages/master.php`, `pages/settings.php`, `pages/payroll-journal.php` | - |
| 2026-02-09 | コードリファクタリング（レベル2）：重複していたCSS（7,217行）・JavaScript（4,690行）・SVGアイコンを共通化。`css/components.css`（モーダル、フォーム、テーブル等）、`js/common-utils.js`（API、バリデーション、モーダル制御等）、`js/icons.js`（30種類のSVGアイコン）を新規作成。37-40%のコード削減を実現 | `css/components.css`, `js/common-utils.js`, `js/icons.js` | - |
| 2026-02-09 | リファクタリング実施完了：28ページ全てを確認し、重複コード（openModal/closeModal関数、.closeクラス名）を削除。customers.php (-105行)、employees.php (-48行)、master.php (-2行)、masters.php (-6行)、tasks.php (-7行)を修正。合計168行削減。他のページは既に共通化済みであることを確認。全183テスト通過 | `pages/customers.php`, `pages/employees.php`, `pages/master.php`, `pages/masters.php`, `pages/tasks.php`, `docs/REFACTORING-GUIDE.md` | `docs/REFACTORING-GUIDE.md` |
| 2026-02-09 | Next.js移行計画策定（レベル3）：Yamato Basicとの技術スタック統一を目指し、段階的移行計画を立案。Strangler Fig Patternを採用し、PHPバックエンドを維持しながらフロントエンドをNext.js（React + TypeScript）に移行する3フェーズロードマップを策定 | `docs/NEXTJS-MIGRATION-PLAN.md` | `docs/NEXTJS-MIGRATION-PLAN.md` |
| 2026-02-09 | セキュリティ脆弱性修正（P0）：(1) js/icons.js の iconButton関数からXSS脆弱性のあるonclick属性を削除、data-action属性に変更、(2) master.php の案件詳細モーダルで全ての動的データにescapeHtml()を適用（12箇所）、(3) customers.php の営業所削除を物理削除から論理削除に変更。全183テスト通過確認 | `js/icons.js`, `pages/master.php`, `pages/customers.php` | - |
| 2026-02-09 | 情報漏洩対策（P0）：(1) 監査ログ改竄防止のためHMAC-SHA256署名実装（signAuditLogEntry/verifyAuditLogEntry/verifyAuditLogIntegrity関数追加）、(2) MF APIキーを環境変数管理に変更（MF_CLIENT_ID/MF_CLIENT_SECRET/MF_ACCESS_TOKEN/MF_REFRESH_TOKEN）、(3) 暗号化キーの自動バックアップスクリプト実装（backup-encryption-key.php、10世代保持）。内部犯行検知・APIキー漏洩防止・データ復旧戦略を強化 | `functions/audit-log.php`, `api/mf-api.php`, `scripts/backup-encryption-key.php`, `scripts/setup-key-backup-cron.sh`, `.env.example` | `docs/SECURITY-AUDIT-2026-02-09.md` |
| 2026-02-10 | 定期請求書作成機能追加：MF請求書をテンプレートとして、CSVに記載したIDリストから毎月自動で請求書を複製・作成する機能。「指定請求書」タグでフィルタリング、締め日タグ（20日〆/15日〆/末〆）で日付自動調整。管理画面での手動実行・cronスクリプトでの自動実行に対応。admin権限のみ実行可能。全183テスト通過確認 | `functions/recurring-invoice.php`, `api/recurring-invoices-api.php`, `pages/recurring-invoices.php`, `scripts/cron-recurring-invoices.php`, `config/recurring-invoices.csv`, `api/auth.php`, `pages/settings.php`, `functions/header.php`, `composer.json` | - |
| 2026-02-11 | クライアントサイドページネーション追加：Paginatorクラスをjs/common-utils.jsに実装し、一覧表示が多いページに50件/ページのページ分割表示を追加。表示件数セレクタ（20/50/100/全て）、件数情報表示、URLパラメータ保存、既存フィルターとの連携に対応。対象: employees.php, troubles.php, trash.php, masters.php(8タブ), customers.php(グループ対応), mf-invoice-list.php(パートナー別), recurring-invoices.php。全183テスト通過確認 | `js/common-utils.js`, `css/components.css`, `pages/employees.php`, `pages/troubles.php`, `pages/trash.php`, `pages/masters.php`, `pages/customers.php`, `pages/mf-invoice-list.php`, `pages/recurring-invoices.php`, `auto-deploy.ps1` | - |
| 2026-02-11 | 6機能追加：(1)一括操作（master.phpに一括ステータス変更追加）、(2)横断検索（ヘッダーにCtrl+K対応の検索バー追加、search.phpページ新規）、(3)データエクスポート強化（api/export.phpでCSV/JSON出力、search.phpにエクスポートUI）、(4)コメント・メモ機能（api/comments.phpで案件等へのコメントCRUD、master.phpの詳細行・カード詳細にコメント表示・投稿）、(5)管理部への隠しメッセージ（admin-messages.php、api/admin-messages.php、全ユーザーが管理部に送信可、管理者のみ全件閲覧可）、(6)バグ修正（全ページのモーダルバツボタンをspan→button type="button"に変更、アクセシビリティ改善）。data-schema.phpにcomments/admin_messagesエンティティ追加。全183テスト通過確認 | `pages/master.php`, `pages/search.php`, `pages/admin-messages.php`, `api/search.php`, `api/comments.php`, `api/admin-messages.php`, `api/export.php`, `api/auth.php`, `functions/header.php`, `functions/footer.php`, `functions/data-schema.php`, `css/components.css`, `pages/customers.php`, `pages/masters.php`, `pages/finance.php`, `pages/tasks.php` | - |

---

## 🧪 テストカバレッジ現況（2026-02-10 調査）

### 全体サマリ

- **テストファイル数:** 12（ユニットテストのみ）
- **テストメソッド数:** 183
- **インテグレーションテスト:** 0（ディレクトリは存在するが空）
- **テスト可能な関数 約250個中、テスト済み 約50個（カバレッジ約20%）**

---

### ✅ テスト済み（十分にカバーされている領域）

| ファイル | テストファイル | カバレッジ | テスト数 | 内容 |
|----------|---------------|-----------|---------|------|
| `functions/validation.php` | `ValidationTest.php` | ~90% | 18 | 全バリデーション関数（email/phone/date/required/numeric/url/postal等）、Validatorクラス |
| `functions/encryption.php` | `EncryptionTest.php` | ~90% | 30 | 暗号化/復号往復、二重暗号化防止、フィールド暗号化、顧客データ一括暗号化、マスク関数、鍵生成 |
| `functions/data-schema.php` | `DataSchemaTest.php` | ~95% | 23 | エンティティ定義、フィールド定義、ensureSchema、getInitialData、必須フィールド |
| `functions/soft-delete.php` | `SoftDeleteTest.php` | ~80% | 10 | softDelete/filterDeleted/getDeletedItems/restoreItem/purgeDeleted |
| `config/config.php`（権限部分） | `PermissionTest.php` | ~85% | 19 | hasPermission全組合せ、isAdmin/canEdit/canDelete |
| `config/config.php`（CSRF部分） | `CsrfTest.php` | ~70% | 10 | トークン生成・セッション保存・再利用・HTML出力・POST/ヘッダー検証 |
| `config/config.php`（データ部分） | `DataPersistenceTest.php` | ~50% | 13 | saveDataの空データ拒否・null拒否、getData構造、JSON往復、env()ヘルパー |
| `functions/security.php`（一部） | `SecurityFunctionTest.php` | ~40% | 20 | パスワードポリシー検証、calculatePasswordStrength、ipInCidr、isHttps |
| `functions/logger.php` | `LoggerTest.php` | ~60% | 9 | シングルトン、ログレベル定数、各レベル出力、コンテキスト、レベルフィルタ、JSON形式 |
| `api/auth.php`（権限定義部分） | `PagePermissionTest.php` | ~60% | 8 | 権限フォーマット検証、edit≧view整合性、管理ページ保護、getPageView/EditPermission |
| `functions/api-middleware.php`（sanitizeのみ） | `SecurityTest.php` | ~15% | 9 | sanitizeInput関数のみ（string/int/float/bool/email型） |
| （横断チェック） | `RegressionGuardTest.php` | — | 19 | auth.php読込漏れ・CSRF漏れ・canDelete漏れ・auditDelete漏れの自動検出 |

---

### ❌ テスト未実施（テストが存在しない領域）

#### 🔴 P0: 致命的（セキュリティ・データ整合性に直結）

| ファイル | 未テスト関数 | リスク |
|----------|-------------|--------|
| `functions/api-middleware.php` | `initApi()`, `getJsonInput()`, `requireParams()`, `successResponse()`, `errorResponse()`, `respondValidationError()` | 全APIの認証・バリデーション基盤 |
| `api/integration/api-auth.php` | `generateApiKey()`, `validateApiKey()`, `validateIpAddress()`, `authenticateApiRequest()` 等10関数 | 外部API認証が正しく動くか未検証 |
| `api/integration/customers.php` | `handleGetCustomers()`, `handlePostCustomers()`, `processSingleCustomer()` 等5関数 | 顧客データAPI、PII漏洩リスク |
| `api/integration/projects.php` | `handleGetProjects()`, `handlePostProjects()` 等5関数 | 案件データAPI |
| `api/integration/loans.php` | `handleGetLoans()`, `handlePostLoans()` 等 | 貸出データAPI |
| `functions/security.php`（残り） | `checkIpRateLimit()`, `checkUserRateLimit()`, `checkLoginAttempts()`, `setSecurityHeaders()`, `getClientIp()` | レート制限・セキュリティヘッダーが正しく動くか未検証 |
| `functions/audit-log.php` | `writeAuditLog()`, `signAuditLogEntry()`, `verifyAuditLogEntry()`, `verifyAuditLogIntegrity()`, `getFilteredAuditLogs()` 等8関数 | 監査ログの署名・検証ロジック未検証 |

#### 🟠 P1: 高（ビジネスロジック・セキュリティ機能）

| ファイル | 未テスト関数 | リスク |
|----------|-------------|--------|
| `functions/login-security.php` | `recordLoginAndNotify()`, `getActiveSessions()`, `terminateSession()`, `checkLoginAttempts()`, `parseUserAgent()`, `undoChange()` 等21関数 | ログインセキュリティ全体が未検証 |
| `functions/optimistic-lock.php` | `acquireEditLock()`, `releaseEditLock()`, `checkVersion()`, `updateEntityVersion()` | 同時編集制御の動作未検証 |
| `functions/mf-auto-mapper.php` | `extractProjectId()`, `extractAssigneeName()`, `autoMapInvoices()`, `applyAutoMapping()` | 請求書自動マッピングの精度未検証 |
| `functions/mf-invoice-sync.php` | `syncMfInvoices()`, `buildInvoiceData()` | MF連携データ同期の動作未検証 |
| `api/mf-api.php` | `MFApiClient`クラス全体 | MF API連携（OAuth、請求書取得等） |
| `api/google-oauth.php` | `GoogleOAuthClient`クラス全体 | Google OAuth認証フロー |
| `api/google-calendar.php` | `GoogleCalendarClient`クラス全体 | Googleカレンダー連携 |
| `api/google-chat.php` | `GoogleChatClient`クラス全体 | Googleチャット連携 |
| `api/google-drive.php` | `GoogleDriveClient`クラス全体 | Googleドライブ連携 |
| `api/google-sheets.php` | `GoogleSheetsClient`クラス全体 | Googleスプレッドシート連携 |

#### 🟡 P2: 中（運用・補助機能）

| ファイル | 未テスト関数 | リスク |
|----------|-------------|--------|
| `functions/notification-functions.php` | `sendNotificationEmail()`, `sendSmtpEmail()`, `notifyNewTrouble()`, `notifyStatusChange()` 等9関数 | メール通知の動作未検証 |
| `functions/photo-attendance-functions.php` | `getEmployees()`, `getPhotoAttendanceData()`, `getUploadStatusForDate()` 等 | アルコールチェック機能 |
| `api/pdf-processor.php` | `PdfProcessor`クラス全体 | PDF処理 |
| `api/loans-api.php` | `LoansApi`クラス全体 | 貸出API |
| `api/background-job.php` | `loadJobs()`, `saveJobs()`, `cleanupOldJobs()` | バックグラウンドジョブ管理 |
| `api/notifications.php` | `getUserNotifications()`, `getUnreadCount()` | 通知取得 |
| `api/alcohol-chat-sync.php` | `syncImagesFromChat()`, `reMatchEmployees()` 等11関数 | チャット画像同期 |
| `api/sync-troubles.php` | `makeMatchKey()`, `mapStatus()`, `normalizeDate()` | トラブル同期 |
| `scripts/backup-data.php` | バックアップ・リストアロジック | データバックアップ |
| `scripts/migrate-encrypt-data.php` | データ暗号化マイグレーション | 既存データの暗号化移行 |
| `config/config.php`（残り） | `createAutoSnapshot()`, `csrfTokenField()` | スナップショット・CSRF HTML |

---

### ⚠️ テストはあるが不足している領域

| テスト済み内容 | 不足している観点 |
|----------------|-----------------|
| `getData()`/`saveData()` 基本動作 | ファイルロック動作（LOCK_SH/LOCK_EX）、同時アクセス、破損復旧、スナップショット生成 |
| `generateCsrfToken()`/検証 | `verifyCsrfToken()`のexit動作、トークン有効期限、ログイン時の再生成 |
| `sanitizeInput()` | `initApi()`全体、`getJsonInput()`、`requireParams()`、レスポンス関数群 |
| Logger基本動作 | グローバルヘルパー(`logInfo`等)、ログローテーション、同時書込 |
| ソフトデリート単体 | ページとの統合（実際のcustomers.php等での動作）、監査ログ連携 |
| パスワードポリシー・IP検証 | レート制限、ログイン試行制限、セキュリティヘッダー出力 |
| 権限階層・canDelete | ページレベルの権限強制（実際のリダイレクト動作）、カスタム権限上書き |

---

### 🚫 インテグレーションテスト（完全欠落）

以下の結合テストが**一切存在しない**（`tests/Integration/` ディレクトリは空）：

- APIエンドポイントのリクエスト→レスポンス一貫テスト
- 認証フロー（Google OAuth→セッション作成→権限チェック）
- CRUD操作の一連フロー（作成→読取→更新→削除→ゴミ箱→復元）
- 外部API連携のモックテスト（MF、Google各API）
- ファイルロック下での同時書込テスト
- CSV/PDFエクスポートの出力検証

---

### 📋 テスト追加の優先ロードマップ

#### フェーズ1（P0: 1-2週間）
1. `ApiMiddlewareTest.php` — initApi/getJsonInput/requireParams/レスポンス関数
2. `ApiAuthIntegrationTest.php` — validateApiKey/authenticateApiRequest/IP検証
3. `AuditLogTest.php` — writeAuditLog/署名生成・検証/整合性チェック
4. `RateLimitTest.php` — checkIpRateLimit/checkUserRateLimit/checkLoginAttempts
5. `IntegrationApiTest.php` — customers/projects/loans APIのハンドラ

#### フェーズ2（P1: 2-4週間）
6. `LoginSecurityTest.php` — セッション管理/ログイン追跡/失敗検知/強制ログアウト
7. `OptimisticLockTest.php` — ロック取得・解放/バージョンチェック/競合検出
8. `MFAutoMapperTest.php` — PJ番号抽出/担当者名抽出/自動マッピング精度
9. `MFInvoiceSyncTest.php` — 請求書同期/差分更新/削除ロジック
10. Google/MF APIクライアントのモックテスト

#### フェーズ3（P2: 1-2ヶ月）
11. `NotificationTest.php` — メール送信（SMTP モック）
12. `PhotoAttendanceTest.php` — アルコールチェック機能
13. `BackupRestoreTest.php` — バックアップ・リストア・暗号化
14. CRUDフロー統合テスト（ページレベル）
15. CSVエクスポート出力検証テスト
