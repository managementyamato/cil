# 変更履歴 2026-02-20

このドキュメントは 2026-02-20 のセッションで実施した全変更内容をまとめたものです。

---

## 1. product_category マイグレーション（本番実行済み）

**対象**: `scripts/migrate-product-category.php`（実行後削除済み）

### 変更内容
- `data.json` の `projects` 配列に `product_category` フィールドを設定するマイグレーションを実施
- 従来の `maker` フィールド値をもとに、本番の `productCategories` から動的逆引きで `productCategoryID` を取得する方式を採用
  - 旧来の静的マッピングではなく、`maker → productCategoryID` の対応表を本番データから生成
- 299 件の `projects` に対して `product_category` を設定
- マイグレーションスクリプトは実行完了後に削除

---

## 2. スプシ連携ボタンバグ修正

**対象ファイル**: `pages/master.php`

### 問題
- スプレッドシート連携ボタンのドロップダウンメニューが `window.onclick` グローバルハンドラで制御されていた
- `style.display` と `classList.d-none` が混在し、メニューの開閉が不安定だった

### 変更内容
- `window.onclick` グローバルハンドラを廃止
- `document.addEventListener('click')` + `classList.add('d-none')` に統一
- `style.display` と `classList.d-none` の混在を解消し、`d-none` クラスのみで制御するよう統一

---

## 3. 製品名（product_category）表示修正

**対象ファイル**: `pages/master.php`

### 問題
- `product_category` フィールドにカテゴリ ID が格納されているケースと、カテゴリ名が直接入っているケースが混在
- テーブル・詳細パネルで ID がそのまま表示されてしまっていた

### 変更内容
- PHP 側に `$categoryMap`（カテゴリ ID → カテゴリ名）の連想配列を追加（`pages/master.php` L587-597）
- `$categoryMap` に名前→名前のマッピングも追加（名前が直接格納されているデータに対応）
- テーブル列・詳細パネルの「製品名」表示を `categoryMap` 経由で名前変換するよう修正
- JavaScript 側に `getCategoryName()` 関数を追加し、カード詳細パネルの動的表示でも同様に変換

---

## 4. モーダル × ボタン・キャンセルボタン修正

**対象ファイル**: 複数ページ

### 問題
- `window.onclick` によるモーダル背景クリック閉鎖と、× ボタン・キャンセルボタンの挙動が干渉
- `style.display='block'` で開いたモーダルが `classList` 操作で閉じられず、閉じられないケースが発生

### 変更内容

#### `style.css`
- `.modal-header` に `position: sticky; top: 0; background: white; z-index: 1;` を追加
- 長いモーダルでスクロール時に × ボタンが画面外に消えなくなった

#### `pages/master.php`
- `showAddModal()`、`showAssigneeModal()`、`showEditModal()`、`copyProject()` の呼び出しを `style.display='block'` から `openModal()` に統一

#### `pages/masters.php`
- `window.onclick` による背景クリック閉鎖を廃止

#### `pages/finance.php`
- `window.onclick` による背景クリック閉鎖を廃止

#### `pages/customers.php`
- 背景クリック閉鎖の処理を削除

#### `pages/employees.php`
- 背景クリック閉鎖の処理を削除

#### `pages/loans.php`
- 背景クリック閉鎖の処理を削除

#### `pages/photo-attendance.php`
- 背景クリック閉鎖 3 箇所を削除

#### `pages/troubles.php`
- 背景クリック閉鎖 3 箇所を削除

---

## 5. 案件一覧テーブル表示改善

**対象ファイル**: `pages/master.php`

### 変更内容
- **顧客名列を削除**: テーブル列数が多く視認性が低かったため削除
- **メーカー列 → 製品名列に変更**: `categoryMap` 経由でカテゴリ名を表示するよう変更
- **フィルターボタンから `【レ】`・`【売】` を削除**: 現場名のプレフィックスを表示に含めなくなったため不要と判断し削除

---

## 6. 現場名表示整形

**対象ファイル**: `pages/master.php`

### 変更内容

#### PHP 側
`trimSiteName()` 関数を追加（`pages/master.php` L68-76）:
```php
function trimSiteName($name) {
    // 【〇】形式のプレフィックスを全て除去（複数連続も対応）
    // \x{3010}=【 \x{3011}=】 Unicode指定でエンコーディング問題を回避
    $name = preg_replace('/^(\x{3010}[^\x{3011}]*\x{3011})+/u', '', $name);
    $name = trim($name);
    // アンダーバー以降を除去
    $pos = strpos($name, '_');
    return $pos !== false ? substr($name, 0, $pos) : $name;
}
```

#### JavaScript 側
同様の `trimSiteName()` 関数を追加し、カード表示・カード詳細タイトルの動的生成でも同一ロジックで整形

#### 適用箇所
- テーブル行の現場名表示: `trimSiteName()` 適用
- カード表示の現場名: `trimSiteName()` 適用
- カード詳細パネルのタイトル: `trimSiteName()` 適用
- **フル表示のまま（整形しない箇所）**: 詳細パネル内の現場情報、編集フォーム内の現場名

---

## 7. バッジ・現場名の列分離

**対象ファイル**: `pages/master.php`

### 問題
- バッジ（レンタル/販売）と現場名が同じ `<td>` 内にあり、現場名の開始位置が行ごとにバラついて縦揃えが乱れていた

### 変更内容
- バッジを独立した `<td>`（幅固定）に分離
- 現場名を次の `<td>` に配置
- これにより現場名の開始位置が全行で縦に揃うようになった

---

## 8. トラブル対応編集モーダルの select ドロップダウン修正

**対象ファイル**: `pages/troubles.php`

### 問題
- モーダル全体に `overflow-y: auto` が指定されていたため、`<select>` のドロップダウンがモーダルの `overflow` 制約を受けて巨大表示またはクリッピングされていた

### 変更内容
- モーダル全体の `overflow-y: auto` を廃止
- フォームコンテンツ部分のみ `overflow-y: auto` を適用するよう移動
- `<select>` のドロップダウンがモーダル外に正常に表示されるようになった

---

## 9. 給与仕訳変換ページの説明文削除

**対象ファイル**: `pages/payroll-journal.php`

### 変更内容
- ページ上部にあった「使い方: 支払い控除一覧表のExcelファイルを...」という説明文テキストブロックを削除
- 運用に慣れたためガイダンス文が不要と判断
