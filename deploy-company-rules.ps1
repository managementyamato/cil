param([switch]$Force)

$projectDir = "C:\Claude\master"
$localDir   = "$projectDir\public_html"
$winscp     = "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com"
$credFile   = "$projectDir\.ftp-credentials"
$php        = "C:\xampp\php\php.exe"

$targetFiles = @("pages\company-rules.php", "api\company-rules.php")

Write-Host "Checking PHP syntax..."
foreach ($rel in $targetFiles) {
    $result = & $php -l "$projectDir\$rel" 2>&1
    if ($LASTEXITCODE -ne 0) { Write-Host "ABORT: $rel" -ForegroundColor Red; Write-Host $result; exit 1 }
    Write-Host "  OK: $rel"
}

$pass = ""
Get-Content $credFile | ForEach-Object { if ($_ -match "^FTP_PASS=(.+)$") { $pass = $Matches[1] } }
if (-not $pass) { Write-Host "ERROR: Password not found" -ForegroundColor Red; exit 1 }

foreach ($rel in $targetFiles) {
    Copy-Item "$projectDir\$rel" "$localDir\$rel" -Force
    Write-Host "Copied: $rel"
}

$puts = $targetFiles | ForEach-Object { "put `"$localDir\$_`" `"/$($_.Replace('\','/'))`"" }
$script = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch abort
option confirm off
option transfer binary
$($puts -join "`n")
close
exit
"@
$sf = "$env:TEMP\winscp_rules_deploy.txt"
$script | Out-File -Encoding ASCII $sf
& $winscp /script="$sf"
$code = $LASTEXITCODE
Remove-Item $sf -EA SilentlyContinue

if ($code -eq 0) { Write-Host "Deploy complete!" -ForegroundColor Green } else { Write-Host "Deploy FAILED" -ForegroundColor Red; exit 1 }
