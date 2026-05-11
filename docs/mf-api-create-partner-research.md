# MF Cloud Invoicing API: POST /partners 調査メモ

> 作成日: 2026-05-11
> 用途: MF未登録取引先を自動作成する機能の開発再開時の参考資料

---

## API仕様（公式情報・確認済み）

### エンドポイント
```
POST https://invoice.moneyforward.com/api/v3/partners
```

### 認証
```
Authorization: Bearer {access_token}
Content-Type: application/json
```

### 必須OAuthスコープ
`mfc/invoice/data.write`

### 最小リクエスト
```json
{ "name": "サンプル取引先" }
```

### オプションフィールド（v2形式・参考）
v2では `{ "partner": { ... } }` のラップ形式だったが、v3 は **ラップなし**。  
オプションフィールド:
- `code` (取引先コード)
- `name_kana`
- `name_suffix` (御中・様 等)
- `zip` (郵便番号)
- `tel`
- `prefecture`, `address1`, `address2`
- `person_name`, `person_title` (担当者)
- `email`
- `memo`

### レスポンス（成功時）
```
HTTP 201 Created
{
  "data": {
    "id": "xxx",
    "code": "...",
    "name": "サンプル取引先",
    "departments": [{ "id": "...", "name": "本社" }],
    ...
  }
}
```

---

## 2026-05-11 試行記録（失敗）

### 試した実装
```php
// api/mf-api.php
public function createPartner(array $data): array {
    return $this->requestCurl('POST', '/partners', $data);
}

// 呼び出し
$resp = $mf->createPartner(['name' => $partnerName]);
```

### 結果
- レスポンス: `{"success":false,"error":"Internal server error"}`
- HTTP 500
- → `set_exception_handler` または `set_error_handler` が捕捉した（詳細不明）

### 可能性のある原因
1. **OAuth スコープ不足**: 既存設定では `mfc/invoice/data.write` を要求しているはずだが、過去に取得した token に含まれていない可能性
2. **同名取引先の既存** (HTTP 422 など) を `Throwable` でうまく捕捉できていなかった
3. **`error_log` などの副作用**で PHP warning が出て set_error_handler が exit してしまった可能性
4. **JSON エンコード時の文字化け**（取引先名に特殊文字が含まれていた場合）
5. **cURL のレスポンスを `json_decode` した結果が null**で、`: array` 戻り値型と一致せず TypeError

---

## 開発再開時の手順

### Step 1: ステージング環境でテスト
1. `docs/cloudflare-tunnel-setup.md` でローカル/ステージング環境を構築
2. ステージング側の MF API 設定で動作確認

### Step 2: 診断エンドポイントを残してデプロイ
```php
// 開発時の診断用
if ($action === 'test_create_partner') {
    header('Content-Type: application/json; charset=utf-8');
    $name = trim($_GET['name'] ?? 'テスト株式会社_' . date('His'));
    try {
        $mf = new MFApiClient();
        $r = $mf->createPartner(['name' => $name]);
        echo json_encode(['success' => true, 'response' => $r],
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } catch (\Throwable $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'class' => get_class($e),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit;
}
```

→ ステージングで実行して、**実際の HTTP ステータスとエラー本文**を取得する。

### Step 3: トークンスコープの確認
1. https://yamato-mgt.com/pages/mf-settings.php で現状の granted_scope を表示
2. `mfc/invoice/data.write` が含まれているか確認
3. 不足していれば、MFで再認証してスコープを追加

### Step 4: 実装の組み込み
- 既に `MFApiClient::createPartner()` メソッドは残置済み（未使用）
- `send_to_mf` のフロー (`api/invoice-requests.php` 内のコメントブロック参照) を再有効化

---

## 関連コミット・ファイル

- `api/mf-api.php` — `createPartner()` / `findPartnersByName()` メソッド残置
- `api/invoice-requests.php` — 自動作成フローを 2026-05-11 17:39 にロールバック
- `pages/invoice-requests.php` — 手動作成手順の案内バナー追加（暫定運用）

---

## ソース

- [MoneyForward Cloud Invoicing API ガイド](https://biz.moneyforward.com/support/invoice/guide/api-guide/a03.html)
- [スタートアップガイド](https://biz.moneyforward.com/support/invoice/guide/api-guide/a04.html)
- [API ドキュメント (公式)](https://invoice.moneyforward.com/docs/api/v3/index.html)
- [Qiita: API検証してみた](https://qiita.com/gatapon/items/e13c41c64be24a69ba78)
- [GitHub: wywy-llc/mf-invoice-api (GAS実装)](https://github.com/wywy-llc/mf-invoice-api)
