# PostToolUse hook: non-blocking advisories injected into the model context.
# Heuristic checks that have false positives, so they NEVER block - they just remind
# Claude to verify. Emitted via hookSpecificOutput.additionalContext (exit 0 always).
#   1. CSRF: a .php that reads $_POST but has no verifyCsrfToken() call.
#   2. New page: a top-level pages/<slug>.php whose slug is missing from page-permissions.json.
#   3. Schema: an edit to scripts/create-tables.sql (needs a production ALTER TABLE migration).
# ASCII-only on purpose (Windows PowerShell 5.1 misparses BOM-less UTF-8).
$ErrorActionPreference = 'SilentlyContinue'

$raw = [Console]::In.ReadToEnd()
if (-not $raw) { exit 0 }
try { $data = $raw | ConvertFrom-Json } catch { exit 0 }

$fp = $data.tool_input.file_path
if (-not $fp) { exit 0 }

$notes = @()

# Full post-edit file content (file is already written at PostToolUse time)
$full = ""
if (Test-Path -LiteralPath $fp) { $full = Get-Content -Raw -LiteralPath $fp }

# Skip deploy mirror / worktree copies to avoid duplicate noise
$isMirror = ($fp -match 'public_html' -or $fp -match 'worktrees')

# --- 1. CSRF on POST-handling PHP ---
if ($fp -match '\.php$' -and -not $isMirror -and $full) {
    if ($full -match '\$_POST\[' -and $full -notmatch 'verifyCsrfToken') {
        $notes += "CSRF check: this PHP reads \$_POST but no verifyCsrfToken() call was found. Confirm CSRF is verified (directly or via an included file) per CLAUDE.md."
    }
}

# --- 2. New page missing from permission registry ---
if ($fp -match '[\\/]master[\\/]pages[\\/]([^\\/_][^\\/]*)\.php$') {
    $slug = $matches[1] + '.php'
    $permFile = 'C:\Claude\master\config\page-permissions.json'
    if (Test-Path -LiteralPath $permFile) {
        try {
            $perm = Get-Content -Raw -LiteralPath $permFile | ConvertFrom-Json
            $keys = @($perm.permissions.PSObject.Properties.Name)
            if ($keys -notcontains $slug) {
                $notes += "Page permission: '$slug' is not registered in config/page-permissions.json. If this is a new page, add view/edit permissions there (CLAUDE.md / new-page rule)."
            }
        } catch {}
    }
}

# --- 3. Schema change needs production ALTER TABLE ---
if ($fp -match 'create-tables\.sql$' -and -not $isMirror) {
    $notes += "Schema change: scripts/create-tables.sql was edited. DB_MODE=db means this does NOT reach production by itself - create and run an ALTER TABLE migration script against production MySQL (CLAUDE.md)."
}

if ($notes.Count -gt 0) {
    $payload = @{
        hookSpecificOutput = @{
            hookEventName     = "PostToolUse"
            additionalContext = ($notes -join "`n")
        }
    }
    $payload | ConvertTo-Json -Compress -Depth 5
}
exit 0
