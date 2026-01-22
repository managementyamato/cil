@echo off
chcp 65001 > nul
echo ========================================
echo YA管理一覧システム - ライブリロード版
echo ========================================
echo.

REM プロジェクトルートディレクトリに移動（scriptsの親ディレクトリ）
cd /d "%~dp0.."

REM PHPの自動検出
set PHP_CMD=php

REM リポジトリ内のPHPを最優先で確認（ポータブル版）
if exist "%~dp0..\lib\php.exe" (
    set "PHP_CMD=%~dp0..\lib\php.exe"
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
echo   - %~dp0..\lib\php.exe (推奨: ポータブル版)
echo   - C:\xampp\php\php.exe
echo   - C:\php\php.exe
echo   - または環境変数PATHに追加
echo.
echo インストール先: https://windows.php.net/download/
echo XAMPP: https://www.apachefriends.org/jp/index.html
echo.
pause
exit /b 1

:php_found

REM Node.jsがインストールされているか確認
where node >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [エラー] Node.jsが見つかりません。
    echo Node.jsをインストールしてください。
    echo.
    echo インストール先: https://nodejs.org/ja/
    echo.
    pause
    exit /b 1
)

echo [OK] Node.js が見つかりました
node -v
echo.

REM npmがインストールされているか確認
where npm >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [エラー] npmが見つかりません。
    echo Node.jsを再インストールしてください。
    echo.
    pause
    exit /b 1
)

echo [OK] npm が見つかりました
call npm -v
echo.

REM node_modulesがあるか確認
if not exist "node_modules" (
    echo node_modulesが見つかりません。依存関係をインストールします...
    echo.
    call npm install
    echo.
    if %ERRORLEVEL% NEQ 0 (
        echo [エラー] npm installが失敗しました。
        echo.
        pause
        exit /b 1
    )
    echo [OK] 依存関係のインストールが完了しました
    echo.
)

echo ========================================
echo ライブリロード付き開発サーバーを起動しています...
echo ========================================
echo.
echo ファイルを編集すると自動でブラウザがリロードされます
echo.
echo ブラウザで以下のURLにアクセスしてください:
echo.
echo     http://localhost:3000
echo.
echo サーバーを停止するには Ctrl+C を押してください
echo ========================================
echo.

REM ポータブルPHPを使用する場合、PATHに追加
if exist "%~dp0..\lib\php.exe" (
    set PATH=%~dp0..\lib;%PATH%
    echo [INFO] ポータブルPHPをPATHに追加しました
    echo.
)

REM npm run devを実行（ライブリロード付き）
call npm run dev
