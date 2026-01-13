@echo off
chcp 65001 > nul
echo ========================================
echo YA管理一覧システム - ライブリロード版
echo ========================================
echo.

REM 現在のディレクトリに移動（バッチファイルの場所）
cd /d "%~dp0"

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

REM npm run devを実行（ライブリロード付き）
call npm run dev
