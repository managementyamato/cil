param(
    [switch]$Force,    # 確認プロンプトをスキップ
    [switch]$SkipTests # テストをスキップ
)

Write-Host "========================================"
Write-Host "  XSERVER FTP Deploy (STAGING)"
Write-Host "  Domain: staging.yamato-mgt.com"
Write-Host "========================================"
Write-Host ""

$projectDir = "C:\Claude\master"
$localDir = "$projectDir\public_html"
$winscp = "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com"
$credFile = "$projectDir\.ftp-credentials"
$php = "C:\xampp\php\php.exe"

# ステージングのFTP上のパス（XSERVERのサブドメイン設定に合わせて変更）
# XSERVERでは通常 /staging.yamato-mgt.com/public_html/ になる
$stagingRemotePath = "/staging.yamato-mgt.com/public_html"

# ============================================================
# [0/3] Pre-deploy checks
# ============================================================
Write-Host "[0/3] Pre-deploy checks..."

# --- PHP文法チェック ---
Write-Host "  Checking PHP syntax..."
$syntaxErrors = @()
$phpFiles = Get-ChildItem -Path $projectDir -Recurse -Include "*.php" -File |
    Where-Object { $_.FullName -notmatch '\\vendor\\' -and
                   $_.FullName -notmatch '\\public_html\\' -and
                   $_.FullName -notmatch '\\node_modules\\' }

foreach ($file in $phpFiles) {
    $result = & $php -l $file.FullName 2>&1
    if ($LASTEXITCODE -ne 0) {
        $syntaxErrors += "  [SYNTAX] $($file.FullName -replace [regex]::Escape($projectDir), '.')"
        $syntaxErrors += "           $result"
    }
}

if ($syntaxErrors.Count -gt 0) {
    Write-Host ""
    Write-Host "ABORT: PHP syntax errors found:" -ForegroundColor Red
    $syntaxErrors | ForEach-Object { Write-Host $_ -ForegroundColor Red }
    exit 1
}
Write-Host "  PHP syntax OK ($($phpFiles.Count) files checked)"

# --- PHPUnitテスト ---
if ($SkipTests) {
    Write-Host "  Tests SKIPPED (-SkipTests specified)" -ForegroundColor Yellow
} else {
    Write-Host "  Running tests..."
    $testOutput = & $php vendor/bin/phpunit --no-coverage 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host ""
        Write-Host "ABORT: Tests failed:" -ForegroundColor Red
        $testOutput | Select-Object -Last 20 | ForEach-Object { Write-Host $_ -ForegroundColor Red }
        exit 1
    }
    $summary = $testOutput | Select-String -Pattern "^(OK|Tests:|FAILURES)" | Select-Object -Last 1
    Write-Host "  Tests OK: $summary"
}

Write-Host ""

# --- 確認プロンプト ---
if (-not $Force) {
    Write-Host "Deploy target: https://staging.yamato-mgt.com/" -ForegroundColor Yellow
    Write-Host "This is STAGING - safe to test, no real data will be affected."
    $confirm = Read-Host "Deploy to staging? [y/N]"
    if ($confirm -notmatch '^[yY]$') {
        Write-Host "Cancelled."
        exit 0
    }
}

Write-Host ""

# ============================================================
# Read FTP password
# ============================================================
$pass = ""
Get-Content $credFile | ForEach-Object {
    if ($_ -match "^FTP_PASS=(.+)$") { $pass = $Matches[1] }
}
if (-not $pass) {
    Write-Host "ERROR: Password not found in .ftp-credentials"
    exit 1
}

# ============================================================
# [1/3] Sync source to public_html (本番と同じ除外設定)
# ============================================================
Write-Host "[1/3] Syncing to public_html..."
$copies = @("api","forms","functions","pages","lib","js","css")
foreach ($dir in $copies) {
    if (Test-Path "$projectDir\$dir") {
        xcopy /E /I /Y "$projectDir\$dir" "$localDir\$dir" | Out-Null
    }
}
Remove-Item "$localDir\pages\test-*.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\color-samples.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\*.backup" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\*.corrupted" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\mf-invoice-list.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\download-invoices-csv.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\print-invoice.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\api\clear-mf-invoices.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\api\create-invoice-api.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\api\schedule-invoice-api.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\api\sync-invoices.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\api\pages\invoices-data.php" -Force -ErrorAction SilentlyContinue

if (Test-Path "$projectDir\config\*.php") { Copy-Item "$projectDir\config\*.php" "$localDir\config\" -Force }
if (Test-Path "$projectDir\config\spreadsheet-sources.json") { Copy-Item "$projectDir\config\spreadsheet-sources.json" "$localDir\config\" -Force }
Copy-Item "$projectDir\index.php" "$localDir\" -Force
Copy-Item "$projectDir\style.css" "$localDir\" -Force
if (Test-Path "$projectDir\app.js") { Copy-Item "$projectDir\app.js" "$localDir\" -Force }
if (Test-Path "$projectDir\.htaccess") { Copy-Item "$projectDir\.htaccess" "$localDir\" -Force }
if (Test-Path "$projectDir\favicon.png") { Copy-Item "$projectDir\favicon.png" "$localDir\" -Force }
Write-Host "Done."
Write-Host ""

# ============================================================
# [2/3] Upload to staging via FTP
# ============================================================
Write-Host "[2/3] Uploading to staging via FTP..."
$script = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch abort
option confirm off
option transfer binary
synchronize remote -filemask="|data.json;users.json;*.token.json;alcohol-sync-log.json;photo-attendance-data.json;mf-config.json;google-config.json;loans-drive-config.json;uploads/" "$localDir" "$stagingRemotePath"
close
exit
"@
$scriptFile = "$env:TEMP\winscp_staging.txt"
$script | Out-File -Encoding ASCII $scriptFile

& $winscp /script="$scriptFile" /log="$projectDir\deploy-staging.log"
$deployExitCode = $LASTEXITCODE

Remove-Item $scriptFile -ErrorAction SilentlyContinue

if ($deployExitCode -eq 0) {
    Write-Host ""
    Write-Host "========================================"
    Write-Host "  Staging deploy complete!"
    Write-Host "  https://staging.yamato-mgt.com/"
    Write-Host ""
    Write-Host "  Confirm it works, then run:"
    Write-Host "  powershell -File auto-deploy.ps1"
    Write-Host "========================================"
} else {
    Write-Host ""
    Write-Host "Staging deploy FAILED. Check deploy-staging.log" -ForegroundColor Red
    exit 1
}
