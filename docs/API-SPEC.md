# YA管理システム API連携仕様書

## 概要

外部システムとのデータ連携用REST APIです。
基幹システムなどから案件・顧客データを受信し、本システムに登録・更新できます。

## 認証

すべてのAPIリクエストには`X-Api-Key`ヘッダーが必要です。

```
X-Api-Key: YOUR_API_KEY
```

APIキーは管理画面の「API連携設定」から生成できます。

## ベースURL

```
https://your-domain.com/api/integration/
```

## 共通レスポンス形式

### 成功時
```json
{
  "success": true,
  "message": "処理内容の説明",
  "data": { ... }
}
```

### エラー時
```json
{
  "success": false,
  "error": "エラーメッセージ"
}
```

## HTTPステータスコード

| コード | 説明 |
|--------|------|
| 200 | 成功 |
| 400 | リクエストエラー（必須パラメータ不足など） |
| 401 | 認証エラー（APIキー不正） |
| 403 | アクセス拒否（IP制限など） |
| 404 | リソースが見つからない |
| 405 | 許可されていないメソッド |
| 500 | サーバーエラー |

---

## 案件API

### 案件一覧取得

```
GET /api/integration/projects.php
```

#### パラメータ

| パラメータ | 必須 | 説明 |
|-----------|------|------|
| id | × | 内部IDで絞り込み |
| external_id | × | 外部IDで絞り込み |

#### レスポンス例

```json
{
  "success": true,
  "message": "案件一覧を取得しました",
  "data": [
    {
      "id": "pj_20260121120000_a1b2",
      "name": "サンプル案件",
      "customer": "株式会社サンプル",
      "partner": "パートナー名",
      "employees": "担当太郎",
      "product": "商品A",
      "start_date": "2026-01-01",
      "end_date": "2026-03-31",
      "sales": "1000000",
      "cost": "800000",
      "memo": "メモ",
      "external_id": "EXT-001",
      "created_at": "2026-01-21 12:00:00",
      "created_by": "API:基幹システム"
    }
  ]
}
```

### 案件登録・更新

```
POST /api/integration/projects.php
```

#### リクエストボディ（単一登録）

```json
{
  "name": "案件名",
  "customer": "顧客名",
  "partner": "パートナー名",
  "employees": "担当者",
  "product": "商品カテゴリ",
  "start_date": "2026-01-01",
  "end_date": "2026-03-31",
  "sales": "1000000",
  "cost": "800000",
  "memo": "メモ",
  "external_id": "EXT-001"
}
```

#### パラメータ

| パラメータ | 必須 | 説明 |
|-----------|------|------|
| name | ○ | 案件名 |
| customer | × | 顧客名 |
| partner | × | パートナー名 |
| employees | × | 担当者 |
| product | × | 商品カテゴリ |
| start_date | × | 開始日（YYYY-MM-DD） |
| end_date | × | 終了日（YYYY-MM-DD） |
| sales | × | 売上 |
| cost | × | 原価 |
| memo | × | メモ |
| external_id | × | 外部システムのID（これで更新判定） |

#### レスポンス例（新規作成）

```json
{
  "success": true,
  "message": "案件を登録しました",
  "data": {
    "action": "created",
    "id": "pj_20260121120000_a1b2",
    "external_id": "EXT-001"
  }
}
```

#### レスポンス例（更新）

```json
{
  "success": true,
  "message": "案件を更新しました",
  "data": {
    "action": "updated",
    "id": "pj_20260121120000_a1b2",
    "external_id": "EXT-001"
  }
}
```

### 案件一括登録

```
POST /api/integration/projects.php
```

#### リクエストボディ（一括登録）

```json
{
  "projects": [
    {
      "name": "案件1",
      "customer": "顧客A",
      "external_id": "EXT-001"
    },
    {
      "name": "案件2",
      "customer": "顧客B",
      "external_id": "EXT-002"
    }
  ]
}
```

#### レスポンス例

```json
{
  "success": true,
  "message": "案件データを一括処理しました",
  "data": {
    "created": 1,
    "updated": 1,
    "errors": []
  }
}
```

---

## 顧客API

### 顧客一覧取得

```
GET /api/integration/customers.php
```

#### パラメータ

| パラメータ | 必須 | 説明 |
|-----------|------|------|
| id | × | 内部IDで絞り込み |
| external_id | × | 外部IDで絞り込み |

#### レスポンス例

```json
{
  "success": true,
  "message": "顧客一覧を取得しました",
  "data": [
    {
      "id": "cust_20260121120000_c1d2",
      "name": "株式会社サンプル",
      "code": "C001",
      "contact_name": "担当者名",
      "phone": "03-1234-5678",
      "email": "sample@example.com",
      "address": "東京都千代田区...",
      "memo": "メモ",
      "external_id": "CUST-001",
      "created_at": "2026-01-21 12:00:00",
      "created_by": "API:基幹システム"
    }
  ]
}
```

### 顧客登録・更新

```
POST /api/integration/customers.php
```

#### リクエストボディ（単一登録）

```json
{
  "name": "顧客名",
  "code": "顧客コード",
  "contact_name": "担当者名",
  "phone": "03-1234-5678",
  "email": "sample@example.com",
  "address": "住所",
  "memo": "メモ",
  "external_id": "CUST-001"
}
```

#### パラメータ

| パラメータ | 必須 | 説明 |
|-----------|------|------|
| name | ○ | 顧客名 |
| code | × | 顧客コード |
| contact_name | × | 担当者名 |
| phone | × | 電話番号 |
| email | × | メールアドレス |
| address | × | 住所 |
| memo | × | メモ |
| external_id | × | 外部システムのID（これで更新判定） |

### 顧客一括登録

```
POST /api/integration/customers.php
```

#### リクエストボディ（一括登録）

```json
{
  "customers": [
    {
      "name": "顧客A",
      "code": "C001",
      "external_id": "CUST-001"
    },
    {
      "name": "顧客B",
      "code": "C002",
      "external_id": "CUST-002"
    }
  ]
}
```

---

## 使用例

### curlでの案件登録

```bash
curl -X POST "https://your-domain.com/api/integration/projects.php" \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: YOUR_API_KEY" \
  -d '{
    "name": "新規案件",
    "customer": "テスト顧客",
    "external_id": "EXT-001"
  }'
```

### PHPでの連携例

```php
<?php
$apiKey = 'YOUR_API_KEY';
$endpoint = 'https://your-domain.com/api/integration/projects.php';

$data = array(
    'name' => '新規案件',
    'customer' => 'テスト顧客',
    'external_id' => 'EXT-001'
);

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'X-Api-Key: ' . $apiKey
));

$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result['success']) {
    echo "登録成功: " . $result['data']['id'];
} else {
    echo "エラー: " . $result['error'];
}
```

### JavaScriptでの連携例

```javascript
const apiKey = 'YOUR_API_KEY';
const endpoint = 'https://your-domain.com/api/integration/projects.php';

const data = {
  name: '新規案件',
  customer: 'テスト顧客',
  external_id: 'EXT-001'
};

fetch(endpoint, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Api-Key': apiKey
  },
  body: JSON.stringify(data)
})
.then(res => res.json())
.then(result => {
  if (result.success) {
    console.log('登録成功:', result.data.id);
  } else {
    console.error('エラー:', result.error);
  }
});
```

---

## 注意事項

1. **external_id による更新判定**
   - `external_id`が指定されている場合、同じ`external_id`を持つレコードがあれば更新、なければ新規作成します
   - `external_id`を指定しない場合は常に新規作成されます

2. **IP制限**
   - 管理画面で許可IPアドレスを設定できます
   - 設定がない場合は全てのIPからのアクセスを許可します

3. **レート制限**
   - 現在レート制限は設けていませんが、大量リクエストは控えてください

4. **データ整合性**
   - 顧客名や担当者名は文字列として保存されます
   - 既存のマスタデータとの紐付けは行いません
