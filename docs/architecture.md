# アーキテクチャ概要

> このファイルは CLAUDE.md の補足ドキュメントです。
> システム構造・データフロー・依存関係の詳細はここを参照してください。

---

## データフロー

```
[ブラウザ] → [pages/*.php] → [api/auth.php（認証）] → [config/config.php（権限）]
                                     ↓
                              [getData()/saveData()]
                                     ↓
                               [data.json（排他ロック）]
```

---

## 認証フロー

```
1. pages/login.php → Google OAuth認証
2. api/google-callback.php → セッション作成
3. api/auth.php → 毎リクエストで認証・権限チェック
4. $_SESSION['user_email'], $_SESSION['user_role'] で識別
```

---

## 権限システム構造

```
権限レベル定義: config/config.php (hasPermission, isAdmin, canEdit, canDelete)
         ↓
ページ権限定義: api/auth.php ($defaultPagePermissions)
         ↓
カスタム上書き: config/page-permissions.json
         ↓
実行時チェック: 各ページで hasPermission(getPageViewPermission('xxx.php'))
```

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

---

## 重要な依存関係

- `config/config.php` は全PHPファイルの起点（セッション開始、関数定義）
- `api/auth.php` は `pages/*.php` で必ず最初に `require`
- `getData()` / `saveData()` 以外でdata.jsonを直接触らない

---

## 危険箇所マップ

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

## セキュリティ設定

- セッションタイムアウト: 環境変数 `SESSION_TIMEOUT_HOURS`（デフォルト8時間）
- SSL証明書検証: 環境変数 `SSL_VERIFY_PEER`（本番ではtrue必須）
- CSPモード: 環境変数 `CSP_MODE`（strict/report/legacy）
- X-Forwarded-For: `config/security-config.json` の `trusted_proxies` に設定したプロキシからのみ信頼
