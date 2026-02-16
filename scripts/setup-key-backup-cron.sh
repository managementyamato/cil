#!/bin/bash
# 暗号化キー自動バックアップ設定スクリプト
#
# このスクリプトは cron ジョブを設定して、
# 毎日午前3時に暗号化キーのバックアップを作成します。
#
# 使用方法:
#   bash scripts/setup-key-backup-cron.sh

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PHP_BIN="${PHP_BIN:-php}"

# cronジョブの内容
CRON_JOB="0 3 * * * cd $PROJECT_ROOT && $PHP_BIN scripts/backup-encryption-key.php --backup >> logs/key-backup.log 2>&1"

echo "暗号化キー自動バックアップの設定"
echo "======================================"
echo ""
echo "以下のcronジョブを追加します:"
echo "$CRON_JOB"
echo ""
echo "この設定により、毎日午前3時に暗号化キーがバックアップされます。"
echo ""
read -p "続行しますか？ (y/N): " -n 1 -r
echo

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "キャンセルしました。"
    exit 0
fi

# 既存のcronジョブを取得
TEMP_CRON=$(mktemp)
crontab -l > "$TEMP_CRON" 2>/dev/null || true

# 重複チェック
if grep -q "backup-encryption-key.php" "$TEMP_CRON"; then
    echo "⚠️  既に同様のcronジョブが存在します。"
    echo "既存のジョブ:"
    grep "backup-encryption-key.php" "$TEMP_CRON"
    rm "$TEMP_CRON"
    exit 1
fi

# 新しいジョブを追加
echo "$CRON_JOB" >> "$TEMP_CRON"
crontab "$TEMP_CRON"
rm "$TEMP_CRON"

echo "✅ cronジョブを追加しました。"
echo ""
echo "確認: crontab -l"
echo "削除: crontab -e で該当行を削除"
echo ""
echo "ログファイル: $PROJECT_ROOT/logs/key-backup.log"
