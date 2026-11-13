$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $PSScriptRoot
$php = 'C:\xampp\php\php.exe'
$mysql = 'C:\xampp\mysql\bin\mysqld.exe'
$url = 'http://127.0.0.1:8000/login'

function Get-Listener($port) {
    @(Get-NetTCPConnection -State Listen -ErrorAction Stop |
        Where-Object { $_.LocalPort -eq $port })
}

function Assert-Service($listeners, $executable, $commandFragment, $name) {
    # MariaDB is authenticated and its installation verified by the PHP check below.
    # Elevated database processes can hide their executable path from Windows users.
    if ($name -eq 'MariaDB') { return }
    foreach ($listener in $listeners) {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($listener.OwningProcess)"
        if (-not $process -or $process.ExecutablePath -ine $executable -or
            ($commandFragment -and (-not $process.CommandLine -or
                $process.CommandLine.Replace('\', '/').IndexOf($commandFragment.Replace('\', '/'), [StringComparison]::OrdinalIgnoreCase) -lt 0))) {
            throw "Port $($listener.LocalPort) is occupied by another service. Stop that service before opening CaseTrack."
        }
        if ($name -eq 'CaseTrack' -and $listener.LocalAddress -ne '127.0.0.1') {
            throw 'The existing CaseTrack server is not bound to 127.0.0.1. Stop it and run this launcher again.'
        }
    }
}

function Wait-Service($port, $executable, $commandFragment, $name) {
    $until = [DateTime]::UtcNow.AddSeconds(30)
    do {
        $listeners = @(Get-Listener $port)
        if ($listeners.Count) {
            Assert-Service $listeners $executable $commandFragment $name
            return
        }
        Start-Sleep -Milliseconds 250
    } while ([DateTime]::UtcNow -lt $until)
    throw "$name did not start within 30 seconds. Check its minimized service window."
}

# Serialize double-clicks across launcher processes without leaving a lock file.
$mutex = New-Object Threading.Mutex($false, 'Local\CaseTrackLocalLauncher8000')
$locked = $false
try {
    try { $locked = $mutex.WaitOne(0) } catch [Threading.AbandonedMutexException] { $locked = $true }
    if (-not $locked) {
        Write-Host 'CaseTrack startup is already in progress.'
        exit 0
    }

    foreach ($required in @($php, $mysql, "$project\artisan", "$project\vendor\autoload.php", "$project\.env", "$project\public\build\manifest.json")) {
        if (-not (Test-Path -LiteralPath $required -PathType Leaf)) {
            throw "Missing: $required. Complete the README setup steps before using this launcher."
        }
    }
    & $php -r "exit(version_compare(PHP_VERSION, '8.4.27', '>=') ? 0 : 1);"
    if ($LASTEXITCODE -ne 0) { throw 'XAMPP PHP 8.4.27 or newer is required.' }
    if (Test-Path -LiteralPath "$project\public\hot") {
        throw 'public/hot enables the Vite development server. Stop Vite and remove the stale public/hot file, then run npm run build before using this launcher.'
    }

    $serverPath = "$project\vendor\laravel\framework\src\Illuminate\Foundation\Console\../resources/server.php"
    if (Test-Path -LiteralPath "$project\server.php") { $serverPath = "$project\server.php" }
    # Laravel passes this router path to PHP; it identifies this checkout's listener.
    $web = @(Get-Listener 8000)
    Assert-Service $web $php $serverPath 'CaseTrack'
    $database = @(Get-Listener 3307)
    Assert-Service $database $mysql '' 'MariaDB'

    if (-not $database.Count) {
        Write-Host 'Starting MariaDB...'
        Start-Process -FilePath $mysql -ArgumentList '--defaults-file="C:\xampp\mysql\bin\my.ini"', '--standalone' -WorkingDirectory 'C:\xampp' -WindowStyle Minimized | Out-Null
        Wait-Service 3307 $mysql '' 'MariaDB'
    }
    $databaseCheck = Start-Process -FilePath $php -ArgumentList ('"{0}\check-local-database.php"' -f $PSScriptRoot) -WorkingDirectory $project -NoNewWindow -PassThru
    try {
        # Cache the handle so Windows PowerShell retains ExitCode after a fast exit.
        $null = $databaseCheck.Handle
        if (-not $databaseCheck.WaitForExit(10000)) {
            $databaseCheck.Kill()
            throw 'Local database verification timed out after 10 seconds. Check the service on port 3307.'
        }
        if ($databaseCheck.ExitCode -ne 0) {
            throw 'Cannot verify the local XAMPP MariaDB database. Check the database message above and your .env settings.'
        }
    } finally {
        $databaseCheck.Dispose()
    }
    if (-not $web.Count) {
        Write-Host 'Starting CaseTrack...'
        Start-Process -FilePath $php -ArgumentList ('"{0}\artisan" serve --host=127.0.0.1 --port=8000 --tries=1 --no-reload' -f $project) -WorkingDirectory $project -WindowStyle Minimized | Out-Null
        Wait-Service 8000 $php $serverPath 'CaseTrack'
    }

    $ready = $false
    $until = [DateTime]::UtcNow.AddSeconds(30)
    do {
        Assert-Service @(Get-Listener 8000) $php $serverPath 'CaseTrack'
        try {
            $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 2 -MaximumRedirection 0
            $ready = $response.StatusCode -eq 200 -and $response.Content -match 'CaseTrack'
        } catch { $ready = $false }
        if (-not $ready) { Start-Sleep -Milliseconds 250 }
    } while (-not $ready -and [DateTime]::UtcNow -lt $until)
    if (-not $ready) { throw 'CaseTrack login did not become ready within 30 seconds. Check the service window and storage/logs/laravel.log.' }

    Write-Host 'Opening CaseTrack. Keep its PHP server window running. MariaDB must also stay running.'
    Start-Process $url
} catch {
    Write-Host "CaseTrack could not open: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
} finally {
    if ($locked) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}
