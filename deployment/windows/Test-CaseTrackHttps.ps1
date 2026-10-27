#requires -Version 5.1
[CmdletBinding()]
param([string]$XamppPath = 'C:\xampp')

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$repository = [IO.Path]::GetFullPath("$PSScriptRoot/../..")
$setup = "$PSScriptRoot/Setup-CaseTrackHttps.ps1"
. $setup -XamppPath $XamppPath
$testRoot = ($repository + '/deployment/test-output/' + [guid]::NewGuid().ToString('N') + ' with spaces').Replace('\', '/')
$testApp = "$testRoot/app"
$testConfig = "$testRoot/httpd.conf"
$testSite = "$testRoot/site.conf"
$testOutput = "$testRoot/generated"
$testApache = "$XamppPath/apache/bin/httpd.exe"
$testOpenSsl = "$XamppPath/apache/bin/openssl.exe"
$testPhp = "$XamppPath/php/php.exe"
$utf8 = New-Object Text.UTF8Encoding($false)
$checks = 0
$server = $null

function Assert([bool]$Condition, [string]$Message) {
    if (-not $Condition) { throw "FAIL: $Message" }
    $script:checks++
    Write-Host "PASS: $Message"
}
function Write-TestFile([string]$Path, [string]$Contents) { [IO.File]::WriteAllText($Path, $Contents, $utf8) }
function Get-FreePort {
    $listener = New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback, 0)
    $listener.Start()
    try { return $listener.LocalEndpoint.Port } finally { $listener.Stop() }
}
function Run-Setup([hashtable]$Changes = @{}, [string]$ExpectedError = '') {
    $parameters = @{
        SiteHostname = 'casetrack.test'; ApplicationPath = $testApp; XamppPath = $XamppPath
        CertificatePath = "$testRoot/server.crt"; PrivateKeyPath = "$testRoot/server.key"
        CaBundlePath = "$testRoot/ca.crt"; OutputDirectory = $testOutput
        ApacheConfig = $testConfig; SiteConfig = $testSite; HttpPort = $testHttpPort; HttpsPort = $testHttpsPort
    }
    foreach ($name in $Changes.Keys) { $parameters[$name] = $Changes[$name] }
    $arguments = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $setup)
    foreach ($name in $parameters.Keys) {
        $arguments += "-$name"
        if ($parameters[$name] -isnot [switch] -and $parameters[$name] -isnot [bool]) { $arguments += [string]$parameters[$name] }
    }
    try {
        $result = Invoke-Checked 'powershell.exe' $arguments
        if ($ExpectedError) { throw "Expected failure: $ExpectedError" }
        return $result
    } catch {
        if (-not $ExpectedError -or "$_" -notmatch $ExpectedError -or "$_" -like '*Expected failure:*') { throw }
        Assert $true "Refused: $ExpectedError"
    }
}

try {
    foreach ($directory in @($testRoot, "$testApp/public/build", "$testApp/vendor/composer", "$testApp/storage/views", "$testApp/bootstrap/cache", "$testRoot/other", "$testRoot/aliased")) {
        [IO.Directory]::CreateDirectory($directory) | Out-Null
    }
    $testHttpPort = Get-FreePort
    do { $testHttpsPort = Get-FreePort } while ($testHttpsPort -eq $testHttpPort)
    Write-TestFile "$testRoot/req.cnf" "[req]`ndistinguished_name=dn`n[dn]`n"
    $null = Invoke-Checked $testOpenSsl @('req', '-config', "$testRoot/req.cnf", '-x509', '-newkey', 'rsa:2048', '-nodes', '-keyout', "$testRoot/ca.key", '-out', "$testRoot/ca.crt", '-days', '2', '-subj', '/CN=CaseTrack disposable test CA', '-addext', 'basicConstraints=critical,CA:TRUE')
    $null = Invoke-Checked $testOpenSsl @('req', '-config', "$testRoot/req.cnf", '-new', '-newkey', 'rsa:2048', '-nodes', '-keyout', "$testRoot/intermediate.key", '-out', "$testRoot/intermediate.csr", '-subj', '/CN=CaseTrack disposable intermediate')
    Write-TestFile "$testRoot/intermediate.cnf" "basicConstraints=critical,CA:TRUE,pathlen:0`nkeyUsage=critical,keyCertSign,cRLSign`n"
    $null = Invoke-Checked $testOpenSsl @('x509', '-req', '-in', "$testRoot/intermediate.csr", '-CA', "$testRoot/ca.crt", '-CAkey', "$testRoot/ca.key", '-set_serial', '1', '-out', "$testRoot/intermediate.crt", '-days', '2', '-extfile', "$testRoot/intermediate.cnf")
    $null = Invoke-Checked $testOpenSsl @('req', '-config', "$testRoot/req.cnf", '-new', '-newkey', 'rsa:2048', '-nodes', '-keyout', "$testRoot/server.key", '-out', "$testRoot/server.csr", '-subj', '/CN=casetrack.test')
    Write-TestFile "$testRoot/extensions.cnf" "subjectAltName=DNS:casetrack.test,DNS:other.test,DNS:default.test`nextendedKeyUsage=serverAuth`nbasicConstraints=CA:FALSE`n"
    $null = Invoke-Checked $testOpenSsl @('x509', '-req', '-in', "$testRoot/server.csr", '-CA', "$testRoot/intermediate.crt", '-CAkey', "$testRoot/intermediate.key", '-set_serial', '2', '-out', "$testRoot/server.crt", '-days', '2', '-extfile', "$testRoot/extensions.cnf")
    $null = Invoke-Checked $testOpenSsl @('x509', '-req', '-in', "$testRoot/server.csr", '-CA', "$testRoot/intermediate.crt", '-CAkey', "$testRoot/intermediate.key", '-set_serial', '3', '-out', "$testRoot/expired.crt", '-days', '-1', '-extfile', "$testRoot/extensions.cnf")
    [IO.File]::AppendAllText("$testRoot/server.crt", [IO.File]::ReadAllText("$testRoot/intermediate.crt"))
    [IO.File]::AppendAllText("$testRoot/expired.crt", [IO.File]::ReadAllText("$testRoot/intermediate.crt"))

    # Build a separate cached Laravel configuration; never use the development DB/key.
    $safeConfig = "$testRoot/laravel-config.php"
    $configBuilder = @'
<?php
require $argv[1].'/vendor/autoload.php';
$_SERVER['APP_CONFIG_CACHE'] = $argv[3].'/no-existing-config.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->useEnvironmentPath($argv[3]);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$config = $app['config']->all();
$config['app']['env'] = 'testing';
$config['app']['debug'] = false;
$config['app']['key'] = 'base64:'.base64_encode(random_bytes(32));
$config['app']['url'] = $argv[4];
$config['database']['default'] = 'sqlite';
$config['database']['connections'] = ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]];
$config['session']['driver'] = 'array';
$config['session']['secure'] = true;
$config['session']['http_only'] = true;
$config['cache']['default'] = 'array';
$config['queue']['default'] = 'sync';
$config['logging']['default'] = 'single';
$config['logging']['channels']['single']['path'] = $argv[3].'/laravel.log';
$config['view']['compiled'] = $argv[3].'/app/storage/views';
file_put_contents($argv[2], '<?php return '.var_export($config, true).';');
'@
    Write-TestFile "$testRoot/build-config.php" $configBuilder
    # Bootstrap only: no Artisan command, providers here do not query a database.
    $null = Invoke-Checked $testPhp @("$testRoot/build-config.php", $repository, $safeConfig, $testRoot, "https://casetrack.test:$testHttpsPort")
    Write-TestFile "$testApp/artisan" '<?php // Fixture path only; setup never runs Artisan.'
    Write-TestFile "$testApp/vendor/autoload.php" "<?php require '$($repository.Replace('\', '/'))/vendor/autoload.php';"
    Copy-Item -LiteralPath "$repository/vendor/composer/platform_check.php" -Destination "$testApp/vendor/composer/platform_check.php"
    Copy-Item -LiteralPath "$repository/public/.htaccess" -Destination "$testApp/public/.htaccess"
    Copy-Item -LiteralPath "$repository/public/build/manifest.json" -Destination "$testApp/public/build/manifest.json"
    Write-TestFile "$testApp/public/index.php" "<?php require '$($repository.Replace('\', '/'))/vendor/autoload.php'; (require '$($repository.Replace('\', '/'))/bootstrap/app.php')->handleRequest(Illuminate\Http\Request::capture());"
    Write-TestFile "$testApp/public/asset.txt" 'CASETRACK-PUBLIC-ASSET'
    Write-TestFile "$testApp/.env" 'PRIVATE-SENTINEL'
    Write-TestFile "$testRoot/aliased/private.txt" 'PRIVATE-SENTINEL'
    Write-TestFile "$testRoot/other/index.html" 'UNRELATED-SITE'
    $apacheRoot = "$XamppPath/apache".Replace('\', '/')
    $phpRoot = "$XamppPath/php".Replace('\', '/')
    $baseConfig = @"
ServerRoot "$apacheRoot"
ServerName default.test
Listen 127.0.0.1:$testHttpPort
Listen 127.0.0.1:$testHttpsPort
PidFile "$testRoot/httpd.pid"
ErrorLog "$testRoot/apache-error.log"
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule alias_module modules/mod_alias.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule env_module modules/mod_env.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule negotiation_module modules/mod_negotiation.so
LoadModule ssl_module modules/mod_ssl.so
LoadModule status_module modules/mod_status.so
LoadModule info_module modules/mod_info.so
LoadFile "$phpRoot/php8ts.dll"
LoadFile "$phpRoot/libsqlite3.dll"
LoadModule php_module "$phpRoot/php8apache2_4.dll"
PHPINIDir "$phpRoot"
TypesConfig "$apacheRoot/conf/mime.types"
DocumentRoot "$testRoot/other"
SetEnv APP_CONFIG_CACHE "$safeConfig"
SetEnv APP_ROUTES_CACHE "$testRoot/no-routes-cache.php"
<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
<Directory "$testRoot/other">
    Require all granted
</Directory>
Alias /outside "$testRoot/aliased"
<Directory "$testRoot/aliased">
    Require all granted
</Directory>
<Location /server-status>
    SetHandler server-status
    Require local
</Location>
<Location /server-info>
    SetHandler server-info
    Require local
</Location>
<VirtualHost *:$testHttpPort>
    ServerName default.test
    ServerAlias other.test
    DocumentRoot "$testRoot/other"
</VirtualHost>
<VirtualHost *:$testHttpsPort>
    ServerName default.test
    ServerAlias other.test
    DocumentRoot "$testRoot/other"
    SSLEngine on
    SSLCertificateFile "$testRoot/server.crt"
    SSLCertificateKeyFile "$testRoot/server.key"
</VirtualHost>
IncludeOptional "$testSite"
"@
    Write-TestFile $testConfig $baseConfig
    $originalConfigHash = Get-ContentHash $testConfig
    $installedConfigHash = Get-ContentHash "$XamppPath/apache/conf/httpd.conf"
    $originalEnvHash = $null
    if (Test-Path -LiteralPath "$repository/.env") { $originalEnvHash = Get-ContentHash "$repository/.env" }
    $null = Run-Setup
    Assert (-not (Test-Path -LiteralPath $testSite)) 'Default generation leaves installed site absent (paths contain spaces)'
    $null = Run-Setup @{
        ApplicationPath = $repository; ApacheConfig = "$XamppPath/apache/conf/httpd.conf"
        SiteConfig = "$XamppPath/apache/conf/extra/casetrack-https.conf"
        OutputDirectory = "$testRoot/installed-config-generation"; HttpPort = 80; HttpsPort = 443
    }
    Assert ((Get-ContentHash "$XamppPath/apache/conf/httpd.conf") -eq $installedConfigHash) 'Generation also validates against the installed XAMPP configuration without changing it'
    $generatedEnv = [IO.File]::ReadAllText("$testOutput/.env.production.example")
    Assert ($generatedEnv -match '(?m)^APP_KEY=\r?$' -and $generatedEnv -match 'SESSION_SECURE_COOKIE=true' -and $generatedEnv -match 'APP_DEBUG=false') 'Generated production template has no key and uses secure defaults'
    Run-Setup @{ ApplicationPath = "$testRoot/missing" } 'Required file missing'
    Run-Setup @{ SiteHostname = 'casetrack.test" injected' } 'must be a DNS hostname'
    Run-Setup @{ PrivateKeyPath = "$testRoot/server.key`nInjected" } 'absolute local Windows path'
    Run-Setup @{ OutputDirectory = "$testApp/public/generated" } 'outside web roots'
    Run-Setup @{ OutputDirectory = $repository } 'replace an input'
    Run-Setup @{ SiteHostname = 'wrong.test' } 'hostname mismatch'
    Run-Setup @{ CertificatePath = "$testRoot/expired.crt" } 'expired'
    Run-Setup @{ PrivateKeyPath = "$testRoot/ca.key" } 'do not match'
    Run-Setup @{ CertificatePath = "$testApp/.env" } 'failed'
    Run-Setup @{ CaBundlePath = "$testRoot/server.crt" } 'failed'
    Write-TestFile $testSite '# unrelated site configuration'
    Run-Setup @{} 'non-package site configuration'
    [IO.File]::Delete($testSite)
    Write-TestFile $testConfig ($baseConfig + "`nNotAnApacheDirective yes`n")
    Run-Setup @{} 'Invalid command'
    Write-TestFile $testConfig ($baseConfig.Replace('LoadModule rewrite_module modules/mod_rewrite.so', ''))
    Run-Setup @{} 'Required Apache module missing: rewrite'
    Write-TestFile $testConfig ($baseConfig.Replace("Listen 127.0.0.1:$testHttpsPort", ''))
    Run-Setup @{} 'No existing literal Listen directive'
    Write-TestFile $testConfig ($baseConfig.Replace('ServerName default.test', 'ServerName casetrack.test'))
    Run-Setup @{} 'Hostname conflicts'
    Write-TestFile $testConfig ($baseConfig -replace '(?s)<VirtualHost.*?</VirtualHost>', '')
    Run-Setup @{ Apply = $true } 'default virtual-host mapping'
    Write-TestFile $testConfig ($baseConfig.Replace('IncludeOptional "' + $testSite + '"', ''))
    Run-Setup @{ Apply = $true } 'Apply requires this exact line'
    Write-TestFile $testConfig $baseConfig
    $null = Run-Setup @{ Apply = $true }
    Assert (Test-Path -LiteralPath $testSite) 'Apply installs only the dedicated site file'
    $null = Run-Setup @{ Apply = $true }
    Assert (@(Get-ChildItem -LiteralPath $testRoot -Filter 'site.conf.*.bak').Count -eq 0) 'Identical apply does not create a backup or rewrite the file'
    [IO.File]::AppendAllText($testSite, "`n# prior installed version`n")
    $prior = [IO.File]::ReadAllText($testSite)
    $null = Run-Setup @{ Apply = $true }
    $backups = @(Get-ChildItem -LiteralPath $testRoot -Filter 'site.conf.*.bak')
    Assert ($backups.Count -eq 1 -and [IO.File]::ReadAllText($backups[0].FullName) -eq $prior) 'Replacement retains the previous configuration in a backup'
    Write-TestFile "$testRoot/replacement.conf" '# deliberately replaced during rollback self-check'
    $prior = [IO.File]::ReadAllText($testSite)
    try { Install-SiteConfig "$testRoot/replacement.conf" $testSite { throw 'Deliberate validation failure' }; throw 'Rollback did not fail' }
    catch { if ("$_" -notmatch 'previous site configuration restored') { throw } }
    Assert ([IO.File]::ReadAllText($testSite) -eq $prior) 'Post-install validation failure restores an existing site'
    try { Install-SiteConfig "$testRoot/replacement.conf" "$testRoot/new-site.conf" { throw 'Deliberate validation failure' }; throw 'Rollback did not fail' }
    catch { if ("$_" -notmatch 'previous site configuration restored') { throw } }
    Assert (-not (Test-Path -LiteralPath "$testRoot/new-site.conf")) 'Post-install validation failure removes a newly installed file'

    $server = Start-Process -FilePath $testApache -ArgumentList @('-f', ('"' + $testConfig + '"'), '-X') -WindowStyle Hidden -PassThru -RedirectStandardOutput "$testRoot/stdout.log" -RedirectStandardError "$testRoot/stderr.log"
    $deadline = (Get-Date).AddSeconds(15)
    do {
        Start-Sleep -Milliseconds 200
        $socket = New-Object Net.Sockets.TcpClient
        try { $socket.Connect('127.0.0.1', $testHttpPort); $ready = $socket.Connected } catch { $ready = $false } finally { $socket.Dispose() }
    } until ($ready -or (Get-Date) -gt $deadline -or $server.HasExited)
    Assert $ready 'Isolated Apache starts with loopback listeners'
    function Request([string]$Path, [switch]$Http, [string]$HostName = 'casetrack.test', [string[]]$Extra = @()) {
        $scheme = 'https'; $port = $testHttpsPort
        if ($Http) { $scheme = 'http'; $port = $testHttpPort }
        # The disposable CA has no CRL service; retain chain/name checks in Schannel.
        $arguments = @('--silent', '--show-error', '--ssl-revoke-best-effort', '--noproxy', '*', '--max-time', '10', '--path-as-is', '--resolve', "${HostName}:${port}:127.0.0.1", '--cacert', "$testRoot/ca.crt", '-i') + $Extra + @("${scheme}://${HostName}:$port$Path")
        return Invoke-Checked 'curl.exe' $arguments
    }
    $response = Request '/cases?office=VIII&x=1' -Http
    Assert ($response -match 'HTTP/1.1 301' -and $response -match "Location: https://casetrack.test:$testHttpsPort/cases\?office=VIII&x=1") 'HTTP redirect preserves path and query at the configured hostname'
    Assert ((Request '/asset.txt') -match 'CASETRACK-PUBLIC-ASSET') 'HTTPS serves a public asset'
    Assert ((Request '/up') -match 'HTTP/1.1 200') 'Laravel health route works through Apache'
    $response = Request '/login'
    Assert ($response -match 'HTTP/1.1 200' -and $response -match '(?i)Set-Cookie:.*secure;.*httponly') 'Laravel login renders and issues secure HTTP-only cookies with isolated configuration'
    Assert ((Request '/cases') -match 'HTTP/1.1 302') 'Laravel rewrites reach an authenticated route'
    foreach ($path in @('/.env', '/../.env', '/vendor/autoload.php', '/storage/logs/laravel.log', '/outside/private.txt', '/server-status', '/server-info', '/phpmyadmin/', '/php-cgi/php-cgi.exe', '/webalizer/')) {
        $response = Request $path
        Assert ($response -match 'HTTP/1.1 (400|403|404)' -and $response -notmatch 'PRIVATE-SENTINEL') "Sensitive or inherited endpoint denied: $path"
    }
    $tlsArguments = @('s_client', '-connect', "127.0.0.1:$testHttpsPort", '-servername', 'casetrack.test', '-verify_hostname', 'casetrack.test', '-verify_return_error', '-CAfile', "$testRoot/ca.crt", '-brief')
    Assert ((Invoke-Checked $testOpenSsl ($tlsArguments + '-tls1_2')) -match 'Protocol version: TLSv1.2') 'TLS 1.2 succeeds with verified chain and hostname'
    Assert ((Invoke-Checked $testOpenSsl ($tlsArguments + '-tls1_3')) -match 'Protocol version: TLSv1.3') 'TLS 1.3 succeeds with verified chain and hostname'
    try { $null = Invoke-Checked $testOpenSsl ($tlsArguments + @('-tls1', '-cipher', 'ALL:@SECLEVEL=0')); throw 'Legacy TLS unexpectedly accepted' }
    catch { Assert ("$_" -match 'alert protocol version') 'Server rejects TLS 1.0 with a protocol-version alert' }
    try { $null = Invoke-Checked $testOpenSsl ($tlsArguments + @('-tls1_1', '-cipher', 'ALL:@SECLEVEL=0')); throw 'Legacy TLS unexpectedly accepted' }
    catch { Assert ("$_" -match 'alert protocol version') 'Server rejects TLS 1.1 with a protocol-version alert' }
    Assert ((Request '/index.html' -Http -HostName 'unknown.test') -match 'UNRELATED-SITE') 'Unmatched HTTP hostname still reaches the original default site'
    Assert ((Request '/index.html' -HostName 'other.test') -match 'UNRELATED-SITE') 'Unrelated HTTPS site still serves its original content with verified trust'
    $response = Request '/asset.txt' -HostName 'casetrack.test' -Extra @('-H', 'Host: other.test')
    Assert ($response -match 'HTTP/1.1 (403|404|421)' -and $response -notmatch 'CASETRACK-PUBLIC-ASSET') 'Unexpected HTTPS Host cannot serve CaseTrack content'
    Assert ((Get-ContentHash $testConfig) -eq $originalConfigHash) 'Setup never changes the parent Apache configuration'
    if ($originalEnvHash) { Assert ((Get-ContentHash "$repository/.env") -eq $originalEnvHash) 'Development .env is unchanged' }
    else { Assert (-not (Test-Path -LiteralPath "$repository/.env")) 'No development .env was created' }
    Write-Host "SUCCESS: $checks checks. Disposable evidence: $testRoot"
} finally {
    if ($server -and -not $server.HasExited) { Stop-Process -Id $server.Id -Force; $server.WaitForExit() }
}
