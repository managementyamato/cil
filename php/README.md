# ポータブルPHP配置ガイド

このフォルダにPHPを配置することで、システム環境にPHPをインストールせずに開発サーバーを起動できます。

## セットアップ手順

### 1. PHPをダウンロード

[PHP公式サイト](https://windows.php.net/download/) にアクセスして、以下をダウンロードしてください：

- **PHP 8.3**セクション
- **VS16 x64 Non Thread Safe**の**Zip**ボタンをクリック
- ファイル名例: `php-8.3.x-nts-Win32-vs16-x64.zip`

### 2. PHPを展開して配置

1. ダウンロードしたZIPファイルを展開
2. 展開したフォルダ内の**すべてのファイル**を、この`php/`フォルダにコピー

### 3. 完成後のフォルダ構造

```
cil/
  ├── php/
  │   ├── php.exe          ← PHPの実行ファイル
  │   ├── php.ini-development
  │   ├── php.ini-production
  │   ├── ext/             ← 拡張機能フォルダ
  │   │   ├── php_curl.dll
  │   │   ├── php_mbstring.dll
  │   │   └── ...
  │   ├── LICENSE
  │   ├── README.md        ← このファイル
  │   └── ...（その他PHPファイル）
  ├── start-server.bat
  └── index.php
```

### 4. php.iniの設定（オプション）

より安定した動作のために、以下の設定を推奨します：

1. `php.ini-development`を`php.ini`にコピー（またはリネーム）
2. 以下の拡張機能を有効化（セミコロンを削除）：
   ```ini
   extension=curl
   extension=mbstring
   extension=openssl
   extension=fileinfo
   ```

### 5. 起動確認

エクスプローラーで`start-server.bat`をダブルクリックしてください。

以下のメッセージが表示されれば成功です：
```
[OK] リポジトリ内のポータブルPHPを使用します
PHP 8.3.x (cli) (built: ...)
```

## トラブルシューティング

### バッチファイルが起動できない・警告が出る

GitHubからダウンロードしたファイルがWindowsによってブロックされている可能性があります。

**解決方法:**

1. エクスプローラーで `start-server.bat` を右クリック
2. 「プロパティ」を開く
3. 下部の「セキュリティ」セクションで「許可する」にチェックを入れる
4. 「OK」をクリック
5. 同様に `start-server-with-browser.bat` と `start-dev-livereload.bat` も設定

または、PowerShellで一括解除：
```powershell
cd C:\Git\cil
Get-ChildItem -Path . -Filter *.bat | Unblock-File
```

### php.exeが見つからない

- `php/php.exe`が存在するか確認してください
- ZIPファイルの展開先を間違えていないか確認してください

### 拡張機能のエラー

- `php.ini`ファイルを作成していない場合は、`php.ini-development`を`php.ini`にコピーしてください
- 必要な拡張機能（curl, mbstring, openssl, fileinfo）を有効化してください

### バージョンの確認

コマンドプロンプトで確認できます：
```bash
php\php.exe -v
```

## 注意事項

- このフォルダ（`php/`）は`.gitignore`に含まれているため、Gitにコミットされません
- 各開発者が個別にPHPをダウンロード・配置する必要があります
- チーム全体で同じバージョンのPHP（推奨: 8.3.x）を使用してください
