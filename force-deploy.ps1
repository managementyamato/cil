$projectDir = "C:\Claude\master"
$localDir = "$projectDir\public_html"
$winscp = "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com"
$credFile = "$projectDir\.ftp-credentials"

$pass = ""
Get-Content $credFile | ForEach-Object {
    if ($_ -match "^FTP_PASS=(.+)$") { $pass = $Matches[1] }
}

Write-Host "Force deploying changed files..."

$script = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch abort
option confirm off
option transfer binary
cd /yamato-mgt.com/public_html
put "$localDir\functions\soft-delete.php" "./functions/"
put "$localDir\functions\header.php" "./functions/"
put "$localDir\functions\audit-log.php" "./functions/"
put "$localDir\pages\trash.php" "./pages/"
put "$localDir\pages\customers.php" "./pages/"
put "$localDir\pages\master.php" "./pages/"
put "$localDir\pages\masters.php" "./pages/"
put "$localDir\pages\employees.php" "./pages/"
put "$localDir\pages\troubles.php" "./pages/"
put "$localDir\pages\bulk-pdf-match.php" "./pages/"
put "$localDir\pages\mf-settings.php" "./pages/"
put "$localDir\pages\google-oauth-settings.php" "./pages/"
put "$localDir\pages\integration-settings.php" "./pages/"
put "$localDir\api\auth.php" "./api/"
put "$localDir\api\loans-api.php" "./api/"
put "$localDir\index.php" "./"
put "$localDir\style.css" "./"
put "$localDir\config\config.php" "./config/"
close
exit
"@
$scriptFile = "$env:TEMP\winscp_force_deploy.txt"
$script | Out-File -Encoding ASCII $scriptFile

& $winscp /script="$scriptFile"

if ($LASTEXITCODE -eq 0) {
    Write-Host "Force deploy complete!"
} else {
    Write-Host "Deploy FAILED"
}

Remove-Item $scriptFile -ErrorAction SilentlyContinue
