# E2E テスト (Playwright)

ローカルブラウザ自動操作で UI フローを検証する。

過去事故の防止が主目的:
- `<form>` ネストでチェックボックス孤児化（troubles.php）
- 削除ハンドラがダッシュボードにリダイレクト迷子
- 削除ボタンのクラス無し / 確認ダイアログ無し

---

## 前提

ローカル開発環境がセットアップ済みであること → `docs/local-setup.md` 参照。

最低限:
- MySQL に `adyamato_gear` DB がある（または `DB_MODE=json`）
- PHP サーバーが `localhost:8000` で `router.php` 付きで起動中

---

## 初回セットアップ

```powershell
npm install
npx playwright install chromium
```

---

## 実行

```powershell
# 全テスト（ヘッドレス）
npm test

# ブラウザを表示しながら
npm run test:headed

# 対話的に選んで実行する UI モード
npm run test:ui

# デバッガで止めながら
npm run test:debug

# 失敗テストのレポートを開く
npm run test:report
```

---

## テストファイル構成

```
tests/e2e/
├── fixtures.js           # 認証済み page を提供する共通 fixture
├── smoke.spec.js         # 主要ページが正常表示されるかの確認
└── delete-flows.spec.js  # 削除ボタン挙動の統一性 + 機能テスト
```

---

## 削除フローを保証するテスト一覧

| テスト | 何を保証するか |
|---|---|
| `bulkDeleteForm がテーブル外に独立配置` | 過去の `<form>` ネスト事故を二度と起こさない |
| `0件選択時にバリデーション alert` | 空 POST がサーバーに飛ばない |
| `削除ハンドラがダッシュボードにリダイレクトしない` | 今回の主要バグの再発防止 |
| `ステータス変更 select が data-trouble-id を持つ` | nested form 解消の構造証拠 |
| `IP削除ボタンに btn-icon danger クラス` | 視覚的統一の維持 |
| `1件選択 → 一括削除 → DBから論理削除` | 実際に削除されることの end-to-end 確認 |

新しく削除機能を追加する時は、ここに同等のテストを足すこと。

---

## CI / デプロイ連携

`auto-deploy.ps1` の Pre-deploy checks に組み込むのが理想:

```powershell
# auto-deploy.ps1 の [0/4] ブロック末尾あたりに追加
Write-Host "  Running E2E tests..."
& npm test
if ($LASTEXITCODE -ne 0) {
    Write-Host "ABORT: E2E tests failed" -ForegroundColor Red
    exit 1
}
```

ただし PHP サーバーと MySQL がローカルで起動している必要があるので、自動起動の仕組みも合わせて整備すること（`playwright.config.js` の `webServer` セクションを有効化する手もある）。

---

## テスト追加時の指針

- **Page Object パターンは導入しない**（ファイル数の少ない小規模プロジェクトのため）
- **`authedPage` fixture を必ず使う**（ログイン処理の重複を避ける）
- **テストデータの後始末はテスト内でやる**（追加した IP を最後に削除する等）
- **`fullyParallel: false` 維持**（セッション干渉を避けるため）

---

## トラブルシューティング

### `Error: browserType.launch: Executable doesn't exist`

```powershell
npx playwright install chromium
```

### `test-login failed: 403`

`.env.local` の `APP_ENV=local` を確認。本番設定では test-login.php がブロックされる。

### `1件選択 → 一括削除` テストで件数が変わらない

`DB_MODE=json` の状態で MySQL のテーブルを直接見ていないか確認。`DB_MODE=dual` で動かすか、論理削除済み行を含めて検証するように変更。
