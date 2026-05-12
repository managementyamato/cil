@echo off
REM ====================================================
REM  Yamato Gear テスト環境起動スクリプト
REM  - XAMPP (Apache + MySQL) を起動
REM  - Cloudflare Tunnel で localhost:8080 を公開
REM ====================================================

setlocal

echo.
echo ========================================
echo  Yamato Gear テスト環境 起動
echo ========================================
echo.

REM --- 1. MySQL 起動 ---
echo [1/3] MySQL 起動中...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I "mysqld.exe" >NUL
if errorlevel 1 (
    start "MySQL" /MIN "C:\xampp\mysql\bin\mysqld.exe" --defaults-file=C:\xampp\mysql\bin\my.ini
    timeout /t 5 /nobreak >NUL
    echo   MySQL 起動完了
) else (
    echo   MySQL は既に起動済み
)

REM --- 2. Apache 起動 ---
echo [2/3] Apache 起動中...
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I "httpd.exe" >NUL
if errorlevel 1 (
    start "Apache" /MIN "C:\xampp\apache\bin\httpd.exe" -f "C:\xampp\apache\conf\httpd.conf"
    timeout /t 3 /nobreak >NUL
    echo   Apache 起動完了
) else (
    echo   Apache は既に起動済み
)

REM --- 3. ローカル動作確認 ---
echo [3/3] ローカル動作確認...
curl -s -o NUL -w "  HTTP %%{http_code}: http://localhost:8080" http://localhost:8080
echo.
echo.

REM --- 4. Cloudflare Tunnel 起動 ---
echo ========================================
echo  Cloudflare Tunnel 開始
echo ========================================
echo  URL がコンソールに表示されたら社内に共有してください
echo  停止するには Ctrl+C
echo ========================================
echo.
cloudflared tunnel --url http://localhost:8080

endlocal
