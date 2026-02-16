$projectDir = "C:\Claude\master"
$localDir = "$projectDir\public_html"
$winscp = "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com"
$credFile = "$projectDir\.ftp-credentials"

$pass = ""
Get-Content $credFile | ForEach-Object {
    if ($_ -match "^FTP_PASS=(.+)$") { $pass = $Matches[1] }
}

Write-Host "Deploying troubles.php..."

$script = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch abort
option confirm off
option transfer binary
cd /yamato-mgt.com/public_html
put "$localDir\pages\troubles.php" "./pages/"
close
exit
"@
$scriptFile = "$env:TEMP\winscp_quick_deploy.txt"
$script | Out-File -Encoding ASCII $scriptFile

& $winscp /script="$scriptFile"

if ($LASTEXITCODE -eq 0) {
    Write-Host "Deploy complete!"
} else {
    Write-Host "Deploy FAILED"
}

Remove-Item $scriptFile -ErrorAction SilentlyContinue
