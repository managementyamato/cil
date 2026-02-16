# 借入金返済管理 - Google Apps Script 連携ガイド

このガイドでは、借入金返済管理システムとGoogleスプレッドシートを連携する方法を説明します。

## 概要

システムで入金確認を行うと、スプレッドシートのセルに自動で色を塗る機能を実装できます。

## API仕様

### エンドポイント

```
GET/POST {サーバーURL}/api/integration/loans.php
```

### 認証

ヘッダーに `X-Api-Key` を設定:
```
X-Api-Key: 8ef4d584fbb6a4702d66742120334d0fad57af0404d3f38fbb499fcce562a5d8
```

### GET パラメータ

| パラメータ | 説明 |
|----------|------|
| `action=loans` | 借入先一覧を取得 |
| `action=repayments` | 返済データを取得 |
| `action=confirmed` | 確認済み返済データのみ取得 |
| `action=summary` | 年間サマリーを取得（デフォルト） |
| `year` | 対象年（例: 2026） |
| `month` | 対象月（例: 1） |
| `loan_id` | 借入先ID |

### POST アクション

| action | 説明 |
|--------|------|
| `confirm` | 入金確認ステータスを更新 |
| `upsert_repayment` | 返済データを登録/更新 |
| `add_loan` | 借入先を追加 |
| `bulk_confirm` | 一括確認 |

## Google Apps Script サンプルコード

### 1. 基本設定

```javascript
// 設定
const CONFIG = {
  API_URL: 'https://your-server.com/api/integration/loans.php',
  API_KEY: '8ef4d584fbb6a4702d66742120334d0fad57af0404d3f38fbb499fcce562a5d8',

  // スプレッドシートの設定
  SHEET_NAME: '借入金返済',
  START_ROW: 3,           // データ開始行
  LOAN_NAME_COL: 1,       // 借入先名の列（A列）
  MONTH_START_COL: 2,     // 1月の列（B列）
  CONFIRMED_BG_COLOR: '#d1fae5'  // 確認済みセルの背景色（緑）
};
```

### 2. API呼び出し関数

```javascript
/**
 * APIからデータを取得
 */
function fetchLoansData(action, params) {
  const url = new URL(CONFIG.API_URL);
  url.searchParams.append('action', action);

  for (const key in params) {
    url.searchParams.append(key, params[key]);
  }

  const options = {
    method: 'GET',
    headers: {
      'X-Api-Key': CONFIG.API_KEY,
      'Content-Type': 'application/json'
    },
    muteHttpExceptions: true
  };

  const response = UrlFetchApp.fetch(url.toString(), options);
  return JSON.parse(response.getContentText());
}

/**
 * 入金確認を送信
 */
function confirmRepayment(loanId, year, month, confirmed) {
  const options = {
    method: 'POST',
    headers: {
      'X-Api-Key': CONFIG.API_KEY,
      'Content-Type': 'application/json'
    },
    payload: JSON.stringify({
      action: 'confirm',
      loan_id: loanId,
      year: year,
      month: month,
      confirmed: confirmed
    }),
    muteHttpExceptions: true
  };

  const response = UrlFetchApp.fetch(CONFIG.API_URL, options);
  return JSON.parse(response.getContentText());
}
```

### 3. 確認済みセルに色を塗る

```javascript
/**
 * システムの確認状況をスプレッドシートに反映
 */
function syncConfirmedStatus() {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(CONFIG.SHEET_NAME);
  const year = new Date().getFullYear();

  // 借入先一覧を取得
  const loansResult = fetchLoansData('loans', {});
  if (!loansResult.success) {
    Logger.log('借入先取得エラー: ' + loansResult.error);
    return;
  }

  const loans = loansResult.data;

  // 確認済みデータを取得
  const confirmedResult = fetchLoansData('confirmed', { year: year });
  if (!confirmedResult.success) {
    Logger.log('確認データ取得エラー: ' + confirmedResult.error);
    return;
  }

  const confirmedData = confirmedResult.data;

  // スプレッドシートのデータを取得
  const lastRow = sheet.getLastRow();
  const loanNames = sheet.getRange(CONFIG.START_ROW, CONFIG.LOAN_NAME_COL, lastRow - CONFIG.START_ROW + 1, 1).getValues();

  // 各借入先・各月のセルを処理
  for (let i = 0; i < loanNames.length; i++) {
    const loanName = loanNames[i][0];
    const loan = loans.find(l => l.name === loanName);

    if (!loan) continue;

    for (let month = 1; month <= 12; month++) {
      const row = CONFIG.START_ROW + i;
      const col = CONFIG.MONTH_START_COL + (month - 1);
      const cell = sheet.getRange(row, col);

      // 確認済みかチェック
      const isConfirmed = confirmedData.some(c =>
        c.loan_id === loan.id &&
        c.year == year &&
        c.month == month
      );

      if (isConfirmed) {
        cell.setBackground(CONFIG.CONFIRMED_BG_COLOR);
      } else {
        cell.setBackground(null);  // 背景色をクリア
      }
    }
  }

  Logger.log('同期完了');
}
```

### 4. トリガー設定（定期実行）

```javascript
/**
 * 1時間ごとに自動同期するトリガーを設定
 */
function createHourlyTrigger() {
  // 既存のトリガーを削除
  const triggers = ScriptApp.getProjectTriggers();
  triggers.forEach(trigger => {
    if (trigger.getHandlerFunction() === 'syncConfirmedStatus') {
      ScriptApp.deleteTrigger(trigger);
    }
  });

  // 新しいトリガーを作成
  ScriptApp.newTrigger('syncConfirmedStatus')
    .timeBased()
    .everyHours(1)
    .create();

  Logger.log('1時間ごとのトリガーを設定しました');
}
```

### 5. スプレッドシートからシステムへデータを送信

```javascript
/**
 * スプレッドシートの返済データをシステムに送信
 */
function pushRepaymentData() {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(CONFIG.SHEET_NAME);
  const year = new Date().getFullYear();

  // 借入先一覧を取得
  const loansResult = fetchLoansData('loans', {});
  if (!loansResult.success) {
    SpreadsheetApp.getUi().alert('エラー: ' + loansResult.error);
    return;
  }

  const loans = loansResult.data;
  const lastRow = sheet.getLastRow();

  // スプレッドシートのデータを読み取り
  for (let row = CONFIG.START_ROW; row <= lastRow; row++) {
    const loanName = sheet.getRange(row, CONFIG.LOAN_NAME_COL).getValue();
    const loan = loans.find(l => l.name === loanName);

    if (!loan) continue;

    for (let month = 1; month <= 12; month++) {
      const col = CONFIG.MONTH_START_COL + (month - 1);
      const cellValue = sheet.getRange(row, col).getValue();

      if (cellValue && !isNaN(cellValue)) {
        // 返済データを送信
        const options = {
          method: 'POST',
          headers: {
            'X-Api-Key': CONFIG.API_KEY,
            'Content-Type': 'application/json'
          },
          payload: JSON.stringify({
            action: 'upsert_repayment',
            loan_id: loan.id,
            year: year,
            month: month,
            principal: cellValue  // セルの値を元金として送信
          }),
          muteHttpExceptions: true
        };

        UrlFetchApp.fetch(CONFIG.API_URL, options);
      }
    }
  }

  SpreadsheetApp.getUi().alert('データを送信しました');
}
```

### 6. カスタムメニューを追加

```javascript
/**
 * スプレッドシートを開いた時にメニューを追加
 */
function onOpen() {
  const ui = SpreadsheetApp.getUi();
  ui.createMenu('借入金管理')
    .addItem('確認状況を同期', 'syncConfirmedStatus')
    .addItem('返済データを送信', 'pushRepaymentData')
    .addSeparator()
    .addItem('自動同期を設定', 'createHourlyTrigger')
    .addToUi();
}
```

## 使い方

1. Google Apps Scriptエディタ（スプレッドシート > 拡張機能 > Apps Script）を開く
2. 上記コードをコピーして貼り付け
3. `CONFIG` の値を実際の環境に合わせて変更
4. `onOpen` 関数を実行してメニューを追加
5. スプレッドシートを開き直すと「借入金管理」メニューが表示される

## 注意事項

- APIキーは秘密情報です。共有しないでください
- 本番環境ではHTTPSを使用してください
- 大量データの処理時はAPI制限に注意してください
