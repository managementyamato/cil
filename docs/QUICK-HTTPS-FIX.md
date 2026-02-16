# HTTPS通信を有効にする（簡単な手順）

## 現在の状況

マネーフォワードクラウドAPIとの認証時に以下のエラーが発生：
```
file_get_contents(): Unable to find the wrapper "https"
```

## 必要なファイル

PHPでHTTPS通信を行うには、以下の4つのファイルが必要です：

### 方法1: 公式サイトからダウンロード（推奨）

1. **PHP 8.2.12をダウンロード**

   以下のリンクをクリック:
   https://windows.php.net/downloads/releases/php-8.2.12-Win32-vs16-x64.zip

2. **ZIPファイルを解凍**

3. **必要なファイルをコピー**

   解凍したフォルダから以下のファイルを探してコピー:

   ```
   📁 [解凍フォルダ]/ext/
      └── php_openssl.dll  → C:\Claude\master\ext\ にコピー
      └── php_curl.dll     → C:\Claude\master\ext\ にコピー

   📁 [解凍フォルダ]/
      └── libcrypto-3-x64.dll → C:\Claude\master\ にコピー
      └── libssl-3-x64.dll    → C:\Claude\master\ にコピー
   ```

4. **結果の確認**

   最終的なファイル構成:
   ```
   C:\Claude\master\
   ├── ext\
   │   ├── php_openssl.dll ← 新規追加
   │   └── php_curl.dll    ← 新規追加
   ├── libcrypto-3-x64.dll ← 新規追加
   ├── libssl-3-x64.dll    ← 新規追加
   ├── php.exe
   ├── php8ts.dll
   └── php.ini
   ```

5. **動作確認**

   コマンドプロンプトで実行:
   ```cmd
   cd C:\Claude\master
   php.exe -m | findstr /i "openssl curl"
   ```

   以下のように表示されればOK:
   ```
   curl
   openssl
   ```

6. **MF認証をテスト**

   ```cmd
   php.exe test-mf-connection.php
   ```

   エラーが出なければ成功です！

## 方法2: 既存のPHPからコピー

PCに既にPHPがインストールされている場合:

1. PHPのインストール先を探す（通常 `C:\php\` など）

2. 以下のファイルをコピー:
   - `C:\php\ext\php_openssl.dll` → `C:\Claude\master\ext\`
   - `C:\php\ext\php_curl.dll` → `C:\Claude\master\ext\`
   - `C:\php\libcrypto-3-x64.dll` → `C:\Claude\master\`
   - `C:\php\libssl-3-x64.dll` → `C:\Claude\master\`

## トラブルシューティング

### エラー: "The specified module could not be found"

→ `libcrypto-3-x64.dll`と`libssl-3-x64.dll`を`C:\Claude\master\`（extフォルダではなくルート）に配置してください

### エラー: DLLファイルが見つからない

→ ダウンロードしたZIPファイルが正しいバージョン（8.2.12 x64 Thread Safe）か確認してください

### OpenSSLは表示されるがエラーが出る

→ PHPのバージョンとDLLのバージョンが一致していることを確認してください

## 参考リンク

- PHP公式ダウンロード: https://windows.php.net/download/
- FIX-HTTPS-SUPPORT.md（詳細版）
