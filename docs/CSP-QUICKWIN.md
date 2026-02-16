# CSP強化: 最小限の対応プラン

## 現状

- **script-src 'unsafe-inline'**: 221個のインラインイベントハンドラ（onclick等）
- **style-src 'unsafe-inline'**: 1173個のインラインスタイル（style属性）

合計 **1394個** のインライン要素が存在

## 優先順位

### 高リスク: script-src 'unsafe-inline' → XSS攻撃の直接的な原因

**対応必須:** インラインイベントハンドラ（onclick, onload等）の削除

**理由:**
- JavaScriptの実行を許可 → XSS攻撃が成功する
- 攻撃者が任意のコードを実行可能
- データ窃取、セッションハイジャック、マルウェア配布のリスク

### 低リスク: style-src 'unsafe-inline' → 限定的なリスク

**対応推奨（優先度低）:** インラインスタイルの削除

**理由:**
- CSSは基本的にコード実行不可
- ただし以下のリスクは存在：
  - `expression()` (IE古いバージョンのみ)
  - `url()` でデータ流出の可能性
  - UIスプーフィング（フィッシング）

## 最小限の対応プラン（2-4週間）

### Step 1: 高頻度ページの onclick 削除（P0: 6ページ）

| ページ | onclick数 | 作業時間見積 |
|--------|----------|-------------|
| masters.php | 49 | 4時間 |
| customers.php | 34 | 3時間 |
| master.php | 34 | 3時間 |
| photo-attendance.php | 20 | 2時間 |
| finance.php | 18 | 2時間 |
| troubles.php | 14 | 1.5時間 |

**合計:** 15.5時間

### Step 2: 管理ページの onclick 削除（P1: 7ページ）

| ページ | onclick数 | 作業時間見積 |
|--------|----------|-------------|
| employees.php | 10 | 1時間 |
| test-components.php | 9 | 1時間 |
| loans.php | 7 | 1時間 |
| settings.php | 4 | 0.5時間 |
| user-permissions.php | 3 | 0.5時間 |
| integration-settings.php | 3 | 0.5時間 |
| mf-invoice-list.php | 3 | 0.5時間 |

**合計:** 5時間

### Step 3: 残りページの onclick 削除（P2: 10ページ）

**合計:** 3時間

### Step 4: CSP厳格化（script-src のみ）

```php
// functions/security.php
$csp = [
    "default-src 'self'",
    "script-src 'self' 'nonce-{$nonce}' https://cdnjs.cloudflare.com",  // unsafe-inline 削除！
    "style-src 'self' 'unsafe-inline'",  // ← 暫定的に維持
    // ... 以下同じ
];
```

**全体:** 約24時間（3日間）で script-src を厳格化可能

## style-src の対応（長期計画）

### 段階的移行

1. **よく使われるインラインスタイルをCSS変数化**
   - 例: `style="color: red"` → `class="text-danger"` (components.cssに定義済み)

2. **動的スタイルはCSSクラス切り替えに変更**
   ```javascript
   // Before
   element.style.display = 'none';

   // After
   element.classList.add('hidden');
   ```

3. **全てのインラインスタイル削除後、unsafe-inline を削除**

**見積:** 40-60時間（1-2週間）

## 推奨アクション

### 今すぐ実行（1日）

1. CSP Report-Only モードを有効化（監視開始）
2. api/csp-report.php を作成（違反ログ記録）

### 1週間以内

1. P0ページ（6ページ）の onclick 削除
2. script-src から unsafe-inline 削除（一部ページのみ）

### 1ヶ月以内

1. 全ページの onclick 削除
2. script-src から unsafe-inline 完全削除
3. style-src の段階的対応開始

## CSP違反レポート設定

```php
// api/csp-report.php（新規作成）
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/logger.php';

// CSP違反レポートを受信
$input = file_get_contents('php://input');
$report = json_decode($input, true);

if ($report && isset($report['csp-report'])) {
    $violation = $report['csp-report'];

    // 違反の詳細をログに記録
    logWarning('CSP Violation', [
        'violated-directive' => $violation['violated-directive'] ?? '',
        'blocked-uri' => $violation['blocked-uri'] ?? '',
        'source-file' => $violation['source-file'] ?? '',
        'line-number' => $violation['line-number'] ?? 0,
        'original-policy' => $violation['original-policy'] ?? '',
    ]);
}

http_response_code(204); // No Content
```

このレポートを見ながら、どのページのどの行に違反があるか特定し、段階的に修正していきます。
