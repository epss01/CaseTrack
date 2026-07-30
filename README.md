# CaseTrack

CaseTrack is a Laravel 11 application scaffolded with authentication views built on **Bootstrap 5** (via [laravel/ui](https://github.com/laravel/ui)) rather than Tailwind, per the project proposal.

## Tech Stack

- **Framework:** Laravel 11 (PHP)
- **Auth scaffolding:** `laravel/ui` (Blade views + Bootstrap 5)
- **Frontend build:** Vite + Sass (compiles `resources/sass/app.scss`)
- **Database:** MySQL — the test suite runs on in-memory SQLite instead (see step 6)

## Prerequisites

Make sure these are installed before setting up the project:

| Tool | Version |
|---|---|
| PHP | **8.4+** |
| Composer | 2.x |
| Node.js | 18+ (LTS recommended) |
| npm | 9+ |
| MySQL | 8.x |

**PHP 8.4 is a hard requirement, not a recommendation** — despite `composer.json` declaring
`"php": "^8.2"`. The lock file resolved against 8.4, and `vendor/composer/platform_check.php`
aborts with a 500 on anything below `8.4.0`. XAMPP's bundled PHP 8.2.12 therefore cannot run
`artisan` at all, so if you use XAMPP for MySQL you still need a separate PHP. If `php -v`
reports below 8.4, install a newer one rather than working around it.

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

   The app runs on **MySQL**. `.env.example` still carries Laravel's stock SQLite default, so
   copying it in step 4 leaves you on the wrong database — set these values in your `.env`:

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3307
   DB_DATABASE=casetrack
   DB_USERNAME=root
   DB_PASSWORD=
   ```

   `3307` is where XAMPP's MySQL listens on this project's setup, because a separate standalone
   MySQL 8 already holds the default `3306`. Check which port your own MySQL uses and set that.

   Create the schema (via phpMyAdmin, or `CREATE DATABASE casetrack;`), then run the migrations:

   ```bash
   php artisan migrate
   ```

   **The test suite never touches this database.** `phpunit.xml` pins `DB_CONNECTION=sqlite` and
   `DB_DATABASE=:memory:`, so `php artisan test` builds a fresh in-memory SQLite database on every
   run. Only what you drive through a browser reaches the MySQL one — worth remembering when a
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

   Visit [http://127.0.0.1:8000](http://127.0.0.1:8000) — it redirects to `/login`. Use `/register` to create an account.

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

Standard Laravel 11 layout:

- `app/Http/Controllers/Auth/` — authentication controllers (login, register, password reset) from `laravel/ui`
- `app/Http/Controllers/HomeController.php` — post-login landing page controller
- `resources/views/auth/` — Bootstrap-styled login/register/password views
- `resources/views/layouts/app.blade.php` — main Bootstrap navbar layout
- `resources/sass/app.scss` — imports Bootstrap SCSS and custom variables
- `routes/web.php` — app routes, including `Auth::routes()`
- `database/migrations/` — schema migrations (users, cache, jobs tables by default)

## Common Commands

```bash
php artisan migrate:fresh   # reset the database
php artisan route:list      # list all routes
php artisan test            # run the test suite (also checks assets are built and current)
npm run build               # rebuild assets after a change under resources/sass or resources/js
npm run dev                 # watch and rebuild while developing
```
