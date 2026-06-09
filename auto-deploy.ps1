param(
    [switch]$Force,    # 確認プロンプトをスキップ
    [switch]$SkipTests, # PHPUnit テストをスキップ（緊急時のみ）
    [switch]$SkipE2E   # Playwright E2E テストをスキップ（ローカル MySQL/PHP サーバーが無い時用）
)

Write-Host "========================================"
Write-Host "  XSERVER FTP Deploy (Auto)"
Write-Host "  Domain: yamato-mgt.com"
Write-Host "========================================"
Write-Host ""

$projectDir = "C:\Claude\master"
$localDir = "$projectDir\public_html"
$winscp = "$env:LOCALAPPDATA\Programs\WinSCP\WinSCP.com"
$credFile = "$projectDir\.ftp-credentials"
$php = "C:\xampp\php\php.exe"

# 本番から物理削除するファイル一覧
# synchronize は remote の不要ファイルを消さないため、明示的に rm する
$productionRemovals = @(
    "/pages/chat.php",
    "/api/chat.php",
    "/js/chat.js",
    "/pages/morning-meeting.php",
    "/api/morning-meeting.php",
    "/pages/leads.php",
    "/pages/weekly-reports.php",
    "/pages/discount-approvals.php",
    "/pages/reminders.php",
    "/pages/workflows.php",
    "/pages/trouble-analysis.php",
    "/pages/download-invoices-csv.php",
    "/pages/print-invoice.php",
    "/api/pages/invoices-data.php",
    "/pages/test-clickjacking.php",
    "/pages/color-samples.php",
    "/mf-sync-debug.json",
    "/mf-api-debug.json",
    "/logs/partners-debug.json",
    "/functions/pj-ledger-data.php",
    "/functions/recurring-invoice.php",
    "/functions/excel-invoice-generator.php",
    # 2026-05-13: 4機能を削除 (案件管理・請求金額分析・プロジェクト管理スプシ連携)
    # NOTE: /pages/price-list.php は v2 で復活したため productionRemovals から除外
    "/pages/pipeline.php",
    "/api/pipeline-api.php",
    "/api/price-list-api.php",
    "/pages/pj-invoice-analysis.php",
    "/api/spreadsheet-projects.php",
    # git log --diff-filter=D で検出した未除去ファイル
    "/pages/mf-invoice-list.php",
    "/pages/pj-ledger.php",
    # 2026-05-15: 指定請求書機能を削除
    "/pages/custom-invoice-list.php",
    "/pages/custom-invoice-create.php",
    "/pages/custom-invoice-manual.php",
    "/api/custom-invoice-api.php",
    "/functions/custom-invoice-generator.php",
    "/config/custom-invoice-drive-config.json",
    # 2026-06-05: 営業ツールの 価格表 タブを廃止 (価格表マスタは master-hub に集約)
    "/pages/sales-tools/tabs/pricing.php",
    "/api/price-list-sync.php"
    # 以下は削除しないこと（メール承認リンクの行き先など、まだ参照されている）:
    # /api/discount-approval-action.php     ← メール承認/却下リンクの行き先
    # /api/discount-approvals.php           ← 旧版・削除済だが残しておく
    # /api/reminders-api.php                ← 削除済だが残しておく
    # /api/workflows-api.php                ← 削除済だが残しておく
    # /api/weekly-reports.php               ← 削除済だが残しておく
    # /api/upload-weekly-image.php          ← まだ使用中
)

# ============================================================
# [0/4] Pre-deploy checks
# ============================================================
Write-Host "[0/4] Pre-deploy checks..."

# --- PHP文法チェック ---
Write-Host "  Checking PHP syntax..."
$syntaxErrors = @()
$phpFiles = Get-ChildItem -Path $projectDir -Recurse -Include "*.php" -File |
    Where-Object { $_.FullName -notmatch '\\vendor\\' -and
                   $_.FullName -notmatch '\\public_html\\' -and
                   $_.FullName -notmatch '\\node_modules\\' }

foreach ($file in $phpFiles) {
    $result = & $php -l $file.FullName 2>&1
    if ($LASTEXITCODE -ne 0) {
        $syntaxErrors += "  [SYNTAX] $($file.FullName -replace [regex]::Escape($projectDir), '.')"
        $syntaxErrors += "           $result"
    }
}

if ($syntaxErrors.Count -gt 0) {
    Write-Host ""
    Write-Host "ABORT: PHP syntax errors found:" -ForegroundColor Red
    $syntaxErrors | ForEach-Object { Write-Host $_ -ForegroundColor Red }
    Write-Host ""
    Write-Host "Fix the errors above before deploying."
    exit 1
}
Write-Host "  PHP syntax OK ($($phpFiles.Count) files checked)"

# --- JS文法チェック ---
Write-Host "  Checking JS syntax..."
$nodeCmd = (Get-Command node -ErrorAction SilentlyContinue)
if ($nodeCmd) {
    $jsErrors = @()
    $jsCheckedCount = 0

    # js/ フォルダの .js ファイル
    $jsFiles = Get-ChildItem -Path "$projectDir\js" -Filter "*.js" -File -ErrorAction SilentlyContinue
    foreach ($file in $jsFiles) {
        $result = & node --check $file.FullName 2>&1
        $jsCheckedCount++
        if ($LASTEXITCODE -ne 0) {
            $relPath = $file.FullName -replace [regex]::Escape($projectDir), '.'
            $jsErrors += "  [SYNTAX] $relPath"
            $result | Where-Object { $_ -match 'SyntaxError|already been declared|Unexpected' } |
                Select-Object -First 2 | ForEach-Object { $jsErrors += "           $_" }
        }
    }

    # PHP内埋め込みJSはスコープ解析なしでは誤検知が多いためスキップ
    # PHPファイル内JSのSyntaxError防止は別途コードレビューで対応

    if ($jsErrors.Count -gt 0) {
        Write-Host ""
        Write-Host "ABORT: JS syntax errors found:" -ForegroundColor Red
        $jsErrors | ForEach-Object { Write-Host $_ -ForegroundColor Red }
        Write-Host ""
        Write-Host "Fix the errors above before deploying."
        exit 1
    }
    Write-Host "  JS syntax OK ($jsCheckedCount blocks checked)"
} else {
    Write-Host "  JS syntax check SKIPPED (node not found)" -ForegroundColor Yellow
}

# --- PHPUnitテスト ---
if ($SkipTests) {
    Write-Host "  Tests SKIPPED (-SkipTests specified)" -ForegroundColor Yellow
} else {
    Write-Host "  Running tests..."
    $testOutput = & $php vendor/bin/phpunit --no-coverage 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host ""
        Write-Host "ABORT: Tests failed:" -ForegroundColor Red
        $testOutput | Select-Object -Last 20 | ForEach-Object { Write-Host $_ -ForegroundColor Red }
        Write-Host ""
        Write-Host "Fix failing tests before deploying. To skip (emergency only): -SkipTests"
        exit 1
    }
    # テスト結果のサマリー行だけ表示
    $summary = $testOutput | Select-String -Pattern "^(OK|Tests:|FAILURES)" | Select-Object -Last 1
    Write-Host "  Tests OK: $summary"
}

# --- Playwright E2E テスト ---
if ($SkipE2E) {
    Write-Host "  E2E SKIPPED (-SkipE2E specified)" -ForegroundColor Yellow
} else {
    Write-Host "  Running Playwright E2E tests..."

    # 前提チェック: node / npm / Playwright インストール / ローカルサーバー起動
    $skipReason = $null
    if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
        $skipReason = "npm not found"
    }
    elseif (-not (Test-Path "$projectDir\node_modules\@playwright\test")) {
        $skipReason = "Playwright not installed (run: npm install && npx playwright install chromium)"
    }
    else {
        # localhost:8000 が LISTEN しているか TCP レベルで確認 (IPv4/IPv6 両対応)
        # 注: $host は PowerShell の予約変数なので使わない
        $portOpen = $false
        foreach ($addrStr in @('127.0.0.1', '::1')) {
            try {
                $ip = [System.Net.IPAddress]::Parse($addrStr)
                $tcp = New-Object System.Net.Sockets.TcpClient($ip.AddressFamily)
                $async = $tcp.BeginConnect($ip, 8000, $null, $null)
                if ($async.AsyncWaitHandle.WaitOne(1000, $false) -and $tcp.Connected) {
                    $portOpen = $true
                    $tcp.Close()
                    break
                }
                $tcp.Close()
            } catch { }
        }

        if (-not $portOpen) {
            $skipReason = "Local PHP server not responding at localhost:8000 (start with: scripts/php.exe -S localhost:8000 router.php)"
        }
    }

    if ($skipReason) {
        Write-Host "  E2E SKIPPED: $skipReason" -ForegroundColor Yellow
        Write-Host "  (Use -SkipE2E to silence this warning in CI)" -ForegroundColor DarkGray
    } else {
        Push-Location $projectDir
        $e2eOutput = & npm test 2>&1
        $e2eExit = $LASTEXITCODE
        Pop-Location

        if ($e2eExit -ne 0) {
            Write-Host ""
            Write-Host "ABORT: E2E tests failed:" -ForegroundColor Red
            $e2eOutput | Select-Object -Last 30 | ForEach-Object { Write-Host $_ -ForegroundColor Red }
            Write-Host ""
            Write-Host "Fix failing E2E tests before deploying. To skip (emergency only): -SkipE2E"
            exit 1
        }
        # サマリ行だけ表示
        $passSummary = $e2eOutput | Select-String -Pattern "passed|failed" | Select-Object -Last 1
        Write-Host "  E2E OK: $passSummary"
    }
}

# --- 変更ファイルの表示 ---
Write-Host ""
Write-Host "  Changes since last deploy:"
$lastTag = git tag --sort=-version:refname | Where-Object { $_ -match '^deploy/' } | Select-Object -First 1
if ($lastTag) {
    $changedFiles = git diff --name-only "$lastTag" HEAD -- api/ pages/ functions/ js/ css/ config/ index.php style.css .htaccess 2>$null
    if ($changedFiles) {
        $changedFiles | ForEach-Object { Write-Host "    M $_" -ForegroundColor Cyan }
    } else {
        Write-Host "    (no changes since last deploy)" -ForegroundColor Gray
    }
    Write-Host "  Last deploy: $lastTag"
} else {
    Write-Host "    (no previous deploy tag found - first deploy)" -ForegroundColor Gray
}

Write-Host ""

# --- 確認プロンプト ---
if (-not $Force) {
    $confirm = Read-Host "Deploy to https://yamato-mgt.com/ ? [y/N]"
    if ($confirm -notmatch '^[yY]$') {
        Write-Host "Cancelled."
        exit 0
    }
}

Write-Host ""

# ============================================================
# Read FTP password
# ============================================================
$pass = ""
Get-Content $credFile | ForEach-Object {
    if ($_ -match "^FTP_PASS=(.+)$") { $pass = $Matches[1] }
}
if (-not $pass) {
    Write-Host "ERROR: Password not found in .ftp-credentials"
    exit 1
}

# ============================================================
# [1/4] Backup server data
# ============================================================
Write-Host "[1/4] Backing up server data..."
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backupDir = "$projectDir\backups\$timestamp"
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null

$backupScript = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch continue
option confirm off
option transfer binary
get "/config/users.json" "$backupDir\users.json"
get "/config/google-config.json" "$backupDir\google-config.json"
get "/config/mf-config.json" "$backupDir\mf-config.json"
get "/config/photo-attendance-data.json" "$backupDir\photo-attendance-data.json"
close
exit
"@
$backupScriptFile = "$env:TEMP\winscp_backup.txt"
$backupScript | Out-File -Encoding ASCII $backupScriptFile
& $winscp /script="$backupScriptFile" 2>&1 | Out-Null
Remove-Item $backupScriptFile -ErrorAction SilentlyContinue

$backupCount = (Get-ChildItem $backupDir -File).Count
Write-Host "Done. $backupCount files saved to backups\$timestamp"
Write-Host ""

# Keep only last 10 backups
$allBackups = Get-ChildItem "$projectDir\backups" -Directory | Sort-Object Name -Descending | Select-Object -Skip 10
foreach ($old in $allBackups) { Remove-Item $old.FullName -Recurse -Force }

# ============================================================
# [2/4] Sync source to public_html
# ============================================================
Write-Host "[2/4] Syncing to public_html..."
$copies = @("api","forms","functions","pages","lib","js","css")
foreach ($dir in $copies) {
    if (Test-Path "$projectDir\$dir") {
        xcopy /E /I /Y "$projectDir\$dir" "$localDir\$dir" | Out-Null
    }
}
# セキュリティ: テストページ・デバッグページを本番から除外
Remove-Item "$localDir\pages\test-*.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\color-samples.php" -Force -ErrorAction SilentlyContinue
# モック（方向性確認用・本番非公開）
Remove-Item "$localDir\pages\*-mock.php" -Force -ErrorAction SilentlyContinue
# バックアップファイルも除外
Remove-Item "$localDir\pages\*.backup" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\*.corrupted" -Force -ErrorAction SilentlyContinue
# チャット機能（削除済み）
Remove-Item "$localDir\pages\chat.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\api\chat.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\js\chat.js" -Force -ErrorAction SilentlyContinue
# 朝礼TODO（非公開）
Remove-Item "$localDir\pages\morning-meeting.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\api\morning-meeting.php" -Force -ErrorAction SilentlyContinue
# リード管理（いったん非公開）
Remove-Item "$localDir\pages\leads.php" -Force -ErrorAction SilentlyContinue
# 削除済みページ
Remove-Item "$localDir\pages\weekly-reports.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\discount-approvals.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\reminders.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\workflows.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\trouble-analysis.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\api\reminders-api.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\api\workflows-api.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\api\weekly-reports.php" -Force -ErrorAction SilentlyContinue
# 2026-06-05: 営業ツールの 価格表 タブ廃止 (xcopy は削除を伝播しないため明示的に rm)
Remove-Item "$localDir\pages\sales-tools\tabs\pricing.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\api\price-list-sync.php" -Force -ErrorAction SilentlyContinue
# upload-weekly-image.php は reports-hub の添付機能で使用中のため削除しない
# discount-approval-action.php はメール承認/却下リンクの行き先で現役のため deploy 対象に含める（削除しない）
# 値引き承認API（reports-hub-apiに統合済み）
Remove-Item "$localDir\api\discount-approvals.php" -Force -ErrorAction SilentlyContinue
# 請求書作成システム（いったん非公開）※sync/clearは公開済み
Remove-Item "$localDir\pages\download-invoices-csv.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\pages\print-invoice.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$localDir\api\pages\invoices-data.php" -Force -ErrorAction SilentlyContinue

if (Test-Path "$projectDir\config\*.php") { Copy-Item "$projectDir\config\*.php" "$localDir\config\" -Force }
# config/db/ (DB アダプタ責務別クラス: JsonColumnHandler / DualModeAdapter / DBSaveModeManager) を再帰コピー
if (Test-Path "$projectDir\config\db") { xcopy /E /I /Y "$projectDir\config\db" "$localDir\config\db" | Out-Null }
# Exclude database.php unless DB_MODE is explicitly set to non-json
# 検出順: .env.local (ローカル開発設定) → .env (デフォルト)
# UTF-8 で読み込む（日本語コメントによる文字化け回避）
$dbMode = $null
$envCandidates = @("$projectDir\.env.local", "$projectDir\.env")
foreach ($envPath in $envCandidates) {
    if ($dbMode) { break }
    if (Test-Path $envPath) {
        $envLine = Get-Content $envPath -Encoding UTF8 | Where-Object { $_ -match '^DB_MODE=' }
        if ($envLine) { $dbMode = ($envLine -replace 'DB_MODE=','').Trim() }
    }
}
if ($dbMode -and $dbMode -ne 'json') {
    Write-Host "  DB_MODE=${dbMode}: including database.php in deploy" -ForegroundColor Yellow
} else {
    Remove-Item "$localDir\config\database.php" -Force -ErrorAction SilentlyContinue
    Write-Host "  Excluded database.php from deploy (DB_MODE=json)"
}
if (Test-Path "$projectDir\config\spreadsheet-sources.json") { Copy-Item "$projectDir\config\spreadsheet-sources.json" "$localDir\config\" -Force }
Copy-Item "$projectDir\index.php" "$localDir\" -Force
Copy-Item "$projectDir\style.css" "$localDir\" -Force
if (Test-Path "$projectDir\app.js") { Copy-Item "$projectDir\app.js" "$localDir\" -Force }
if (Test-Path "$projectDir\.htaccess") { Copy-Item "$projectDir\.htaccess" "$localDir\" -Force }
if (Test-Path "$projectDir\favicon.png") { Copy-Item "$projectDir\favicon.png" "$localDir\" -Force }
Write-Host "Done."
Write-Host ""

# ============================================================
# [3/4] Upload via FTP
# ============================================================
Write-Host "[3/4] Uploading via FTP..."

# --- Step A: 削除 (rm) ---
# 既に存在しないファイルへの rm は失敗しても無視（WinSCP が exit 1 を返す偽陽性を防止）
if ($productionRemovals.Count -gt 0) {
    $rmLines = ($productionRemovals | ForEach-Object { "rm $_" }) -join "`n"
    $rmScript = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch continue
option confirm off
$rmLines
close
exit
"@
    $rmScriptFile = "$env:TEMP\winscp_rm.txt"
    $rmScript | Out-File -Encoding ASCII $rmScriptFile
    & $winscp /script="$rmScriptFile" /log="$projectDir\deploy.log" /append
    # rm の終了コードは無視（ファイルが既に存在しない場合に 1 が返るため）
    Remove-Item $rmScriptFile -ErrorAction SilentlyContinue
}

# --- Step B: 同期 (synchronize) ---
$script = @"
open ftp://management%40yamato-mgt.com:$pass@sv2304.xserver.jp/ -passive=on
option batch abort
option confirm off
option transfer binary
synchronize remote -filemask="|.env;.env.local;.env.example;data.json;users.json;*.token.json;alcohol-sync-log.json;photo-attendance-data.json;mf-config.json;google-config.json;loans-drive-config.json;uploads/" "$localDir" "/"
close
exit
"@
$scriptFile = "$env:TEMP\winscp_sync.txt"
$script | Out-File -Encoding ASCII $scriptFile

& $winscp /script="$scriptFile" /log="$projectDir\deploy.log" /append
$deployExitCode = $LASTEXITCODE

Remove-Item $scriptFile -ErrorAction SilentlyContinue

if ($deployExitCode -eq 0) {
    # デプロイ成功: Gitタグを記録
    $deployTag = "deploy/$timestamp"
    git tag $deployTag 2>$null
    Write-Host ""
    Write-Host "========================================"
    Write-Host "  Deploy complete!"
    Write-Host "  https://yamato-mgt.com/"
    Write-Host "  Tag: $deployTag"
    Write-Host "========================================"
} else {
    Write-Host ""
    Write-Host "Deploy FAILED. Check deploy.log" -ForegroundColor Red
    exit 1
}
