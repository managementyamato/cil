# チーム用セットアップガイド - YA管理一覧システム

このガイドに従って、Gitフォルダから開発環境を構築してください。

---

## 📋 概要

最新ブランチ `claude/develop-qLvWs-MDPbl` には以下の変更が含まれています：

### ✅ 実装済みの変更

1. **左サイドバーの整理**
   - 削除: 「報告」「顧客マスタ」「パートナーマスタ」「商品マスタ」
   - 残存: 「分析」「一覧」「プロジェクト管理」「損益」「従業員マスタ」「ユーザー管理」

2. **PJ番号入力の制限**
   - 数字と大文字Pのみ入力可能
   - 小文字pは自動で大文字Pに変換

3. **未使用ファイルの整理**
   - `deprecated/` フォルダに移動済み（削除はしていません）

---

## 🚀 セットアップ手順

### ステップ1: リポジトリをクローン

PowerShellまたはコマンドプロンプトを開いて以下を実行：

```powershell
# Gitフォルダに移動（なければ作成）
cd C:\
mkdir Git
cd Git

# リポジトリをクローン
git clone https://github.com/managementyamato/cil.git
cd cil

# 作業ブランチに切り替え
git checkout claude/develop-qLvWs-MDPbl
```

---

### ステップ2: PHPをセットアップ

**重要**: このプロジェクトは**XAMPPのPHPコマンドをリポジトリ内にコピー**して使用します。

#### 2-1. XAMPPからPHPをコピー

PowerShellで以下を実行：

```powershell
# C:\Git\cil にいることを確認
cd C:\Git\cil

# phpフォルダを作成
mkdir php

# XAMPPのPHPをコピー
xcopy C:\xampp\php\*.* php\ /E /I /Y
```

#### 2-2. コピーが成功したか確認

```powershell
dir php\php.exe
```

`php.exe` が表示されればOK！

---

### ステップ3: サーバーを起動

#### 方法A: 手動でコマンド実行（確実）

```powershell
cd C:\Git\cil
php\php.exe -S localhost:8000
```

以下のメッセージが表示されれば成功：
```
[日時] PHP 8.x.x Development Server (http://localhost:8000) started
```

**このウィンドウは閉じないでください！**

#### 方法B: バッチファイルで実行（予定）

エクスプローラーで `C:\Git\cil` を開いて：
- `start-server-with-browser.bat` をダブルクリック

**注意**: 現在バッチファイルに問題があるため、方法Aを推奨します。

---

### ステップ4: ブラウザでアクセス

ブラウザで以下のURLを開く：
```
http://localhost:8000
```

ログイン画面が表示されます。

---

## ✅ 変更内容の確認

### 1. 左サイドバーの確認

ログイン後、左サイドバーに以下の**6つのメニューのみ**表示されていること：

✅ **表示されるメニュー：**
- 分析
- 一覧
- プロジェクト管理
- 損益
- 従業員マスタ
- ユーザー管理（管理者のみ）

❌ **削除されたメニュー（表示されないこと）：**
- ~~報告~~
- ~~顧客マスタ~~
- ~~パートナーマスタ~~
- ~~商品マスタ~~

---

### 2. PJ番号入力制限の確認

1. 「一覧」ページに移動
2. 「新規報告」ボタンをクリック
3. **PJ番号フィールド**で以下をテスト：

| 入力 | 期待される結果 |
|------|--------------|
| `abc` | 何も入力できない（または `P` のみ残る） |
| `p123` | 自動で `P123` に変換される |
| `12P34` | そのまま `12P34` として入力できる |
| `!@#` | 何も入力できない |

---

## 🛠️ トラブルシューティング

### Q: `php.exe` が見つからない

**確認事項:**
1. XAMPPがインストールされているか？
   ```powershell
   dir C:\xampp\php\php.exe
   ```

2. コピーが成功したか？
   ```powershell
   dir C:\Git\cil\php\php.exe
   ```

**解決方法:**
- XAMPPがない場合: [XAMPPをダウンロード](https://www.apachefriends.org/jp/index.html)してインストール
- コピーに失敗した場合: ステップ2-1を再実行

---

### Q: ブラウザで「接続が拒否されました」エラー

**原因**: サーバーが起動していません

**確認方法:**
```powershell
netstat -ano | findstr :8000
```
何も表示されなければサーバーは停止しています。

**解決方法:**
```powershell
cd C:\Git\cil
php\php.exe -S localhost:8000
```

---

### Q: 「Address already in use」エラー

**原因**: ポート8000が既に使用されています

**解決方法:**
既存のサーバーを停止：
```powershell
taskkill /F /IM php.exe
```

その後、再度起動：
```powershell
php\php.exe -S localhost:8000
```

---

## 📂 フォルダ構成

正しくセットアップできていれば、以下のフォルダ構成になります：

```
C:\Git\cil\
  ├── php/                    ← XAMPPからコピーしたPHP
  │   ├── php.exe
  │   ├── php.ini
  │   └── ext/
  ├── deprecated/             ← 未使用マスタファイル
  │   ├── customers.php
  │   ├── partners.php
  │   └── products.php
  ├── start-server.bat        ← サーバー起動用
  ├── start-server-with-browser.bat
  ├── index.php
  ├── header.php              ← サイドバー変更済み
  ├── report.php              ← PJ番号制限追加済み
  └── ...
```

---

## 🎯 起動の流れまとめ

1. PowerShellを開く
2. `cd C:\Git\cil` でフォルダに移動
3. `php\php.exe -S localhost:8000` でサーバー起動
4. ブラウザで `http://localhost:8000` を開く
5. サーバーを停止する場合は `Ctrl+C`

---

## 📞 サポート

問題が解決しない場合は、以下の情報を添えて連絡してください：

- エラーメッセージのスクリーンショット
- 実行したコマンドと結果
- 使用しているWindowsのバージョン

---

**最終更新**: 2026-01-13
**ブランチ**: `claude/develop-qLvWs-MDPbl`
