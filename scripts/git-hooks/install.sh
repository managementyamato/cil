#!/bin/sh
# git hooks をインストール
set -e

HOOKS_SRC=$(dirname "$0")
HOOKS_DST=.git/hooks

if [ ! -d .git ]; then
    echo "[install] ERROR: git リポジトリのルートで実行してください"
    exit 1
fi

cp "$HOOKS_SRC/pre-commit" "$HOOKS_DST/pre-commit"
chmod +x "$HOOKS_DST/pre-commit"
echo "[install] OK: .git/hooks/pre-commit を設置"
echo "         無効化したい時は .git/hooks/pre-commit を削除"
