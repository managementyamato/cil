# CSP強化: unsafe-inline 削除プラン

## 現状

**問題:** `script-src 'unsafe-inline'` が設定されており、XSS攻撃のリスクが残存
**原因:** 221個のインラインイベントハンドラ（onclick, onload, onerror等）が残存

## 段階的移行計画

### Phase 1: 準備完了 ✅
- [x] 全 `<script>` / `<style>` タグに nonce 属性を付与
- [x] `cspNonce()` / `nonceAttr()` 関数を実装
- [x] CSP設定に nonce を含める（現在は unsafe-inline と併用）

### Phase 2: インラインイベントハンドラの削除（進行中）

#### 優先度P0（セキュリティ重要・外部入力を扱うページ）

| ページ | インラインイベント数 | 担当 | ステータス |
|--------|---------------------|------|-----------|
| `masters.php` | 49 | - | ⏳ 未着手 |
| `customers.php` | 34 | - | ⏳ 未着手 |
| `master.php` | 34 | - | ⏳ 未着手 |
| `photo-attendance.php` | 20 | - | ⏳ 未着手 |
| `finance.php` | 18 | - | ⏳ 未着手 |
| `troubles.php` | 14 | - | ⏳ 未着手 |

#### 優先度P1（管理・設定ページ）

| ページ | インラインイベント数 | 担当 | ステータス |
|--------|---------------------|------|-----------|
| `employees.php` | 10 | - | ⏳ 未着手 |
| `test-components.php` | 9 | - | ⏳ 未着手 |
| `loans.php` | 7 | - | ⏳ 未着手 |
| `settings.php` | 4 | - | ⏳ 未着手 |
| `user-permissions.php` | 3 | - | ⏳ 未着手 |
| `integration-settings.php` | 3 | - | ⏳ 未着手 |
| `mf-invoice-list.php` | 3 | - | ⏳ 未着手 |

#### 優先度P2（低頻度ページ）

| ページ | インラインイベント数 | 担当 | ステータス |
|--------|---------------------|------|-----------|
| その他17ページ | 1-2個ずつ | - | ⏳ 未着手 |

### Phase 3: CSP厳格化

全インラインイベントハンドラ削除後：

```php
// functions/security.php の CSP 設定を変更
$csp = [
    "default-src 'self'",
    "script-src 'self' 'nonce-{$nonce}' https://cdnjs.cloudflare.com",  // unsafe-inline 削除
    "style-src 'self' 'nonce-{$nonce}'",  // unsafe-inline 削除
    // ... 以下同じ
];
```

## 移行パターン

### パターン1: ボタンの onclick

```html
<!-- ❌ Before -->
<button onclick="deleteItem('<?= $id ?>')">削除</button>

<!-- ✅ After -->
<button class="delete-btn" data-id="<?= htmlspecialchars($id) ?>">削除</button>

<script<?= nonceAttr() ?>>
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        deleteItem(id);
    });
});
</script>
```

### パターン2: フォームの onsubmit

```html
<!-- ❌ Before -->
<form onsubmit="handleSubmit(event)">

<!-- ✅ After -->
<form id="myForm">

<script<?= nonceAttr() ?>>
document.getElementById('myForm').addEventListener('submit', handleSubmit);
</script>
```

### パターン3: 画像の onerror

```html
<!-- ❌ Before -->
<img src="..." onerror="this.src='/default.png'">

<!-- ✅ After -->
<img src="..." class="img-with-fallback">

<script<?= nonceAttr() ?>>
document.querySelectorAll('.img-with-fallback').forEach(img => {
    img.addEventListener('error', function() {
        this.src = '/default.png';
    });
});
</script>
```

### パターン4: iconButton関数の data属性パターン（既に実装済み）

```javascript
// js/icons.js で既に実装済み
const btn = iconButton('delete', 'btn-icon', '', '削除');
btn.setAttribute('data-id', id);
btn.addEventListener('click', () => deleteItem(id));
```

## 進捗管理

- [ ] Phase 2 完了（全インラインイベントハンドラ削除）
- [ ] Phase 3 完了（CSP厳格化）
- [ ] 全ページで動作確認
- [ ] CSP違反レポートの監視（report-uri 設定）

## CSP違反レポート設定（Phase 3後）

```php
// functions/security.php
$csp[] = "report-uri /api/csp-report.php";
```

```php
// api/csp-report.php（新規作成）
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/logger.php';

$input = file_get_contents('php://input');
$report = json_decode($input, true);

if ($report && isset($report['csp-report'])) {
    logWarning('CSP Violation', $report['csp-report']);
}

http_response_code(204);
```

## 参考リンク

- [MDN: Content Security Policy](https://developer.mozilla.org/ja/docs/Web/HTTP/CSP)
- [CSP Evaluator](https://csp-evaluator.withgoogle.com/)
- [Content Security Policy チートシート](https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html)
