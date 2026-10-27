#requires -Version 5.1
[CmdletBinding()]
param(
    [string]$SiteHostname,
    [string]$ApplicationPath,
    [string]$XamppPath = 'C:\xampp',
    [string]$CertificatePath,
    [string]$PrivateKeyPath,
    [string]$CaBundlePath,
    [string]$OutputDirectory,
    [string]$ApacheConfig,
    [string]$SiteConfig,
    [ValidateRange(1, 65535)][int]$HttpPort = 80,
    [ValidateRange(1, 65535)][int]$HttpsPort = 443,
    [switch]$Apply
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Invoke-Checked([string]$Executable, [string[]]$Arguments) {
    # Quote Windows argv directly: PowerShell 5.1 loses embedded quotes with &.
    $quoted = foreach ($argument in $Arguments) {
        $argument = [regex]::Replace($argument, '(\\*)"', '$1$1\"')
        '"' + [regex]::Replace($argument, '(\\+)$', '$1$1') + '"'
    }
    $start = New-Object Diagnostics.ProcessStartInfo
    $start.FileName = $Executable
    $start.Arguments = $quoted -join ' '
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $start.RedirectStandardInput = $true
    $process = New-Object Diagnostics.Process
    $process.StartInfo = $start
    try {
        $null = $process.Start()
        $process.StandardInput.Close()
        $stdout = $process.StandardOutput.ReadToEndAsync()
        $stderr = $process.StandardError.ReadToEndAsync()
        $process.WaitForExit()
        $result = ($stdout.Result + "`n" + $stderr.Result).Trim()
        if ($process.ExitCode -ne 0) { throw "$Executable failed ($($process.ExitCode)):`n$result" }
        return $result
    } finally { $process.Dispose() }
}

function Get-SafePath([string]$Value, [string]$Label) {
    if ($Value -notmatch '^[A-Za-z]:[\\/]' -or $Value -match '[\x00-\x1f"$<>]') {
        throw "$Label must be an absolute local Windows path without control characters, quotes, dollar signs or angle brackets."
    }
    return [IO.Path]::GetFullPath($Value).TrimEnd('\', '/').Replace('\', '/')
}

function Test-Within([string]$Path, [string]$Parent) {
    return $Path.Equals($Parent, [StringComparison]::OrdinalIgnoreCase) -or
        $Path.StartsWith($Parent.TrimEnd('/') + '/', [StringComparison]::OrdinalIgnoreCase)
}

function Get-ContentHash([string]$Path) {
    $stream = [IO.File]::OpenRead($Path)
    $hash = [Security.Cryptography.SHA256]::Create()
    try { return [BitConverter]::ToString($hash.ComputeHash($stream)) }
    finally { $stream.Dispose(); $hash.Dispose() }
}

function Read-Vhosts([string]$Dump) {
    $defaults = @{}
    $sites = @()
    $address = ''
    foreach ($line in ($Dump -split "`n")) {
        if ($line -match '^\s*(\S+:\d+)\s+is a NameVirtualHost') { $address = $Matches[1] }
        elseif ($line -match '^\s*default server (\S+) \((.+):\d+\)') {
            $defaults[$address] = $Matches[1] + '|' + $Matches[2]
        } elseif ($line -match '^\s*(\S+:\d+)\s+(\S+) \((.+):\d+\)') {
            $address = $Matches[1]
            $defaults[$address] = $Matches[2] + '|' + $Matches[3]
            $sites += [pscustomobject]@{ Name = $Matches[2]; Source = $Matches[3] }
        } elseif ($line -match '^\s*port \d+ namevhost (\S+) \((.+):\d+\)') {
            $sites += [pscustomobject]@{ Name = $Matches[1]; Source = $Matches[2] }
        } elseif ($line -match '^\s+(?:wild )?alias (\S+)' -and $sites.Count -gt 0) {
            $sites += [pscustomobject]@{ Name = $Matches[1]; Source = $sites[-1].Source }
        }
    }
    return @{ Defaults = $defaults; Sites = $sites }
}

function Install-SiteConfig([string]$Candidate, [string]$Destination, [scriptblock]$Verify) {
    if ((Test-Path -LiteralPath $Destination) -and
        (Get-ContentHash $Candidate) -eq (Get-ContentHash $Destination)) {
        & $Verify
        Write-Host 'Installed site configuration is already identical; no changes.'
        return
    }
    $backup = $null
    $temporary = $Destination + '.' + [guid]::NewGuid().ToString('N') + '.tmp'
    try {
        [IO.File]::Copy($Candidate, $temporary)
        if (Test-Path -LiteralPath $Destination) {
            $backup = $Destination + '.' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.' + [guid]::NewGuid().ToString('N') + '.bak'
            [IO.File]::Replace($temporary, $Destination, $backup)
        } else { [IO.File]::Move($temporary, $Destination) }
        try { & $Verify } catch {
            if ($backup) { [IO.File]::Copy($backup, $Destination, $true) }
            else { [IO.File]::Delete($Destination) }
            throw "Post-install validation failed; previous site configuration restored. $_"
        }
        Write-Host "Site configuration installed. Backup: $backup"
    } finally {
        if (Test-Path -LiteralPath $temporary) { [IO.File]::Delete($temporary) }
    }
}

function Invoke-Setup {
    if ($env:OS -ne 'Windows_NT') { throw 'Only Windows/XAMPP with mod_php is supported.' }
    foreach ($name in @('SiteHostname', 'ApplicationPath', 'CertificatePath', 'PrivateKeyPath', 'CaBundlePath')) {
        if (-not (Get-Variable $name -ValueOnly)) { Set-Variable $name (Read-Host $name) }
    }
    if ($SiteHostname.Length -gt 253 -or $SiteHostname -notmatch '^(?=.+\.)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$') {
        throw 'SiteHostname must be a DNS hostname, without scheme, port, path or wildcard.'
    }
    $SiteHostname = $SiteHostname.ToLowerInvariant()
    if ($HttpPort -eq $HttpsPort) { throw 'HTTP and HTTPS ports must differ.' }
    $ApplicationPath = Get-SafePath $ApplicationPath 'ApplicationPath'
    $XamppPath = Get-SafePath $XamppPath 'XamppPath'
    $CertificatePath = Get-SafePath $CertificatePath 'CertificatePath'
    $PrivateKeyPath = Get-SafePath $PrivateKeyPath 'PrivateKeyPath'
    $CaBundlePath = Get-SafePath $CaBundlePath 'CaBundlePath'
    if (-not $ApacheConfig) { $ApacheConfig = "$XamppPath/apache/conf/httpd.conf" }
    if (-not $SiteConfig) { $SiteConfig = "$XamppPath/apache/conf/extra/casetrack-https.conf" }
    if (-not $OutputDirectory) { $OutputDirectory = "$PSScriptRoot/../generated/$SiteHostname" }
    $ApacheConfig = Get-SafePath $ApacheConfig 'ApacheConfig'
    $SiteConfig = Get-SafePath $SiteConfig 'SiteConfig'
    $OutputDirectory = Get-SafePath $OutputDirectory 'OutputDirectory'
    $packageRoot = Get-SafePath "$PSScriptRoot/../.." 'Package root'
    $public = "$ApplicationPath/public"
    $httpd = "$XamppPath/apache/bin/httpd.exe"
    $openssl = "$XamppPath/apache/bin/openssl.exe"
    $php = "$XamppPath/php/php.exe"
    foreach ($file in @($httpd, $openssl, $php, $ApacheConfig, $CertificatePath, $PrivateKeyPath, $CaBundlePath,
            "$ApplicationPath/artisan", "$public/index.php", "$public/.htaccess", "$public/build/manifest.json",
            "$ApplicationPath/vendor/autoload.php", "$XamppPath/php/php8apache2_4.dll")) {
        if (-not (Test-Path -LiteralPath $file -PathType Leaf)) { throw "Required file missing: $file" }
    }
    if (Test-Path -LiteralPath "$public/hot") { throw 'public/hot exists: remove the deployment copy of the Vite development marker after building assets.' }
    foreach ($directory in @("$ApplicationPath/storage", "$ApplicationPath/bootstrap/cache", (Split-Path $SiteConfig))) {
        if (-not (Test-Path -LiteralPath $directory -PathType Container)) { throw "Required directory missing: $directory" }
    }
    if ($SiteConfig -eq $ApacheConfig -or (Test-Within $OutputDirectory "$XamppPath/apache/conf")) {
        throw 'Use a separate site file and an output directory outside installed Apache configuration.'
    }
    if ([IO.Path]::GetExtension($SiteConfig) -ne '.conf' -or $SiteConfig -in @($CertificatePath, $PrivateKeyPath, $CaBundlePath)) {
        throw 'SiteConfig must be a separate dedicated .conf file, never a certificate or key.'
    }
    if ((Test-Path -LiteralPath $SiteConfig) -and [IO.File]::ReadAllText($SiteConfig).Trim() -and
        -not [IO.File]::ReadAllText($SiteConfig).StartsWith('# CaseTrack HTTPS deployment package')) {
        throw 'Refusing to replace a non-package site configuration. Use a separate dedicated site file.'
    }
    $phpVersion = Invoke-Checked $php @('-r', 'echo PHP_VERSION;')
    if ([version]$phpVersion -lt [version]'8.4.27' -or [version]$phpVersion -ge [version]'8.5') {
        throw "PHP 8.4.27+ within the supported 8.4 series is required; found $phpVersion."
    }
    $null = Invoke-Checked $php @('-r', 'foreach ([''ctype'',''curl'',''dom'',''fileinfo'',''filter'',''hash'',''mbstring'',''openssl'',''pcre'',''PDO'',''pdo_mysql'',''session'',''tokenizer'',''xml'',''zip''] as $e) { if (!extension_loaded($e)) { fwrite(STDERR, ''Missing PHP extension: ''.$e); exit(1); } }')
    $null = Invoke-Checked $php @("$ApplicationPath/vendor/composer/platform_check.php")
    $versionText = Invoke-Checked $httpd @('-v')
    if ($versionText -notmatch 'Apache/(2\.4\.\d+)' -or [version]$Matches[1] -lt [version]'2.4.42') {
        throw 'Apache 2.4.42+ is required for virtual-host TLS protocol isolation.'
    }
    $null = Invoke-Checked $httpd @('-t', '-f', $ApacheConfig)
    $modules = Invoke-Checked $httpd @('-M', '-f', $ApacheConfig)
    foreach ($module in @('ssl', 'rewrite', 'alias', 'authz_core', 'dir', 'php')) {
        if ($modules -notmatch "(?m)^\s+$($module)_module\s") { throw "Required Apache module missing: $module" }
    }
    $includeDump = Invoke-Checked $httpd @('-t', '-D', 'DUMP_INCLUDES', '-f', $ApacheConfig)
    $configuration = ''
    foreach ($line in ($includeDump -split "`n")) {
        if ($line -match '^\s+\((?:\*|\d+)\) (.+)$') {
            $configuration += "`n" + [IO.File]::ReadAllText($Matches[1].Trim())
        }
    }
    $defines = @{}
    foreach ($match in [regex]::Matches($configuration, '(?im)^\s*Define\s+(\w+)\s+"?([^"\r\n]+)')) {
        $defines[$match.Groups[1].Value] = $match.Groups[2].Value.Trim()
    }
    # Resolve ordinary Apache Define paths; refuse expressions/relative web roots.
    $webRoots = @("$XamppPath/htdocs", $public)
    foreach ($match in [regex]::Matches($configuration, '(?im)^\s*(DocumentRoot|(?:Script)?Alias)\s+([^\r\n]+)')) {
        $arguments = @([regex]::Matches($match.Groups[2].Value, '"([^"]*)"|(\S+)') | ForEach-Object {
            if ($_.Groups[1].Success) { $_.Groups[1].Value } else { $_.Groups[2].Value }
        })
        $index = 0
        if ($match.Groups[1].Value -ne 'DocumentRoot') { $index = 1 }
        if ($arguments.Count -le $index) { throw 'Cannot interpret Apache web root/alias; review configuration manually.' }
        $root = $arguments[$index]
        foreach ($name in $defines.Keys) { $root = $root.Replace('${' + $name + '}', $defines[$name]) }
        $webRoots += (Get-SafePath $root 'Apache web root/alias (literal or Define-based paths required)')
    }
    foreach ($root in $webRoots) {
        foreach ($sensitive in @($OutputDirectory, $CertificatePath, $PrivateKeyPath, $CaBundlePath, $SiteConfig)) {
            if (Test-Within $sensitive $root) { throw "Sensitive/output path must be outside web roots: $sensitive (root $root)" }
        }
        if ($root -ne $public -and ((Test-Within $ApplicationPath $root) -or
            ((Test-Within $root $ApplicationPath) -and -not (Test-Within $root $public)))) {
            throw "Application source would be exposed by another web root: $root"
        }
    }
    foreach ($port in @($HttpPort, $HttpsPort)) {
        if ($configuration -notmatch "(?m)^\s*Listen\s+(?:[^\s]+:)?$port(?:\s|$)") {
            throw "No existing literal Listen directive for port $port. CHR IT must configure listeners separately."
        }
    }
    $san = Invoke-Checked $openssl @('x509', '-in', $CertificatePath, '-noout', '-ext', 'subjectAltName')
    if ($san -notmatch 'DNS:') { throw 'Certificate must contain a DNS subjectAltName; CN-only certificates are unsupported.' }
    $null = Invoke-Checked $openssl @('verify', '-purpose', 'sslserver', '-verify_hostname', $SiteHostname, '-CAfile', $CaBundlePath, '-untrusted', $CertificatePath, $CertificatePath)
    $certKey = Invoke-Checked $openssl @('x509', '-in', $CertificatePath, '-pubkey', '-noout')
    $privateKey = Invoke-Checked $openssl @('pkey', '-in', $PrivateKeyPath, '-passin', 'pass:', '-pubout')
    if ($certKey.Trim() -ne $privateKey.Trim()) { throw 'Certificate and private key do not match.' }
    $dates = Invoke-Checked $openssl @('x509', '-in', $CertificatePath, '-noout', '-dates')
    Write-Host "Certificate verified against supplied CA bundle (not the client trust store):`n$dates"
    $before = Read-Vhosts (Invoke-Checked $httpd @('-S', '-f', $ApacheConfig))
    foreach ($site in $before.Sites) {
        if ($SiteHostname -like $site.Name -and $site.Source.Replace('\', '/') -ne $SiteConfig) {
            throw "Hostname conflicts with another virtual host: $($site.Name) in $($site.Source)"
        }
    }
    $authority = $SiteHostname
    if ($HttpsPort -ne 443) { $authority += ":$HttpsPort" }
    $values = @{
        HOSTNAME = $SiteHostname; HOST_REGEX = [regex]::Escape($SiteHostname)
        PUBLIC_PATH = $public; PUBLIC_REGEX = [regex]::Escape($public)
        HTTP_PORT = "$HttpPort"; HTTPS_PORT = "$HttpsPort"; HTTPS_AUTHORITY = $authority
        CERTIFICATE_PATH = $CertificatePath; PRIVATE_KEY_PATH = $PrivateKeyPath
    }
    $template = [IO.File]::ReadAllText("$PSScriptRoot/../apache/casetrack-https.conf.template")
    foreach ($name in $values.Keys) { $template = $template.Replace("{{$name}}", $values[$name]) }
    [IO.Directory]::CreateDirectory($OutputDirectory) | Out-Null
    $candidate = "$OutputDirectory/casetrack-https.conf"
    $staged = "$OutputDirectory/httpd-validation.conf"
    foreach ($output in @($candidate, $staged, "$OutputDirectory/.env.production.example")) {
        if ($output -in @($ApacheConfig, $SiteConfig, $CertificatePath, $PrivateKeyPath, $CaBundlePath,
                "$ApplicationPath/.env.production.example", "$packageRoot/.env.production.example")) {
            throw "Output would replace an input or installed configuration: $output"
        }
    }
    $utf8 = New-Object Text.UTF8Encoding($false)
    [IO.File]::WriteAllText($candidate, $template, $utf8)
    $environment = [IO.File]::ReadAllText("$PSScriptRoot/../../.env.production.example").Replace('INSTALL_HOSTNAME', $authority)
    [IO.File]::WriteAllText("$OutputDirectory/.env.production.example", $environment, $utf8)
    $main = [IO.File]::ReadAllText($ApacheConfig)
    $includePattern = '(?im)^\s*Include(?:Optional)?[ \t]+"' + [regex]::Escape($SiteConfig) + '"[ \t]*\r?$'
    $hasInclude = [regex]::Matches($main, $includePattern).Count
    if ($hasInclude -gt 1) { throw 'Duplicate CaseTrack include lines in main Apache configuration.' }
    $replacement = 'Include "' + $candidate + '"'
    if ($hasInclude) { $main = [regex]::Replace($main, $includePattern, [System.Text.RegularExpressions.MatchEvaluator]{ param($m) $replacement }) }
    else { $main += "`n$replacement`n" }
    [IO.File]::WriteAllText($staged, $main, $utf8)
    $null = Invoke-Checked $httpd @('-t', '-f', $staged)
    $afterDump = Invoke-Checked $httpd @('-S', '-f', $staged)
    $after = Read-Vhosts ($afterDump.Replace($staged, $ApacheConfig))
    $changedDefaults = @(@($after.Defaults.Keys) + @($before.Defaults.Keys) | Where-Object {
        -not $before.Defaults.ContainsKey($_) -or -not $after.Defaults.ContainsKey($_) -or $before.Defaults[$_] -ne $after.Defaults[$_]
    })
    Write-Host "Generated and syntax-checked: $candidate`nEnvironment template: $OutputDirectory/.env.production.example`n$afterDump"
    if ($changedDefaults.Count -gt 0) {
        if ($Apply) { throw 'Apply refused: CaseTrack would change a default virtual-host mapping. CHR IT must establish/preserve defaults first.' }
        Write-Warning 'CaseTrack would change a default virtual-host mapping. Generation only; establish/preserve defaults before apply.'
    }
    if (-not $Apply) { Write-Host 'Validation/generation only. Installed configuration and .env unchanged.'; return }
    if (-not $hasInclude) { throw "Apply requires this exact line, after existing default sites, in ${ApacheConfig}:`nIncludeOptional `"$SiteConfig`"" }
    Install-SiteConfig $candidate $SiteConfig {
        $null = Invoke-Checked $httpd @('-t', '-f', $ApacheConfig)
        $null = Invoke-Checked $httpd @('-S', '-f', $ApacheConfig)
    }
    Write-Host 'No Apache reload performed. CHR IT must approve activation and verify the HTTPS PHP runtime and client trust.'
}

if ($MyInvocation.InvocationName -ne '.') {
    try { Invoke-Setup } catch { Write-Error "CaseTrack HTTPS setup failed: $_" -ErrorAction Continue; exit 1 }
}
