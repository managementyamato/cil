# よくある失敗パターン（NG例集）

> このファイルは CLAUDE.md の補足ドキュメントです。
> コード実装時は必ずここのNG例を確認してください。

---

## NG例0: data.json の直接操作（最重要）

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

**2026-02-11 実際に発生した事故:** data.jsonが破損し、全データが消失。バックアップから復元するまでログイン不可。

---

## NG例1: 権限定義の追加漏れ

```php
// ❌ NG: $defaultPagePermissions に追加し忘れ
// → デフォルトの 'sales' 権限が適用され、誰でも見れてしまう

// ✅ OK: 必ず api/auth.php に追加
'secret-page.php' => ['view' => 'admin', 'edit' => 'admin'],
```

---

## NG例2: CSRFトークン検証漏れ

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

---

## NG例3: data.json の直接操作（コード例）

```php
// ❌ NG: ロックなしで直接読み書き
$data = json_decode(file_get_contents('data.json'), true);
file_put_contents('data.json', json_encode($data));

// ❌ NG: 空のデータで上書き
$data = ['projects' => []];  // 他のデータが全て消える！
saveData($data);

// ❌ NG: キーの削除
$data = getData();
unset($data['employees']);  // 従業員データが全て消失！
saveData($data);

// ✅ OK: 必ず getData() / saveData() を使用
$data = getData();
$data['projects'][] = $newProject;
saveData($data);
```

---

## NG例4: 削除権限のチェック漏れ

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

---

## NG例5: HTMLエスケープ漏れ（PHP出力）

```php
// ❌ NG: XSS脆弱性
echo "<p>名前: " . $name . "</p>";

// ✅ OK
echo "<p>名前: " . htmlspecialchars($name) . "</p>";
```

---

## NG例6: JavaScriptでのinnerHTML使用時のエスケープ漏れ

```javascript
// ❌ NG: XSS脆弱性
const name = response.name; // APIレスポンス
element.innerHTML = `<div>${name}</div>`;

// ✅ OK: escapeHtml() 関数を使用
element.innerHTML = `<div>${escapeHtml(name)}</div>`;

// ✅ さらに安全: textContent を使用（HTMLタグが不要な場合）
element.textContent = name;
```

---

## NG例7: モーダル作成時の禁止パターン

```html
<!-- ❌ NG: modal-backdrop / open は旧パターン（contacts.php等の独自実装） -->
<div class="modal-backdrop" id="modal">
<div class="modal-box">

<!-- ✅ OK: 統一パターン -->
<div id="addModal" class="modal">
<div class="modal-content">
```

```javascript
// ❌ NG: classList.add('open') は旧パターン
document.getElementById('modal').classList.add('open');

// ❌ NG: openModal/closeModal を使わず直接操作
document.getElementById('addModal').classList.add('active');
document.getElementById('addModal').style.display = 'block';

// ❌ NG: オーバーレイ（背景）クリックで閉じる（誤操作防止のため禁止）
modal.addEventListener('click', e => {
    if (e.target === modal) modal.classList.remove('active');
});

// ✅ OK: common-utils.js の関数を使う（body scroll制御も含む）
openModal('addModal');
closeModal('addModal');
// モーダルは✕ボタン or キャンセルボタンのみで閉じる
```

```html
<!-- ❌ NG: onclick属性でモーダルを開く -->
<button onclick="openModal('addModal')">追加</button>

<!-- ❌ NG: form-control（CSS未定義） -->
<input class="form-control" name="name">

<!-- ❌ NG: CSRFトークンなし -->
<form id="addForm">
    <input name="name">
</form>

<!-- ✅ OK: data-action + form-input + csrfTokenField -->
<button type="button" class="btn btn-primary" data-action="openAddModal">追加</button>
<form id="addForm">
    <?= csrfTokenField() ?>
    <input class="form-input" name="name">
</form>
```

---

## NG例8: iconButton関数でのonclick属性使用

```javascript
// ❌ NG: XSS脆弱性（過去の実装）
iconButton('delete', 'btn-icon', "deleteItem('" + id + "')", '削除');

// ✅ OK: data属性を使い、イベントリスナーで登録
const btn = iconButton('delete', 'btn-icon', '', '削除');
btn.setAttribute('data-id', id);
btn.addEventListener('click', () => deleteItem(id));
```

> **参考:** モーダルの正しい実装パターンは `docs/patterns.md`「モーダルダイアログ（必須ルール）」を参照
