# 価格表 v2 設計ドキュメント

**ステータス**: ドラフト (レビュー待ち)
**作成日**: 2026-06-05
**前提**: 旧 sales-tools の価格表タブ + Google Sheets ベースの実装は 2026-06-05 に廃止済み

---

## 1. 何を解決するか

### 1.1 現状の課題

| 問題 | 内容 |
|---|---|
| 営業が社長に毎回確認している | 顧客ランク・取引形態・サイズの組み合わせで「これいくらでしたっけ?」を毎回聞いている。営業のスピードが落ち、社長の時間も消費する |
| 旧価格表タブは複雑すぎた | Google Sheets のシート構造に UI を合わせていたため、シート毎に微妙に違う構造を吸収する JS が 1800 行に肥大化 |
| 見積書を作るときに情報がバラバラ | 価格表で単価を見て、別のシートで送料を確認し、別のところでケーブル代を引いてくる、と縦割りで非効率 |

### 1.2 ゴール

**営業が価格表ページだけを見て、社長に確認せずに顧客に出せる見積書を作成できる。**

サブゴール:
- 製品 × サイズ × 顧客ランク × 取引形態 (販売/レンタル) を 1 画面で見渡せる
- オプション (設置費・送料・ケーブル類) を選んで単価合計まで自動算出
- 出力した見積書は社長確認なしでそのまま客先に出せる品質

### 1.3 非ゴール (今回スコープ外)

- 値引き申請ワークフロー (既に reports-hub の 値引き申請 タブに実装済み)
- 過去見積の履歴管理 (sales-tools の history タブで別途検討)
- 顧客マスタとの自動連動 (顧客ランクは別途取得する想定)

---

## 2. 設計原則

### 2.1 「データ → UI」ではなく「UI → データ」

旧版は「Google Sheets の構造をそのまま JSON 化 → UI で解釈」だったため、シート構造が変わるたびに JS が壊れた。

今回は **見積書作成フローから逆算してデータ構造を決める**:

```
営業の実際の動作:
  「P社 (ランクA) に UTM-P4 (81インチ) をレンタルで」
  ↓
  必要な情報:
   - 製品: UTM-P4
   - サイズ: 81インチ
   - 顧客ランク: A
   - 取引形態: レンタル
   - オプション: 設置費 + 送料 + HDMIケーブル (5m)
  ↓
  価格表が返す:
   - 月額レンタル (ランクA) = ¥X円
   - 設置費 = ¥Y円
   - 送料 (都内) = ¥Z円
   - HDMIケーブル (5m) = ¥W円
   - 合計 = ¥(X+Y+Z+W) /月初月
```

この情報構造を **そのまま正規化** したテーブル設計にする。

### 2.2 既存資産との関係

- **削除**: 旧 `pages/price-master.php` (管理画面) と `api/price-master.php` (API) — 旧 JSON 構造に依存
- **削除**: `data/product-prices.json` (退避済バックアップは残す)
- **保持**: `config/sales-tools-products.json` (製品定義マスタは新システムでも初期データソースとして利用可能)
- **新規**: master-hub の「価格表マスタ」タブを置き換える形で新管理画面を実装
- **新規**: 営業向けの閲覧/見積モードを別ページとして実装

---

## 3. データモデル (MySQL スキーマ)

### 3.1 ER 図 (概念)

```
products (製品マスタ)
   │
   └─< product_variants (バリアント = 製品 × サイズ等の組み合わせ)
            │
            └─< price_rules (バリアント × ランク × 取引形態 → 単価)

options (オプションマスタ: 設置費・送料・ケーブル類)
   │
   └─< option_tiers (オプションの段階制価格: サイズ別・距離別等)
```

### 3.2 テーブル定義

#### `pl_products` — 製品マスタ
| カラム | 型 | 説明 |
|---|---|---|
| id | varchar(64) PK | 例: `monitarou-utm-p4` (kebab-case) |
| code | varchar(64) UNIQUE | 業務コード (任意) |
| name | varchar(255) | 例: `モニたろう UTM-P4` |
| category | varchar(64) | 例: `LEDビジョン` / `LCD` |
| description | text | 補足説明 (1〜2行) |
| display_order | int | UI 並び順 |
| is_active | tinyint(1) | 表示する/隠す |
| created_at, updated_at, deleted_at | timestamp |

#### `pl_product_variants` — バリアント (サイズ・解像度等)
| カラム | 型 | 説明 |
|---|---|---|
| id | varchar(64) PK | 例: `monitarou-utm-p4-81in` |
| product_id | varchar(64) FK | `pl_products.id` |
| size_label | varchar(64) | 例: `81インチ` (UIに表示) |
| size_inch | decimal(6,2) | 並び替え・検索用 (e.g. 81.00) |
| resolution | varchar(32) | 例: `1600x1280` |
| screen_area_m2 | decimal(6,3) | 例: 2.048 |
| attributes_json | json | その他属性 (シリーズ名・ピッチ等) |
| display_order | int |
| is_active | tinyint(1) |
| created_at, updated_at, deleted_at | timestamp |

UNIQUE INDEX (product_id, size_label)

#### `pl_price_rules` — 価格ルール (本体価格)
| カラム | 型 | 説明 |
|---|---|---|
| id | bigint PK AUTO_INCREMENT |  |
| variant_id | varchar(64) FK | `pl_product_variants.id` |
| customer_rank | enum('S','A','B') | 顧客ランク |
| transaction_type | enum('sale','rental') | 販売 or レンタル |
| price_label | varchar(64) | 例: `販売価格` / `月額` / `初月` |
| amount | int | 円 (整数。税抜) |
| notes | varchar(255) | 補足 (期間制約・条件等) |
| display_order | int | 同 variant 内での並び順 |
| created_at, updated_at, deleted_at | timestamp |

UNIQUE INDEX (variant_id, customer_rank, transaction_type, price_label)

→ 1 バリアントに対し最大 3 ランク × 2 形態 × 複数ラベル (月額/初月/設置時)。
   現状データの例だと S/A/B × sale/rental × {販売価格, ①月額, ②月額...} で 1 行あたり 12〜18 レコード。

#### `pl_options` — オプションマスタ
| カラム | 型 | 説明 |
|---|---|---|
| id | varchar(64) PK | 例: `installation-led` |
| name | varchar(255) | 例: `LED 設置費` |
| category | enum('installation','shipping','cable','accessory','other') | |
| pricing_mode | enum('fixed','per_unit','tiered_by_size','tiered_by_distance','tiered_by_length') | 価格の決まり方 |
| unit_label | varchar(32) | 例: `1台` / `1m` / `1配送` |
| base_amount | int | pricing_mode=fixed の場合の固定額 |
| description | text | 用途・条件 |
| display_order | int |
| is_active | tinyint(1) |
| created_at, updated_at, deleted_at | timestamp |

#### `pl_option_tiers` — オプションの段階制価格
`pricing_mode` が `tiered_by_*` のとき、複数行で段階を表現。

| カラム | 型 | 説明 |
|---|---|---|
| id | bigint PK AUTO_INCREMENT |  |
| option_id | varchar(64) FK | `pl_options.id` |
| tier_label | varchar(128) | 例: `50インチ未満` / `都内(23区)` / `1〜5m` |
| min_value | decimal(10,3) NULL | 該当範囲の下限 (インチ・km・m等) |
| max_value | decimal(10,3) NULL | 該当範囲の上限 |
| amount | int | この段階の金額 |
| display_order | int |
| created_at, updated_at | timestamp |

INDEX (option_id, display_order)

#### `pl_quote_drafts` (Phase 3 で追加 - 今回は仮設計のみ)
営業が見積書を作成中の状態を保存するテーブル。詳細は Phase 3 で詰める。

### 3.3 重要な制約・運用ルール

- **論理削除**: 全テーブルで `deleted_at` を採用。CLAUDE.md の「物理削除禁止」ルールに従う
- **CSRF + 権限**: 編集系 API は全て `verifyCsrfToken()` + admin チェック
- **DB_SAVE_MODE への影響なし**: 価格表用のレコードは `saveEntity()` を介さず、専用の `lib/price-list-repository.php` (新規) で個別 INSERT/UPDATE する。既存の DB_SAVE_MODE=full_replace 運用を一切変更しない (CLAUDE.md の 🚨 最重要 #0)
- **金額は税抜き整数 (円)**: 小数点なし。消費税は見積書生成時に外税で算出

---

## 4. UI 構成

### 4.1 ページ構成

| パス | 用途 | 権限 |
|---|---|---|
| `pages/price-hub.php` | 価格表ハブ (新規) — タブで切り替え | view: sales / edit: admin |
| └ 閲覧タブ | 営業向け閲覧モード (高速検索) | sales |
| └ 見積タブ | 製品・サイズ・ランク・オプションを選んで合計算出 | sales |
| └ マスタ管理タブ | 製品・バリアント・価格・オプションの CRUD | admin のみ |

または **master-hub に「価格表」タブとして組み込み** (master-hub.php の `$MASTER_TABS` に追加)。
推奨は **master-hub 統合** — UI 統一性が高く、新ページを増やさない。

### 4.2 画面イメージ (master-hub 統合案)

```
master-hub.php?tab=price-list
┌─────────────────────────────────────────────────┐
│ マスタ                                            │
│ [マスタ管理] [価格表] [製品マスタ] [外部リンク] ... │
├─────────────────────────────────────────────────┤
│ 価格表マスタ                                       │
│ ┌──────────────────────────────────────────────┐│
│ │ [サブタブ] 閲覧 | 見積 | 管理(admin)            ││
│ └──────────────────────────────────────────────┘│
│                                                  │
│ [閲覧モード]                                       │
│ 検索: [______________]  ランク: [S] [A] [B]       │
│ 形態: [販売] [レンタル]                            │
│                                                  │
│ ┌──────────────────────────────────────────────┐│
│ │ モニたろう UTM-P4                              ││
│ │ ┌──────┬──────────┬────────┬────────┬────────┐││
│ │ │サイズ│解像度    │ S 月額 │ A 月額 │ B 月額 │││
│ │ ├──────┼──────────┼────────┼────────┼────────┤││
│ │ │81in │1600x1280 │ ¥74,600│ ¥82,000│ ¥98,000│││
│ │ │108in│1920x1600 │¥125,000│¥135,000│¥160,000│││
│ │ └──────┴──────────┴────────┴────────┴────────┘││
│ └──────────────────────────────────────────────┘│
│                                                  │
│ [見積モード] (右側カート風サイドパネル)               │
│ 製品: UTM-P4 / 81in / ランクA / レンタル          │
│   月額: ¥82,000                                   │
│ + 設置費 (LED 80in未満): ¥35,000                  │
│ + 送料 (都内): ¥12,000                            │
│ + HDMIケーブル 5m × 2: ¥3,600                     │
│ ─────────────                                    │
│ 初月合計: ¥132,600 (税抜)                          │
│ 翌月以降: ¥82,000 (税抜)                           │
│ [見積書を作成] (Phase 3)                           │
└─────────────────────────────────────────────────┘
```

### 4.3 共通UIパターン

- ハブシェル (`_hub-shell-top.php`) に統合 → 既存ハブと同じ見た目
- ボタン系: 既存 `.btn-primary` / `.btn-secondary` を使う
- フォーム: `.form-input` / `.form-group` 統一
- onclick 属性禁止 → data-action + addEventListener (今日確立したパターン)
- 外部リンクは app.js が自動で _blank 化

---

## 5. API 仕様

`/api/price-list.php` (1 ファイル) に集約。`action` パラメータで分岐。

### 5.1 閲覧系 (sales 以上)

| action | パラメータ | 戻り値 |
|---|---|---|
| `list_products` | (なし) | アクティブな製品一覧 (id, name, category) |
| `get_product` | `product_id` | 製品詳細 + バリアント一覧 + 価格行列 (rank × transaction_type) |
| `list_options` | `category?` | オプション一覧 (段階制の場合は tiers も含む) |

### 5.2 見積系 (sales 以上, Phase 2)

| action | パラメータ | 戻り値 |
|---|---|---|
| `calc_quote` | `variant_id, rank, transaction_type, options[]` | 各行の小計 + 合計 |
| `save_draft` (Phase 3) | quote data | draft_id |
| `list_drafts` (Phase 3) | (なし) | 自分の下書き一覧 |

### 5.3 管理系 (admin のみ)

| action | パラメータ | 説明 |
|---|---|---|
| `create_product` / `update_product` / `delete_product` | 製品データ | 製品CRUD (delete は論理削除) |
| `create_variant` / `update_variant` / `delete_variant` | バリアントデータ | バリアントCRUD |
| `upsert_price_rule` / `delete_price_rule` | 価格行 | 価格ルールCRUD |
| `create_option` / `update_option` / `delete_option` | オプションデータ | オプションCRUD |
| `upsert_option_tier` / `delete_option_tier` | 段階データ | 段階制CRUD |

### 5.4 インポート系 (admin のみ, Phase 1 完了後)

| action | パラメータ | 説明 |
|---|---|---|
| `import_csv` | アップロードされた CSV | 製品+バリアント+価格ルールを一括取り込み |
| `import_preview` | CSV | 取り込み前に差分を表示 (新規/更新/削除の件数) |

---

## 6. データインポート方針

### 6.1 標準フォーマット (CSV)

**1 行 = 1 価格レコード** にすることで、Excel/スプレッドシートで誰でも編集可能:

```csv
product_id,product_name,category,variant_id,size_label,size_inch,resolution,customer_rank,transaction_type,price_label,amount,notes
monitarou-utm-p4,モニたろう UTM-P4,LEDビジョン,monitarou-utm-p4-81in,81インチ,81,1600x1280,S,sale,販売価格,1584000,
monitarou-utm-p4,モニたろう UTM-P4,LEDビジョン,monitarou-utm-p4-81in,81インチ,81,1600x1280,S,rental,月額,74600,
monitarou-utm-p4,モニたろう UTM-P4,LEDビジョン,monitarou-utm-p4-81in,81インチ,81,1600x1280,A,rental,月額,82000,
...
```

製品/バリアントは「初出時に自動作成」。重複行は UPSERT (キー: variant_id + customer_rank + transaction_type + price_label)。

### 6.2 オプション CSV (別ファイル)

```csv
option_id,name,category,pricing_mode,unit_label,base_amount,tier_label,tier_min,tier_max,tier_amount
installation-led,LED 設置費,installation,tiered_by_size,1台,,50インチ未満,,50,25000
installation-led,LED 設置費,installation,tiered_by_size,1台,,50-80インチ,50,80,35000
installation-led,LED 設置費,installation,tiered_by_size,1台,,80インチ以上,80,,50000
shipping-tokyo,送料 (都内),shipping,fixed,1配送,12000,,,,,
hdmi-cable,HDMIケーブル,cable,tiered_by_length,1本,,1m,,1,800
hdmi-cable,HDMIケーブル,cable,tiered_by_length,1本,,2m,1,2,1200
...
```

### 6.3 初期データ移行

旧 `data/product-prices.json.backup.20260519_150100` から、上記 CSV へ手動変換 (1 回限り)。
変換スクリプト `scripts/migrate-legacy-prices.php` (新規) を 1 回だけ実行。

### 6.4 セーフティ

- 取り込み前に **DRY-RUN モード** で差分プレビュー (件数のみ)
- 全件 UPDATE/INSERT する場合でも、`pl_price_rules.deleted_at IS NULL` 行のスナップショットを `backups/price-list/YYYYMMDD_HHMMSS.json` に保存
- インポート完了後、自動でアラート: 「X 件作成 / Y 件更新 / Z 件未変更」

---

## 7. フェーズ分け

| Phase | 内容 | 工数感 | 価値 |
|---|---|---|---|
| **0 (本ドキュメント)** | 設計レビュー・確定 | 30分(レビュー) | 失敗コスト削減 |
| **1** | スキーマ作成 + マスタ管理画面 (CRUD) + 閲覧モード | 1日 | 営業が単価を引ける |
| **2** | オプションマスタ + 見積モード (合計算出のみ) | 半日 | 営業が合計金額を出せる |
| **3** | 見積書 PDF/Excel 出力 + 下書き保存 | 1日 | 社長確認撤廃 (ゴール達成) |
| **4** | レガシーデータ移行 + 旧 price-master.php / api 撤去 | 半日 | 二重管理解消 |

各 Phase は **独立して本番投入可能** にする。Phase 1 だけでも単価照会としては使える。

---

## 8. リスクと対策

| リスク | 対策 |
|---|---|
| 既存の DB_SAVE_MODE が壊れる | 価格表専用テーブルは `saveEntity()` を経由しない。専用リポジトリ層で直接 SQL |
| マスタ画面が複雑化して 1800 行 JS 再来 | ハブシェルで CRUD 画面を分割 (製品マスタ / バリアント / 価格 / オプション の 4 サブタブ) |
| 初期データ移行ミス | DRY-RUN + バックアップ自動保存。本番投入前にステージング (or 別 DB) で検証 |
| 営業が見積書品質を信頼できない | Phase 3 完了時に社長が 5 件サンプル確認してから本番運用に切り替え |
| CSV の文字コード問題 | UTF-8 BOM 付きで保存・読込。Excel 日本語版でも文字化けしない |

---

## 9. レビュー観点 (この時点で決めたいこと)

1. **データモデルの粒度** は妥当か?
   - 特に `price_label` を文字列で持つ vs 別テーブル化 → 文字列で持つことで柔軟性優先 (例: ①月額、②月額、初月といった任意ラベルが多い)
2. **オプションの分類** (5種: installation / shipping / cable / accessory / other) で十分か?
3. **見積書出力フォーマット** は Phase 3 で詰めるが、PDF か Excel かどちらが主?
4. **master-hub 統合 vs 独立ページ (price-hub.php)** はどちらにする?
5. **Phase 1 ~ 4 のうち、最初に作るのは Phase 1 でいいか?**

---

## 10. 関連ドキュメント

- [`docs/architecture.md`](architecture.md) — 全体アーキテクチャ
- [`docs/patterns.md`](patterns.md) — UI/API パターン
- [`docs/feature-removal-checklist.md`](feature-removal-checklist.md) — Phase 4 で旧 price-master 撤去時に参照
- `CLAUDE.md` 🚨 最重要 #0 — DB_SAVE_MODE は触らない (上記 §3.3 で対応済み)
