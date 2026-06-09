# PostToolUse hook: run `php -l` on edited .php files; exit 2 (block) on syntax error.
# Silent on success. Enforces "every PHP edit is syntax-checked automatically".
# ASCII-only on purpose: Windows PowerShell 5.1 misparses BOM-less UTF-8 multibyte chars.
$ErrorActionPreference = 'SilentlyContinue'

$raw = [Console]::In.ReadToEnd()
if (-not $raw) { exit 0 }
try { $data = $raw | ConvertFrom-Json } catch { exit 0 }

$fp = $data.tool_input.file_path
if (-not $fp) { exit 0 }
if ($fp -notmatch '\.php$') { exit 0 }
if (-not (Test-Path -LiteralPath $fp)) { exit 0 }

$php = 'C:\xampp\php\php.exe'
$ini = 'C:\Claude\master\lib\php.ini'
if (-not (Test-Path -LiteralPath $php)) { exit 0 }

if (Test-Path -LiteralPath $ini) {
    $out = & $php -c $ini -l $fp 2>&1
} else {
    $out = & $php -l $fp 2>&1
}

if ($LASTEXITCODE -ne 0) {
    [Console]::Error.WriteLine("[php-lint] PHP syntax error in: $fp")
    [Console]::Error.WriteLine(($out | Out-String).Trim())
    exit 2
}
exit 0
