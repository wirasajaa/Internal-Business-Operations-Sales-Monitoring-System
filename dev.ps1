<#
    ERP Project Dev Launcher (Windows)
    Starts the Laravel backend (php artisan serve) and the Vue frontend
    (frontend/, npm run dev) together, each in its own console window,
    then actively checks their ports and reports a clear OK/FAIL status.

    Usage: just double-click this file, or right-click -> "Run with
    PowerShell". Both ways are handled the same (see the self-relaunch
    block below), so it will not flash-and-close.
#>

# --- Self-relaunch guarantee -------------------------------------------
# Windows can invoke a .ps1 in several ways (double-click, "Run with
# PowerShell", a plain `powershell -File ...`), and some of them start
# PowerShell in -NonInteractive mode or without -NoExit, which is exactly
# what makes a script's window flash and close instantly. To make this
# script behave identically no matter how it was started, the first run
# immediately re-launches itself in a clean, guaranteed-interactive
# `-NoExit` session and exits. Everything below this block always runs
# inside that guaranteed session.
if (-not $env:ERP_DEV_LAUNCHER_RELAUNCHED) {
    $psExe = (Get-Process -Id $PID).Path
    $env:ERP_DEV_LAUNCHER_RELAUNCHED = "1"
    Start-Process -FilePath $psExe -ArgumentList @(
        "-NoExit", "-NoProfile", "-ExecutionPolicy", "Bypass", "-File", $PSCommandPath
    )
    exit
}
# -------------------------------------------------------------------------

$ErrorActionPreference = "Stop"
$root         = $PSScriptRoot
$frontend     = Join-Path $root "frontend"
$backendPort  = 8000
$frontendPort = 5173

$Host.UI.RawUI.WindowTitle = "ERP Dev Launcher"

if (-not (Test-Path (Join-Path $root "artisan"))) {
    Write-Host "artisan not found in $root - run this script from the erp-project root." -ForegroundColor Red
    Read-Host "Press Enter to close"
    exit 1
}
if (-not (Test-Path (Join-Path $frontend "node_modules"))) {
    Write-Host "frontend\node_modules not found - run 'npm install' inside frontend\ first." -ForegroundColor Yellow
}

Write-Host "== ERP Dev Launcher ==" -ForegroundColor Cyan

# Each child window prints a clear status and pauses on exit/error instead
# of silently closing, so failures are always visible.
$backendCmd = @"
`$Host.UI.RawUI.WindowTitle = 'ERP Backend - php artisan serve'
Set-Location -LiteralPath '$root'
Write-Host 'Starting: php artisan serve' -ForegroundColor Blue
php artisan serve
Write-Host ''
Write-Host 'Backend process stopped/exited.' -ForegroundColor Red
Read-Host 'Press Enter to close this window'
"@

$frontendCmd = @"
`$Host.UI.RawUI.WindowTitle = 'ERP Frontend - npm run dev'
Set-Location -LiteralPath '$frontend'
Write-Host 'Starting: npm run dev' -ForegroundColor Green
npm run dev
Write-Host ''
Write-Host 'Frontend process stopped/exited.' -ForegroundColor Red
Read-Host 'Press Enter to close this window'
"@

Write-Host "Starting backend  (php artisan serve) ..." -ForegroundColor Blue
Start-Process powershell -ArgumentList @("-NoExit", "-NoProfile", "-Command", $backendCmd) | Out-Null

Write-Host "Starting frontend (npm run dev) ..." -ForegroundColor Green
Start-Process powershell -ArgumentList @("-NoExit", "-NoProfile", "-Command", $frontendCmd) | Out-Null

Write-Host ""
Write-Host "Waiting for both services to come up (up to 25s)..." -ForegroundColor Cyan

function Test-PortOpen([int]$port) {
    try {
        return @(Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction Stop).Count -gt 0
    } catch {
        return $false
    }
}

$maxWaitSeconds = 25
$elapsed    = 0
$backendUp  = $false
$frontendUp = $false
while ($elapsed -lt $maxWaitSeconds -and (-not $backendUp -or -not $frontendUp)) {
    Start-Sleep -Seconds 1
    $elapsed++
    if (-not $backendUp)  { $backendUp  = Test-PortOpen $backendPort }
    if (-not $frontendUp) { $frontendUp = Test-PortOpen $frontendPort }
}

Write-Host ""
if ($backendUp) {
    Write-Host "[OK]   Backend  is up  -> http://127.0.0.1:$backendPort" -ForegroundColor Green
} else {
    Write-Host "[FAIL] Backend did not respond on port $backendPort within ${maxWaitSeconds}s - check the 'ERP Backend' window for the error." -ForegroundColor Red
}
if ($frontendUp) {
    Write-Host "[OK]   Frontend is up  -> http://127.0.0.1:$frontendPort" -ForegroundColor Green
} else {
    Write-Host "[FAIL] Frontend did not respond on port $frontendPort within ${maxWaitSeconds}s - check the 'ERP Frontend' window for the error." -ForegroundColor Red
}

Write-Host ""
Write-Host "Each service keeps running in its own window (titled 'ERP Backend' / 'ERP Frontend') with live logs."
Write-Host "Closing a window (or Ctrl+C inside it) stops that service."
Read-Host "Press Enter to close this launcher window (the two service windows keep running)"
