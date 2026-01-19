# OpenSSL DLLダウンロードスクリプト
# PowerShellで実行: .\download-openssl-dlls.ps1

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "OpenSSL DLL自動ダウンロードスクリプト" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$phpVersion = "8.2.12"
$phpUrl = "https://windows.php.net/downloads/releases/php-$phpVersion-Win32-vs16-x64.zip"
$tempZip = "$env:TEMP\php-$phpVersion.zip"
$tempExtract = "$env:TEMP\php-$phpVersion-extract"
$targetDir = "C:\Claude\master"
$extDir = "$targetDir\ext"

Write-Host "作業ディレクトリ: $targetDir" -ForegroundColor Yellow
Write-Host ""

# extフォルダが存在しない場合は作成
if (-not (Test-Path $extDir)) {
    Write-Host "extフォルダを作成中..." -ForegroundColor Green
    New-Item -ItemType Directory -Path $extDir | Out-Null
    Write-Host "✓ extフォルダを作成しました" -ForegroundColor Green
}

Write-Host ""
Write-Host "PHP $phpVersion をダウンロード中..." -ForegroundColor Green
Write-Host "URL: $phpUrl" -ForegroundColor Gray

try {
    # ダウンロード
    Invoke-WebRequest -Uri $phpUrl -OutFile $tempZip -UseBasicParsing
    Write-Host "✓ ダウンロード完了" -ForegroundColor Green

    # 解凍
    Write-Host ""
    Write-Host "ZIPファイルを解凍中..." -ForegroundColor Green
    if (Test-Path $tempExtract) {
        Remove-Item $tempExtract -Recurse -Force
    }
    Expand-Archive -Path $tempZip -DestinationPath $tempExtract -Force
    Write-Host "✓ 解凍完了" -ForegroundColor Green

    # 必要なファイルをコピー
    Write-Host ""
    Write-Host "必要なファイルをコピー中..." -ForegroundColor Green

    $filesToCopy = @(
        @{
            Source = "$tempExtract\ext\php_openssl.dll"
            Dest = "$extDir\php_openssl.dll"
            Name = "php_openssl.dll"
        },
        @{
            Source = "$tempExtract\ext\php_mbstring.dll"
            Dest = "$extDir\php_mbstring.dll"
            Name = "php_mbstring.dll"
        },
        @{
            Source = "$tempExtract\libcrypto-3-x64.dll"
            Dest = "$targetDir\libcrypto-3-x64.dll"
            Name = "libcrypto-3-x64.dll"
        },
        @{
            Source = "$tempExtract\libssl-3-x64.dll"
            Dest = "$targetDir\libssl-3-x64.dll"
            Name = "libssl-3-x64.dll"
        }
    )

    foreach ($file in $filesToCopy) {
        if (Test-Path $file.Source) {
            Copy-Item -Path $file.Source -Destination $file.Dest -Force
            Write-Host "  ✓ $($file.Name) をコピーしました" -ForegroundColor Green
        } else {
            Write-Host "  ✗ $($file.Name) が見つかりません" -ForegroundColor Red
        }
    }

    # クリーンアップ
    Write-Host ""
    Write-Host "一時ファイルを削除中..." -ForegroundColor Green
    Remove-Item $tempZip -Force -ErrorAction SilentlyContinue
    Remove-Item $tempExtract -Recurse -Force -ErrorAction SilentlyContinue
    Write-Host "✓ クリーンアップ完了" -ForegroundColor Green

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "セットアップ完了！" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "次のコマンドで確認してください:" -ForegroundColor Yellow
    Write-Host "  php.exe -m | findstr /i openssl" -ForegroundColor White
    Write-Host ""
    Write-Host "MF認証のテスト:" -ForegroundColor Yellow
    Write-Host "  php.exe test-mf-connection.php" -ForegroundColor White

} catch {
    Write-Host ""
    Write-Host "エラーが発生しました: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "手動でのセットアップ方法については QUICK-HTTPS-FIX.md を参照してください" -ForegroundColor Yellow
}

Write-Host ""
