# リファクタリングガイド

**作成日**: 2026-02-09
**目的**: 各ページのインラインCSS/JavaScriptを共通コンポーネントに置き換え

---

## 📋 進捗状況

| ページ | CSS削減 | JS削減 | 状態 | 担当 |
|--------|---------|--------|------|------|
| test-components.php | - | - | ✅ 完了 | - |
| customers.php | 105行 | - | ✅ 完了 | - |
| employees.php | 48行 | - | ✅ 完了 | - |
| master.php | 2行 | - | ✅ 完了 | - |
| masters.php | 6行 | - | ✅ 完了 | - |
| tasks.php | 7行 | - | ✅ 完了 | - |
| troubles.php | - | - | ✅ 完了（既に共通化済み） | - |
| loans.php | - | - | ✅ 完了（既に共通化済み） | - |
| index.php | - | - | ✅ 完了（既に共通化済み） | - |
| settings.php | - | - | ✅ 完了（既に共通化済み） | - |
| finance.php | - | - | ✅ 完了（既に共通化済み） | - |
| payroll-journal.php | - | - | ✅ 完了（既に共通化済み） | - |
| integration-settings.php | - | - | ✅ 完了（既に共通化済み） | - |
| ... | ... | ... | ✅ その他も確認済み | - |

**全体進捗**: 28/28ページ完了 (100%) - **削減: 168行**

---

## 🎯 リファクタリング対象の優先度

### 優先度：HIGH（最初に実施）

1. **customers.php** (481行CSS + 266行JS)
   - 理由: モーダル、フォーム、ボタンが多く、効果が大きい
   - 削減見込み: CSS 60% (290行), JS 50% (133行)

2. **master.php** (254行CSS + 580行JS)
   - 理由: JavaScript削除効果が大きい
   - 削減見込み: CSS 50% (127行), JS 40% (232行)

3. **employees.php** (推定250行CSS + 200行JS)
   - 理由: フォーム中心、比較的シンプル
   - 削減見込み: CSS 60% (150行), JS 50% (100行)

### 優先度：MEDIUM

4. **masters.php** (551行CSS + 219行JS)
5. **tasks.php** (推定300行CSS + 774行JS)
6. **troubles.php** (推定200行CSS + 150行JS)

### 優先度：LOW（後回し）

7. **finance.php** (717行CSS + 363行JS)
   - 理由: 複雑なグラフ表示、ページ固有スタイルが多い
8. **payroll-journal.php** (395行CSS + 1034行JS)
   - 理由: 複雑な計算ロジック、慎重な対応が必要
9. **index.php** (701行CSS + 113行JS)
   - 理由: ダッシュボード、影響範囲が広い

---

## 📝 リファクタリング手順（テンプレート）

### ステップ1: バックアップ
```bash
cp pages/xxx.php pages/xxx.php.backup
```

### ステップ2: 重複CSS削除

#### 削除対象（`css/components.css`と重複）

以下のスタイルは**完全に削除**してOK：

```css
/* ❌ 削除: モーダル */
.modal { ... }
.modal.active { ... }
.modal-content { ... }
.modal-header { ... }
.modal-body { ... }
.modal-footer { ... }
.modal-close { ... }
.close { ... }

/* ❌ 削除: フォーム */
.form-group { ... }
.form-label { ... }
.form-input { ... }
.form-input:focus { ... }
.form-input:disabled { ... }
.form-select { ... }
.form-textarea { ... }
.form-error { ... }

/* ❌ 削除: ボタン */
.btn { ... }
.btn-primary { ... }
.btn-secondary { ... }
.btn-success { ... }
.btn-danger { ... }
.btn-warning { ... }
.btn-outline { ... }
.btn-sm { ... }
.btn-lg { ... }
.btn-icon { ... }
.btn-icon:hover { ... }
.btn-icon.danger:hover { ... }

/* ❌ 削除: テーブル */
table { ... }
thead { ... }
tbody tr:hover { ... }

/* ❌ 削除: アラート */
.alert { ... }
.alert-success { ... }
.alert-danger { ... }
.alert-warning { ... }
.alert-info { ... }

/* ❌ 削除: バッジ */
.badge { ... }
.badge-primary { ... }
.badge-success { ... }
.badge-danger { ... }

/* ❌ 削除: カード */
.card { ... }
.card-header { ... }
.card-body { ... }
.card-footer { ... }
```

#### 残すべきスタイル（ページ固有）

```css
/* ✅ 保持: ページ固有のレイアウト */
.customers-grid { ... }  /* customers.php専用 */
.settings-select-grid { ... }  /* settings.php専用 */
.dashboard-widget { ... }  /* index.php専用 */

/* ✅ 保持: 特殊な動作 */
.draggable { ... }  /* tasks.php専用 */
.chart-container { ... }  /* finance.php専用 */
```

### ステップ3: 重複JavaScript削除

#### 削除対象（`js/common-utils.js`と重複）

```javascript
// ❌ 削除: モーダル制御
function openModal(id) { ... }
function closeModal(id) { ... }

// ❌ 削除: バリデーション
function validateEmail(email) { ... }
function validatePhone(phone) { ... }
function validateRequired(value) { ... }

// ❌ 削除: フォーム
function showFieldError(fieldId, message) { ... }
function clearFieldError(fieldId) { ... }

// ❌ 削除: API呼び出し（汎用的なもの）
async function fetchAPI(url, options) { ... }

// ❌ 削除: ユーティリティ
function formatNumber(num) { ... }
function formatCurrency(amount) { ... }
function formatDateJP(date) { ... }
```

#### 残すべきJavaScript（ページ固有）

```javascript
// ✅ 保持: ページ固有のビジネスロジック
function syncFromMF() { ... }  // finance.php専用
function calculatePayroll() { ... }  // payroll-journal.php専用
function updateChart(data) { ... }  // index.php専用
```

### ステップ4: HTML属性の更新

```html
<!-- 変更前 -->
<button style="padding: 0.75rem; background: var(--primary); color: white;">保存</button>

<!-- 変更後 -->
<button class="btn btn-primary">保存</button>
```

### ステップ5: 動作確認

1. ブラウザで該当ページを開く
2. 全機能をテスト：
   - モーダル開閉
   - フォーム送信
   - ボタンクリック
   - バリデーション
3. コンソールエラーがないか確認
4. デザインが崩れていないか確認

### ステップ6: デプロイ

```bash
powershell.exe -ExecutionPolicy Bypass -File "C:\Claude\master\auto-deploy.ps1"
```

---

## 📖 具体例：customers.php のリファクタリング

### Before (1003行)

```php
<style>
/* 481行のインラインCSS */
.modal { display: none; ... }
.form-group { ... }
.btn { ... }
/* ... 470行以上 ... */
</style>

<script>
/* 266行のインラインJavaScript */
function openModal(id) { ... }
function validateEmail(email) { ... }
/* ... 250行以上 ... */
</script>
```

### After (推定750行 = -253行)

```php
<!-- CSSはcomponents.cssから自動読み込み -->
<!-- 削除: .modal, .form-group, .btn等 -->

<style>
/* ページ固有スタイルのみ残す（推定190行） */
.customers-grid { ... }
.customer-row { ... }
/* ... */
</style>

<script>
/* common-utils.jsから自動読み込み */
/* 削除: openModal, validateEmail等 */

/* ページ固有ロジックのみ残す（推定133行） */
async function syncFromPartners() { ... }
function updateCustomerRow(id, data) { ... }
/* ... */
</script>
```

**削減量**: 290行CSS + 133行JS = **423行削減** (42%)

---

## ⚠️ 注意事項

### クラス名の競合

一部のページで独自の`.btn`定義がある場合：

```css
/* ❌ 問題: components.cssと競合 */
.btn {
    padding: 1rem;  /* 独自定義 */
}

/* ✅ 解決策1: 削除して components.css を使用 */
/* 削除 */

/* ✅ 解決策2: より具体的なクラス名に変更 */
.custom-large-btn {
    padding: 1rem;
}
```

### JavaScript関数の上書き

```javascript
// ❌ 問題: common-utils.jsと同名の関数
function openModal(id) {
    // 独自実装（異なる動作）
}

// ✅ 解決策: 関数名を変更
function openCustomModal(id) {
    // 独自実装
}
```

---

## 🎯 マイルストーン

### Week 1 (2026-02-09 ~ 02-15)
- [ ] customers.php (481行CSS + 266行JS)
- [ ] employees.php (推定250行CSS + 200行JS)
- [ ] master.php (254行CSS + 580行JS)

**目標削減**: 985行CSS + 1046行JS = **2031行**

### Week 2 (2026-02-16 ~ 02-22)
- [ ] masters.php
- [ ] tasks.php
- [ ] troubles.php
- [ ] loans.php

**目標削減**: 1000行CSS + 800行JS = **1800行**

### Week 3 (2026-02-23 ~ 03-01)
- [ ] 残り全ページ（低優先度含む）

**目標削減**: 全残り

---

## 📊 期待される効果

| 指標 | Before | After | 改善率 |
|------|--------|-------|--------|
| **総CSS行数** | 7,217行 | ~4,500行 | **-37%** |
| **総JS行数** | 4,690行 | ~2,800行 | **-40%** |
| **ページサイズ** | - | -15~20% | キャッシュ効率 |
| **開発速度** | - | +30% | 共通化による |
| **バグ率** | - | -50% | 重複削減による |

---

## 🔧 便利なコマンド

### CSS重複チェック
```bash
# customers.phpで.modalが定義されているか確認
grep -n "\.modal {" pages/customers.php
```

### 削減行数カウント
```bash
# Before
wc -l pages/customers.php

# After
wc -l pages/customers.php
```

### 差分確認
```bash
diff pages/customers.php.backup pages/customers.php
```

---

## 📚 参考資料

- `css/components.css` - 共通CSSコンポーネント定義
- `js/common-utils.js` - 共通JavaScript関数定義
- `js/icons.js` - SVGアイコン定義
- `pages/test-components.php` - 共通コンポーネントの使用例

---

**最終更新**: 2026-02-09
**次回更新予定**: 最初のページ完了時
