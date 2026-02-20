# 機能追加パターン集

> このファイルは CLAUDE.md の補足ドキュメントです。
> 機能追加・API実装・各種コード例はここを参照してください。

---

## パターン1: 新規ページ追加

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

---

## パターン2: 新規APIエンドポイント追加

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

---

## パターン3: 削除機能の追加

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

        // 削除処理（論理削除を使うこと）
        // softDelete($data, 'items', $itemId);

        saveData($data);
        $message = '削除しました';
        $messageType = 'success';
    }
}
```

---

## パターン4: data.json に新しいエンティティを追加

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

## CSRF保護コード例

### HTMLフォームの場合
```php
<form method="POST">
    <?= csrfTokenField() ?>
    <!-- フォーム内容 -->
</form>
```

### PHP側のPOST処理
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

---

## 権限チェック コード例

```php
// サイドバーの権限制御
<?php if (hasPermission(getPageViewPermission('xxx.php'))): ?>
<a href="/pages/xxx.php">メニュー名</a>
<?php endif; ?>

// 削除処理の権限制御
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_xxx'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        // 削除処理
    }
}
```

---

## 入力バリデーション

```php
// 個別関数
validateEmail($email);
validatePhone($phone);
validateDate($date);
validateRequired($value);
validateNumeric($value);
validateUrl($url);
validatePostalCode($code);

// Validatorクラス（複数項目をまとめて検証）
$validator = new Validator();
$validator->required('name', $name, '名前')
          ->email('email', $email, 'メール')
          ->phone('phone', $phone, '電話番号')
          ->date('date', $date, '日付');

if ($validator->hasErrors()) {
    respondValidationError($validator);
}
```

---

## ログ記録

```php
logInfo('ユーザーがログイン', ['user' => $email]);
logWarning('処理に時間がかかっています', ['duration' => $sec]);
logError('エラー発生', ['error' => $message]);
logException($e, 'API呼び出し失敗');
```

---

## レート制限

```php
checkIpRateLimit(100, 60);          // IPベース（1分間に100リクエスト）
checkUserRateLimit($userId, 60, 60); // ユーザーベース

$result = checkLoginAttempts($email, 5, 15); // 5回失敗で15分ロック
if (!$result['allowed']) {
    echo $result['message'];
    exit;
}
```

---

## 楽観的ロック（同時編集対策）

```php
require_once __DIR__ . '/../functions/optimistic-lock.php';

// 編集開始時：ロックを取得
$lock = acquireEditLock('projects', $projectId, $userId);
if (!$lock['success']) {
    respondLockError($lock['lockedBy']);
}

// 保存時：バージョンをチェック
$versionCheck = checkVersion('projects', $projectId, $clientVersion);
if ($versionCheck['conflict']) {
    respondConflictError($versionCheck['currentVersion']);
}

// 保存後：バージョンを更新
$newVersion = updateEntityVersion('projects', $projectId, $newData);

// 編集終了時：ロックを解放
releaseEditLock('projects', $projectId, $userId);
```

---

## 監査ログ

```php
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
```

---

## データ暗号化

```php
require_once __DIR__ . '/../functions/encryption.php';

$encrypted = encryptData($phoneNumber);
$decrypted = decryptData($encrypted);

$employee = encryptFields($employee, ['phone', 'address', 'my_number']);
$employee = decryptFields($employee, ['phone', 'address', 'my_number']);

echo maskPhone('03-1234-5678');   // 03-****-5678
echo maskEmail('user@example.com'); // u**r@example.com
```

---

## APIミドルウェア

```php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'rateLimit' => 100,
    'allowedMethods' => ['GET', 'POST']
]);

$input = getJsonInput();
requireParams($input, ['name', 'email']);
$name = sanitizeInput($input['name'], 'string');
$email = sanitizeInput($input['email'], 'email');

successResponse(['id' => $newId], '作成しました');
errorResponse('エラーメッセージ', 400);
```
