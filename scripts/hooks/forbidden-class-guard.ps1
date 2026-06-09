# PostToolUse hook: block edits that introduce forbidden / legacy CSS class tokens
# into new pages. Rules:
#   (A) "form-control" is forbidden everywhere (CSS-undefined, see CLAUDE.md).
#   (B) Legacy independent classes tracked in docs/ui-legacy-classes.md
#       (ct-*, employee-table, hub-modal, ...) must NOT be introduced in new pages.
#       Existing legacy pages are exempted while they are migrated page-by-page.
# Only inspects the NEW text being written (not pre-existing content elsewhere in the file),
# and only for code files (.php/.js/.css/.html) so doc files that mention the class are exempt.
# ASCII-only on purpose (Windows PowerShell 5.1 misparses BOM-less UTF-8).
$ErrorActionPreference = 'SilentlyContinue'

$raw = [Console]::In.ReadToEnd()
if (-not $raw) { exit 0 }
try { $data = $raw | ConvertFrom-Json } catch { exit 0 }

$fp = $data.tool_input.file_path
if (-not $fp) { exit 0 }
if ($fp -notmatch '\.(php|js|css|html|htm)$') { exit 0 }

# New text only: Edit=new_string / Write=content / MultiEdit=edits[].new_string
$content = ""
if ($data.tool_input.new_string) { $content += "`n" + $data.tool_input.new_string }
if ($data.tool_input.content)    { $content += "`n" + $data.tool_input.content }
if ($data.tool_input.edits) {
    foreach ($e in $data.tool_input.edits) { $content += "`n" + $e.new_string }
}
if (-not $content) { exit 0 }

# ----- (A) form-control: forbidden everywhere -----
# Match "form-control" as a whole class token (not form-control-foo, form-controls, etc.)
if ($content -match 'form-control(?![-\w])') {
    [Console]::Error.WriteLine("[forbidden-class] 'form-control' is forbidden (CSS-undefined). Use 'form-input' with a 'form-group' wrapper. See CLAUDE.md.")
    exit 2
}

# ----- (B) Legacy classes: blocked in NEW pages, allowed in tracked legacy pages -----
# Normalise the file path so we can match regardless of slash style.
$fpNorm = $fp.Replace('\', '/').ToLower()

# Pages that already use these legacy classes (per docs/ui-legacy-classes.md) and will be
# migrated page-by-page. Edits to them keep working.
$legacyAllowed = @(
    'pages/contacts.php',
    'pages/employees.php',
    'pages/finance.php',
    'pages/search.php',
    'pages/company-rules.php',
    'pages/manuals.php',
    'pages/slides.php',
    'pages/reports-hub.php'
)
# Also allow the docs themselves and any file under pages/reports-hub/* (legacy hub tabs).
$isAllowed = $false
foreach ($a in $legacyAllowed) {
    if ($fpNorm.EndsWith($a)) { $isAllowed = $true; break }
}
if (-not $isAllowed -and $fpNorm -match '/pages/reports-hub/') { $isAllowed = $true }
if (-not $isAllowed -and $fpNorm -match '/docs/ui-legacy-classes\.md$') { $isAllowed = $true }
# Templates intentionally describe the canonical alternatives; exempt them too.
if (-not $isAllowed -and $fpNorm -match '/pages/_template-[a-z]+\.php$') { $isAllowed = $true }

if (-not $isAllowed) {
    # Each entry: token (matched as whole class), canonical replacement.
    $legacy = @(
        @{ token = 'ct-search';          fix = 'form-input' },
        @{ token = 'f-input';            fix = 'form-input' },
        @{ token = 'cell-input';         fix = 'form-input' },
        @{ token = 'search-page-input';  fix = 'form-input' },
        @{ token = 'rules-search-input'; fix = 'form-input' },
        @{ token = 'ct-table';           fix = 'data-table' },
        @{ token = 'employee-table';     fix = 'data-table' },
        @{ token = 'inv-check-tbl';      fix = 'data-table' },
        @{ token = 'audit-table';        fix = 'data-table' },
        @{ token = 'bulk-input-style';   fix = 'btn btn-secondary' },
        @{ token = 'bulk-remove-btn';    fix = 'btn btn-danger' },
        @{ token = 'search-filter-btn';  fix = 'btn btn-sm btn-secondary' },
        @{ token = 'month-btn';          fix = 'btn btn-sm' },
        @{ token = 'month-btn-size';     fix = 'btn btn-sm' },
        @{ token = 'contact-modal-bg';   fix = 'modal' },
        @{ token = 'modal-box';          fix = 'modal-content' },
        @{ token = 'modal-head';         fix = 'modal-header' },
        @{ token = 'modal-foot';         fix = 'modal-footer' },
        @{ token = 'rules-edit-modal';   fix = 'modal' },
        @{ token = 'form-modal';         fix = 'modal' },
        @{ token = 'slide-modal';        fix = 'modal' },
        @{ token = 'confirm-list-modal'; fix = 'modal' },
        @{ token = 'hub-modal';          fix = 'modal' },
        @{ token = 'hub-modal-header';   fix = 'modal-header' }
    )
    foreach ($e in $legacy) {
        # Whole-class match: token is not preceded/followed by another class-name character.
        $pattern = "(?<![-\w])" + [Regex]::Escape($e.token) + "(?![-\w])"
        if ($content -match $pattern) {
            [Console]::Error.WriteLine("[forbidden-class] '$($e.token)' is a legacy class tracked in docs/ui-legacy-classes.md and must not be introduced in new pages. Use '$($e.fix)' instead.")
            exit 2
        }
    }
}

exit 0
