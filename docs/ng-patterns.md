# よくある失敗パターン（NG例集）

> このファイルは CLAUDE.md の補足ドキュメントです。
> コード実装時は必ずここのNG例を確認してください。

---

## NG例0: 業務データへのアクセス（getData / saveData 経由必須）

2026-05-20 以降、業務データは MySQL に統一済み（data.json は廃止・退避済み）。

```php
// ✅ 正しい操作: getData() / saveData() を必ず経由
$data = getData();
$data['projects'][] = $newProject;
saveData($data);

// 単一行更新は saveEntityRow() を推奨（他人の編集を上書きしない）
saveEntityRow('weekly_reports', $row);
```

- PDO を直接使う場合も最低限 `Database::saveEntity()` 等の公開 API を経由すること
- スキーマ変更は `functions/data-schema.php` と `scripts/create-tables.sql` を必ず同期
- 退避された旧 data.json は `backups/archived-data-json/` 配下にあり、リファレンス用途のみ

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

## NG例3: 全件 saveData() でのデータ破壊

```php
// ❌ NG: キーごと空配列で上書き → 他人の変更が全部消える
$data = ['projects' => []];
saveData($data);

// ❌ NG: 取得 → unset → 保存
$data = getData();
unset($data['employees']);  // 従業員データが全て消失
saveData($data);

// ❌ NG: 同時編集対象で saveData()
$data = getData();
$data['weekly_reports'][$idx] = $myReport;
saveData($data);  // 他人の同タイミング編集が消える

// ✅ OK: 同時編集が発生するテーブルは saveEntityRow() で 1 行だけ更新
saveEntityRow('weekly_reports', $myReport);

// ✅ OK: 全件 saveData は対象エンティティをホワイトリスト指定
$data = getData();
$data['projects'][] = $newProject;
saveData($data, ['projects']);
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

---

## NG例9: 本番状態をローカルから推測する

```
# ❌ NG: ローカルの.envやgitの状態を見て「本番もこうなっている」と報告する
DB_MODE=db と .env に書いてあるから本番もそのはず → 間違い

# ✅ OK: SSH・FTP・curlで本番に直接確認してから報告する
- 本番DBのカラム存在確認 → MySQLに直接クエリ
- 本番のファイル状態確認 → FTPで直接確認
- 本番の動作確認 → curlまたはブラウザで直接アクセス
```

**過去の事故:** DB_MODE=dual をローカル.envから推測して報告し、2回訂正が必要になった。

---

## NG例10: 1回目の修正が効かなかったときにすぐ追加修正する

```
# ❌ NG: 推測の積み重ね
修正A → 失敗 → 修正B → 失敗 → 修正C → 失敗（原因不明のまま続ける）

# ✅ OK: 1回失敗したら必ず再調査してから次の修正
1. 修正Aが効かなかった → 一度立ち止まる
2. ログ・DBクエリ・curlで「何が起きているか」を確認
3. 仮説を3つ立てて証拠を1つ取得してから次の修正を実施
4. gitで変更を破棄して白紙に戻すことも選択肢に入れる
```

**過去の事故:** troubles.php削除ボタンのバグでOPcache・フォームネスト・サーバー設定と推測を重ねて解決できなかった。
