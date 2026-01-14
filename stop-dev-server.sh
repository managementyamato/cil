#!/bin/bash
# 開発サーバー停止スクリプト

echo "開発サーバーを停止中..."

if [ -f /tmp/php-server.pid ]; then
    PID=$(cat /tmp/php-server.pid)
    if ps -p $PID > /dev/null; then
        kill $PID
        echo "✓ サーバーを停止しました (PID: $PID)"
    else
        echo "サーバーは既に停止しています"
    fi
    rm /tmp/php-server.pid
else
    # PIDファイルがない場合は名前で検索
    pkill -f "php -S 0.0.0.0:8000"
    echo "✓ サーバーを停止しました"
fi
