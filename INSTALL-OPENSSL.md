# OpenSSL DLLのインストール方法

## 現在の問題

PHPでHTTPS通信を行うために必要なOpenSSL拡張のDLLファイルが見つかりません。

## 解決方法（3つの選択肢）

### 🚀 方法1: PowerShellスクリプトで自動インストール（最も簡単）

1. **PowerShellを管理者として起動**
   - スタートメニューで「PowerShell」を検索
   - 右クリック → 「管理者として実行」

2. **スクリプトを実行**
   ```powershell
   cd C:\Claude\master
   Set-ExecutionPolicy -ExecutionPolicy Bypass -Scope Process
   .\download-openssl-dlls.ps1
   ```

3. **完了確認**
   ```cmd
   php.exe -m | findstr /i openssl
   ```
   「openssl」と表示されればOK！

---

### 📦 方法2: 手動ダウンロード（推奨）

1. **PHP 8.2.12をダウンロード**

   ブラウザで以下のURLを開く:
   ```
   https://windows.php.net/downloads/releases/php-8.2.12-Win32-vs16-x64.zip
   ```

2. **ZIPファイルを解凍**

   ダウンロードしたZIPファイルを右クリック → 「すべて展開」

3. **必要なファイルをコピー**

   解凍したフォルダから以下のファイルをコピー:

   **ステップ1:** `ext`フォルダから
   ```
   php_openssl.dll → C:\Claude\master\ext\ にコピー
   ```

   **ステップ2:** ルートフォルダから
   ```
   libcrypto-3-x64.dll → C:\Claude\master\ にコピー
   libssl-3-x64.dll    → C:\Claude\master\ にコピー
   ```

4. **最終的なファイル構成**
   ```
   C:\Claude\master\
   ├── ext\
   │   └── php_openssl.dll    ← 新規追加
   ├── libcrypto-3-x64.dll    ← 新規追加
   ├── libssl-3-x64.dll       ← 新規追加
   ├── php.exe
   └── php.ini
   ```

---

### 🔧 方法3: 既存のPHPからコピー

既にPHPがインストールされている場合:

1. **PHPのインストール先を探す**
   ```cmd
   where php
   ```
   例: `C:\php\php.exe`

2. **以下のファイルをコピー**
   ```
   C:\php\ext\php_openssl.dll    → C:\Claude\master\ext\
   C:\php\libcrypto-3-x64.dll    → C:\Claude\master\
   C:\php\libssl-3-x64.dll       → C:\Claude\master\
   ```

---

## インストール後の確認

### 1. OpenSSLが有効になっているか確認

```cmd
cd C:\Claude\master
php.exe -m | findstr /i openssl
```

**期待される結果:**
```
openssl
```

### 2. MF認証をテスト

```cmd
php.exe test-mf-connection.php
```

**成功時の出力:**
```
✅ mf-config.json が見つかりました
✅ MFApiClientを初期化しました
```

エラーが出なければ成功です！

### 3. ブラウザでテスト

1. サーバーを起動:
   ```cmd
   .\start-server.bat
   ```

2. ブラウザで開く:
   ```
   http://localhost:8000/mf-settings.php
   ```

3. 「OAuth認証を開始」ボタンをクリック

---

## トラブルシューティング

### エラー: "The specified module could not be found"

**原因:** `libcrypto-3-x64.dll`と`libssl-3-x64.dll`が見つからない

**解決策:**
- これらのファイルを`C:\Claude\master\`（`ext`フォルダではなくルート）に配置
- ファイル名が正確に一致しているか確認

### エラー: "Unable to load dynamic library 'openssl'"

**原因:** DLLファイルのバージョンが合わない

**解決策:**
- 必ずPHP 8.2.12のDLLを使用
- 32bit版ではなく64bit版（x64）を使用

### PowerShellスクリプトが実行できない

**エラー:** "このシステムではスクリプトの実行が無効になっているため..."

**解決策:**
```powershell
Set-ExecutionPolicy -ExecutionPolicy Bypass -Scope Process
```

### それでも解決しない場合

1. `FIX-HTTPS-SUPPORT.md`の詳細なガイドを確認
2. GitHubのIssuesで質問
3. 手動インストール（方法2）を試す

---

## 参考リンク

- PHP公式ダウンロード: https://windows.php.net/download/
- PHP Windows用ドキュメント: https://www.php.net/manual/ja/install.windows.php
