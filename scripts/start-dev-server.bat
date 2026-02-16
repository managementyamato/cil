@echo off
REM ========================================
REM YA管理システム 開発サーバー起動スクリプト
REM ========================================
REM
REM 【重要】このスクリプト以外でサーバーを起動しないでください
REM
REM 必要な設定:
REM - lib/php.ini を使用（プロジェクト内の設定）
REM - ポート: 8000
REM - Google OAuth redirect_uri: http://localhost:8000/api/google-callback.php
REM

cd /d "%~dp0\.."
echo.
echo ========================================
echo YA管理システム 開発サーバー
echo ========================================
echo.
echo [設定]
echo   PHP設定: lib/php.ini
echo   ポート: 8000
echo   URL: http://localhost:8000/
echo.
echo [注意]
echo   - Google OAuthは localhost:8000 でのみ動作します
echo   - config/google-config.json の redirect_uri を変更しないでください
echo   - lib/php.ini を変更しないでください
echo.
echo ----------------------------------------
echo サーバーを起動中...
echo 終了するには Ctrl+C を押してください
echo ----------------------------------------
echo.

C:\xampp\php\php.exe -c lib/php.ini -S localhost:8000
