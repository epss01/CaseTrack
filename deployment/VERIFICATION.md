# Local deployment-package verification

Verified **2026-10-26** on the development workstation. This is **not** an office
deployment or an approval of the office server's security posture.

## Environment and commands

- Windows PowerShell 5.1; no additional PowerShell modules or packages installed.
- XAMPP PHP CLI **8.4.27**; `php8apache2_4.dll` file version **8.4.27**.
- Apache **2.4.58 (Win64)** and OpenSSL **3.1.3**.
- `powershell.exe -NoProfile -ExecutionPolicy Bypass -File deployment/windows/Test-CaseTrackHttps.ps1`
  — **50 checks passed**, exit code 0.
- `php artisan test` — **364 passed, 10 skipped, 1,427 assertions**, exit code 0.
  The skips retain the existing blocked 60th-day torture-case checks.
- `git diff --check` — passed. No frontend sources changed; no asset rebuild was
  needed. The Laravel suite's existing asset check passed.

## What the deployment harness verified

- Default generation with paths containing spaces leaves the site destination
  absent; generated environment contains an empty APP_KEY and secure defaults.
- Generation/staged syntax validation also succeeds using the actual installed
  XAMPP configuration, without changing that configuration. The existing lack of
  an HTTP default vhost is reported as a generation warning, not silently fixed.
- Rejection of missing files, unsafe hostname/path input, output under a web root,
  output overwriting an input, invalid/expired/wrong-host certificates, wrong CA,
  mismatched keys, non-package site files, invalid Apache syntax, missing rewrite
  module/listener, conflicting hostnames, changed defaults and missing explicit include.
- Disposable apply installs only its site file; identical apply does not rewrite
  it; replacement preserves the previous bytes in a unique backup. Deliberately
  injected verification failures restore an existing file or remove a new file.
- A separate `httpd -X` process bound only to unused `127.0.0.1` high ports starts
  and is stopped by the harness. HTTP redirects preserve path/query and use the
  configured hostname/HTTPS port.
- HTTPS public asset, Laravel `/up`, `/login`, and unauthenticated `/cases` redirect
  work. Login issues Secure and HttpOnly cookies using isolated configuration.
- Requests for `.env`, parent traversal, vendor source, storage logs, an inherited
  alias outside `public`, server-status, server-info, phpMyAdmin, php-cgi and webalizer
  receive 400/403/404 without exposing the fixture's private sentinel.
- OpenSSL negotiates **TLS 1.2 and TLS 1.3**, verifying hostname and a temporary
  root/intermediate/leaf chain. **TLS 1.0 and TLS 1.1** receive protocol-version alerts.
- Unmatched HTTP hosts retain the original default site; an unrelated HTTPS name
  retains its original content with certificate verification. An unexpected Host
  header cannot serve CaseTrack's asset.
- Parent fixture configuration and development `.env` hashes remain unchanged;
  the installed Apache main configuration hash is also unchanged after generation.

The final disposable evidence directory is
`deployment/test-output/9e3234f3ee2d45bfbdca2516fcb6fc7c with spaces` (ignored by Git).
It contains development certificates/keys and configurations, never production
material. Do not distribute it or use its certificates for deployment.

## Isolation and limits

The fixture Laravel configuration is generated without loading the development
`.env`: it uses an ephemeral key, SQLite `:memory:`, array sessions/cache, synchronous
queue configuration, and temporary compiled-view/log paths. The HTTP smoke test
does not migrate or provision a database. The separate Laravel suite uses the
repository's existing in-memory SQLite test configuration.

Windows Schannel requests use an explicit temporary CA file and
`--ssl-revoke-best-effort` because the disposable CA has no revocation service.
Chain and hostname validation remain enabled. No CA was installed in a trust store.
The script execution-policy override was process-scoped; no saved policy changed.
Apache needed an unsandboxed test invocation to resolve workspace paths; that
invocation still used only disposable configuration and loopback listeners.

No software, hosts-file entries, firewall rules, installed site configuration,
running Apache service, persistent database, or vault wiki was changed. Apply was
exercised only against disposable destinations. No production migrations, role
seeding, Admin provisioning, database backup/restore or office-client tests were run.

## Still required from CHR IT

Confirm the office OS and supported/patched Apache/OpenSSL/PHP/MariaDB versions;
provide hostname/DNS, issuing CA and certificates, protected key storage, database
settings and service identity/ACLs. Approve the default-site/include preparation,
configuration apply, application/database installation steps and service activation
separately. Verify real client trust/revocation, served PHP version, unrelated office
sites, production cookies/assets, backup restoration and renewal monitoring before
accepting deployment. See the [runbook](README.md).
