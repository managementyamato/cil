@echo off
setlocal enabledelayedexpansion

echo ========================================
echo   XSERVER FTP Deploy
echo   Domain: yamato-mgt.com
echo ========================================
echo.

set FTP_HOST=sv2304.xserver.jp
set FTP_USER=management@yamato-mgt.com
set REMOTE_DIR=/yamato-mgt.com/public_html

set WINSCP=%USERPROFILE%\AppData\Local\Programs\WinSCP\WinSCP.com
if not exist "%WINSCP%" (
    echo ERROR: WinSCP not found at %WINSCP%
    pause
    exit /b 1
)

set LOCAL_DIR=%~dp0public_html
set SRC_DIR=%~dp0
set CRED_FILE=%~dp0.ftp-credentials

:: Read password from .ftp-credentials
set FTP_PASS=
if exist "%CRED_FILE%" (
    for /f "tokens=1,* delims==" %%a in (%CRED_FILE%) do (
        if "%%a"=="FTP_PASS" set FTP_PASS=%%b
    )
)

if "%FTP_PASS%"=="" (
    echo ERROR: .ftp-credentials not found or FTP_PASS not set
    echo Create .ftp-credentials with: FTP_PASS=yourpassword
    pause
    exit /b 1
)

echo [1/3] Backing up server data...
:: Create backup directory with timestamp
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set DATETIME=%%I
set TIMESTAMP=%DATETIME:~0,8%_%DATETIME:~8,6%
set BACKUP_DIR=%SRC_DIR%backups\%TIMESTAMP%
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

set BACKUP_SCRIPT=%TEMP%\winscp_backup.txt
(
echo open ftp://management%%40yamato-mgt.com:%FTP_PASS%@%FTP_HOST%/ -passive=on
echo option batch abort
echo option confirm off
echo option transfer binary
echo get "/yamato-mgt.com/public_html/data.json" "%BACKUP_DIR%\data.json"
echo get "/yamato-mgt.com/public_html/config/users.json" "%BACKUP_DIR%\users.json"
echo get "/yamato-mgt.com/public_html/config/google-config.json" "%BACKUP_DIR%\google-config.json"
echo get "/yamato-mgt.com/public_html/config/mf-config.json" "%BACKUP_DIR%\mf-config.json"
echo get "/yamato-mgt.com/public_html/config/photo-attendance-data.json" "%BACKUP_DIR%\photo-attendance-data.json"
echo close
echo exit
) > "%BACKUP_SCRIPT%"
"%WINSCP%" /script="%BACKUP_SCRIPT%" >nul 2>nul
del "%BACKUP_SCRIPT%" 2>nul
echo Done. Saved to backups\%TIMESTAMP%
echo.

echo [2/3] Syncing to public_html (mirror mode)...
:: robocopy /MIR でローカルにないファイルも削除（ミラーリング）
if not exist "%LOCAL_DIR%\config" mkdir "%LOCAL_DIR%\config"
robocopy "%SRC_DIR%api" "%LOCAL_DIR%\api" /MIR /NFL /NDL /NJH /NJS >nul
robocopy "%SRC_DIR%forms" "%LOCAL_DIR%\forms" /MIR /NFL /NDL /NJH /NJS >nul
robocopy "%SRC_DIR%functions" "%LOCAL_DIR%\functions" /MIR /NFL /NDL /NJH /NJS >nul
robocopy "%SRC_DIR%pages" "%LOCAL_DIR%\pages" /MIR /NFL /NDL /NJH /NJS >nul
if exist "%SRC_DIR%lib" robocopy "%SRC_DIR%lib" "%LOCAL_DIR%\lib" /MIR /NFL /NDL /NJH /NJS >nul
if exist "%SRC_DIR%config\*.php" copy /Y "%SRC_DIR%config\*.php" "%LOCAL_DIR%\config\" >nul
copy /Y "%SRC_DIR%index.php" "%LOCAL_DIR%\" >nul
copy /Y "%SRC_DIR%style.css" "%LOCAL_DIR%\" >nul
copy /Y "%SRC_DIR%app.js" "%LOCAL_DIR%\" >nul 2>nul
if exist "%SRC_DIR%.htaccess" copy /Y "%SRC_DIR%.htaccess" "%LOCAL_DIR%\" >nul
echo Done.
echo.

:: Auto mode (--auto flag or called from CLI)
if "%1"=="--auto" goto :do_deploy

echo From: %LOCAL_DIR%
echo To:   %FTP_HOST%:%REMOTE_DIR%
echo.
set /p CONFIRM=Deploy now? (y/n):
if /i not "%CONFIRM%"=="y" (
    echo Cancelled.
    pause
    exit /b
)

:do_deploy
set SCRIPT_FILE=%TEMP%\winscp_deploy.txt
(
echo open ftp://management%%40yamato-mgt.com:%FTP_PASS%@%FTP_HOST%/ -passive=on
echo option batch abort
echo option confirm off
echo option transfer binary
echo synchronize remote -delete -filemask="|data.json;users.json;*.token.json;alcohol-sync-log.json;photo-attendance-data.json;mf-config.json;google-config.json;loans-drive-config.json;mf-sync-config.json;pdf-sources.json;spreadsheet-sources.json;alcohol-chat-config.json" "%LOCAL_DIR%" "%REMOTE_DIR%"
echo close
echo exit
) > "%SCRIPT_FILE%"

echo [3/3] Uploading via FTP...
"%WINSCP%" /script="%SCRIPT_FILE%" /log="%~dp0deploy.log"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================
    echo   Deploy complete!
    echo   https://yamato-mgt.com/
    echo ========================================
) else (
    echo.
    echo Deploy FAILED. Check deploy.log
)

del "%SCRIPT_FILE%" 2>nul

if not "%1"=="--auto" pause
