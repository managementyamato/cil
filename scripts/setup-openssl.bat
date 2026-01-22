@echo off
echo ========================================
echo OpenSSL/cURL拡張セットアップスクリプト
echo ========================================
echo.
echo このスクリプトは、PHPでHTTPS通信を有効にするために
echo 必要なファイルのダウンロード先を案内します。
echo.
echo 【現在のPHP環境】
..\lib\php.exe -v 2>nul || php.exe -v
echo.
echo ----------------------------------------
echo 必要な作業:
echo ----------------------------------------
echo.
echo 1. PHP 8.2.12 (x64 Thread Safe)の完全版をダウンロード
echo    URL: https://windows.php.net/downloads/releases/php-8.2.12-Win32-vs16-x64.zip
echo.
echo 2. ダウンロードしたZIPファイルを解凍
echo.
echo 3. 以下のファイルをlibフォルダにコピー:
echo    [解凍フォルダ]\ext\php_openssl.dll  → %cd%\..\ext\php_openssl.dll
echo    [解凍フォルダ]\ext\php_curl.dll     → %cd%\..\ext\php_curl.dll
echo    [解凍フォルダ]\libcrypto-3-x64.dll  → %cd%\..\lib\libcrypto-3-x64.dll
echo    [解凍フォルダ]\libssl-3-x64.dll     → %cd%\..\lib\libssl-3-x64.dll
echo.
echo 4. php.iniで拡張を有効化（既に有効化済み）
echo.
echo ----------------------------------------
echo セットアップ後の確認:
echo ----------------------------------------
echo.
echo   ..\lib\php.exe -m | findstr /i "openssl curl"
echo.
echo OpenSSLとcURLが表示されれば成功です。
echo.
echo 詳細は FIX-HTTPS-SUPPORT.md を参照してください。
echo.
pause
