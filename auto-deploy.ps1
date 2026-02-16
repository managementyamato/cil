Write-Host "========================================"
Write-Host "  XSERVER FTP Deploy (Auto)"
Write-Host "  Domain: yamato-mgt.com"
Write-Host "========================================"
Write-Host ""

$projectDir = "C:\Claude\master"
$localDir = "$projectDir\public_html"
$winscp = "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com"
$credFile = "$projectDir\.ftp-credentials"

# Read password
$pass = ""
Get-Content $credFile | ForEach-Object {
    if ($_ -match "^FTP_PASS=(.+)$") { $pass = $Matches[1] }
}
if (-not $pass) {
    Write-Host "ERROR: Password not found in .ftp-credentials"
    exit 1
}

# Backup server data files before deploy
Write-Host "[1/3] Backing up server data..."
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backupDir = "$projectDir\backups\$timestamp"
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null

$backupScript = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch abort
option confirm off
option transfer binary
get "/data.json" "$backupDir\data.json"
get "/config/users.json" "$backupDir\users.json"
get "/config/google-config.json" "$backupDir\google-config.json"
get "/config/mf-config.json" "$backupDir\mf-config.json"
get "/config/photo-attendance-data.json" "$backupDir\photo-attendance-data.json"
close
exit
"@
$backupScriptFile = "$env:TEMP\winscp_backup.txt"
$backupScript | Out-File -Encoding ASCII $backupScriptFile
& $winscp /script="$backupScriptFile" 2>&1 | Out-Null
Remove-Item $backupScriptFile -ErrorAction SilentlyContinue

$backupCount = (Get-ChildItem $backupDir -File).Count
Write-Host "Done. $backupCount files saved to backups\$timestamp"
Write-Host ""

# Keep only last 10 backups
$allBackups = Get-ChildItem "$projectDir\backups" -Directory | Sort-Object Name -Descending | Select-Object -Skip 10
foreach ($old in $allBackups) { Remove-Item $old.FullName -Recurse -Force }

# Sync source to public_html
Write-Host "[2/3] Syncing to public_html..."
$copies = @("api","forms","functions","pages","lib","js","css")
foreach ($dir in $copies) {
    if (Test-Path "$projectDir\$dir") {
        xcopy /E /I /Y "$projectDir\$dir" "$localDir\$dir" | Out-Null
    }
}
# セキュリティ: テストページ・デバッグページを本番から除外
Remove-Item "$localDir\pages\test-*.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\color-samples.php" -Force -ErrorAction SilentlyContinue
# バックアップファイルも除外
Remove-Item "$localDir\pages\*.backup" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\*.corrupted" -Force -ErrorAction SilentlyContinue

if (Test-Path "$projectDir\config\*.php") { Copy-Item "$projectDir\config\*.php" "$localDir\config\" -Force }
Copy-Item "$projectDir\index.php" "$localDir\" -Force
Copy-Item "$projectDir\style.css" "$localDir\" -Force
if (Test-Path "$projectDir\app.js") { Copy-Item "$projectDir\app.js" "$localDir\" -Force }
if (Test-Path "$projectDir\.htaccess") { Copy-Item "$projectDir\.htaccess" "$localDir\" -Force }
if (Test-Path "$projectDir\favicon.png") { Copy-Item "$projectDir\favicon.png" "$localDir\" -Force }
Write-Host "Done."
Write-Host ""

# Deploy
Write-Host "[3/3] Uploading via FTP..."
$script = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch abort
option confirm off
option transfer binary
synchronize remote -filemask="|data.json;users.json;*.token.json;alcohol-sync-log.json;photo-attendance-data.json;mf-config.json;google-config.json;loans-drive-config.json;uploads/" "$localDir" "/"
close
exit
"@
$scriptFile = "$env:TEMP\winscp_deploy.txt"
$script | Out-File -Encoding ASCII $scriptFile

& $winscp /script="$scriptFile" /log="$projectDir\deploy.log"

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "========================================"
    Write-Host "  Deploy complete!"
    Write-Host "  https://yamato-mgt.com/"
    Write-Host "========================================"
} else {
    Write-Host ""
    Write-Host "Deploy FAILED. Check deploy.log"
}

Remove-Item $scriptFile -ErrorAction SilentlyContinue
