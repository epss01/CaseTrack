# CaseTrack

CaseTrack is a Laravel 11 application scaffolded with authentication views built on **Bootstrap 5** (via [laravel/ui](https://github.com/laravel/ui)) rather than Tailwind, per the project proposal.

## Contributing

For office-server HTTPS installation, see [the deployment runbook](deployment/README.md).
The deployment package initially supports Windows/XAMPP; office activation still requires CHR IT.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the branching strategy — `main` is checkpoint-only,
`develop` is the working trunk, features branch off `develop`.

## Tech Stack

- **Framework:** Laravel 11 (PHP)
- **Auth scaffolding:** `laravel/ui` (Blade views + Bootstrap 5)
- **Frontend build:** Vite + Sass (compiles `resources/sass/app.scss`)
- **Database:** MariaDB (bundled with XAMPP) — the test suite runs on in-memory SQLite instead (see step 6)

## Prerequisites

Make sure these are installed before setting up the project:

| Tool | Version |
|---|---|
| PHP | **8.4.27+** |
| Composer | 2.x |
| Node.js | 18+ (LTS recommended) |
| npm | 9+ |
| MariaDB | 10.4+ (bundled with XAMPP) |

**PHP 8.4.27 is the security floor, not a recommendation** — `composer.json` declares
`"php": "^8.4"` and `vendor/composer/platform_check.php` aborts with a 500 on anything below
`8.4.0`, while PHP releases below 8.4.27 are affected by CVE-2026-109618 and
CVE-2026-109619. If `php -v` reports below 8.4.27, upgrade it rather than working around it.

## Setup Instructions (fresh clone)

1. **Clone the repo**

   ```bash
   git clone <repo-url> CaseTrack
   cd CaseTrack
   ```

2. **Install PHP dependencies**

   ```bash
   composer install
   ```

3. **Install JS dependencies**

   ```bash
   npm install
   ```

4. **Copy the environment file**

   ```bash
   cp .env.example .env
   ```

5. **Generate the app key**

   ```bash
   php artisan key:generate
   ```

6. **Set up the database**

   The app runs on **MariaDB** (XAMPP's bundled database, not a separate MySQL install).
   `.env.example` already carries the right driver and port for this project's setup, so step 4
   should leave you on the correct values — confirm your `.env` has these:

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3307
   DB_DATABASE=casetrack
   DB_USERNAME=root
   DB_PASSWORD=
   ```

   (`DB_CONNECTION=mysql` is correct even though the server is MariaDB — Laravel's MySQL driver
   speaks MariaDB's wire protocol natively.) `3307` is where XAMPP's MariaDB listens on this
   project's setup, because a separate standalone MySQL 8 already holds the default `3306`.
   Check which port your own database uses and set that.

   Create the schema (via phpMyAdmin, or `CREATE DATABASE casetrack;`), then run the migrations:

   ```bash
   php artisan migrate
   ```

   **The test suite never touches this database.** `phpunit.xml` pins `DB_CONNECTION=sqlite` and
   `DB_DATABASE=:memory:`, so `php artisan test` builds a fresh in-memory SQLite database on every
   run. Only what you drive through a browser reaches the MariaDB one — worth remembering when a
   query behaves differently under test than it does in the app.

7. **Build front-end assets**

   For local development with hot reload:

   ```bash
   npm run dev
   ```

   Or a one-off production build:

   ```bash
   npm run build
   ```

8. **Start the app**

   ```bash
   php artisan serve
   ```

   Visit [http://127.0.0.1:8000](http://127.0.0.1:8000) — it redirects to `/login`.

   **A fresh database has no Admin account, so registering alone won't get you in.**
   `/register` lands a new account in `pending` status — it can't log in until an Admin
   approves it, and there's no route or view to create the first Admin (deliberately: it's
   the entire provisioning surface for that role). Bootstrap one from the shell first:

   ```bash
   php artisan make:admin
   ```

   It prompts for a username, name, and password. Log in as that account to approve any
   subsequent `/register` signups from `/admin/registrations`, or seed a full set of demo
   accounts instead (Investigators and Supervisors, already approved — see
   `database/seeders/DemoDataSeeder.php`; it refuses to run outside `local`/`testing`):

   ```bash
   php artisan db:seed --class=DemoDataSeeder
   ```

## Double-click local startup (Windows/XAMPP)

After completing setup, double-click **Open CaseTrack.cmd** in the project folder.
It starts XAMPP MariaDB on port 3307 and CaseTrack on
[http://127.0.0.1:8000/login](http://127.0.0.1:8000/login), then opens your default
browser. Existing matching services are reused. You can create a Windows desktop
shortcut to the `.cmd`; keep the launcher itself in the project folder.
For a custom Windows shortcut icon, use `public/images/casetrack-icon.ico` in
the shortcut's **Properties > Change Icon** dialog. The icon uses CaseTrack's
gold-and-navy CT mark. Windows applies custom icons to shortcuts (`.lnk`),
not to individual `.cmd` files. A locally created `CaseTrack.lnk` can be copied
to the desktop; recreate its target and icon paths if the project moves.

This launcher uses `C:\xampp`, PHP 8.4.27 or newer, the existing local `.env`
database settings (MariaDB at `127.0.0.1:3307`), and assets from `npm run build`.
Stop Vite before using it; a remaining `public/hot` file is reported as a setup
error. It does not install dependencies, migrate or seed the database, or log in.

New service windows start minimized. Keep the PHP window and MariaDB running
while using CaseTrack; closing the browser does not stop them. Close the PHP
server window when finished, and stop MariaDB through XAMPP when no other app
needs it. Startup failures remain visible in the launcher window.

## Pulling changes into an existing clone

`public/build` is **not committed** (see `.gitignore`), so compiled CSS and JS exist only on
machines that have built them. After pulling a branch, rebuild before trusting what you see:

```bash
composer install
npm install
php artisan migrate
npm run build
```

For day-to-day local work `npm run dev` replaces the last step and rebuilds as you edit.

**Why this matters more than it sounds.** A *missing* build fails loudly — Laravel throws
`Vite manifest not found` on the first page load, so you cannot miss it. A *stale* build does
not: the page renders perfectly using the previous build's CSS, so a styling change that
landed in the branch simply does not appear, and nothing tells you why. It looks like the
change is broken rather than unbuilt.

`php artisan test` carries a guard for exactly this (`tests/Feature/AssetsAreBuiltTest.php`).
It fails when `public/build` is missing or older than the newest file under `resources/sass`
or `resources/js`, names the file involved, and tells you what to run. It skips itself while
`npm run dev` is running, since the dev server serves assets from memory.

Only `resources/sass/**` and `resources/js/**` are compiled. Blade templates, PHP classes and
routes are read at request time and never need a rebuild.

## Case imports

Import accepts the CHR monitoring XLSX workbook with its existing title and merged
headers, or a flat CSV/XLSX using the labels shown on the upload page. Only the active
worksheet is imported. Repeat docket numbers for additional people, or explicitly
merge the docket cells; unmerged blank docket numbers are rejected.

Supervisors can select a fallback investigator for a blank investigator heading.
A name already in the workbook must resolve to an eligible account and is never
overridden by the selector. Investigators can import only their own cases.
Preview reports incomplete cases before anything is written. Missing case status
defaults to Docketed, complexity defaults to 3 for review, and torture classification
remains unknown.

CHR's 30-day extension **request date** and 120th-day ROP **submission date** are
stored separately from deadlines. They can be viewed and edited on the case timeline;
neither grants an extension nor marks the FIR submitted. Run `php artisan migrate`
before using this importer on another installation. Existing CSV export labels
and deadline calculations are unchanged.

The application accepts files up to **20 MB**. Configure the PHP runtime serving
the website (not just CLI PHP), then restart that server:

```ini
upload_max_filesize = 20M
post_max_size = 24M
```

Any reverse proxy or web-server request limit must also allow the multipart request.
Workbook size alone does not predict parsing memory: a highly compressed workbook
with many populated cells may require splitting into smaller imports. Local testing
of a 6.3 MB synthetic CHR workbook with 350 rows used 58 MB peak PHP memory and
0.88 seconds for parsing; this is not a guarantee for every 20 MB workbook.

## Cases and monitoring exports

Cases opens as a folder grid with a List option. The selected view is remembered
for the current session; search and pagination work in either view without JavaScript.

Edit case supports corrections, additions and explicit removal of victims,
respondents and complainants, plus Unknown/Yes/No torture-case classification.
Uncheck a removal before saving to undo it. Empty people groups are allowed when
editing; intake still requires at least one victim and respondent. Adding rows
uses JavaScript; existing-person edits and removal checkboxes work without it.
Timeline dates remain in their separate editor. Both editors share the case's
version token, so a successful save invalidates older open forms. Supervisors
must provide an audit reason in either editor.

Confirmed torture cases now receive a 60-day RORP milestone. The timeline editor
can record an **externally granted 30-day extension**, with a required approval
reference or explanatory audit note. The approved prototype assumes this moves
the deadline to 90 calendar days from docketing, not from approval. This assumption
requires CHR validation before office adoption. CaseTrack does not approve grants;
the initial-ROP extension request date alone never changes this deadline.
Unknown classification is explicitly marked as not evaluated for the 60-day rule.
Existing manual base-deadline overrides are retained; recorded grants take precedence.

Before office rollout, run `php artisan cases:check-torture-deadlines`. This read-only
preflight reports stored base dates differing from docket +60, extensions differing
from docket +90, and grants on unconfirmed/non-torture cases. A nonzero exit means
records need explicit review/correction; the command never changes them. Legacy
dates are not automatically overwritten. Grant removal and docket-date changes
that recalculate a grant require an explanatory note and are audited atomically.
The CSV's eleven columns and CHR workbook layout do not carry grant metadata.

Reports → CHR monitoring worksheet previews the office monitoring fields.
**Download CHR Excel** exports every case matching the current reporting filters,
with one worksheet per investigator in the CHR template layout. The existing
eleven-column CSV remains available. Both downloads enforce the user's case scope.
The Excel file is a monitoring document, not a complete CaseTrack backup: the CHR
template has no case-progress status or complexity column. Import previews new
cases only, rejects existing dockets, and reads only the active worksheet.

## Project Structure

Beyond the `laravel/ui` scaffold, the app is organized by feature:

- `app/Http/Controllers/Auth/` — login, registration, and password-confirmation controllers from `laravel/ui` (email-based reset/verify are disabled — the schema has no email column)
- `app/Http/Controllers/` — `CaseController`, `CaseTimelineController` (case intake, editing, direct investigator closure/reopening, timelines); `ReportController`, `AlertController` (case listings, CSV export, deadline alerts); `WorkloadController` (workload capacity ranking, performance ratings); `RegistrationApprovalController`, `UserAccountController`, `AuditLogController` (Admin-only: registration approval, account management, audit log viewer); `HomeController` (routes to the right dashboard by role)
- `app/Models/` — `CaseModel` (not `Case`, a reserved word), `User`, `Role`, `AuditLog`, `CaseTimeline`, `Victim`, `Respondent`, `Complainant`
- `app/Policies/CaseModelPolicy.php` — per-case authorization (view/update/delete/reassign and assigned-investigator-only closure changes), wired via `authorizeResource()`
- `app/Http/Middleware/` — `EnsureUserHasRole` (route-level role gate), `EnsureAccountIsActive` (ends a session if its account is deactivated or unapproved mid-session)
- `app/Services/CaseDeadlineService.php` — the only place that does date arithmetic on statutory case-timeline deadlines
- `app/Console/Commands/` — `make:admin` (the only way to provision an Admin account — no route, no view), `users:rotate-password`
- `resources/views/` — grouped by feature (`cases/`, `reports/`, `workload/`, `admin/`, `auth/`), sharing `layouts/app.blade.php`
- `resources/sass/app.scss` — imports Bootstrap SCSS and custom variables
- `routes/web.php` — role-gated route groups (`role:Investigator,Supervisor`, `role:Supervisor`, `role:Admin`) plus `Auth::routes()`
- `database/migrations/` — case/victim/respondent/complainant/timeline tables, `audit_logs`, the Admin role and account-status columns, alongside the three Laravel defaults (`users`, `cache`, `jobs`)
- `database/seeders/` — `RoleSeeder`, `DatabaseSeeder`, `DemoDataSeeder` (all refuse to run outside `local`/`testing`)

## Common Commands

```bash
php artisan migrate:fresh    # reset the database
php artisan route:list       # list all routes
php artisan test             # run the test suite (also checks assets are built and current)
php artisan make:admin       # provision an Admin account (no route/view exists for this)
npm run build                # rebuild assets after a change under resources/sass or resources/js
npm run dev                  # watch and rebuild while developing

# generate a new password for an account, ending its sessions
php artisan users:rotate-password <username> --actor=<you>
```

## Running with Docker

An optional, additive alternative to the setup above — app (`php:8.4.27-cli`) + database
(`mariadb:10.4`, matching the version above) in containers. This doesn't replace the host
workflow; both work side by side against their own separate databases.

```bash
cp .env.docker .env
docker compose up -d --build
docker compose exec app php artisan migrate
```

Visit [http://localhost:8000/login](http://localhost:8000/login). First boot installs Composer
and npm dependencies and builds assets automatically (a few minutes); later boots skip whatever
already exists. Seed demo accounts the same way as the host setup:
`docker compose exec app php artisan db:seed --class=DemoDataSeeder`.

A couple of things that will otherwise cost you time:

- The container runs as **root** — fine for a throwaway dev container on a bind mount, not a
  choice to carry into any future production image.
- The database publishes on **`127.0.0.1:3308`**, not 3306 or 3307 — both are already taken on
  a machine also running XAMPP and a standalone MySQL. Only matters if you want to reach it from
  a host tool like phpMyAdmin; the app container talks to it over the Docker network regardless.
- `node_modules` is a separate volume, not the bind-mounted host folder — the host's copy has
  Windows-native build binaries (`esbuild`/`rollup`) that don't run inside the Linux container.

`docker compose down -v` stops everything and drops the database volume.

## Switching back to XAMPP (from Docker)

The reverse direction of the section above — hop back to the host XAMPP setup that
`## Setup Instructions` assumes by default, without hand-editing `.env`:

```bash
cp .env.xampp .env
php artisan key:generate
```

A couple of things that will otherwise cost you time:

- **Start MariaDB first** — XAMPP Control Panel, or `C:\xampp\mysql_start.bat`. `.env.xampp`
  points at **port 3307** (XAMPP's bundled MariaDB), not Docker's 3308 or the standalone MySQL 8
  on 3306.
- **Use a patched PHP runtime.** This machine's XAMPP PHP was upgraded to 8.4.27 at
  `C:\xampp\php\php.exe` and is on `PATH`, so invoke it normally:

  ```bash
  php artisan serve
  ```

  Other machines must provide PHP 8.4.27 or newer and Composer 2.
- No asset rebuild needed for this switch — `public/build` doesn't depend on which `.env` is
  active.
