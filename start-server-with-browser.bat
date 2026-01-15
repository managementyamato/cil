@echo off
chcp 65001 > nul
echo ========================================
echo YA管理一覧システム - 開発サーバー起動
echo ========================================
echo.

REM 現在のディレクトリに移動（バッチファイルの場所）
cd /d "%~dp0"

REM PHPの自動検出
set PHP_CMD=php

REM リポジトリ内のPHPを最優先で確認（ポータブル版）
if exist "%~dp0php\php.exe" (
    set PHP_CMD=%~dp0php\php.exe
    echo [OK] リポジトリ内のポータブルPHPを使用します
    "%PHP_CMD%" -v | findstr /C:"PHP"
    echo.
    goto :php_found
)

REM 環境変数のPHPを確認
where php >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [OK] 環境変数のPHPを使用します
    php -v | findstr /C:"PHP"
    echo.
    goto :php_found
)

REM XAMPPのPHPを確認
if exist "C:\xampp\php\php.exe" (
    set PHP_CMD=C:\xampp\php\php.exe
    echo [OK] XAMPP のPHPを使用します: %PHP_CMD%
    "%PHP_CMD%" -v | findstr /C:"PHP"
    echo.
    goto :php_found
)

REM C:\php を確認
if exist "C:\php\php.exe" (
    set PHP_CMD=C:\php\php.exe
    echo [OK] C:\php のPHPを使用します: %PHP_CMD%
    "%PHP_CMD%" -v | findstr /C:"PHP"
    echo.
    goto :php_found
)

REM PHPが見つからない場合
echo [エラー] PHPが見つかりません。
echo.
echo 以下のいずれかの場所にPHPをインストールしてください:
echo   - %~dp0php\php.exe (推奨: ポータブル版)
echo   - C:\xampp\php\php.exe
echo   - C:\php\php.exe
echo   - または環境変数PATHに追加
echo.
echo インストール先: https://windows.php.net/download/
echo XAMPP: https://www.apachefriends.org/jp/index.html
echo.
echo ポータブル版の設置方法は php\README.md を参照してください
echo.
pause
exit /b 1

:php_found

REM ブランチ確認
git branch --show-current
echo.

echo ========================================
echo 開発サーバーを起動しています...
echo ========================================
echo.
echo 3秒後にブラウザが自動で開きます...
echo.
echo サーバーを停止するには Ctrl+C を押してください
echo ========================================
echo.

REM 3秒待ってからブラウザを開く（非同期）
start "" cmd /c "timeout /t 3 /nobreak >nul && start http://localhost:8000"

REM PHPビルトインサーバー起動
"%PHP_CMD%" -S localhost:8000
