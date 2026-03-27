param([switch]$Force)

$projectDir = "C:\Claude\master"
$localDir   = "$projectDir\public_html"
$winscp     = "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com"
$credFile   = "$projectDir\.ftp-credentials"
$php        = "C:\xampp\php\php.exe"

$pass = ""
Get-Content $credFile | ForEach-Object { if ($_ -match "^FTP_PASS=(.+)$") { $pass = $Matches[1] } }
if (-not $pass) { Write-Host "ERROR: Password not found" -ForegroundColor Red; exit 1 }

$result = & $php -l "$projectDir\api\rules-image-upload.php" 2>&1
if ($LASTEXITCODE -ne 0) { Write-Host "ABORT: syntax error" -ForegroundColor Red; Write-Host $result; exit 1 }
Write-Host "PHP syntax OK"

Copy-Item "$projectDir\api\rules-image-upload.php" "$localDir\api\rules-image-upload.php" -Force
Write-Host "Copied: api/rules-image-upload.php"

$rulesUploadDir = "$localDir\uploads\rules"
if (-not (Test-Path $rulesUploadDir)) {
    New-Item -ItemType Directory -Path $rulesUploadDir -Force | Out-Null
}
$htaccess = "$rulesUploadDir\.htaccess"
if (-not (Test-Path $htaccess)) {
    "Options -Indexes" | Out-File -Encoding ASCII $htaccess
}

$sf = [System.IO.Path]::GetTempFileName()
$lines = @(
    "open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on",
    "option confirm off",
    "option transfer binary",
    "option batch abort",
    "put `"$localDir\api\rules-image-upload.php`" `"/api/rules-image-upload.php`"",
    "option batch continue",
    "mkdir `"/uploads/rules`"",
    "option batch abort",
    "put `"$htaccess`" `"/uploads/rules/.htaccess`"",
    "close",
    "exit"
)
[System.IO.File]::WriteAllLines($sf, $lines, [System.Text.Encoding]::ASCII)
& $winscp /script="$sf"
$code = $LASTEXITCODE
Remove-Item $sf -EA SilentlyContinue

if ($code -eq 0) { Write-Host "Deploy complete!" -ForegroundColor Green } else { Write-Host "Deploy FAILED" -ForegroundColor Red; exit 1 }
