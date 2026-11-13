# Run with powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/Launcher/launcher-check.ps1.
# Service and browser operations are mocked; no database or web server is started.
$ErrorActionPreference = 'Stop'
$launcher = Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) 'scripts\open-casetrack.ps1'
$root = Split-Path -Parent (Split-Path -Parent $launcher)
$router = "$root\vendor\laravel\framework\src\Illuminate\Foundation\Console\../resources/server.php"

function Assert($condition, $message) {
    if (-not $condition) { throw $message }
}
function Test-Path {
    param($LiteralPath, $PathType)
    if ($LiteralPath -eq $global:launcherTest.missing) { return $false }
    if ($LiteralPath -eq "$root\public\hot") { return $global:launcherTest.hot }
    if ($LiteralPath -eq "$root\server.php") { return $false }
    return $true
}
function Get-NetTCPConnection {
    param($State, $ErrorAction)
    if ($global:launcherTest.db) { [pscustomobject]@{ LocalPort = 3307; LocalAddress = '127.0.0.1'; OwningProcess = 101 } }
    if ($global:launcherTest.web) { [pscustomobject]@{ LocalPort = 8000; LocalAddress = $global:launcherTest.address; OwningProcess = 102 } }
}
function Get-CimInstance {
    param($ClassName, $Filter)
    if ($Filter -match '101$') { throw 'Database identity must be checked through the authenticated PHP check, not CIM.' }
    return [pscustomobject]@{ ExecutablePath = 'C:\xampp\php\php.exe'; CommandLine = $global:launcherTest.command }
}
function Start-Process {
    param($FilePath, $ArgumentList, $WorkingDirectory, $WindowStyle, [switch]$NoNewWindow, [switch]$Wait, [switch]$PassThru)
    if ($FilePath -eq 'http://127.0.0.1:8000/login') { $global:launcherTest.opened++; return }
    if ($ArgumentList -like '*check-local-database.php*') {
        $check = [pscustomobject]@{ ExitCode = $global:launcherTest.databaseCode }
        $check | Add-Member ScriptMethod WaitForExit {
            param($milliseconds)
            Assert ($milliseconds -eq 10000) 'Database verification must have a 10-second bound.'
            return -not $global:launcherTest.databaseTimeout
        }
        $check | Add-Member ScriptMethod Kill { $global:launcherTest.killed = $true }
        $check | Add-Member ScriptMethod Dispose { $global:launcherTest.disposed = $true }
        return $check
    }
    Assert ($WindowStyle -eq 'Minimized') 'Service windows must be minimized.'
    if ($FilePath -like '*mysqld.exe') { $global:launcherTest.db = $true; $global:launcherTest.dbStarts++ }
    else {
        Assert ($ArgumentList -match '--host=127.0.0.1 --port=8000 --tries=1') 'Server must use the fixed loopback port.'
        Assert ($WorkingDirectory -eq $root) 'Wrong working directory.'
        $global:launcherTest.web = $true
        $global:launcherTest.webStarts++
    }
}
function Invoke-WebRequest {
    param($Uri, [switch]$UseBasicParsing, $TimeoutSec, $MaximumRedirection)
    [pscustomobject]@{ StatusCode = 200; Content = 'CaseTrack' }
}

$global:launcherTest = @{}
foreach ($scenario in @('stopped', 'running', 'repeat', 'missing', 'conflict', 'other-checkout', 'public-binding', 'vite', 'elevated-database', 'wrong-database', 'database-timeout')) {
    $global:launcherTest.db = $scenario -ne 'stopped'
    $global:launcherTest.web = $scenario -ne 'stopped'
    $global:launcherTest.dbStarts = 0
    $global:launcherTest.webStarts = 0
    $global:launcherTest.opened = 0
    $global:launcherTest.databaseCode = if ($scenario -eq 'wrong-database') { 1 } else { 0 }
    $global:launcherTest.databaseTimeout = $scenario -eq 'database-timeout'
    $global:launcherTest.killed = $false
    $global:launcherTest.disposed = $false
    $global:launcherTest.missing = if ($scenario -eq 'missing') { "$root\vendor\autoload.php" } else { '' }
    $global:launcherTest.hot = $scenario -eq 'vite'
    $global:launcherTest.address = if ($scenario -eq 'public-binding') { '0.0.0.0' } else { '127.0.0.1' }
    $global:launcherTest.command = switch ($scenario) {
        'conflict' { 'php -S 127.0.0.1:8000 unrelated.php' }
        'other-checkout' { 'php -S 127.0.0.1:8000 C:\other\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php' }
        default { 'php -S 127.0.0.1:8000 ' + $router.Replace('Console\../', 'Console/../') }
    }
    $global:LASTEXITCODE = 0
    & $launcher
    $code = $LASTEXITCODE
    if ($scenario -in @('missing', 'conflict', 'other-checkout', 'public-binding', 'vite', 'wrong-database', 'database-timeout')) {
        Assert ($code -eq 1 -and $global:launcherTest.opened -eq 0) "$scenario should fail without opening a browser."
        Assert ($global:launcherTest.dbStarts -eq 0 -and $global:launcherTest.webStarts -eq 0) "$scenario should not start services."
    } else {
        Assert ($code -eq 0 -and $global:launcherTest.opened -eq 1) "$scenario should open the browser."
        $expectedStarts = if ($scenario -eq 'stopped') { 1 } else { 0 }
        Assert ($global:launcherTest.dbStarts -eq $expectedStarts -and $global:launcherTest.webStarts -eq $expectedStarts) "$scenario started duplicate services."
        if ($scenario -eq 'repeat') {
            & $launcher
            Assert ($global:launcherTest.opened -eq 2 -and $global:launcherTest.dbStarts -eq 0 -and $global:launcherTest.webStarts -eq 0) 'Repeated launch must reuse services.'
        }
    }
    if ($scenario -eq 'database-timeout') {
        Assert ($global:launcherTest.killed -and $global:launcherTest.disposed) 'Timed-out verifier must be stopped and disposed.'
    }
    Write-Host "PASS: $scenario"
}
