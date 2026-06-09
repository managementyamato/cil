# 機能追加パターン集

> このファイルは CLAUDE.md の補足ドキュメントです。
> 機能追加・API実装・各種コード例はここを参照してください。

---

## パターン1: 新規ページ追加

**まずテンプレートを複製すること。** 既存ページと骨格を揃えるための雛形が3種類用意されている:

| 用途 | 雛形 | 元にしたページ |
|---|---|---|
| 一覧 / 検索 / CRUD 型 | [pages/_template-list.php](../pages/_template-list.php) | audit-log.php |
| 設定フォーム型 | [pages/_template-settings.php](../pages/_template-settings.php) | notification-settings.php |
| ハブ (タブ集約) 型 | [pages/_template-hub.php](../pages/_template-hub.php) | master-hub.php |

各テンプレート先頭の手順コメントに従って:
1. `pages/<新ページ名>.php` にコピー
2. テンプレート直接アクセス防止の die() ブロックを削除
3. `<NEW_PAGE_TITLE>` 等のプレースホルダを置換
4. `api/auth.php` の `$defaultPagePermissions` に追加
5. `pages/user-permissions.php` のキーリストに追加
6. `functions/header.php` のサイドバーリンクを追加

雛形なしのスクラッチ実装は、以下の最小骨格で:

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

---

## 📐 API設計規約（新規API実装時は必ず従うこと）

### ファイル命名

| パターン | 使用条件 | 例 |
|---|---|---|
| `api/{resource}.php` | **既定**（全APIはこちら） | `api/contacts.php`, `api/troubles.php`, `api/dashboard.php` |
| `api/{resource}-api.php` | 既存ページと同名のPHPファイルがあって衝突する場合のみ | `api/loans-api.php`（`pages/loans.php`と区別） |
| `api/pages/{page}-data.php` | ページ専用のデータ取得API（ページと1:1対応） | `api/pages/customers-data.php` |

- **新規APIには原則 `-api.php` サフィックスを付けない**（現状65ファイル中12のみサフィックス付き、ノイズ）
- ファイル名は kebab-case 固定（snake_case・camelCase 禁止）
- デバッグ用は `api/debug-*.php` に置く（`.gitignore` で除外済み）

### レスポンス形式（必須）

全てのAPIは `api-middleware.php` の以下関数のみを使うこと：

```php
successResponse($data, $message = null);  // → {"success": true, "data": ..., "message": "..."}
errorResponse($message, $code = 400);     // → {"success": false, "error": "..."}
```

**禁止：** 直接 `echo json_encode(['success' => ..., ...])` を書くこと。形式がばらつく原因になる。

### HTTPメソッドの判定

基本は `REQUEST_METHOD` で分岐し、同一メソッド内の操作種別は `action` パラメータで分岐する。

```php
initApi(['requireAuth' => true, 'requireCsrf' => true, 'allowedMethods' => ['GET', 'POST']]);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 取得系（副作用なし）
    $action = $_GET['action'] ?? 'list';
    switch ($action) {
        case 'list':   successResponse(getList()); break;
        case 'detail': successResponse(getDetail($_GET['id'] ?? '')); break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 変更系（副作用あり）
    $input = getJsonInput();
    $action = $input['action'] ?? '';
    switch ($action) {
        case 'create': /* ... */ break;
        case 'update': /* ... */ break;
        case 'delete': /* ... */ break;
    }
}
```

- 読み取りだけなら GET、データを変更するなら POST
- GET で `action=create` のような副作用操作を受けない（CSRF保護が効かない）

### 権限チェックの使い分け

| 関数 | 用途 |
|---|---|
| `hasPermission($role)` | 任意のロール以上で許可したいとき |
| `isAdmin()` | 管理部のみ許可（システム設定・削除） |
| `canEdit()` | 製品技術部以上で編集許可 |
| `canDelete()` | 削除は必ずこれ（admin のみ） |
| `canEditCurrentPage()` | ページ単位の編集権限（`getPageEditPermission()`連動） |

---

## 📅 日付フォーマット統一（新規実装ではこれを使うこと）

`functions/date-helpers.php`（config.phpから自動ロード済み）に統一ヘルパーがある。

| 用途 | 関数 | 出力例 |
|---|---|---|
| 保存用（data.json / DB） | `formatDateIso($val)` | `2026-04-16 14:30:00` |
| 日付表示（一覧・詳細） | `formatDate($val)` | `2026/04/16` |
| 日時表示（一覧・詳細） | `formatDateTime($val)` | `2026/04/16 14:30` |
| 相対時刻表示（通知） | `formatDateRelative($val)` | `3分前` / `昨日` / `2026/04/16` |

```php
// ✅ 新規実装ではヘルパーを使う
$data['created_at'] = formatDateIso();                          // 保存時
echo formatDateTime($record['created_at']);                     // 表示時
echo '<td>' . htmlspecialchars(formatDate($row['date'])) . '</td>';

// ❌ 新規コードで直に書かない（既存コードは段階的に移行）
$data['created_at'] = date('Y-m-d H:i:s');
echo date('Y/m/d', strtotime($value));
```

入力は `DateTime`、ISO文字列、UNIXタイムスタンプ、`null`（空文字）のいずれも受け付ける。

---

## 🎨 UI統一パターン（新規ページ作成時は必ずこれに従うこと）

> **既存ページとの不整合を増やさないために、以下のパターンを厳守する。**
> 既存ページ（my-workspace.php, announcements.phpなど）に独自パターンがあるが、新規実装では使わない。

---

### アラート・メッセージ表示

**クラス名の統一ルール:**

```php
// ✅ 正しい
$messageType = 'success';   // → alert-success（緑）
$messageType = 'danger';    // → alert-danger（赤）
$messageType = 'warning';   // → alert-warning（黄）

// ❌ 禁止（使わない）
$messageType = 'error';     // alert-error は存在しない
```

**HTML出力パターン:**

```php
<?php if (!empty($message)): ?>
<div class="alert alert-<?= htmlspecialchars($messageType) ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>
```

---

### モーダルダイアログ（必須ルール）

> **⚠️ モーダル作成時は以下のルールを厳守すること。既存ページに独自パターン（`modal-backdrop`/`open` 等）があるが、新規実装では使わない。**

#### ルール一覧

| # | ルール | 理由 |
|---|--------|------|
| 1 | クラスは `class="modal"` + `active` で開閉 | `modal-backdrop`/`open` は旧パターン。CSS定義は `style.css` の `.modal` / `.modal.active` |
| 2 | 開閉は `openModal(id)` / `closeModal(id)` を使う | `common-utils.js` に定義済み。body scroll制御も含む |
| 3 | 閉じるボタンは `data-close-modal="モーダルID"` | onclick属性は禁止（XSS防止） |
| 3b | オーバーレイ（背景）クリックでは閉じない | ✕ボタンのみで閉じる。誤操作防止 |
| 4 | フォームには `<?= csrfTokenField() ?>` 必須 | CSRF対策 |
| 5 | 入力フィールドは `class="form-input"` | `form-control` はCSS未定義 |
| 6 | ラッパーは `<div class="form-group">` | 統一レイアウト |
| 7 | innerHTML使用時は `escapeHtml()` 必須 | XSS防止 |
| 8 | 送信ボタンは処理中 `disabled` にする | 二重送信防止 |
| 9 | モーダルIDはページ内で一意にする | 複数モーダル時の競合防止 |

#### HTML構造（全ページ統一）

```html
<!-- トリガーボタン -->
<button type="button" class="btn btn-primary" data-action="openAddModal">新規追加</button>

<!-- モーダル本体 -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>タイトル</h3>
            <button type="button" class="close" data-close-modal="addModal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addForm">
                <?= csrfTokenField() ?>
                <div class="form-group">
                    <label for="nameInput">名前 <span class="required">*</span></label>
                    <input type="text" id="nameInput" name="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="statusSelect">ステータス</label>
                    <select id="statusSelect" name="status" class="form-input">
                        <option value="">選択してください</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal="addModal">キャンセル</button>
            <button type="submit" form="addForm" class="btn btn-primary">保存</button>
        </div>
    </div>
</div>
```

#### JavaScript（開閉・送信）

```javascript
// ✅ 開閉は common-utils.js の openModal / closeModal を使う
// 独自に classList.add('active') を書かない

// 閉じるボタン（data-close-modal属性で統一）
document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.dataset.closeModal));
});

// ❌ オーバーレイクリックで閉じるコードは書かない（✕ボタンのみで閉じる）
// modal.addEventListener('click', e => { if (e.target === modal) ... }); ← 禁止

// フォーム送信（async/await + ローディング制御）
document.getElementById('addForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('[type="submit"]') || document.querySelector('[form="addForm"][type="submit"]');
    setLoading(btn, true, '保存中...');
    try {
        const data = await apiPost('/api/xxx.php', {
            action: 'create',
            name: document.getElementById('nameInput').value
        });
        showAlert('保存しました', 'success');
        closeModal('addModal');
        this.reset();
        // 一覧を更新
        loadList();
    } catch (err) {
        // apiPost内でshowAlertされるので追加処理は不要
    } finally {
        setLoading(btn, false);
    }
});
```

#### 編集モーダル（追加/編集兼用パターン）

```javascript
// 追加と編集で同じモーダルを使い回す場合
let editingId = null;

function openAddModal() {
    editingId = null;
    document.getElementById('modalTitle').textContent = '新規追加';
    document.getElementById('addForm').reset();
    openModal('addModal');
}

function openEditModal(id) {
    editingId = id;
    document.getElementById('modalTitle').textContent = '編集';
    // フォームに値をセット
    document.getElementById('nameInput').value = /* データから取得 */;
    openModal('addModal');
}

// 送信時に editingId で分岐
const action = editingId ? 'update' : 'create';
const payload = editingId
    ? { action: 'update', id: editingId, name: ... }
    : { action: 'create', name: ... };
```

#### 削除確認モーダル

```html
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>削除確認</h3>
            <button type="button" class="close" data-close-modal="deleteModal">&times;</button>
        </div>
        <div class="modal-body">
            <p><span id="deleteName"></span> を削除しますか？</p>
            <p class="text-danger" style="font-size: 0.85rem;">この操作は取り消せません。</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal="deleteModal">キャンセル</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">削除</button>
        </div>
    </div>
</div>
```

```javascript
let deleteTargetId = null;

function confirmDelete(id, name) {
    deleteTargetId = id;
    document.getElementById('deleteName').textContent = name;
    openModal('deleteModal');
}

document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
    if (!deleteTargetId) return;
    setLoading(this, true, '削除中...');
    try {
        await apiPost('/api/xxx.php', { action: 'delete', id: deleteTargetId });
        showAlert('削除しました', 'success');
        closeModal('deleteModal');
        loadList();
    } finally {
        setLoading(this, false);
        deleteTargetId = null;
    }
});
```

---

### フォームグループ

> **⚠️ 入力フィールドのクラス名は `form-input` に統一すること。`form-control` は CSS に存在しないため絶対に使わない。**

```html
<!-- ✅ 正しい -->
<div class="form-group">
    <label for="nameInput">名前 <span class="required">*</span></label>
    <input type="text" id="nameInput" name="name" class="form-input" required>
    <small class="form-hint">ヒントテキスト（任意）</small>
</div>

<div class="form-group">
    <label for="statusSelect">ステータス</label>
    <select id="statusSelect" name="status" class="form-input">
        <option value="">選択してください</option>
    </select>
</div>

<div class="form-group">
    <label for="memoText">メモ</label>
    <textarea id="memoText" name="memo" class="form-input" rows="3"></textarea>
</div>

<!-- ❌ 禁止（使わない） -->
<input class="form-control">          <!-- CSS未定義、スタイルが当たらない -->
<div class="form-row">...</div>       <!-- announcements独自 -->
<div class="ws-form-group">...</div>  <!-- my-workspace独自 -->
```

**クラス名の使い分け:**

| 要素 | 正しいクラス | 禁止クラス |
|------|------------|----------|
| `<input>` | `form-input` | `form-control` |
| `<select>` | `form-input` | `form-control` |
| `<textarea>` | `form-input` | `form-control` |
| ラッパー `<div>` | `form-group` | `form-row` / `ws-form-group` |

---

### ページ見出し

```html
<!-- ✅ 正しい -->
<div class="page-header">
    <h2>ページタイトル</h2>
    <div class="page-header-actions">
        <!-- 右側のボタン類 -->
        <button type="button" class="btn btn-primary" data-action="openAddModal">新規追加</button>
    </div>
</div>

<!-- 設定系サブページ（settings配下から遷移するページ）の場合 -->
<div class="settings-detail-header">
    <h2>ページタイトル</h2>
    <a href="settings.php" class="btn btn-secondary btn-sm">← 設定に戻る</a>
</div>
```

---

### テーブルと空状態

```html
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>列名</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
            <tr>
                <td colspan="【列数】" class="text-center text-muted p-2rem">
                    データがありません
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
```

---

### ボタンイベント（data-actionパターン）

新規ページでは `data-action` によるイベントデリゲーションを使う。

```html
<!-- HTML -->
<button type="button" class="btn btn-primary" data-action="openAddModal">新規追加</button>
<button type="button" class="btn btn-sm btn-secondary" data-action="edit" data-id="<?= $item['id'] ?>">編集</button>
<button type="button" class="btn btn-sm btn-danger" data-action="delete" data-id="<?= $item['id'] ?>">削除</button>
```

```javascript
// JavaScript（イベントデリゲーション）
document.addEventListener('click', e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    const id = btn.dataset.id;

    switch (action) {
        case 'openAddModal': openModal('addModal'); break;
        case 'edit': openEditModal(id); break;
        case 'delete': confirmDelete(id); break;
    }
});
```

---

### API fetch呼び出し

```javascript
// ✅ 統一パターン（async/await + エラーハンドリング）
const csrfToken = '<?= generateCsrfToken() ?>';

async function apiPost(endpoint, payload) {
    try {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '処理に失敗しました');
        return data;
    } catch (err) {
        showAlert(err.message, 'danger');
        throw err;
    }
}

// 使用例
const data = await apiPost('/api/xxx.php', { action: 'create', name: 'foo' });
showAlert('保存しました', 'success');
```

---

### ローディング状態

ボタンクリック中は必ず無効化してフィードバックを出す。

```javascript
function setLoading(btn, isLoading, loadingText = '処理中...') {
    btn.disabled = isLoading;
    if (isLoading) {
        btn.dataset.originalText = btn.textContent;
        btn.textContent = loadingText;
    } else {
        btn.textContent = btn.dataset.originalText || btn.textContent;
    }
}

// 使用例
const btn = e.currentTarget;
setLoading(btn, true, '保存中...');
try {
    await apiPost('/api/xxx.php', payload);
    showAlert('保存しました', 'success');
} finally {
    setLoading(btn, false);
}
```

---

### トースト通知（showAlert）

ページ内アラートをJSから出す場合は `showAlert()` を使う（ページリロードなしで表示）。

```javascript
// HTMLにアラート用コンテナを用意
// <div id="alertContainer"></div>

function showAlert(message, type = 'success', duration = 4000) {
    const container = document.getElementById('alertContainer');
    if (!container) return;
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    container.appendChild(alert);
    setTimeout(() => alert.remove(), duration);
}
```
