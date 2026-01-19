# ローカル開発環境セットアップ

## 瞬時プレビューのための開発環境構築

### 方法1：GitHubからクローン（推奨）

```bash
# 1. XAMPPのhtdocsに移動
cd C:\xampp\htdocs

# 2. リポジトリをクローン
git clone https://github.com/managementyamato/cli.git trouble-management
cd trouble-management

# 3. ブランチをチェックアウト
git checkout claude/setup-trouble-management-ersIX
```

その後：
- XAMPPのApacheを起動
- ブラウザで `http://localhost/trouble-management/` を開く
- ファイルを編集したら、ブラウザを更新（F5）するだけでプレビュー可能

### 方法2：ZIPダウンロード

定期的にZIPをダウンロードして更新：

```bash
# このリポジトリで実行
./sync-to-local.sh
```

その後、`trouble-management-latest.zip` をダウンロードしてXAMPPに配置

## データファイルの扱い

初回セットアップ後、以下のファイルは上書きしないでください：
- `data.json` - トラブルデータ
- `users.json` - ユーザー情報

## 開発フロー

### Gitを使う場合（推奨）
1. ローカルで編集
2. `git add .`
3. `git commit -m "変更内容"`
4. `git push origin claude/setup-trouble-management-ersIX`

### ZIPを使う場合
1. Claude Code環境で編集
2. `./sync-to-local.sh` を実行
3. ZIPをダウンロード
4. XAMPPに上書き（data.jsonとusers.jsonは保護）
