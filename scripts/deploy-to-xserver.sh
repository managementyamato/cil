#!/bin/bash

# FTP接続情報
FTP_HOST="cil.yamato-basic.com"
FTP_USER="cil@cil.yamato-basic.com"
FTP_PASS="Adyamato8010_"
REMOTE_DIR="/public_html"

echo "=== エックスサーバーへのデプロイ開始 ==="

# mf-config-production.json を mf-config.json としてアップロード
echo "設定ファイルをアップロード中..."
curl -T "mf-config-production.json" --user "$FTP_USER:$FTP_PASS" "ftp://$FTP_HOST/mf-config.json" 2>&1

# PHPファイルをアップロード
echo "PHPファイルをアップロード中..."
for file in *.php; do
    echo "  $file"
    curl -T "$file" --user "$FTP_USER:$FTP_PASS" "ftp://$FTP_HOST/$file" 2>&1 | grep -v "% Total"
done

# CSS/JSファイルをアップロード
echo "CSS/JSファイルをアップロード中..."
for file in *.css *.js; do
    if [ -f "$file" ]; then
        echo "  $file"
        curl -T "$file" --user "$FTP_USER:$FTP_PASS" "ftp://$FTP_HOST/$file" 2>&1 | grep -v "% Total"
    fi
done

# JSONファイルをアップロード (mf-config.jsonは除く)
echo "JSONファイルをアップロード中..."
for file in *.json; do
    if [ "$file" != "mf-config.json" ] && [ "$file" != "mf-config-production.json" ]; then
        echo "  $file"
        curl -T "$file" --user "$FTP_USER:$FTP_PASS" "ftp://$FTP_HOST/$file" 2>&1 | grep -v "% Total"
    fi
done

# 画像ファイルをアップロード
echo "画像ファイルをアップロード中..."
for file in *.png *.jpg *.jpeg *.gif; do
    if [ -f "$file" ]; then
        echo "  $file"
        curl -T "$file" --user "$FTP_USER:$FTP_PASS" "ftp://$FTP_HOST/$file" 2>&1 | grep -v "% Total"
    fi
done

# uploadsディレクトリを作成
echo "uploadsディレクトリを作成中..."
curl --user "$FTP_USER:$FTP_PASS" "ftp://$FTP_HOST/" -Q "MKD uploads" 2>&1 | grep -v "already exists"

# .htaccessがあればアップロード
if [ -f ".htaccess" ]; then
    echo ".htaccessをアップロード中..."
    curl -T ".htaccess" --user "$FTP_USER:$FTP_PASS" "ftp://$FTP_HOST/.htaccess" 2>&1 | grep -v "% Total"
fi

echo ""
echo "=== デプロイ完了 ==="
echo "次のステップ："
echo "1. https://cil.yamato-basic.com/mf-settings.php にアクセス"
echo "2. マネーフォワードのリダイレクトURIを https://cil.yamato-basic.com/mf-callback.php に設定"
echo "3. OAuth認証を実行"
