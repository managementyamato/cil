@echo off
REM ====================================================
REM ローカルMySQL に yamato_gear_test DB を作成し、
REM 本番から取得したダンプファイルをインポートする
REM
REM 使い方:
REM   1. MySQL を起動 (start-test-tunnel.bat を先に走らせる)
REM   2. 本番のダンプファイル (yamato_gear.sql) を C:\Claude\master\backups\mysql-dump\ に置く
REM   3. このスクリプトを実行
REM ====================================================

set MYSQL="C:\xampp\mysql\bin\mysql.exe"
set DUMP=C:\Claude\master\backups\mysql-dump\yamato_gear.sql

echo.
echo [1/3] yamato_gear_test データベースを作成...
%MYSQL% -u root -e "CREATE DATABASE IF NOT EXISTS yamato_gear_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
    echo   ERROR: DB作成失敗
    exit /b 1
)
echo   OK

if not exist "%DUMP%" (
    echo.
    echo [2/3] スキップ: ダンプファイルが見つかりません
    echo   %DUMP% に本番ダンプを置いてから再実行してください
    echo.
    echo   本番ダンプ取得方法:
    echo   1. https://www.xserver.ne.jp/login_server.php からサーバーパネル
    echo   2. データベース ^> phpMyAdmin
    echo   3. adyamato_gear を選択 ^> エクスポート
    echo   4. ダウンロードした sql ファイルを上記パスに保存
    exit /b 1
)

echo.
echo [2/3] ダンプを yamato_gear_test にインポート中...
%MYSQL% -u root yamato_gear_test < "%DUMP%"
if errorlevel 1 (
    echo   ERROR: インポート失敗
    exit /b 1
)
echo   OK

echo.
echo [3/3] テーブル一覧:
%MYSQL% -u root yamato_gear_test -e "SHOW TABLES;"

echo.
echo ========================================
echo  ローカルDB セットアップ完了
echo  DB名: yamato_gear_test
echo  ユーザー: root
echo  パスワード: (なし)
echo ========================================
