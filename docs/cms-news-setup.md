# HP更新 (cms-news) セットアップ手順

> yamato-mgt.com の `/pages/cms-news.php` から、ヤマト広告コーポレートサイト (`ya-corporate-site` Astro リポジトリ) のお知らせ記事を **GitHub Contents API** 経由で編集・自動コミット・自動 push する仕組み。Cloudflare Pages がそれを検知して本番 HP を自動デプロイします。

---

## 全体構成

```
[管理部の任意のPC]
   ↓ ブラウザ
yamato-mgt.com/pages/cms-news.php  ← admin 限定
   ↓ GitHub Contents API (HTTPS, PAT認証)
github.com/YA-Naoto/ya-corporate-site
   ↓ Cloudflare Pages 自動デプロイ
本番 HP (ya-corporate-site.pages.dev → yamato-agency.com)
```

旧設計 (XServer に clone → git push) からの変更点:
- サーバー上のリポジトリ clone は **不要**
- SSH 鍵 / Deploy Key の登録は **不要**
- `proc_open` / `git` バイナリの依存は **なし**
- 設定は UI から登録 (`config/cms-config.json` に保存、PAT は暗号化)

---

## セットアップ手順（管理者）

### 1. GitHub で fine-grained PAT を発行

1. GitHub にログイン → 右上アイコン → **Settings**
2. 左メニュー最下部の **Developer settings** をクリック
3. **Personal access tokens** → **Fine-grained tokens** → **Generate new token**
4. 項目を入力:
   - **Token name**: `yamato-mgt-cms` 等
   - **Expiration**: 1年程度 (期限切れ前に再発行が必要)
   - **Repository access**: *Only select repositories* → `ya-corporate-site` を選択
   - **Repository permissions**: `Contents` のみ **Read and write** に設定
5. **Generate token** → 表示された `github_pat_xxxx` をコピー
   - ⚠️ 一度離れると二度と表示されません。必ずコピーしてから次へ進む

### 2. yamato-mgt 管理画面で登録

1. https://yamato-mgt.com/pages/cms-settings.php にアクセス (admin 限定)
   - サイドバー: 設定 → HP更新 設定
2. フォームに入力:
   - **GitHub PAT**: コピーした `github_pat_xxxx`
   - **リポジトリ**: `YA-Naoto/ya-corporate-site` 形式
   - **ブランチ**: 本番反映ブランチ名 (例: `main` または `claude/yamato-astro-migration-continue-EfTu7`)
   - **記事フォルダ**: `src/content/news`
   - **カテゴリ**: 1行ごと または カンマ区切りで列挙
   - **コミッター名 / email**: 任意 (commit 表示用)
3. **接続テスト** ボタンで疎通確認 → `[OK] 接続OK: owner/repo@branch` が出れば成功
4. **保存** ボタン

### 3. 動作確認

1. https://yamato-mgt.com/pages/cms-news.php にアクセス
2. 既存記事の一覧が表示されることを確認
3. テスト用に1件追加 → **保存して公開**
4. 「公開完了」モーダルに commit ハッシュが表示される
5. Cloudflare Pages の管理画面でデプロイがトリガーされたことを確認

---

## トラブルシューティング

| 症状 | 原因 | 対処 |
|---|---|---|
| 接続テストで `PAT が無効か期限切れです` | PAT 入力ミス or 期限切れ | PAT 再発行 → 再登録 |
| 接続テストで `権限不足` | Contents が Read-only | PAT 編集で Read **and write** に変更 |
| 接続テストで `リポジトリ or ブランチが見つかりません` | repo名 or ブランチ名のスペルミス | 入力を再確認 |
| 一覧読み込みで「ファイル一覧の取得に失敗」 | `content_dir` のパスが間違っている | 設定で記事フォルダを修正 |
| 保存時に `409` | 同じIDの記事が既に存在 | 別のファイルIDで作成 |
| 「公開完了」したのに本番 HP が変わらない | Cloudflare Pages のビルドが失敗 | Cloudflare の Deployments ログを確認 |

---

## PAT 期限切れ対応

PAT は最大1年で期限切れになります。期限が近づいたら:
1. GitHub で新しい PAT を発行 (上記 1. と同じ手順)
2. cms-settings.php の **GitHub PAT** 欄に新しいトークンを入れて **保存**
   - 空欄のまま保存すると既存トークンが維持されます
3. **接続テスト** で確認

---

## セキュリティ・運用ノート

- **PAT の保存**: `config/cms-config.json` に AES-256-GCM 暗号化 (enc: プレフィックス) で保存
  - 鍵は `config/encryption.key` または `ENCRYPTION_KEY` 環境変数
- **アクセス制限**: cms-news.php / cms-settings.php とも `admin` 権限のみ
- **CSRF 保護**: 全 state-changing アクションで `verifyCsrfToken()` 必須
- **PAT 権限の最小化**: 対象リポジトリだけ・Contents だけに絞っているので、流出時の被害範囲は最小

---

## 関連ファイル

- `pages/cms-news.php` — 管理 UI + GitHub API 連携
- `pages/cms-settings.php` — PAT 等の設定 UI
- `api/cms/cms-config.php` — 設定の読込・保存・暗号化
- `api/cms/github-api.php` — GitHub Contents API クライアント
- `config/cms-config.json` — 設定の保存先（PAT は暗号化）
- `functions/encryption.php` — 暗号化ヘルパー
- `api/auth.php` — cms-news / cms-settings の権限定義
- `config/page-permissions.json` — 同上 (UIから設定可能なオーバーライド)
- `functions/header.php` — サイドバー「HP更新」リンク
