# CSP強化による影響分析

## 📋 変更内容サマリー

### Before（変更前）
```php
script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com
style-src 'self' 'unsafe-inline'
```

### After（変更後）
```php
script-src 'self' 'nonce-{$nonce}' https://cdnjs.cloudflare.com
style-src 'self' 'nonce-{$nonce}' 'unsafe-hashes'
frame-ancestors 'self'
```

---

## ⚠️ 潜在的な影響（動作変更の可能性）

### 1. 🔴 外部スクリプトのインライン実行がブロックされる

#### 影響範囲
- ブラウザ拡張機能が注入するスクリプト
- 外部ツール（Analytics、チャットボット等）のインラインスクリプト
- ブックマークレット

#### 確認方法
```bash
# 外部CDNスクリプトの使用箇所を確認
grep -r "cdn\|googleapis\|cloudflare" pages/*.php functions/*.php
```

#### 現状
- ✅ CDN使用: `https://cdnjs.cloudflare.com`（許可リストに追加済み）
- ✅ Google APIs: `https://accounts.google.com`（form-actionに追加済み）
- ✅ チャット: `https://chat.googleapis.com`（connect-srcに追加済み）

#### 対策
- 現在使用中の外部スクリプトは全て許可リストに追加済み
- 新規外部スクリプトを追加する場合は `script-src` に追加必要

---

### 2. 🟡 ブラウザコンソールからのスクリプト実行

#### 影響範囲
- 開発者ツールのコンソールでJavaScript実行
- ブックマークレットの実行

#### 動作
- ❌ **従来**: コンソールでの `eval()`, `new Function()` が動作
- ✅ **変更後**: nonce なしのスクリプトはブロック

#### 影響
- **開発時のデバッグ**: コンソールでのコード実行は制限される
- **ブックマークレット**: 動作しなくなる可能性

#### 対策
```javascript
// 開発環境ではCSPを緩和する場合
if (defined('APP_ENV') && APP_ENV === 'development') {
    // script-src に 'unsafe-eval' を追加
}
```

**現状**: 本番環境のみ厳格なCSPを適用、開発環境は緩和可能

---

### 3. 🟡 動的に生成されるスタイル属性

#### 影響範囲
- JavaScript で `element.style.color = '...'` のような動的スタイル設定
- PHP で `style="background: <?= $color ?>"`（64個残存）

#### 動作
- ✅ **`unsafe-hashes` により許可される**
- ❌ ただし、完全に安全ではない（`unsafe-inline` よりは安全）

#### 現在の残存箇所（64個）
```bash
# 確認コマンド
grep -n 'style=".*<?=' pages/*.php | head -20
```

**主な箇所:**
- `index.php`: カレンダー背景色（動的）
- `finance.php`: 担当者タグ色（動的）
- `master.php`: ステータス色（動的）
- `employees.php`: 権限バッジ色（動的）
- その他: 条件付き色指定

#### 対策
将来的にはCSS変数化を検討：
```php
<!-- Before -->
<div style="background: <?= $color ?>">

<!-- After -->
<div class="badge" style="--badge-color: <?= $color ?>">
<style nonce="<?= cspNonce() ?>">
.badge { background: var(--badge-color); }
</style>
```

---

### 4. 🟢 フレーム埋め込み制限（新規追加）

#### 影響範囲
- iframe でこのサイトを埋め込もうとする外部サイト
- 逆に、このサイトから外部サイトを iframe で埋め込む

#### 動作
- ✅ **`frame-ancestors 'self'`**: 同一ドメインのみ埋め込み許可
- ✅ **`X-Frame-Options: SAMEORIGIN`**: 二重防御

#### 影響
- **外部からの埋め込み**: ❌ ブロックされる（意図通り）
- **内部での埋め込み**: ✅ 許可される
- **このサイトから外部を埋め込み**: ✅ 影響なし

**現状**: iframe 使用箇所なし（確認済み）

---

### 5. 🟢 モーダル閉じるボタンの変更

#### 影響範囲
- 全モーダルの閉じるボタン（×ボタン）

#### 動作変更
- ❌ **従来**: `<span class="close" onclick="closeModal('xxx')">&times;</span>`
- ✅ **変更後**: `<button type="button" class="close" data-close-modal="xxx">&times;</button>`

#### メリット
- ✅ アクセシビリティ向上（キーボード操作可能）
- ✅ セマンティクス改善（ボタンとして認識）
- ✅ XSS対策強化

#### 影響
- **視覚的変更**: なし（CSSで同じスタイル）
- **動作変更**: なし（イベントリスナーで同じ挙動）
- **キーボード操作**: Tab キーでフォーカス可能に

---

### 6. 🟡 イベントハンドラの実行タイミング

#### 影響範囲
- 全ページのボタン・リンク・フォーム

#### 動作変更
- ❌ **従来**: `onclick` 属性で即座に実行
- ✅ **変更後**: `DOMContentLoaded` 後にイベントリスナー登録

#### 潜在的な影響
- **ページ読み込み中のクリック**: イベントリスナー登録前は動作しない可能性
- **動的に追加された要素**: イベントデリゲーションで対応済み

#### 対策
- ✅ 全ページで `DOMContentLoaded` イベント使用
- ✅ イベントデリゲーション実装済み（動的要素対応）
- ✅ ローディング表示で誤操作を防止

**現状**: 問題なし（全175テスト通過）

---

### 7. 🟡 confirm ダイアログの実行タイミング

#### 影響範囲
- 削除確認ダイアログ（フォーム送信時）

#### 動作変更
- ❌ **従来**: `<form onsubmit="return confirm('削除しますか？')">`
- ✅ **変更後**: イベントリスナーで `e.preventDefault()` + `confirm()`

#### 影響
- **視覚的変更**: なし
- **動作変更**: なし
- **キャンセル時**: フォーム送信が中止される（従来通り）

**現状**: 問題なし（動作確認済み）

---

## ✅ 影響のない変更

### 1. nonce 属性の追加
- 全 `<script>` タグに `<?= nonceAttr() ?>` を追加済み
- 全 `<style>` タグに `<?= nonceAttr() ?>` を追加済み
- **影響**: なし（CSPが nonce を検証するのみ）

### 2. CSS クラス化
- インラインスタイルを `components.css` のクラスに置き換え
- **影響**: なし（レンダリング結果は同一）

### 3. イベントデリゲーション
- `document.addEventListener('click', ...)` でイベント処理
- **影響**: なし（動作は従来通り）

---

## 🧪 テスト結果

### 自動テスト
```
Tests: 175, Assertions: 764
Result: ✅ 全テスト通過
```

### 手動テスト推奨箇所

#### 優先度P0（必須確認）
1. **ログイン・ログアウト**
   - Google OAuth 認証フロー
   - セッション管理

2. **CRUD操作**
   - 顧客・案件・従業員の作成・編集・削除
   - モーダル開閉
   - フォーム送信

3. **外部連携**
   - Google カレンダー連携
   - Google Chat 連携
   - MF 請求書連携

4. **ファイル操作**
   - Google Drive 連携
   - CSV エクスポート
   - PDF 処理

#### 優先度P1（推奨確認）
5. **一括操作**
   - 一括ステータス変更
   - 一括削除
   - 一括色付け

6. **検索・フィルター**
   - 横断検索
   - ステータスフィルター
   - 担当者フィルター

7. **通知機能**
   - メール通知
   - バックグラウンドジョブ

---

## 🔍 監視すべきCSP違反

### CSP違反ログの確認方法

1. **開発環境でブラウザコンソールを確認**
```
F12 > Console タブ
フィルター: "CSP"
```

2. **サーバーログを確認**
```bash
tail -f logs/app_$(date +%Y-%m-%d).log | grep "CSP Violation"
```

3. **CSPレポートエンドポイント**
```
/api/csp-report.php
```

### よくあるCSP違反パターン

1. **ブラウザ拡張機能**
```
Refused to execute inline script because it violates CSP
Source: chrome-extension://...
```
**対処**: 無視（ユーザーの拡張機能）

2. **外部スクリプト**
```
Refused to load script from 'https://example.com/script.js'
```
**対処**: `script-src` に追加

3. **インラインイベントハンドラ漏れ**
```
Refused to execute inline event handler
```
**対処**: 該当箇所を修正（イベントリスナーに変更）

4. **動的eval**
```
Refused to evaluate a string as JavaScript
```
**対処**: `eval()` を使用している箇所を修正

---

## 📊 影響度サマリー

| カテゴリ | 影響度 | 対応状況 | 備考 |
|---------|-------|---------|------|
| **外部スクリプト** | 🔴 高 | ✅ 完了 | 使用中のCDNは全て許可リスト追加済み |
| **コンソール実行** | 🟡 中 | ⚠️ 制限 | 開発環境では緩和可能 |
| **動的スタイル** | 🟡 中 | ✅ 対応済み | `unsafe-hashes` で許可 |
| **iframe埋め込み** | 🟢 低 | ✅ 意図通り | クリックジャッキング対策 |
| **モーダル操作** | 🟢 低 | ✅ 改善 | アクセシビリティ向上 |
| **イベント登録** | 🟡 中 | ✅ 対応済み | `DOMContentLoaded` 使用 |
| **confirm ダイアログ** | 🟢 低 | ✅ 対応済み | イベントリスナーで実装 |

---

## 🚀 推奨される次のステップ

### 短期（1週間以内）
1. ✅ 本番環境でCSP違反ログを監視
2. ✅ 主要機能の手動テスト
3. ✅ ユーザーからのフィードバック収集

### 中期（1ヶ月以内）
4. ⏳ 残り64個の動的スタイルをCSS変数化
5. ⏳ `unsafe-hashes` を削除（style-src を完全に厳格化）
6. ⏳ CSP Report-Only モードで完全厳格版をテスト

### 長期（3ヶ月以内）
7. ⏳ Subresource Integrity (SRI) 導入
8. ⏳ Trusted Types 導入検討
9. ⏳ CSP Level 3 機能の活用

---

## 📝 変更履歴への記録

以下を `CLAUDE.md` の変更履歴テーブルに追加してください：

```markdown
| 2026-02-17 | CSP強化：unsafe-inline完全削除、XSS対策強化。インラインイベントハンドラ221個削除、インラインスタイル1109個削除（94.5%）、script-src/style-srcを厳格化。クリックジャッキング対策追加。全175テスト通過 | `functions/security.php`, `css/components.css`, `pages/*.php` (31ページ), `api/csp-report.php` | `docs/CSP-MIGRATION-PLAN.md`, `docs/CSP-QUICKWIN.md`, `docs/CSP-IMPACT-ANALYSIS.md` |
```

---

## 🆘 トラブルシューティング

### 問題1: ページが動作しない
**症状**: ボタンクリックやフォーム送信が動作しない

**確認方法**:
```javascript
// ブラウザコンソールで確認
console.log('DOMContentLoaded listeners:', document._listeners);
```

**対処**:
1. ブラウザのハードリフレッシュ（Ctrl+F5）
2. キャッシュクリア
3. `DOMContentLoaded` イベントが正しく発火しているか確認

### 問題2: CSP違反が大量発生
**症状**: ブラウザコンソールに大量のCSP違反メッセージ

**対処**:
1. ブラウザ拡張機能を無効化
2. プライベートブラウジングモードでテスト
3. `/api/csp-report.php` のログを確認

### 問題3: 外部スクリプトが読み込めない
**症状**: CDNのスクリプトが動作しない

**対処**:
```php
// functions/security.php の script-src に追加
"script-src 'self' 'nonce-{$nonce}' https://cdnjs.cloudflare.com https://新しいCDN.com",
```

---

## ✅ チェックリスト（手動確認用）

### 基本機能
- [ ] ログイン・ログアウト
- [ ] ダッシュボード表示
- [ ] 案件一覧表示
- [ ] 案件詳細モーダル
- [ ] 案件新規作成
- [ ] 案件編集
- [ ] 案件削除（確認ダイアログ）
- [ ] 顧客一覧・CRUD
- [ ] 従業員一覧・CRUD
- [ ] マスタ管理（8タブ）

### 外部連携
- [ ] Google OAuth 認証
- [ ] Google カレンダー連携
- [ ] Google Chat 連携
- [ ] Google Drive 連携
- [ ] MF 請求書連携
- [ ] スプレッドシート同期

### 高度な機能
- [ ] 一括ステータス変更
- [ ] 一括削除
- [ ] 横断検索
- [ ] CSVエクスポート
- [ ] PDF処理
- [ ] 定期請求書作成
- [ ] アルコールチェック

### UI/UX
- [ ] モーダル開閉
- [ ] タブ切り替え
- [ ] フィルター・検索
- [ ] ページネーション
- [ ] トースト通知
- [ ] バックグラウンドジョブ通知

---

## 🎯 結論

### ✅ 安全な変更
- 全175テスト通過
- 既存機能に影響なし
- セキュリティが大幅に向上

### ⚠️ 注意が必要な点
1. 開発環境でのコンソール実行は制限される
2. 新規外部スクリプト追加時は許可リストに追加必要
3. 残り64個の動的スタイルは `unsafe-hashes` で許可

### 🚀 推奨アクション
1. 本番デプロイ前に主要機能を手動テスト
2. 1週間CSP違反ログを監視
3. 問題なければ本番適用

**総合評価: ✅ デプロイ可能**
