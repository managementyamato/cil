# mbstring DLL installation script

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "mbstring DLL Installation Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$phpVersion = "8.2.30"
$phpUrl = "https://windows.php.net/downloads/releases/php-$phpVersion-Win32-vs16-x64.zip"
$tempZip = Join-Path $env:TEMP "php-mbstring.zip"
$tempExtract = Join-Path $env:TEMP "php-mbstring-extract"
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$targetExtDir = Join-Path (Split-Path -Parent $scriptDir) "ext"

try {
    # Cleanup temp folders
    if (Test-Path $tempExtract) {
        Remove-Item $tempExtract -Recurse -Force
    }
    if (Test-Path $tempZip) {
        Remove-Item $tempZip -Force
    }

    Write-Host "Downloading PHP $phpVersion..." -ForegroundColor Green
    Write-Host "URL: $phpUrl" -ForegroundColor Gray

    # Download
    Invoke-WebRequest -Uri $phpUrl -OutFile $tempZip -UseBasicParsing
    $size = [math]::Round((Get-Item $tempZip).Length / 1MB, 2)
    Write-Host "Download complete ($size MB)" -ForegroundColor Green

    # Extract
    Write-Host ""
    Write-Host "Extracting ZIP file..." -ForegroundColor Green
    Expand-Archive -Path $tempZip -DestinationPath $tempExtract -Force
    Write-Host "Extraction complete" -ForegroundColor Green

    # Check for file
    Write-Host ""
    Write-Host "Checking for files..." -ForegroundColor Green

    $mbstringSource = Join-Path $tempExtract "ext\php_mbstring.dll"
    Write-Host "Looking for: $mbstringSource" -ForegroundColor Gray

    if (!(Test-Path $mbstringSource)) {
        throw "php_mbstring.dll not found in extracted archive"
    }

    Write-Host "Found php_mbstring.dll" -ForegroundColor Green

    # Create ext folder if it doesn't exist
    if (!(Test-Path $targetExtDir)) {
        New-Item -ItemType Directory -Path $targetExtDir | Out-Null
    }

    # Copy file
    Write-Host ""
    Write-Host "Copying files..." -ForegroundColor Green
    $targetFile = Join-Path $targetExtDir "php_mbstring.dll"
    Copy-Item -Path $mbstringSource -Destination $targetFile -Force
    Write-Host "Copied php_mbstring.dll" -ForegroundColor Green

    # Cleanup
    Write-Host ""
    Write-Host "Cleaning up..." -ForegroundColor Green
    Remove-Item $tempZip -Force -ErrorAction SilentlyContinue
    Remove-Item $tempExtract -Recurse -Force -ErrorAction SilentlyContinue
    Write-Host "Cleanup complete" -ForegroundColor Green

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "Setup Complete!" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Verify installation with:" -ForegroundColor Yellow
    Write-Host "  php.exe -m | findstr /i mbstring" -ForegroundColor White

} catch {
    Write-Host ""
    Write-Host "Error occurred: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "Manual download instructions:" -ForegroundColor Yellow
    Write-Host "  1. Download: $phpUrl" -ForegroundColor White
    Write-Host "  2. Extract ZIP and copy ext\php_mbstring.dll to C:\Claude\master\ext\" -ForegroundColor White
}

Write-Host ""
