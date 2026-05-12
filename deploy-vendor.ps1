#!/usr/bin/env pwsh
# vendor/ のうち本番で必要なディレクトリだけFTPアップロード
# phpunit/sebastian/nikic/phar-io/theseer 等の dev 依存は除外

$ErrorActionPreference = "Stop"
$winscp = "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com"
$projectDir = "C:\Claude\master"

# FTP password
$pass = ""
$credFile = "$projectDir\.ftp-credentials"
Get-Content $credFile | ForEach-Object {
    if ($_ -match "^FTP_PASS=(.+)$") { $pass = $Matches[1] }
}
if (-not $pass) {
    Write-Host "FTP_PASS not set in .ftp-credentials" -ForegroundColor Red
    exit 1
}

Write-Host "Uploading vendor/ (production subset) to xserver..."

$script = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch abort
option confirm off
option transfer binary
option failonnomatch off
put "$projectDir\vendor\autoload.php" "/vendor/autoload.php"
put -filemask="|*.md;*.markdown;tests/;doc/;docs/;examples/" "$projectDir\vendor\composer" "/vendor/"
put -filemask="|*.md;*.markdown;tests/;doc/;docs/;examples/" "$projectDir\vendor\ezyang" "/vendor/"
put -filemask="|*.md;*.markdown;tests/;doc/;docs/;examples/" "$projectDir\vendor\maennchen" "/vendor/"
put -filemask="|*.md;*.markdown;tests/;doc/;docs/;examples/" "$projectDir\vendor\markbaker" "/vendor/"
put -filemask="|*.md;*.markdown;tests/;doc/;docs/;examples/" "$projectDir\vendor\myclabs" "/vendor/"
put -filemask="|*.md;*.markdown;tests/;doc/;docs/;examples/" "$projectDir\vendor\phpoffice" "/vendor/"
put -filemask="|*.md;*.markdown;tests/;doc/;docs/;examples/" "$projectDir\vendor\psr" "/vendor/"
close
exit
"@

$scriptFile = "$env:TEMP\winscp_vendor.txt"
$script | Out-File -Encoding ASCII $scriptFile

& $winscp /script="$scriptFile" /log="$projectDir\vendor-deploy.log"
$code = $LASTEXITCODE

Remove-Item $scriptFile -ErrorAction SilentlyContinue

if ($code -eq 0) {
    Write-Host "vendor/ upload complete!" -ForegroundColor Green
} else {
    Write-Host "vendor/ upload failed (exit=$code). See vendor-deploy.log" -ForegroundColor Red
    exit $code
}
