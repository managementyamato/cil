# 権限システム設計書

## 概要

本システムは3段階の権限レベルと、ページごとの閲覧・編集権限設定を提供します。

## 権限レベル

| レベル | 名称 | 説明 |
|--------|------|------|
| `sales` | 営業部 | 基本的な閲覧・自分のタスク管理のみ |
| `product` | 製品管理部 | データの閲覧・編集が可能 |
| `admin` | 管理部 | 全機能にアクセス可能（削除含む） |

**権限の階層**: `admin` > `product` > `sales`

上位権限は下位権限の操作をすべて実行できます。

---

## 権限の種類

### 1. 閲覧権限（view）
- ページへのアクセス可否
- サイドバーへのメニュー表示
- ダッシュボードでの各ウィジェット表示

### 2. 編集権限（edit）
- データの追加・更新
- フォームの送信

### 3. 削除権限（delete）
- **管理部（admin）のみ**
- 全ページ共通ルール

---

## ページ別デフォルト権限

| ページ | 閲覧権限 | 編集権限 |
|--------|----------|----------|
| ダッシュボード（index.php） | 営業部以上 | 営業部以上 |
| タスク管理（tasks.php） | 営業部以上 | 営業部以上 |
| トラブル対応（troubles.php） | 営業部以上 | 製品管理部以上 |
| プロジェクト管理（master.php） | 製品管理部以上 | 製品管理部以上 |
| 損益（finance.php） | 製品管理部以上 | 製品管理部以上 |
| 借入金（loans.php） | 製品管理部以上 | 製品管理部以上 |
| 給与仕訳（payroll-journal.php） | 製品管理部以上 | 製品管理部以上 |
| アルコールチェック（photo-attendance.php） | 製品管理部以上 | 製品管理部以上 |
| マスタ管理（masters.php） | 製品管理部以上 | 製品管理部以上 |
| 設定（settings.php） | 管理部のみ | 管理部のみ |

---

## 権限設定方法

### 管理画面から設定
1. 管理部アカウントでログイン
2. 設定 → アカウント権限設定
3. 「ページ別アクセス権限」セクションで各ページの閲覧・編集権限を変更

### 設定ファイル
権限設定は `config/page-permissions.json` に保存されます。

```json
{
    "permissions": {
        "troubles.php": {
            "view": "sales",
            "edit": "product"
        },
        "master.php": {
            "view": "product",
            "edit": "product"
        }
    },
    "updated_at": "2026-02-05 17:00:00"
}
```

---

## 関連関数

### config/config.php

```php
// 権限チェック（指定レベル以上か判定）
hasPermission($requiredRole)  // 例: hasPermission('product')

// 管理者チェック
isAdmin()  // adminのみtrue

// 編集権限チェック（製品管理部以上）
canEdit()  // product以上でtrue

// 削除権限チェック（管理部のみ）
canDelete()  // adminのみtrue
```

### api/auth.php

```php
// ページの閲覧権限を取得
getPageViewPermission($page)  // 例: getPageViewPermission('troubles.php')

// ページの編集権限を取得
getPageEditPermission($page)

// 現在のページの編集権限があるかチェック
canEditCurrentPage()

// 指定ページの編集権限があるかチェック
canEditPage($page)
```

---

## ダッシュボードの表示制御

権限に応じて以下の項目が表示/非表示になります：

| 項目 | 表示条件 |
|------|----------|
| 未対応トラブル | troubles.php の閲覧権限 |
| 進行中案件 | master.php の閲覧権限 |
| 対応完了率 | troubles.php の閲覧権限 |
| アルコールチェック同期 | photo-attendance.php の閲覧権限 |
| トラブル対応状況（グラフ） | troubles.php の閲覧権限 |
| 案件ステータス | master.php の閲覧権限 |
| クイックアクション | 各リンク先の閲覧権限 |
| タスク管理 | 全員表示 |
| カレンダー | 全員表示 |
| 最近のアクティビティ | 全員表示 |

---

## サイドバーの表示制御

各メニューは対応するページの閲覧権限に基づいて表示されます。

```php
<?php if (hasPermission(getPageViewPermission('troubles.php'))): ?>
<a href="/pages/troubles.php">トラブル対応</a>
<?php endif; ?>
```

---

## 削除権限の適用箇所

以下のページ/APIで削除操作が管理部のみに制限されています：

- **master.php**: プロジェクト削除、一括削除
- **employees.php**: 従業員削除
- **masters.php**: 顧客・担当者・パートナー・商品カテゴリ削除
- **loans.php**: 借入先削除
- **troubles.php**: トラブル一括削除

削除ボタンは管理部以外には非表示になります。

---

## 新規ページ追加時の手順

1. `api/auth.php` の `$defaultPagePermissions` に権限を追加
2. `functions/header.php` のサイドバーに権限チェック付きでリンクを追加
3. ダッシュボードに表示する場合は `pages/index.php` に権限チェックを追加
4. 削除処理がある場合は `canDelete()` チェックを追加

---

## 変更履歴

| 日付 | 内容 |
|------|------|
| 2026-02-05 | 権限システム実装 |
| 2026-02-05 | 削除権限を管理部のみに制限 |
| 2026-02-05 | ダッシュボードの権限制御追加 |
| 2026-02-05 | サイドバーの権限制御追加 |
