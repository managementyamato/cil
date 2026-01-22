@echo off
echo ========================================
echo OpenSSL DLL自動インストーラー
echo ========================================
echo.
echo このスクリプトはPowerShellを使用してOpenSSL DLLを
echo 自動的にダウンロード・インストールします。
echo.
echo インターネット接続が必要です。
echo.
pause

echo.
echo PowerShellスクリプトを実行中...
echo.

powershell -ExecutionPolicy Bypass -File "%~dp0download-openssl-dlls.ps1"

echo.
echo ========================================
echo.
pause
