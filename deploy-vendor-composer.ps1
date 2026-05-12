#!/usr/bin/env pwsh
# vendor/composer のみアップロード（dev依存除外の再生成後）
$ErrorActionPreference = "Stop"
$winscp = "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com"
$projectDir = "C:\Claude\master"

$pass = ""
Get-Content "$projectDir\.ftp-credentials" | ForEach-Object {
    if ($_ -match "^FTP_PASS=(.+)$") { $pass = $Matches[1] }
}
if (-not $pass) { Write-Host "FTP_PASS not found" -ForegroundColor Red; exit 1 }

$script = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch abort
option confirm off
option transfer binary
put "$projectDir\vendor\autoload.php" "/vendor/autoload.php"
synchronize remote "$projectDir\vendor\composer" "/vendor/composer"
close
exit
"@
$scriptFile = "$env:TEMP\winscp_composer.txt"
$script | Out-File -Encoding ASCII $scriptFile
& $winscp /script="$scriptFile" /log="$projectDir\vendor-composer-deploy.log"
$code = $LASTEXITCODE
Remove-Item $scriptFile -ErrorAction SilentlyContinue
if ($code -eq 0) { Write-Host "composer/ upload complete!" -ForegroundColor Green } else { Write-Host "failed" -ForegroundColor Red; exit $code }
