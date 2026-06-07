# Sheba POS Auto Print Service — PowerShell Worker Launcher
# Double-click this file OR right-click > Run with PowerShell

Set-Location -Path $PSScriptRoot

Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "   Sheba Restaurant Auto Print Service" -ForegroundColor Cyan
Write-Host "   Press Ctrl+C to stop." -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

# --- Find PHP executable ---
$phpPaths = @(
    "C:\xampp3\php\php.exe",
    "C:\xampp\php\php.exe",
    "D:\xampp\php\php.exe",
    "D:\xampp3\php\php.exe",
    "C:\php\php.exe"
)

$phpBin = $null
foreach ($p in $phpPaths) {
    if (Test-Path $p) {
        $phpBin = $p
        break
    }
}

# Fallback: try php from system PATH
if (-not $phpBin) {
    try {
        $found = Get-Command php -ErrorAction Stop
        $phpBin = $found.Source
    } catch {
        Write-Host "[ERROR] PHP not found! Install XAMPP or add php.exe to PATH." -ForegroundColor Red
        Write-Host ""
        Read-Host "Press Enter to exit"
        exit 1
    }
}

Write-Host "[+] Using PHP: $phpBin" -ForegroundColor Green
Write-Host ""

# --- Verify PHP works ---
try {
    $test = & "$phpBin" -r "echo 'OK';" 2>&1
    if ($test -ne "OK") { throw "PHP check failed" }
    Write-Host "[+] PHP check: OK" -ForegroundColor Green
} catch {
    Write-Host "[ERROR] PHP executable found but cannot run: $_" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

# --- Check worker file exists ---
$workerFile = Join-Path $PSScriptRoot "local_print_worker.php"
if (-not (Test-Path $workerFile)) {
    Write-Host "[ERROR] local_print_worker.php not found in: $PSScriptRoot" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host "[+] Worker file found: $workerFile" -ForegroundColor Green
Write-Host ""
Write-Host "Starting auto-print monitoring loop..." -ForegroundColor Yellow
Write-Host ""

# --- Main loop ---
while ($true) {
    & "$phpBin" -f "$workerFile"
    Write-Host ""
    Write-Host "[!] Worker exited. Restarting in 5 seconds..." -ForegroundColor Yellow
    Start-Sleep -Seconds 5
}
