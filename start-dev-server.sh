#!/bin/bash
# 開発サーバー起動スクリプト

echo "========================================"
echo "トラブル管理システム - 開発サーバー"
echo "========================================"
echo ""

# 既存のサーバーを停止
pkill -f "php -S 0.0.0.0:8000" 2>/dev/null

echo "PHPサーバーを起動中..."
nohup php -S 0.0.0.0:8000 > /tmp/php-server.log 2>&1 &
SERVER_PID=$!
echo $SERVER_PID > /tmp/php-server.pid

sleep 2

if ps -p $SERVER_PID > /dev/null; then
    echo "✓ サーバー起動成功 (PID: $SERVER_PID)"
    echo ""
    echo "アクセス方法:"
    echo "1. Claude Code画面下部の「ポート」タブを開く"
    echo "2. ポート 8000 の公開URLをクリック"
    echo ""
    echo "または："
    echo "   http://localhost:8000 (ローカル)"
    echo ""
    echo "サーバーログ: tail -f /tmp/php-server.log"
    echo "========================================"
else
    echo "✗ サーバー起動失敗"
    cat /tmp/php-server.log
fi
