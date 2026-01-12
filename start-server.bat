@echo off
chcp 65001 > nul
echo ========================================
echo YA管理一覧システム - 開発サーバー起動
echo ========================================
echo.

REM 現在のディレクトリに移動（バッチファイルの場所）
cd /d "%~dp0"

REM PHPがインストールされているか確認
where php >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [エラー] PHPが見つかりません。
    echo PHPをインストールして、環境変数PATHに追加してください。
    echo.
    echo インストール先: https://windows.php.net/download/
    echo.
    pause
    exit /b 1
)

echo [OK] PHP が見つかりました
php -v | findstr /C:"PHP"
echo.

REM ブランチ確認
git branch --show-current
echo.

echo ========================================
echo 開発サーバーを起動しています...
echo ========================================
echo.
echo ブラウザで以下のURLにアクセスしてください:
echo.
echo     http://localhost:8000
echo.
echo サーバーを停止するには Ctrl+C を押してください
echo ========================================
echo.

REM PHPビルトインサーバー起動
php -S localhost:8000
