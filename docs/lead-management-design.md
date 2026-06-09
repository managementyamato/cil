# リード管理 v2 設計ドキュメント

**ステータス**: ドラフト (レビュー待ち)
**作成日**: 2026-06-05
**参照 UI**: `https://sales.yamato-compass.com/contacts` (Yamato Compass Sales)

---

## 1. なぜやるか

### 1.1 課題
- 現状 `pages/sales-tools/tabs/leads.php` はフラットなリード CRUD のみ。**ステータス遷移の履歴が残らない**ため「いつ商談中になったか」「誰がいつ成約に動かしたか」が追えない
- 「名刺交換した相手」と「具体的な案件 (リード)」と「取引のある顧客」が同じテーブルに混在 (`leads`) しがちで、データ的に区別がない
- 営業会議で「このリード、最後にいつ動いた?」を毎回手で調べている
- 顧客マスタ (AM管理) と リード が完全に分断され、リードが「成約」になっても顧客マスタに反映する仕組みがない

### 1.2 ゴール
1. **3層構造で情報を整理**: 名刺 (素データ) → リード (案件) → 顧客 (取引先)
2. **タイムライン**: リードのステータス遷移と手動メモを時系列で記録・閲覧
3. **昇格フロー**: 名刺→リード、リード→顧客への昇格を 1 クリックで
4. **営業会議のフォロー対象** が一発で見えるリスト (例: 14日以上動いていない商談中)

### 1.3 非ゴール (今回スコープ外)
- 名刺画像 OCR の AI 精度改善 (既存実装維持)
- 商談記録ページの全体ビュー (Yamato Compass の "商談記録" メニュー相当) → Phase 4 以降
- 営業会議の自動アジェンダ生成

---

## 2. 設計原則

### 2.1 既存資産の扱い
| 既存 | 新版での扱い |
|---|---|
| `pages/sales-tools/tabs/leads.php` | UI 全面刷新 (3層タブ + タイムライン) |
| `api/leads-api.php` | エンドポイント拡張 (action 増やす) |
| `leads` テーブル | カラム追加 (担当者・確度・取引形態・期限等) |
| `customers` テーブル (AM管理) | スキーマ変更なし。リード→顧客昇格時にレコード作成のみ |
| `pages/sales-tools/tabs/customers.php` (AM 管理) | スキーマ変更なし。今回はここに手をつけない |
| 名刺 OCR (sales-tools/_scripts.php の leadScanBtn) | 出力先を business_cards テーブルに変更。OCR 処理自体は維持 |
| `pages/reports-hub/tabs/lead.php` (非公開) | 今回作る v2 と機能重複するため Phase 4 で正式に削除 |

### 2.2 「3層を分ける」判断基準
**名刺 (`business_cards`)**: 名刺交換 1 回 = 1 レコード。同じ人と複数回交換すれば複数枚 (履歴として残す)。
**リード (`leads`)**: 「具体的な案件・商談」。1 つの名刺から複数のリードが派生し得る (例: 同じ担当者から異なる現場の話が同時並行)。
**顧客 (`customers`)**: 取引のある「会社」。1 顧客に対し複数のリード・複数の名刺が紐付く。

### 2.3 DB_SAVE_MODE への影響なし
価格表 v2 と同じ方針 — 新テーブルは `saveEntity()` を経由せず、専用リポジトリ層 (`functions/lead-repository.php`) で直接 PDO。

---

## 3. データモデル

### 3.1 ER 図 (概念)

```
business_cards (名刺)
   │
   │  (任意で promoted_lead_id)
   ↓
leads (リード)  ←──┐
   │              │
   │ (任意で       │  lead_activities (タイムライン)
   │  customer_id) │  - status_change
   ↓              │  - manual_note
customers (顧客) ─┘  - promotion (名刺→リード, リード→顧客)
                     - meeting (商談記録)
```

### 3.2 テーブル定義

#### `business_cards` (新規)
名刺交換のローデータ。

| カラム | 型 | 説明 |
|---|---|---|
| id | varchar(36) PK | UUID |
| company_name | varchar(255) | 会社名 (OCR) |
| person_name | varchar(255) | 氏名 (OCR) |
| title | varchar(255) | 役職 |
| department | varchar(255) | 部署 |
| phone | varchar(64) | 電話 |
| mobile | varchar(64) | 携帯 |
| email | varchar(255) | メール |
| fax | varchar(64) | FAX |
| website | varchar(512) | URL |
| address | text | 住所 |
| business_card_image_path | varchar(512) | 名刺画像パス (既存 leads から移行) |
| exchanged_at | date | 名刺交換日 |
| ocr_source | varchar(32) | `scan` / `csv_import` / `manual` |
| ocr_confidence | tinyint | 0-100 (OCR信頼度) |
| registered_by | varchar(255) | 登録者メール |
| promoted_lead_id | varchar(36) | このカードから昇格したリードID (NULL可) |
| notes | text | メモ |
| created_at, updated_at, deleted_at, deleted_by | datetime / varchar |

INDEX (company_name), (person_name), (exchanged_at), (registered_by), (deleted_at)

#### `leads` (既存テーブルに **カラム追加**)
※ ALTER で追加するだけで既存データは残す。

| 追加カラム | 型 | 説明 |
|---|---|---|
| customer_id | varchar(36) | 紐付く顧客 (NULL可・成約時にセット) |
| business_card_id | varchar(36) | 起点の名刺 (NULL可) |
| dealer_name | varchar(255) | ディーラー名 (仲介業者) |
| dealer_branch | varchar(255) | ディーラー営業所 |
| end_user_company | varchar(255) | エンドユーザー / ゼネコン |
| site_name | varchar(255) | 現場名 |
| prefecture | varchar(64) | 都道府県 |
| product_name | varchar(128) | 商談中の製品 |
| product_size | varchar(64) | サイズ |
| transaction_type | varchar(32) | `sale` / `rental_12m` / `rental_24m` / `rental_long` 等 |
| confidence | tinyint | 確度 1-5 (Yamato Compass 風: 1=低 ~ 5=高) |
| quote_status | varchar(32) | `未` / `発行前` / `発行済` / `承認待ち` 等 |
| expected_close_date | date | 想定クロージング日 (営業会議のフォロー対象判定) |
| last_activity_at | datetime | 最後のタイムライン記録日時 (索引高速化用) |
| assigned_to | varchar(255) | 担当者メール (既存 `am` カラムとは別 — `am` は AM担当の意味で残置) |

既存の `status` カラム (ENUM 相当 `新規/接触済/商談中/成約/失注`) は維持。

INDEX 追加: (customer_id), (assigned_to), (status, last_activity_at), (confidence DESC, status)

#### `lead_activities` (新規) — タイムライン
リードの履歴 (ステータス遷移・手動メモ・商談記録) を統一管理。

| カラム | 型 | 説明 |
|---|---|---|
| id | bigint PK AUTO_INCREMENT | |
| lead_id | varchar(36) | 対象リード |
| type | enum('status_change','manual_note','promotion','meeting','quote','system') | 種別 |
| from_status | varchar(32) | (status_change の場合) 変更前 |
| to_status | varchar(32) | (status_change の場合) 変更後 |
| title | varchar(255) | 例: 「商談中 → 成約」「定期訪問」「見積発行」 |
| body | text | 詳細メモ |
| occurred_at | datetime | 出来事の発生日時 (手動メモは入力日時) |
| created_by | varchar(255) | 操作したユーザーのメール |
| created_by_name | varchar(255) | 表示用キャッシュ |
| created_at | datetime | レコード作成日時 |
| deleted_at | datetime | 論理削除 |
| deleted_by | varchar(255) | |

INDEX (lead_id, occurred_at DESC), (type), (created_by), (deleted_at)

**運用ルール**:
- リードの status 変更があったら API 側で自動的に `type=status_change` の activity を INSERT
- 「商談しました」「電話しました」等の手動エントリは `type=manual_note` / `type=meeting`
- 名刺→リード 昇格時は `type=promotion` で `title='名刺から昇格'`
- リード→顧客 昇格時は `type=promotion` で `title='顧客マスタに昇格'`

#### `customers` (既存テーブル・スキーマ変更なし)
既存の AM 管理用テーブルをそのまま使う。リード→顧客 昇格時:
1. `customers` に INSERT (`source='lead_promotion'`)
2. `leads.customer_id` に新規顧客の id をセット
3. `lead_activities` に `type=promotion title='顧客マスタに昇格'` を INSERT

### 3.3 マイグレーション戦略
- **新規 2 テーブル** (`business_cards`, `lead_activities`): `CREATE TABLE IF NOT EXISTS` で安全
- **leads 拡張**: 全カラム `ALTER TABLE leads ADD COLUMN ... DEFAULT NULL` で後方互換。本番投入前に必ず実行
- **既存 leads のうち、名刺画像があるレコード**: Phase 4 で `business_cards` に分離移行 (1 回限りのスクリプト)

---

## 4. UI 構成

### 4.1 ページ配置

| パス | 用途 |
|---|---|
| `pages/sales-tools.php` | 既存 |
| └ `tabs/leads.php` (3 サブタブに再構成) | 名刺 / リード / 顧客昇格対象 を 1 タブ内で切替 |

または **新しいハブとして独立**:

| パス | 用途 |
|---|---|
| `pages/leads-hub.php` (新規) | 名刺 / リード / (顧客は master-hub の既存 customers タブへ誘導) |

**推奨**: 既存 `sales-tools.php` の `leads` タブを 3 サブタブ構成に再構成 (Yamato Compass の `/contacts` と同じ)。新規ページを増やさずに UI 統一性を保つ。
顧客タブは Yamato Compass を真似て同じ場所に出すが、表示するレコードは既存 `customers` テーブルのもの。

### 4.2 画面構成 (sales-tools.php?tab=leads)

```
営業ツール → [リード] タブ
┌──────────────────────────────────────────────┐
│ [サブタブ] 名刺 (N) | リード (M) | 顧客 (K)    │
├──────────────────────────────────────────────┤
│ [リード サブタブを選択中]                       │
│                                                │
│ 🏆 リード登録数ランキング                       │
│  🥇 西井 74件 🥈 鈴木 65件 🥉 浅井 35件 ...    │
│                                                │
│ 一覧                                           │
│ ┌─[絞り込み]──────────────────────────────┐ │
│ │ ステータス▾ 担当者▾ 確度▾  並び順▾ 検索🔍│ │
│ └────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────┐ │
│ │ ◉ セフテック株式会社  鈴木           [継続中]││
│ │   モニすけ OB3.0Lite / 岐阜県 / 大野        ││
│ │   レンタル(12-23ヶ月) / 確度: 2             ││
│ │   最終更新: 3日前 (商談中→継続中)           ││
│ └─────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────┐ │
│ │ ◉ ヤマト食品  浅井                  [成約]  ││
│ │   電子黒板 / 東京都 / 渋谷                  ││
│ │   ⚠️ 14日動きなし — 営業会議でフォロー対象 ││
│ └─────────────────────────────────────────┘ │
└──────────────────────────────────────────────┘

[リード 1件クリック → 詳細サイドパネル]
┌──────────────────────────────────────────────┐
│ セフテック株式会社 — 大野                       │
│ [継続中▾] [編集] [顧客に昇格] [削除]            │
├──────────────────────────────────────────────┤
│ [タブ] 概要 | タイムライン | 関連名刺          │
│                                                │
│ [タイムライン サブタブ]                         │
│  + 手動でメモを追加                             │
│ ┌──────────────────────────────────────────┐ │
│ │ ● 2026-06-05 14:30  西井さんがステータス変更 ││
│ │   商談中 → 継続中                          ││
│ │                                             ││
│ │ ● 2026-06-03 10:00  西井さん [meeting]      ││
│ │   定期訪問。岡山の実績はご存知でした。       ││
│ │                                             ││
│ │ ● 2026-05-22 09:15  鈴木さんがリードを作成   ││
│ │   名刺「大野さん」から昇格                   ││
│ └──────────────────────────────────────────┘ │
└──────────────────────────────────────────────┘
```

### 4.3 名刺 サブタブ
- 既存の名刺スキャン UI を維持
- 一覧は `business_cards` テーブルから (今は leads テーブルに混在)
- 各カードに「リード昇格」ボタン → リード作成モーダル (名刺情報を初期値として転記)

### 4.4 顧客 サブタブ
- `customers` テーブルから取引中・取引実績のあるレコードを表示
- 「リードから昇格」を起点に作られたレコードと、既存の AM 管理由来のレコードを区別表示
- 編集は `master-hub.php#price` 経由 (新規追加はここからもできる)

---

## 5. API 仕様

`/api/leads-api.php` を拡張。`type` パラメータで対象テーブル切替:

| action | 対象 | 概要 |
|---|---|---|
| `list` (type=card / lead / customer) | 各テーブル | フィルタ付き一覧 |
| `get` (type=card / lead / customer) | 各テーブル | 1件詳細 |
| `create` (type=card / lead) | business_cards / leads | 作成 |
| `update` (type=card / lead) | business_cards / leads | 更新。lead の status 変更時は自動で activity を INSERT |
| `delete` (type=card / lead) | business_cards / leads | 論理削除 (admin) |
| `promote_card_to_lead` | leads | 名刺をリードに昇格 |
| `promote_lead_to_customer` | customers | リードを顧客に昇格 |
| `list_activities` | lead_activities | リードの履歴一覧 |
| `add_activity` | lead_activities | 手動メモ・商談記録の追加 |
| `delete_activity` | lead_activities | 履歴の論理削除 (admin) |
| `ranking` | leads | 担当者別リード登録数ランキング |

### 5.1 ステータス変更時の auto-activity
`update` で `status` フィールドが変わった場合、API 側で自動 INSERT:
```php
$activity = [
    'lead_id'     => $leadId,
    'type'        => 'status_change',
    'from_status' => $oldStatus,
    'to_status'   => $newStatus,
    'title'       => sprintf('%s → %s', $oldStatus, $newStatus),
    'body'        => $request['change_reason'] ?? null,
    'occurred_at' => date('Y-m-d H:i:s'),
    'created_by'  => $currentUser,
];
```

これにより **「ステータス変更を記録し忘れる」事故が起きない**。

---

## 6. 営業会議フォロー対象の自動抽出

リード一覧の上部に「営業会議でフォローすべきリード」専用ビューを設置:

**抽出条件** (OR):
- `status IN ('商談中','接触済')` AND `last_activity_at < NOW() - INTERVAL 14 DAY`
- `expected_close_date < CURDATE()` AND `status NOT IN ('成約','失注')`

→ 各カードに ⚠️ バッジ表示。並び順は `last_activity_at ASC` (古いほど上)。

---

## 7. フェーズ分け

| Phase | 内容 | 工数感 | 価値 |
|---|---|---|---|
| **0 (本ドキュメント)** | 設計レビュー・確定 | 30分 | 失敗コスト削減 |
| **1** | スキーマ (新規2 + ALTER) + リポジトリ + API (status変更時 auto-activity) + リード詳細にタイムライン表示 | 1日 | **ステータス遷移が自動記録される (ゴール 2)** |
| **2** | 名刺 (business_cards) サブタブ + 名刺→リード昇格 | 半日 | 3層の入口完成 |
| **3** | 顧客サブタブ + リード→顧客昇格 + ランキング + フォロー対象自動抽出 | 1日 | **営業会議で使える形に (ゴール 4)** |
| **4** | 既存 leads データ移行 (画像あり → business_cards 分離) + 旧 reports-hub/tabs/lead.php 削除 | 半日 | 二重管理解消 |
| **5 (将来)** | 商談記録の全体ビュー (Yamato Compass の `/meetings` 相当) | 半日 | 横断ビューが欲しい場合のみ |

各 Phase は独立して本番投入可能。Phase 1 だけでも「ステータス変更履歴が残る」価値は出る。

---

## 8. リスクと対策

| リスク | 対策 |
|---|---|
| 既存 leads データに新カラム導入時の互換性 | 全カラム `DEFAULT NULL`。NULL 許容で既存レコード壊さない |
| 名刺と既存 leads (source=business_card) の重複データ | Phase 4 の移行スクリプトで `source=business_card` の leads を `business_cards` に移し、leads 側は削除 (or `business_card_id` で紐付け) |
| タイムラインが膨大になる懸念 | `INDEX (lead_id, occurred_at DESC)` で高速化。詳細パネルでは最新 50 件を遅延ロード、「もっと見る」で追加読み込み |
| ステータス変更時の自動記録で API パフォーマンス低下 | INSERT 1 件のみ。問題なし。トランザクション不要 (失敗してもリード更新は成功させる) |
| 名刺/リード/顧客 で同一会社名が重複入力される | UI 上で「同じ会社名のレコードがあります」サジェスト (Phase 3) |
| 顧客マスタとの整合性 | リード→顧客昇格時、既に customers にあれば既存レコードに紐付ける確認モーダル (Phase 3) |

---

## 9. レビュー観点 (このタイミングで決めたいこと)

1. **3層構造の導入で合意か?** (名刺 / リード / 顧客の役割定義)
2. **既存 leads テーブルへの ALTER** で進めて OK か? (新テーブルにせず既存拡張)
3. **顧客サブタブ** は AM 管理 (`customers` テーブル) と統合する案で OK か?
4. **配置**: `sales-tools.php?tab=leads` の中に 3 サブタブを置く案で OK か? (新規ページにしない)
5. **タイムラインの auto-activity** はステータス変更だけで OK か? (他の更新 — 担当変更・確度変更等も記録する?)
6. **Phase 1 から実装着手** で OK か?

---

## 10. 関連ドキュメント

- [`docs/architecture.md`](architecture.md)
- [`docs/patterns.md`](patterns.md)
- [`docs/price-list-design.md`](price-list-design.md) — 同じ「DB_SAVE_MODE 非侵襲」「専用リポジトリ層」パターンを採用
- 参照 UI: `https://sales.yamato-compass.com/contacts` (Yamato Compass Sales)
