# UIクラス・レガシー非統一要素 棚卸し

> このドキュメントは、`patterns.md` の UI統一ルール（`form-input` / `data-table` / `btn btn-primary` など）に従っていない**既存ページの独自クラス**を列挙したもの。
>
> **新規実装では絶対に真似しないこと。** 既存コードは動作しているため、段階的に寄せていく対象として記録する。
> ページ全体のデザインを一括で置き換えると崩れる可能性があるため、各ページの改修時にそのページだけ統一スタイルへ移行する方針。

---

## 🔴 フォーム要素：`form-input` 未使用の独自クラス

| ファイル | 独自クラス | 用途 | 代替 |
|---|---|---|---|
| [pages/contacts.php](../pages/contacts.php) | `ct-search`, `f-input`, `cell-input` | 検索・入力 | `form-input` |
| [pages/search.php](../pages/search.php) | `search-page-input` | 検索入力 | `form-input` |
| [pages/company-rules.php](../pages/company-rules.php) | `rules-search-input` | 検索入力 | `form-input` |

**移行手順（ページ単位）:**
1. 該当ページの `class="xxx-input"` を全て `form-input` に置換
2. 対応する CSS 定義（`<style>` 内 or style.css 内）から削除
3. 開発サーバーで表示崩れがないか確認

---

## 🔴 テーブル：`data-table` 未使用の独自クラス

| ファイル | 独自クラス | 代替 |
|---|---|---|
| [pages/contacts.php](../pages/contacts.php) | `ct-table` | `data-table` |
| [pages/employees.php](../pages/employees.php) | `employee-table` | `data-table` |
| [pages/finance.php](../pages/finance.php) | `inv-check-tbl`, `audit-table` | `data-table` |

---

## 🟡 ボタン：`btn btn-*` 未使用の独自クラス

| ファイル | 独自クラス | 代替 |
|---|---|---|
| [pages/employees.php](../pages/employees.php) | `bulk-input-style`, `bulk-remove-btn` | `btn btn-secondary` / `btn btn-danger` |
| [pages/search.php](../pages/search.php) | `search-filter-btn` | `btn btn-sm btn-secondary` |
| [pages/finance.php](../pages/finance.php) | `month-btn`, `month-btn-size` | `btn btn-sm` |

---

## 🟡 モーダル：`modal` + `.active` 未使用の独自実装

| ファイル | 独自実装 | 代替 |
|---|---|---|
| [pages/contacts.php](../pages/contacts.php) | `contact-modal-bg`, `modal-box`, `modal-head`, `modal-foot` | `modal` + `modal-content` + `modal-header` + `modal-body` + `modal-footer` |
| [pages/company-rules.php](../pages/company-rules.php) | `rules-edit-modal` | 上記同 |
| [pages/slides.php](../pages/slides.php) | `form-modal`, `slide-modal`, `confirm-list-modal` | 上記同 |
| [pages/reports-hub.php](../pages/reports-hub.php) | `hub-modal`, `hub-modal-header` 等 | 上記同 |

**注意:** モーダル移行は `openModal()` / `closeModal()` 呼び出しとCSS依存関係が絡むため、ページ単位で段階的に実施。

---

## ✅ すでに統一済み（触らない）

- `form-control` の使用 → 0件（CLAUDE.mdルール厳守）
- `file_get_contents/file_put_contents('data.json')` → 0件（getData/saveData経由）
- POSTページの `verifyCsrfToken()` → 全ページ実装済み

---

## 移行優先度

| 優先度 | 理由 |
|---|---|
| **高** | フォーム要素（`form-input`統一）— 挙動は同じでCSS置き換えのみで済むことが多い |
| **中** | テーブル（`data-table`統一）— `<th>`, `<td>` の padding等に差が出る可能性 |
| **中** | ボタン — 色・サイズが統一される |
| **低** | モーダル — JavaScript の openModal/closeModal 呼び出しが伴うため影響範囲大 |

---

## 関連ドキュメント

- [patterns.md](patterns.md) — 新規実装時のUI統一パターン
- [ng-patterns.md](ng-patterns.md) — よくある失敗パターン
- [CLAUDE.md](../CLAUDE.md) — UI統一ルール（`form-input` 必須・`form-control` 禁止）
