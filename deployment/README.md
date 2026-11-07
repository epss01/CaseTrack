# CaseTrack office HTTPS deployment

This package prepares a deployment; it does **not** establish that the CHR office
server has been deployed. Initial script support is **Windows, Windows PowerShell
5.1+, Apache 2.4/XAMPP with mod_php, and PHP 8.4.27+ in the 8.4 series**. Linux,
reverse proxies, subdirectory hosting, FastCGI and containers need a separate
review. Templates contain no production secrets. Local `.env`, `.env.xampp` and
`.env.docker` are unchanged.

## Installation worksheet — CHR IT supplies these

| Value | Requirement |
| --- | --- |
| OS and server owner | Confirm Windows/XAMPP or arrange a port of this package. |
| Hostname and DNS | A dedicated DNS name; internal DNS resolves it to the office server. No IP URL, wildcard site alias or hosts-file workaround. |
| Application directory | A separate production copy, **outside** `htdocs` and other sites' roots/aliases. Only its `public` directory is served. |
| XAMPP directory | Existing Apache, OpenSSL, PHP CLI and matching thread-safe mod_php. |
| Certificate and private key | PEM full chain (leaf first, then intermediates) and matching unencrypted PEM key outside web roots; key readable only by authorized operators and the Apache service identity. Encrypted keys need a separately reviewed unattended key-management setup. |
| CA bundle | Trusted issuing root(s), obtained independently from CHR IT or the public CA. Script trust in this supplied file is not evidence of client trust. |
| Database | Dedicated MariaDB host, port, database, least-privilege account and secret. The development port `3307` is not a production assumption. |
| Operations | Service identity, backup location/retention, maintenance window, renewal owner and client trust distribution. Confirm `Asia/Manila` timezone. |

The version floor is a compatibility check, **not** a security approval of an old
XAMPP distribution. CHR IT must verify supported, patched Apache/OpenSSL/MariaDB
and PHP versions independently. Confirm the **HTTPS-served PHP runtime**, not
only `php -v`; a different Apache module or `PHPINIDir` can defeat a CLI check.
Do not leave a public `phpinfo()` page. The existing Laravel 11 security decisions
remain: no email validation/auth flow or signed/temporary URLs; introducing either
requires the previously documented Laravel upgrade first.

## Obtain a certificate

Preferred internal route: CHR IT issues a server-authentication certificate from
its managed CA with the **exact hostname in DNS subjectAltName (SAN)**. Generate
the private key and CSR using IT's approved tools and policy; send the CSR, never
the private key, to the issuer. Obtain the leaf certificate, intermediate chain,
and independently authenticated CA root. Assemble the server full-chain PEM with
the leaf first and intermediates afterward; do not include the private key.

Alternatively, use a public CA for a name under a CHR-controlled registered
domain. IT proves control using the CA's DNS-01 TXT challenge, optionally delegated
to a restricted validation zone. The application can remain on private LAN DNS/IP;
it need not be reachable from the internet for DNS-01. This does require authority
over publicly visible DNS, and a public certificate exposes its names in certificate
transparency records. Private-only names such as `.local` are unsuitable for this
route. Keep DNS API credentials on an approved renewal host with minimum privileges.
No ACME client or software is installed by this package.

For a private CA, IT must distribute the CA root through its managed device/browser
trust mechanism to **every client**. Intermediates should be served by Apache.
Installing trust only on the server does not make office browsers trust it.
Self-signed development certificates are for isolated testing only; clicking through
a browser warning or disabling verification is not production acceptance.

References: [Apache certificate chains and TLS](https://httpd.apache.org/docs/2.4/mod/mod_ssl.html),
[DNS-01 validation](https://letsencrypt.org/docs/challenge-types/),
[Laravel 11 deployment](https://laravel.com/docs/11.x/deployment).

## Generate and validate (default — no installed configuration changes)

Use a production application copy with dependencies and built assets already
present. The scripts do not install software, invoke Artisan, connect to databases,
copy keys, modify `.env`, install trust, change hosts/firewall rules, or reload Apache.
Review scripts before running them. Where local policy allows it, the example's
`-ExecutionPolicy Bypass` applies only to that process; it does not change the
machine's saved execution policy. Follow organizational signing policy if required.

```powershell
$settings = @{
    SiteHostname = 'casetrack.REPLACE_WITH_CHR_DOMAIN'
    ApplicationPath = 'C:/CHR/CaseTrack'
    XamppPath = 'C:/xampp'
    CertificatePath = 'C:/CHR/tls/casetrack-fullchain.pem'
    PrivateKeyPath = 'C:/CHR/tls/casetrack-key.pem'
    CaBundlePath = 'C:/CHR/tls/approved-roots.pem'
    OutputDirectory = 'C:/CHR/deployment-review'
}
& ./deployment/windows/Setup-CaseTrackHttps.ps1 @settings
```

Run from an allowed PowerShell session, or invoke the script using
`powershell.exe -NoProfile -ExecutionPolicy Bypass -File ...` with the same named
parameters. Missing hostname/application/certificate/key/CA values are prompted.
The XAMPP root defaults to `C:/xampp`; output defaults to the package's ignored
`deployment/generated/<hostname>` directory. No database secrets are prompted.
Optional `ApacheConfig` and `SiteConfig` parameters select an alternative main
configuration and dedicated site destination. HTTP/HTTPS ports default to 80/443;
alternate ports exist for isolated verification and must already have listeners.

Outputs are `casetrack-https.conf`, `.env.production.example`, and
`httpd-validation.conf` (a staging copy of the main configuration, **not** an
installation replacement). The generator checks certificates against the supplied
CA bundle, syntax, modules, basic asset presence and virtual-host mappings. It does
not certify DNS resolution, service-account ACLs, asset freshness, client trust or
database readiness. Failures exit nonzero with the missing requirement. A warning
about changed default mappings means the generated file is **not ready to apply**.

## Prepare Apache and apply — separate IT-approved operation

1. Inventory and back up the current Apache configuration, includes and site
   mappings. Review `httpd.exe -S`. Existing listeners and required modules must
   already be configured; the script never enables them globally.
2. Preserve the current default sites on both ports. Adding the **first** named
   HTTP vhost can steal requests previously served by Apache's main server.
   IT must establish appropriate default vhosts before adding CaseTrack, preserving
   the current behavior and certificate. Do not copy an arbitrary default example
   over existing sites. See [Apache virtual-host matching](https://httpd.apache.org/docs/2.4/vhosts/details.html).
3. After default-site definitions, IT adds this exact absolute-path include to
   the main configuration (forward slashes and double quotes):

   ```apache
   IncludeOptional "C:/xampp/apache/conf/extra/casetrack-https.conf"
   ```

   Use the selected `SiteConfig` path if different. This explicit include is the
   supported integration point; wildcard, nested and relative includes for the
   CaseTrack site are not supported by the installer. Do not include it twice.
4. Regenerate and review. The generated vhosts scope TLS and redirects to
   CaseTrack and block XAMPP aliases/diagnostic handlers within that site. Review
   any custom inherited Apache `Location`, alias, proxy or PHP configuration;
   arbitrary office customizations still require IT review. No junction/symlink
   may expose private application files through any public directory or alias.
5. With separate approval to change installed configuration:

   ```powershell
   & ./deployment/windows/Setup-CaseTrackHttps.ps1 @settings -Apply
   ```

   Only the dedicated site file is installed. Replacements receive timestamped,
   unique `.bak` files beside the site file. Identical content is not rewritten.
   Failed post-install syntax checks restore the prior file (or remove a newly
   created file). A missing explicit include, hostname conflict or changed default
   mapping refuses apply. Existing nonempty site files must have the package's
   identifying first-line comment; an unrelated configuration is never replaced.
   **Apache is not reloaded.**
6. IT separately authorizes and performs its normal Apache reload/restart, then
   tests the hostname from office clients and retests unrelated sites. Retain the
   configuration backup until acceptance. A successful script run alone is not
   deployment completion.

## Application and database — operator steps, never run by the script

These steps change an installation/database and require the separately approved
maintenance window and database permission. Do not run them against the development
copy. Pin the release/commit, preserve the lockfiles, and use the approved PHP binary.
Distribute reviewed release files, not a copy of the whole development working
directory: exclude its `.env`, `deployment/generated` and `deployment/test-output`.
Install dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader`;
build assets with `npm ci` then `npm run build` on the build host and deploy
`public/build`. Do not deploy `public/hot`, development tooling, sample credentials,
or development-generated configuration/route caches under `bootstrap/cache`.
Grant Apache read access to the release and write access only where needed:
`storage` and `bootstrap/cache`. Restrict `.env`, keys, backups and logs; validate
permissions using the actual service identity. Never grant blanket Everyone write.

**First installation:**

1. IT provisions an empty dedicated MariaDB database and account. Configure the
   generated production environment as the installation's `.env`; fill every
   `INSTALL_*` value and database password securely. Keep `APP_ENV=production`,
   `APP_DEBUG=false`, HTTPS URL and secure-cookie settings.
2. Run `php artisan config:clear` to discard any stale configuration cache, then
   generate a **new production** key with `php artisan key:generate --force` and
   back it up securely. Never copy the development key.
3. Run `php artisan migrate --force`, then
   `php artisan db:seed --class=RoleSeeder --force`. The existing **RoleSeeder only**
   creates the three role reference rows, without users/cases. Do not run the
   default DatabaseSeeder or DemoDataSeeder; both are development-only.
4. Run `php artisan make:admin` interactively. Give credentials directly to the
   designated Admin through the approved channel. Subsequent staff register and
   receive Admin approval through the existing workflow; Admin has no case access.

**Existing production database:**

1. Back up the database, release/assets, `.env` including the **existing APP_KEY**,
   needed storage, certificates and Apache configuration. Test restoration in a
   separate environment. Stop writes during the approved upgrade window with
   `php artisan down`; capture a consistent backup, including audit logs.
2. Preserve database identity, credentials and APP_KEY. Merge production HTTPS
   settings into the installed environment; do not replace it with the blank
   template or regenerate the key. Run `php artisan config:clear` after the
   environment edits so subsequent commands use the intended settings.
   Existing Admin accounts remain; use
   `make:admin` only when an explicitly approved new Admin is actually needed.
3. Inspect `php artisan migrate:status` and pending migrations. If the guarded
   legacy closure migration is pending and `status_before_closure` still exists,
   use these read-only checks (including soft-deleted records):

   ```sql
   SELECT COUNT(*) FROM cases WHERE status = 'Pending Closure';
   SELECT COUNT(*) FROM cases WHERE status_before_closure IS NOT NULL;
   ```

   Any nonzero count blocks that migration. Refer the records for explicit
   business resolution; do not automatically rewrite them or bypass the guard.
4. Run only reviewed forward migrations with `php artisan migrate --force`.
   RoleSeeder may be run explicitly if reference roles are missing; never seed
   demo data. **Never run `migrate:fresh`, `migrate:refresh`, `db:wipe`, or a reset.**

After either path, run `php artisan config:cache` and `php artisan view:cache`
against the final production environment. Do not use a blanket `optimize:clear`
that could clear database-backed application cache unintentionally. Review pending
queue work and arrange an approved worker only if the deployment uses queued jobs.
Run `php artisan up` after the approved changes are ready.

Acceptance from an office client: HTTP redirects to the exact HTTPS hostname;
certificate SAN/chain/date and browser trust pass without exceptions; `/up` and
login work; cookies have Secure/HttpOnly; unauthorized case access remains denied;
assets load without a Vite development server; sensitive paths and XAMPP tools are
unreachable through the CaseTrack name; unrelated sites still behave as before.
Do not put real case data into an installation that has not passed acceptance.

## Renewal, backups and rollback

CHR IT owns renewal, expiry alerts and a named backup operator. Record the issuer,
renewal method, certificate expiry and next review date in IT's operational register.
Monitor expiry at least daily with an alert window appropriate to the issuer
(start at 30 days). DNS-01 automation, if approved, belongs to IT; this package
creates no scheduled tasks. Also renew/test private CA roots and intermediates and
distribute new trust **before** switching certificates.

Before replacement, back up the current certificate/chain, key and site configuration
to protected storage. Validate the renewed SAN, server-auth usage, validity, full
chain and matching key, using this generator with the renewed files in separate
paths. Keep the old files intact until acceptance. Apply the reviewed path change,
authorize the normal Apache reload, then verify the certificate actually served
from an office client, both supported TLS versions, and unrelated sites. Never
assume replacing files on disk changed the certificate held by a running process.

Configuration rollback: restore the dedicated `.bak` file (or remove only the new
CaseTrack include/site during a first-install rollback), run Apache syntax/mapping
checks, and obtain approval to reload. Certificate rollback restores the previous
still-valid certificate/key paths and uses the same verification sequence.

Application rollback restores a compatible release/assets and configuration with
the original key. If migrations have changed schema/data, keep maintenance mode
and restore the tested **matching database backup** with separate approval; a code
rollback or `migrate:rollback` alone cannot reconstruct lost historical values.
Document any writes after the backup and reconcile them before reopening. Keep
case/audit data; never solve a failed deployment by resetting the database.

## Reproduce local verification

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File deployment/windows/Test-CaseTrackHttps.ps1
php artisan test
```

The dependency-free harness creates disposable files under ignored
`deployment/test-output`, uses a temporary CA explicitly (no trust installation),
and starts/stops its **own** Apache process on unused loopback-only high ports.
It exercises generation, refusal paths, disposable apply/backup/rollback,
HTTPS/Laravel routing, cookies, protocol negotiation, inherited endpoint denial,
and unrelated-site behavior. Laravel HTTP checks use separate cached configuration
with SQLite `:memory:`, an ephemeral key, array sessions/cache and temporary view/log
paths. No persistent database is migrated or written. Test keys must never be used
for deployment. The Laravel suite independently uses its existing in-memory SQLite
configuration. Schannel test requests allow unavailable revocation information
because the disposable CA has no CRL service; certificate chain and hostname
verification stay enabled. Production revocation/trust behavior requires IT testing.
See [VERIFICATION.md](VERIFICATION.md) for the recorded local result.

The vault wiki's existing HTTPS-decision entry will be stale after this package is
accepted; it has deliberately not been edited. Office deployment remains pending
CHR IT's installation values, platform/security review, service changes and acceptance.
