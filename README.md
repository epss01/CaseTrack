# CaseTrack

CaseTrack is a Laravel 11 application scaffolded with authentication views built on **Bootstrap 5** (via [laravel/ui](https://github.com/laravel/ui)) rather than Tailwind, per the project proposal.

## Contributing

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
| PHP | **8.4+** |
| Composer | 2.x |
| Node.js | 18+ (LTS recommended) |
| npm | 9+ |
| MariaDB | 10.4+ (bundled with XAMPP) |

**PHP 8.4 is a hard requirement, not a recommendation** — `composer.json` declares
`"php": "^8.4"` and `vendor/composer/platform_check.php` aborts with a 500 on anything below
`8.4.0`. XAMPP's bundled PHP 8.2.12 therefore cannot run `artisan` at all, so if you use XAMPP
for the database you still need a separate PHP. If `php -v` reports below 8.4, install a newer
one rather than working around it.

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

## Project Structure

Beyond the `laravel/ui` scaffold, the app is organized by feature:

- `app/Http/Controllers/Auth/` — login, registration, and password-confirmation controllers from `laravel/ui` (email-based reset/verify are disabled — the schema has no email column)
- `app/Http/Controllers/` — `CaseController`, `CaseTimelineController` (case intake, editing, closure, timelines); `ReportController`, `AlertController` (case listings, CSV export, deadline alerts); `WorkloadController` (workload capacity ranking, performance ratings); `RegistrationApprovalController`, `UserAccountController`, `AuditLogController` (Admin-only: registration approval, account management, audit log viewer); `HomeController` (routes to the right dashboard by role)
- `app/Models/` — `CaseModel` (not `Case`, a reserved word), `User`, `Role`, `AuditLog`, `CaseTimeline`, `Victim`, `Respondent`, `Complainant`
- `app/Policies/CaseModelPolicy.php` — per-case authorization (view/update/delete/reassign/closure), wired via `authorizeResource()`
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

An optional, additive alternative to the setup above — app (`php:8.4-cli`) + database
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

The reverse direction of the section above — hop back to the host XAMPP + Herd Lite setup that
`## Setup Instructions` assumes by default, without hand-editing `.env`:

```bash
cp .env.xampp .env
php artisan key:generate
```

A couple of things that will otherwise cost you time:

- **Start MariaDB first** — XAMPP Control Panel, or `C:\xampp\mysql_start.bat`. `.env.xampp`
  points at **port 3307** (XAMPP's bundled MariaDB), not Docker's 3308 or the standalone MySQL 8
  on 3306.
- **Use Herd Lite's PHP, not XAMPP's bundled one.** XAMPP's bundled PHP (8.2.12) can't run
  `artisan` at all — this project needs 8.4+. The working binary is Herd Lite, at
  `C:\Users\admin\.config\herd-lite\bin\php.exe`, and it's **not on PATH**, so invoke it by
  absolute path:

  ```bash
  "C:/Users/admin/.config/herd-lite/bin/php.exe" artisan serve
  ```

  Composer lives beside it at `...\herd-lite\bin\composer.phar`, same rule.
- No asset rebuild needed for this switch — `public/build` doesn't depend on which `.env` is
  active.
