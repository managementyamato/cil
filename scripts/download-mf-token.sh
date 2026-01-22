#!/bin/bash

# FTP接続情報
FTP_HOST="cil.yamato-basic.com"
FTP_USER="cil@cil.yamato-basic.com"
FTP_PASS="Adyamato8010_"

echo "=== 本番環境からMFトークンをダウンロード ==="

# 本番環境のmf-config.jsonをダウンロード
echo "ダウンロード中..."
curl --user "$FTP_USER:$FTP_PASS" "ftp://$FTP_HOST/mf-config.json" -o "mf-config.json" 2>&1 | grep -v "% Total"

if [ $? -eq 0 ]; then
    echo ""
    echo "✓ mf-config.jsonをダウンロードしました"
    echo ""
    echo "内容確認："
    cat mf-config.json | head -10
    echo ""

    # access_tokenの有無をチェック
    if grep -q "access_token" mf-config.json; then
        echo "✓ アクセストークンが含まれています"
    else
        echo "! アクセストークンがありません"
        echo "  本番環境 (https://cil.yamato-basic.com/mf-settings.php) でOAuth認証を完了してください"
    fi
else
    echo ""
    echo "✗ ダウンロードに失敗しました"
fi
