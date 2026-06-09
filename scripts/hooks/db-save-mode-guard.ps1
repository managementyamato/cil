# PreToolUse hook: block any edit that flips DB_SAVE_MODE to "upsert".
# Guardrail for CLAUDE.md rule #0 (never change DB_SAVE_MODE).
# Caused production regressions twice (2026-05-11 / 2026-05-12).
# ASCII-only on purpose: Windows PowerShell 5.1 misparses BOM-less UTF-8 multibyte chars.
$ErrorActionPreference = 'SilentlyContinue'

$raw = [Console]::In.ReadToEnd()
if (-not $raw) { exit 0 }
try { $data = $raw | ConvertFrom-Json } catch { exit 0 }

$fp = $data.tool_input.file_path
if (-not $fp) { exit 0 }

# Aggregate post-edit text: Edit=new_string / Write=content / MultiEdit=edits[].new_string
$content = ""
if ($data.tool_input.new_string) { $content += "`n" + $data.tool_input.new_string }
if ($data.tool_input.content)    { $content += "`n" + $data.tool_input.content }
if ($data.tool_input.edits) {
    foreach ($e in $data.tool_input.edits) { $content += "`n" + $e.new_string }
}
if (-not $content) { exit 0 }

$danger = $false
# 1) DEFAULT_MODE = 'upsert'
if ($content -match "DEFAULT_MODE\s*=\s*['""]upsert['""]") { $danger = $true }
# 2) env('DB_SAVE_MODE', 'upsert') default flipped to upsert
if ($content -match "DB_SAVE_MODE['""]\s*,\s*['""]upsert['""]") { $danger = $true }
# 3) .env: DB_SAVE_MODE=upsert
if ($fp -match '\.env' -and $content -match "DB_SAVE_MODE\s*=\s*upsert") { $danger = $true }

if ($danger) {
    [Console]::Error.WriteLine("[DB_SAVE_MODE GUARD] Suspected violation of CLAUDE.md rule #0: switching DB_SAVE_MODE to upsert.")
    [Console]::Error.WriteLine("This change caused production regressions on 2026-05-11 and 2026-05-12.")
    [Console]::Error.WriteLine("Do NOT change it without the staging verification steps in docs/db-save-mode-history.md.")
    exit 2
}
exit 0
