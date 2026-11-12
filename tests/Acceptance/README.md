# Isolated MariaDB concurrency acceptance

Run from the repository root on an authorized **local development MariaDB**:

```text
php tests/Acceptance/MariaDbConcurrentSaves.php
```

This is a separate acceptance harness, not part of `php artisan test`. It needs
the installed development dependencies, PHP 8.4.27+ with PDO MySQL, and a local
MariaDB server. It uses the configured local TCP host/port and credentials only
to create a randomly named `casetrack_acceptance_<16 hex digits>` database.
The account needs fixture creation/migration/drop privileges and permission to
observe `information_schema.INNODB_LOCK_WAITS` / `INNODB_TRX` (normally PROCESS).
DB_URL/socket connections and non-loopback hosts are refused. **Do not use this
on an office/production server**, even one running on localhost.

The harness does not write `.env` or select the normal application database.
Every application connection is replaced in memory and checked with
`SELECT DATABASE()` before migration or request execution. Only reviewed forward
migrations run, on the new empty fixture. The database is dropped in `finally`
after worker processes stop; cleanup checks the generated name and excludes the
configured database. An abrupt OS/process kill can prevent `finally` cleanup;
inspect only the harness's generated fixture before an explicitly scoped cleanup.
Never drop an arbitrary database by prefix search.

Two PHP processes send PUT requests through Laravel's HTTP kernel, route-role
middleware, validation, record policies and existing controllers. Test-only model
events hold the first request inside its locked transaction. The coordinator
releases it only after MariaDB reports the second connection actually waiting
on its InnoDB lock. This is controlled overlapping execution, not simultaneous
browser clicks or a throughput benchmark. Testing mode uses array sessions/cache
and bypasses CSRF; browser authentication, CSRF and network/TLS are not certified.
Production application code has no acceptance hooks.

Seven scenarios check competing timeline and case saves, both orderings of
reassignment/grant recording, Supervisor accountability, and rollback after an
injected extension-audit failure while another grant request waits. They assert
HTTP outcomes, stale error keys, persisted fields, versions, exact successful
audit counts and grant references. An authorization failure may retain the
existing separate `ACCESS_DENIED` audit; it must not retain a successful mutation.

Connection credentials travel to child processes over stdin, not arguments or
fixture files. Local barrier files under ignored `storage/app/casetrack_acceptance_*`
contain only synthetic coordination markers/connection IDs. Existing config and
route caches are bypassed using unique nonexistent paths. A normal exit prints
sanitized JSON evidence and verifies `.env`'s SHA-256 did not change.

See the [Stage 5 record](../../deployment/STAGE5_ACCEPTANCE.md) for actual results
and the separately pending Narrator and CHR checks.
