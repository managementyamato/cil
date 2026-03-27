param(
    [switch]$Force
)

Write-Host "========================================"
Write-Host "  Contacts Feature Deploy"
Write-Host "  Domain: yamato-mgt.com"
Write-Host "========================================"
Write-Host ""

$projectDir = "C:\Claude\master"
$localDir   = "$projectDir\public_html"
$winscp     = "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com"
$credFile   = "$projectDir\.ftp-credentials"
$php        = "C:\xampp\php\php.exe"

# デプロイ対象ファイル
$targetFiles = @(
    "pages\contacts.php",
    "api\contacts.php",
    "api\auth.php",
    "functions\header.php",
    "functions\data-schema.php"
)

# PHP構文チェック
Write-Host "Checking PHP syntax..."
foreach ($rel in $targetFiles) {
    $full = "$projectDir\$rel"
    if (-not (Test-Path $full)) { Write-Host "  SKIP (not found): $rel" -ForegroundColor Yellow; continue }
    $result = & $php -l $full 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host "ABORT: Syntax error in $rel" -ForegroundColor Red
        Write-Host $result -ForegroundColor Red
        exit 1
    }
    Write-Host "  OK: $rel"
}
Write-Host ""

if (-not $Force) {
    Write-Host "Files to deploy:"
    $targetFiles | ForEach-Object { Write-Host "  $_" -ForegroundColor Cyan }
    Write-Host ""
    $confirm = Read-Host "Deploy to https://yamato-mgt.com/ ? [y/N]"
    if ($confirm -notmatch '^[yY]$') { Write-Host "Cancelled."; exit 0 }
}

# FTPパスワード読み込み
$pass = ""
Get-Content $credFile | ForEach-Object {
    if ($_ -match "^FTP_PASS=(.+)$") { $pass = $Matches[1] }
}
if (-not $pass) { Write-Host "ERROR: Password not found in .ftp-credentials" -ForegroundColor Red; exit 1 }

# public_html にコピー
Write-Host ""
Write-Host "Copying to public_html..."
foreach ($rel in $targetFiles) {
    $src  = "$projectDir\$rel"
    $dest = "$localDir\$rel"
    if (-not (Test-Path $src)) { continue }
    $destDir = Split-Path $dest -Parent
    if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }
    Copy-Item $src $dest -Force
    Write-Host "  Copied: $rel"
}

# FTP アップロード（個別ファイル）
Write-Host ""
Write-Host "Uploading via FTP..."
$puts = $targetFiles | ForEach-Object {
    $remotePath = "/" + $_.Replace("\", "/")
    $localPath  = "$localDir\" + $_
    "put `"$localPath`" `"$remotePath`""
}
$putCmds = $puts -join "`n"

$script = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch abort
option confirm off
option transfer binary
$putCmds
close
exit
"@

$scriptFile = "$env:TEMP\winscp_contacts_deploy.txt"
$script | Out-File -Encoding ASCII $scriptFile
& $winscp /script="$scriptFile" /log="$projectDir\deploy-contacts.log"
$exitCode = $LASTEXITCODE
Remove-Item $scriptFile -ErrorAction SilentlyContinue

if ($exitCode -eq 0) {
    Write-Host ""
    Write-Host "========================================"
    Write-Host "  Deploy complete!"
    Write-Host "  https://yamato-mgt.com/pages/contacts.php"
    Write-Host "========================================"
} else {
    Write-Host "Deploy FAILED. Check deploy-contacts.log" -ForegroundColor Red
    exit 1
}
