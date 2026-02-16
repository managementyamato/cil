Write-Host "Restoring data files to Xserver..."

$projectDir = "C:\Claude\master"
$winscp = "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com"
$pass = ""
Get-Content "$projectDir\.ftp-credentials" | ForEach-Object {
    if ($_ -match "^FTP_PASS=(.+)$") { $pass = $Matches[1] }
}

$script = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch abort
option confirm off
option transfer binary
cd /yamato-mgt.com/public_html
put "$projectDir\public_html\config\users.json" config/
put "$projectDir\public_html\config\notification-config.json" config/
put "$projectDir\public_html\config\photo-attendance-data.json" config/
put "$projectDir\public_html\config\mf-config.json" config/
put "$projectDir\public_html\config\mf-sync-config.json" config/
put "$projectDir\public_html\config\integration-config.json" config/
put "$projectDir\public_html\config\google-config.json" config/
put "$projectDir\public_html\config\alcohol-chat-config.json" config/
put "$projectDir\public_html\config\alcohol-sync-log.json" config/
put "$projectDir\public_html\config\loans-drive-config.json" config/
put "$projectDir\public_html\config\pdf-sources.json" config/
put "$projectDir\public_html\config\spreadsheet-sources.json" config/
put "$projectDir\public_html\data\data.json" data/
put "$projectDir\public_html\data\audit-log.json" data/
put "$projectDir\public_html\data\loans.json" data/
put "$projectDir\public_html\data\integration-log.json" data/
put "$projectDir\public_html\data\background-jobs.json" data/
close
exit
"@
$scriptFile = "$env:TEMP\winscp_restore.txt"
$script | Out-File -Encoding ASCII $scriptFile

& $winscp /script="$scriptFile" /log="$projectDir\restore.log"

if ($LASTEXITCODE -eq 0) {
    Write-Host "Data restore complete!"
} else {
    Write-Host "Restore FAILED. Check restore.log"
}
Remove-Item $scriptFile -ErrorAction SilentlyContinue
