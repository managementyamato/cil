# DB_SAVE_MODE 事故履歴と運用ルール

> このドキュメントは過去の重大事故を記録し、再発を防ぐためのものです。
> **絶対に削除しないこと。**

---

## 概要

`config/database.php` の `saveEntity()` には 2 つの保存モードがある:

| モード | 動作 | リスク | 速度 |
|--------|------|-------|------|
| `full_replace` (デフォルト) | DELETE-ALL + INSERT-ALL | 低 | 遅 |
| `upsert` | INSERT...ON DUPLICATE KEY UPDATE + 差分削除 | 高 | 速 |

切替: `.env` の `DB_SAVE_MODE` 環境変数で制御。

**デフォルトは `full_replace`** （安全側）。

---

## 事故 #1: 2026-05-11 ログイン全停止 (約1時間)

### 経緯
- UPSERT 化を本番に投入
- 保存処理で `employees` テーブルが空になった（推定: id 比較ロジックのバグ）
- `getData()` が空データを返す
- `auth.php` でユーザー検索失敗
- **全社員ログイン不可**

### 対処
- `database.php` を旧バージョンに緊急ロールバック
- 復旧まで約1時間

### 教訓
- DB 保存ロジック変更は本番直投入禁止
- 空データを返さないようガード必要

---

## 事故 #2: 2026-05-12 週報送信500エラー

### 経緯
- UPSERT コードが再投入された
- 週報送信時に `weekly_reports` への UPSERT が失敗
- 500 Internal Server Error
- 副次効果として `getData()` 例外伝播 → サイドバー権限が一時的に消失

### 対処
- 本番 `.env` に `DB_SAVE_MODE=full_replace` を追加
- 即時復旧

### 教訓
- env 設定 1 つで挙動が変わる構造は脆弱
- **コード側のデフォルトも `full_replace` にすべき**（defense in depth）
- UPSERT 失敗時の自動フォールバックが必要

---

## 現在の防御策（2026-05-12 実装）

### 1. コードデフォルト変更
```php
// config/database.php
$mode = env('DB_SAVE_MODE', 'full_replace');  // ← 旧: 'upsert'
```

→ `.env` から `DB_SAVE_MODE` が消えても `full_replace` で動く。

### 2. UPSERT 失敗時の自動フォールバック
```php
if ($mode === 'upsert') {
    try {
        self::saveEntityUpsert($pdo, $entity, $data);
    } catch (\Throwable $e) {
        error_log("[Database] UPSERT failed → fallback to full_replace");
        self::saveEntityFullReplace($pdo, $entity, $data);
    }
}
```

→ UPSERT バグがあっても、自動で旧式に切替えて完了する。

### 3. getData() の cascading failure 防止
```php
// DB エラー + data.json 無し → 空データを返さず例外スロー
if ($dbError && data.jsonも読めない) {
    throw new Exception('データ取得失敗');
}
```

→ 「空データで認証が壊れる」状況を防ぐ。

### 4. 本番 .env の明示的フラグ
```env
# 本番 .env
DB_SAVE_MODE=full_replace
```

→ 明示的に安全モードを宣言。

---

## UPSERT を再有効化する場合の手順（厳守）

1. **ステージング環境構築**
   - `docs/cloudflare-tunnel-setup.md` 参照
   - ローカル MySQL + 本番データのスナップショット

2. **全エンティティで保存テスト**
   - projects / employees / customers / mf_invoices / weekly_reports 等
   - 1件編集・複数件編集・全件削除のすべてを検証

3. **1週間以上のステージング運用**
   - 業務シナリオを再現
   - ログイン → 編集 → 送信 → 削除 等の一連の流れ

4. **自動フォールバックの動作確認**
   - 意図的に UPSERT を失敗させて、full_replace に切替わることを確認

5. **本番投入は「営業時間外」かつ「即ロールバック可能」**
   - 平日19時以降や土日深夜
   - .env の DB_SAVE_MODE を upsert に変更（コードは触らない）
   - 問題発生時は 30 秒で `.env` を full_replace に戻せる体制

---

## 関連ファイル

- `config/database.php` — saveEntity / saveEntityUpsert / saveEntityFullReplace
- `config/config.php` — getData / saveData
- `CLAUDE.md` — トップレベルの警告
- 本番 `.env` — DB_SAVE_MODE 設定
- `docs/cloudflare-tunnel-setup.md` — ステージング環境構築手順

---

## 連絡先（事故再発時）

緊急ロールバック手順（30秒）:
1. FTP で本番 `/.env` を取得
2. `DB_SAVE_MODE=full_replace` の行を確認 or 追加
3. 本番に書き戻す
4. ブラウザでリロード

これで `full_replace` モードに戻り、データ書き込みが安定する。
