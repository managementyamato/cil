# 機能削除チェックリスト

機能を完全に取り除く際の手順とチェックリスト。
2026-05-13 に 4機能 (案件管理 / 価格表 / 請求金額分析 / プロジェクト管理スプシ連携) を一括削除した実例を元に作成。

---

## 全体フロー

```
1. 並列調査 (Explore agent で影響範囲をマッピング)
   ↓
2. 削除可・グレーゾーン区分け
   ↓
3. 直前コミット (チェックポイント)
   ↓
4. 削除実行 (機械的順序で)
   ↓
5. 横断確認 (grep で残り参照ゼロを確認)
   ↓
6. PHP構文チェック
   ↓
7. changelog 記録 → コミット
   ↓
8. 本番デプロイ
   ↓
9. 本番のFTP実在確認 (rm が効いたか)
```

---

## ステップ1: 並列調査 (推奨)

複数機能を一気に削除する場合、`Explore` agent で並列調査するのが効率的。

```
削除対象機能ごとに以下を調査:
1. ページ本体 (pages/*.php)
2. API endpoint (api/*.php)
3. functions/ ヘルパー
4. data.json / DBスキーマ (data-schema.php, create-tables.sql)
5. config/ 設定ファイル
6. サイドバー (functions/header.php) / 設定 (pages/settings.php) / 権限 (api/auth.php, user-permissions.php, page-permissions.json)
7. 他ページからの参照・リンク
8. JSファイルでの参照
9. テスト (tests/, scripts/)
10. ドキュメント (docs/)
11. mockups/ (HTMLモック)
12. cache/, snapshots/, logs/ で機能専用のもの

「削除可」「グレーゾーン (他機能と共有)」の2区分でレポート
```

並列調査の例 (4機能同時):

```javascript
// 4つを並列で起動 (1メッセージ内で複数 Agent tool 呼び出し)
Agent({ subagent_type: "Explore", description: "機能Aの調査", prompt: "..." })
Agent({ subagent_type: "Explore", description: "機能Bの調査", prompt: "..." })
Agent({ subagent_type: "Explore", description: "機能Cの調査", prompt: "..." })
Agent({ subagent_type: "Explore", description: "機能Dの調査", prompt: "..." })
```

調査時間は単発と並列で大差なく、合計 1〜2分。

---

## ステップ2: 必須チェックリスト

調査結果を元に、削除対象を以下の表に当てはめて漏れを防ぐ。

### A. ページ削除 (`pages/<feature>.php`)

| # | 場所 | チェック内容 |
|---|---|---|
| 1 | `pages/<feature>.php` | 物理削除 |
| 2 | `public_html/pages/<feature>.php` | 物理削除 (auto-deployで再生成されるが先に消すと安全) |
| 3 | `api/auth.php` | `$defaultPagePermissions` 配列から該当エントリ削除 |
| 4 | `public_html/api/auth.php` | 同上 (本番ミラー) |
| 5 | `pages/user-permissions.php` | `$defaultPagePermissions` から削除 |
| 6 | `pages/user-permissions.php` | `$pageLabels` から削除 |
| 7 | `public_html/pages/user-permissions.php` | 上記2箇所を本番ミラーでも削除 |
| 8 | `config/page-permissions.json` | 該当キーを削除 + `updated_at` 更新 |
| 9 | `functions/header.php` | サイドバーリンク (`<?php if (hasPermission(...))?>...<a href="/pages/...">...</a><?php endif; ?>`) 削除 |
| 10 | `functions/header.php` | `$_ag` (アクティブグループ) 判定リストから削除 (例: `in_array($_cp, ['master.php', ...])`) |
| 11 | `pages/settings.php` | 設定画面カード (`$settingTypes`, `$directLinks`) に登録されていれば削除 |
| 12 | 他ページ | 該当ページへのリンクを `grep -rn "<feature>.php"` で探して削除 |
| 13 | `auto-deploy.ps1` | `$productionRemovals` に `/pages/<feature>.php` を追加 |

### B. API endpoint 削除 (`api/<feature>.php`)

| # | 場所 | チェック内容 |
|---|---|---|
| 1 | `api/<feature>.php` | 物理削除 |
| 2 | `public_html/api/<feature>.php` | 物理削除 |
| 3 | 呼び出し元 JS | `fetch('<feature>.php')` を `grep -rn '<feature>.php'` で探して削除 |
| 4 | `auto-deploy.ps1` | `$productionRemovals` に `/api/<feature>.php` を追加 |

### C. データエンティティ削除 (例: `deals`, `price_tiers`)

| # | 場所 | チェック内容 |
|---|---|---|
| 1 | `functions/data-schema.php` | `'<entity>' => [...]` ブロック全体削除 |
| 2 | `config/database.php` | `$tableEntities` 配列から `'<entity>'` 削除 |
| 3 | `scripts/create-tables.sql` | `DROP TABLE IF EXISTS` + `CREATE TABLE` ブロック削除 |
| 4 | `saveData()` 呼び出し箇所 | `saveData($data, ['<entity>'])` の第2引数から削除 |
| 5 | `getData()` 参照箇所 | `$data['<entity>']` への直接参照を全て削除 |
| 6 | 本番MySQL | `DROP TABLE <entity>` を手動実行 (誤削除防止のため自動化しない) |

### D. テーブルカラム削除 (例: `synced_from`)

| # | 場所 | チェック内容 |
|---|---|---|
| 1 | `functions/data-schema.php` | `'<column>' => [...]` 行を削除 |
| 2 | `scripts/create-tables.sql` | `\`<column>\` ... DEFAULT NULL,` 行を削除 |
| 3 | コード内参照 | `$record['<column>']` 等を全削除 |
| 4 | 本番MySQL | `ALTER TABLE <table> DROP COLUMN <column>` 手動実行 |

### E. 統合ページの一部機能削除 (例: reports-hub.php の "deal" タブ)

| # | 場所 | チェック内容 |
|---|---|---|
| 1 | 該当タブの HTML | `<!-- TAB N: 機能名 -->` 〜 次のタブまで削除 |
| 2 | タブボタン | `<button class="hub-tab" data-tab="<key>">...</button>` 削除 |
| 3 | モーダル | 該当モーダル HTML 全削除 |
| 4 | CSS | 機能専用クラスを削除 (`.deal-stage[data-s="..."]` 等) |
| 5 | JS関数 | `loadXxx()`, `renderXxx()`, イベントリスナー削除 |
| 6 | JS変数 | `let allXxx = []; let xxxFilter = ...;` 削除 |
| 7 | 初期化呼び出し | `loadXxx().catch(...)` 削除 |
| 8 | バックエンド API | `case 'xxx':` ブロック削除 (GET list / POST CRUD 両方) |
| 9 | メール通知 | `case 'xxx_create':` ブロック削除 |
| 10 | ファイル先頭コメント | "○機能を統合" の機能数を更新 |

---

## ステップ3: 直前コミット (チェックポイント)

破壊的変更の前に必ずコミットを切る。何かあった時に `git revert` で戻せるようにする。

```bash
git status
git add <変更したファイル群>
git commit -m "<前回までの作業内容>"
```

---

## ステップ4: 削除実行の順序

機械的に以下の順で進めると漏れにくい:

```
1. ページ.php 物理削除
2. API endpoint 物理削除
3. JS関数・モーダル・UI削除
4. データスキーマ削除 (data-schema.php, database.php, create-tables.sql)
5. 権限定義削除 (auth.php × 2, user-permissions.php × 2, page-permissions.json)
6. サイドバー・設定画面リンク削除 (header.php, settings.php)
7. auto-deploy.ps1 の $productionRemovals に追加
```

---

## ステップ5: 横断確認

削除後、参照漏れがないか `grep` で全件確認:

```bash
grep -rn "<feature>\|<feature>-api\|<entity>\|<function_name>" \
    api/ pages/ functions/ config/ js/ tests/ 2>/dev/null | \
    grep -v public_html | head -30
```

出力ゼロが理想。ヒットしたら個別判断 (本当に残すべきか、消し忘れか)。

---

## ステップ6: PHP構文チェック

```powershell
$php = 'C:\xampp\php\php.exe'
$files = @(  # 修正したPHPファイル全部
    'C:\Claude\master\pages\xxx.php',
    'C:\Claude\master\api\xxx.php',
    ...
)
foreach ($f in $files) {
    & $php -l $f 2>&1 | Select-String -NotMatch 'already loaded'
}
```

`No syntax errors detected` を全件確認。

---

## ステップ7: changelog 記録 + コミット

`docs/changelog.md` に1行追記:

```markdown
| 2026-MM-DD | <N>機能を削除: ①〜 ②〜 ③〜。横断的に auth/user-permissions/page-permissions/header.php/data-schema/database.php/create-tables.sql/auto-deploy.ps1 から該当エントリを除去 | 修正: `<files>` ; 削除: `<files>` | - |
```

その後コミット:

```bash
git add -u api/ pages/ functions/ config/ docs/ scripts/ auto-deploy.ps1
git status --short  # 確認
git commit -m "$(cat <<'EOF'
<N>機能を削除: <機能リスト>

## 削除した機能
1. <機能A>: 削除内容
2. ...

## 横断的な変更
- ...

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## ステップ8: 本番デプロイ

```powershell
cd C:\Claude\master
powershell -File auto-deploy.ps1 -Force -SkipTests
```

「Deploy FAILED」が出る場合: `rm /pages/xxx.php` で「存在しないファイル」を削除しようとしてエラーになるパターン。**実害はゼロ** (synchronize での upload は成功している)。

---

## ステップ9: 本番のFTP実在確認

削除が本番に反映されたか確認 (`rm` が効いたか):

```powershell
# WinSCP で本番に接続 → ls で 6ファイルの不存在を確認
$pass = (Get-Content "C:\Claude\master\.ftp-credentials" | Where-Object { $_ -match '^FTP_PASS=(.+)$' }) -replace 'FTP_PASS=', ''
$winscp = "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com"
$script = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch continue
ls /pages/<feature>.php
ls /api/<feature>.php
exit
"@
$script | Out-File -Encoding ASCII "$env:TEMP\check.txt"
& $winscp /script="$env:TEMP\check.txt" 2>&1 | Select-String "No such file"
```

`No such file or directory` が全件出ればOK = 削除成功。

---

## 落とし穴 (実例から学んだ Gotchas)

### 1. xcopy /E /I /Y は削除を同期しない

`auto-deploy.ps1` の xcopy ステップは追加・更新のみ。`master/pages/xxx.php` を削除しても `public_html/pages/xxx.php` には残る。

**対策**:
- 削除実行後、自分で `rm public_html/pages/<feature>.php` も実行する
- または `$productionRemovals` に頼って本番側で削除 (xcopy → synchronize upload → 本番に再アップロード→ rm で削除、の流れになるが結果は同じ)

### 2. `$productionRemovals` は「ファイル無い」エラーで FAILED 扱いになる

`auto-deploy.ps1` の `rm /pages/<feature>.php` で「既に存在しない」場合 WinSCP は exit code 1 を返す。スクリプトは「Deploy FAILED」と表示するが、**実際の転送・削除は成功している**。

**確認方法**: FTP で `ls` してファイルが本当に消えているかを目視確認。

### 3. 統合ページ (例: reports-hub.php) の一部機能削除は範囲が広い

UI (HTML) + JS + CSS + イベントリスナー + バックエンド API の `case 'xxx':` + メール通知の `case 'xxx_create':` まで波及。`grep -c "<feature>"` で件数を見てから着手すると見通しが立つ。

### 4. CSS の同名クラスに注意

例: `reports-hub.php` の `.lead-status[data-s="商談中"]` は **リードのステータス値**であり、**deal (商談) 機能とは別**。文字列で機械的に消すと別機能を壊す。**「機能名」と「ステータス名」の混同に注意**。

### 5. JS関数の循環参照

例: `loadDeals()` を削除したら、`loadDeals()` を呼んでいる初期化コード (`loadDeals().catch(...)`) も削除。grep で関数名を検索して呼び出し元を漏らさない。

### 6. config/ の JSON ファイルは gitignore されていることがある

例: `config/spreadsheet-sources.json`, `config/cms-config.json` 等。本番に元から配置されているシークレットや環境固有データはコミット対象外。**ローカルで編集してもデプロイされない**ので、本番側は別途手動メンテが必要。

### 7. MySQL テーブルは自動 DROP しない

`scripts/create-tables.sql` から DROP+CREATE ブロックを削除しても、それは「次回テーブル初期化時の差分」でしかない。**既存の本番テーブルは残る**。手動で `DROP TABLE <name>` を phpMyAdmin から実行する必要あり。

**自動化しない理由**: 誤って必要なテーブルを消すリスクが大きい。意図的に手動操作にする。

---

## 実例: 2026-05-13 の削除作業

参考までに、このチェックリストを元にした実作業の規模:

| 項目 | 数値 |
|---|---|
| 削除した機能 | 4 (案件管理 / 価格表 / 請求金額分析 / プロジェクト管理スプシ連携) |
| 物理削除したファイル | 7 (pages/×3 + api/×3 + mockups/×1) |
| 修正したファイル | 12 |
| 削除した行数 | 4,860 |
| 追加した行数 | 18 (主に削除コメント・notes) |
| 並列調査時間 | 約2分 (Explore agent 4本同時) |
| 削除作業時間 | 約15分 (Edit 多数) |
| 検証・デプロイ時間 | 約5分 |
| **総作業時間** | **約22分** |

---

## まとめ

「**並列調査 → 区分け → コミット → 機械的削除 → grep確認 → デプロイ → FTP実在確認**」の流れで進めると、

- 削除漏れが起きにくい
- 安全にロールバック可能
- 本番でも確実に消える

このパターンを使う時は、まずこのドキュメントを開いてからチェックリストに従う。
