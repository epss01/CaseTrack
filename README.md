# CaseTrack

CaseTrack is a Laravel 11 application scaffolded with authentication views built on **Bootstrap 5** (via [laravel/ui](https://github.com/laravel/ui)) rather than Tailwind, per the project proposal.

## Tech Stack

- **Framework:** Laravel 11 (PHP)
- **Auth scaffolding:** `laravel/ui` (Blade views + Bootstrap 5)
- **Frontend build:** Vite + Sass (compiles `resources/sass/app.scss`)
- **Database:** SQLite by default (see [Database](#database) below to switch to MySQL/Postgres)

## Prerequisites

Make sure these are installed before setting up the project:

| Tool | Version |
|---|---|
| PHP | 8.2+ |
| Composer | 2.x |
| Node.js | 18+ (LTS recommended) |
| npm | 9+ |

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

   The project defaults to SQLite, which needs no server setup. Create the database file, then run migrations:

   ```bash
   touch database/database.sqlite
   php artisan migrate
   ```

   If you'd rather use MySQL/Postgres, update `DB_CONNECTION` and the `DB_*` values in `.env`, then run `php artisan migrate`.

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
php artisan test            # run the test suite
npm run build                # rebuild assets after Bootstrap/SCSS changes
```
