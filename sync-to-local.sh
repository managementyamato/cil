#!/bin/bash
# ローカルマシンにファイルを同期するためのスクリプト

echo "ZIPファイルを作成中..."
zip -q -r trouble-management-latest.zip *.php *.css 2>/dev/null

echo "作成完了: trouble-management-latest.zip"
ls -lh trouble-management-latest.zip

echo ""
echo "============================================"
echo "ローカルXAMPPで使う手順:"
echo "============================================"
echo "1. trouble-management-latest.zip をダウンロード"
echo "2. C:\xampp\htdocs\ に解凍"
echo "3. http://localhost/trouble-management-latest/ でアクセス"
echo ""
echo "※ data.json と users.json は既存のものを残してください"
echo "============================================"
