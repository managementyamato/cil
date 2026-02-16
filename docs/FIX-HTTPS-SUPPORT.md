# HTTPS通信サポートの有効化

## 問題

PHPでHTTPS通信を行う際に以下のエラーが発生：
```
file_get_contents(): Unable to find the wrapper "https" - did you forget to enable it when you configured PHP?
```

## 原因

OpenSSL拡張がインストールされていないため、HTTPS通信ができません。

## 解決方法

### 方法1: 完全版PHPをダウンロード（推奨）

1. [PHP for Windows](https://windows.php.net/download/)から以下をダウンロード:
   - **PHP 8.2 (8.2.x) VC15 x64 Thread Safe** (ZIPファイル)
   - 例: `php-8.2.12-Win32-vs16-x64.zip`

2. ダウンロードしたZIPファイルを解凍

3. 以下のファイルを `C:\Claude\master\` にコピー:
   - `ext\php_openssl.dll`
   - `ext\php_curl.dll`
   - `libcrypto-3-x64.dll` (ルートフォルダから)
   - `libssl-3-x64.dll` (ルートフォルダから)

4. `ext`フォルダがない場合は作成:
   ```bash
   mkdir ext
   ```

5. DLLファイルを配置:
   ```
   C:\Claude\master\
   ├── ext\
   │   ├── php_openssl.dll
   │   └── php_curl.dll
   ├── libcrypto-3-x64.dll
   └── libssl-3-x64.dll
   ```

6. `php.ini`で拡張を有効化（既に設定されている場合は確認のみ）:
   ```ini
   extension_dir="C:\Claude\master\ext"
   extension=openssl
   extension=curl
   ```

### 方法2: 既存のPHPインストールからコピー

既にPHPがインストールされている場合:

1. 既存のPHPフォルダを探す（例: `C:\php\`）

2. 以下のファイルをコピー:
   ```
   C:\php\ext\php_openssl.dll → C:\Claude\master\ext\php_openssl.dll
   C:\php\ext\php_curl.dll → C:\Claude\master\ext\php_curl.dll
   C:\php\libcrypto-3-x64.dll → C:\Claude\master\libcrypto-3-x64.dll
   C:\php\libssl-3-x64.dll → C:\Claude\master\libssl-3-x64.dll
   ```

## 設定後の確認

1. PHPモジュールを確認:
   ```bash
   ./php.exe -m | grep -i openssl
   ./php.exe -m | grep -i curl
   ```

   以下が表示されればOK:
   ```
   curl
   openssl
   ```

2. 接続テストを実行:
   ```bash
   ./php.exe test-mf-connection.php
   ```

## トラブルシューティング

### エラー: "The specified module could not be found"

**原因**: `libcrypto-3-x64.dll`と`libssl-3-x64.dll`がない

**解決策**: これらのDLLファイルを`C:\Claude\master\`に配置

### エラー: "Unable to load dynamic library"

**原因**: DLLファイルのバージョンが合わない、またはパスが間違っている

**解決策**:
1. PHPバージョンと一致するDLLファイルを使用
2. `extension_dir`のパスを確認
3. ファイル名が正しいか確認（`php_openssl.dll`など）

### OpenSSLは動作するがcURLが動作しない

**原因**: cURLのDLL依存関係が不足

**解決策**: 以下の追加DLLが必要な場合があります:
- `libssh2.dll`
- `nghttp2.dll`

これらもPHPの完全版ZIPに含まれています。
