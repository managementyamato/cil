$projectDir = "C:\Claude\master"
$localDir = "$projectDir\public_html"
$winscp = "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com"
$credFile = "$projectDir\.ftp-credentials"

$pass = ""
Get-Content $credFile | ForEach-Object {
    if ($_ -match "^FTP_PASS=(.+)$") { $pass = $Matches[1] }
}

Write-Host "Deploying all files..."

$script = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch abort
option confirm off
option transfer binary
synchronize remote -filemask="|data.json;users.json;*.token.json;alcohol-sync-log.json;photo-attendance-data.json;mf-config.json;google-config.json;loans-drive-config.json;uploads/;data/" "$localDir" "/yamato-mgt.com/public_html"
close
exit
"@
$scriptFile = "$env:TEMP\winscp_manual_deploy.txt"
$script | Out-File -Encoding ASCII $scriptFile

& $winscp /script="$scriptFile"

if ($LASTEXITCODE -eq 0) {
    Write-Host "Deploy complete!"
} else {
    Write-Host "Deploy FAILED"
}

Remove-Item $scriptFile -ErrorAction SilentlyContinue
