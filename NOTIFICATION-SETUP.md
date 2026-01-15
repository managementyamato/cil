# アルコールチェック未完了通知の設定ガイド

1回しかアップロードしていない従業員を検出して、翌日に管理部へメール通知を送る機能の設定方法です。

## 📋 概要

### 機能
- 前日に出勤前・退勤前のいずれか1回しかアップロードしていない従業員を自動検出
- 翌朝に管理部へメールで通知
- テキストメール + HTMLメールの両方を送信

### 通知内容
- 対象日
- 未完了者の一覧（名前、ナンバー、所属、実施状況）
- どちらのチェックが未実施か

---

## 🚀 クイック設定（3ステップ）

### 1. メールアドレスの設定

`check-incomplete-uploads.php` を開いて、以下の2箇所を編集：

```php
// メール送信先（管理部のメールアドレス）
define('ADMIN_EMAIL', 'admin@yamato-agency.com'); // ← ここを変更

// 送信元アドレス
define('FROM_EMAIL', 'noreply@yamato-agency.com'); // ← ここを変更
```

### 2. テスト実行

コマンドラインで実行してテスト：

```bash
php check-incomplete-uploads.php
```

**出力例**：
```
アルコールチェック未完了者チェック開始
=====================================

対象日: 2026-01-14

未完了者: 2名
  - 管理部 (東京エリア)
  - 田中太郎 (大阪エリア)

メール送信中...
✓ メール送信成功: admin@yamato-agency.com

処理完了
```

### 3. 自動実行の設定（Cron）

#### Linuxの場合

```bash
# Cronの編集
crontab -e

# 毎朝8:00に実行
0 8 * * * cd /path/to/web && php check-incomplete-uploads.php >> /var/log/alcohol-check.log 2>&1
```

#### Windowsの場合（タスクスケジューラ）

1. タスクスケジューラを開く
2. 「基本タスクの作成」をクリック
3. 以下のように設定：
   - **名前**: アルコールチェック通知
   - **トリガー**: 毎日、午前8:00
   - **操作**: プログラムの開始
   - **プログラム**: `php.exe` のフルパス
   - **引数**: `check-incomplete-uploads.php` のフルパス
   - **開始**: スクリプトのあるフォルダ

---

## 📧 メール設定

### PHPのメール設定確認

PHPの `mail()` 関数を使用するため、サーバーのメール設定が必要です。

#### php.ini の確認（Windowsの場合）

```ini
[mail function]
SMTP = smtp.example.com
smtp_port = 587
sendmail_from = noreply@yamato-agency.com

; SMTPAuthが必要な場合
smtp_auth = on
smtp_user = your-smtp-username
smtp_pass = your-smtp-password
```

#### Linuxの場合

sendmail または postfix が必要です：

```bash
# sendmailのインストール確認
which sendmail

# インストールされていない場合
sudo apt-get install sendmail  # Debian/Ubuntu
sudo yum install sendmail      # CentOS/RHEL
```

### メールが送信されない場合

#### トラブルシューティング

1. **PHPのエラーログを確認**
   ```bash
   tail -f /var/log/php_errors.log
   ```

2. **sendmailのログを確認**（Linux）
   ```bash
   tail -f /var/log/mail.log
   ```

3. **テストスクリプトで確認**
   ```php
   <?php
   $result = mail('test@example.com', 'Test', 'Test message');
   echo $result ? 'Success' : 'Failed';
   ?>
   ```

#### 代替案：外部メールサービスを使用

PHPMailerなどのライブラリを使ってGmail、SendGridなどを使用することも可能です。

---

## ⚙️ カスタマイズ

### 通知時刻の変更

Cronの時刻を変更：

```bash
# 毎朝9:00に実行
0 9 * * * cd /path/to/web && php check-incomplete-uploads.php

# 平日のみ実行（月〜金）
0 8 * * 1-5 cd /path/to/web && php check-incomplete-uploads.php
```

### 複数の管理者に送信

`check-incomplete-uploads.php` を編集：

```php
// カンマ区切りで複数指定
define('ADMIN_EMAIL', 'admin1@yamato-agency.com, admin2@yamato-agency.com');
```

または、BCCを使用：

```php
$headers .= "Bcc: admin2@yamato-agency.com, admin3@yamato-agency.com\r\n";
```

### 通知条件の変更

#### 完全未実施者（0回）のみ通知

`check-incomplete-uploads.php` の `getPartialCompletions` 関数を修正：

```php
// 1回もアップロードしていない場合のみ
if (!$status['start'] && !$status['end']) {
    $partialCompletions[] = [...];
}
```

#### 2日連続で未完了の場合のみ通知

より高度なロジックが必要になります（別途実装）。

---

## 📊 メール内容のカスタマイズ

### 件名の変更

`check-incomplete-uploads.php` の件名部分を編集：

```php
$subject = "【重要】アルコールチェック未実施者通知 (" . date('Y/m/d', strtotime($yesterday)) . ")";
```

### HTMLメールのデザイン変更

`generateHtmlEmailBody` 関数内のCSSを編集して、色やレイアウトを変更できます。

---

## 🔍 ログとモニタリング

### ログファイルの確認

Cronで実行する場合、ログファイルに出力を保存：

```bash
0 8 * * * cd /path/to/web && php check-incomplete-uploads.php >> /var/log/alcohol-check.log 2>&1
```

ログファイルの内容確認：

```bash
tail -n 50 /var/log/alcohol-check.log
```

### 実行履歴の記録

スクリプトを拡張して、実行結果をデータベースやJSONファイルに記録することも可能です。

---

## ⚠️ 注意事項

### セキュリティ

- メール送信先を厳重に管理してください
- スクリプトファイルに機密情報（パスワード等）を直接書かないでください
- 可能であれば、設定ファイルを別ファイルに分離してください

### パフォーマンス

- 従業員数が多い場合、処理時間がかかる可能性があります
- 必要に応じて、バッチ処理やキューイングを検討してください

### 信頼性

- メール送信の失敗を監視してください
- 定期的にログを確認してください
- テスト環境で十分に動作確認してから本番導入してください

---

## 🛠️ トラブルシューティング

### Q: メールが届かない

**確認事項**:
1. PHPの `mail()` 関数が動作するか確認
2. 送信先メールアドレスが正しいか確認
3. スパムフォルダに入っていないか確認
4. サーバーのメール送信制限を確認

**解決策**:
- PHPMailerなどの外部ライブラリを使用
- SMTPサーバーを経由して送信
- SendGrid、Amazon SES等の外部サービスを利用

### Q: Cronが実行されない

**確認事項**:
1. Cronが正しく設定されているか確認: `crontab -l`
2. PHPのパスが正しいか確認: `which php`
3. スクリプトのパスが正しいか確認
4. 実行権限があるか確認: `chmod +x check-incomplete-uploads.php`

**デバッグ方法**:
```bash
# Cronのログを確認（Ubuntuの場合）
grep CRON /var/log/syslog

# 手動実行してエラー確認
cd /path/to/web && php check-incomplete-uploads.php
```

### Q: 前日のデータが取得できない

**確認事項**:
1. `photo-attendance-data.json` が存在するか確認
2. データ形式が正しいか確認
3. タイムゾーン設定が正しいか確認

---

## 📚 関連ファイル

- `check-incomplete-uploads.php`: メイン通知スクリプト
- `photo-attendance-functions.php`: データ取得関数
- `config.php`: 共通設定

---

## 🚀 次のステップ

1. メールアドレスを設定
2. テスト実行で動作確認
3. Cronまたはタスクスケジューラで自動実行を設定
4. 数日間様子を見て、正常に動作しているか確認

通知機能の導入により、未完了者を早期に発見して対応できるようになります。
