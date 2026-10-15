# Dairy ERP

> **Note for Claude Code:** keep this README complete and true at the end of every phase (see `CLAUDE.md` →
> "Documentation you must keep up to date"). Replace every `_TODO_` as soon as the information exists.
> By the end of Phase 7 there must be no `_TODO_` left.

A web-based ERP for an Indian dairy business: own cow/buffalo herd, milk collection center (farmers, Fat/SNF
rate charts, payments), milk sales (B2C home delivery subscriptions and B2B bulk supply with monthly billing),
cow/buffalo buying & selling, expenses, staff, and profit reports — with every litre and every rupee traceable.

---

## Project status

| Phase | Module | Status |
|---|---|---|
| 0 | Foundation (login, roles, settings, appearance, monitoring, audit log, backups) | ⬜ Not started |
| 1 | Expense Management (no approval) + Excel import | ⬜ Not started |
| 2 | Animals, Milk Production, Milk Stock | ⬜ Not started |
| 3 | Customers, Delivery, Billing, Payments | ⬜ Not started |
| 4 | Farmers & Collection Center | ⬜ Not started |
| 5 | Health, Breeding, Feed | ⬜ Not started |
| 6 | Animal Trading & Staff | ⬜ Not started |
| 7 | Dashboard & Reports | ⬜ Not started |

Detailed task-by-task progress: [`docs/PROGRESS.md`](docs/PROGRESS.md) · Version history: [`CHANGELOG.md`](CHANGELOG.md)

---

## Features

_TODO: for each module, list the working features in plain language once its phase is done._

- **Expenses** — _TODO_
- **Herd & milk production** — _TODO_
- **Milk stock** — _TODO_
- **Customers, delivery & billing** — _TODO_
- **Collection center** — _TODO_
- **Health, breeding & feed** — _TODO_
- **Animal trading** — _TODO_
- **Staff & salary** — _TODO_
- **Dashboard & reports** — _TODO_
- **Admin panel** — users, roles & permissions, farm settings, **Appearance** (logo, colors, fonts, theme, login
  page, PDF header/footer), audit log, system monitor.

---

## Tech stack

- PHP 8.3+, Laravel 12.x (or newer stable) — modular monolith
- MySQL 8 (InnoDB, utf8mb4)
- Blade + Bootstrap 5.3 + jQuery, Select2, Flatpickr, DataTables, Chart.js — **no npm / no Vite / no build step**
  (libraries committed in `public/vendor/`, versions in `public/vendor/VERSIONS.md`)
- Packages: spatie/laravel-permission, spatie/laravel-activitylog, spatie/laravel-backup, maatwebsite/excel,
  barryvdh/laravel-dompdf, yajra/laravel-datatables-oracle, laravel/pulse, sentry/sentry-laravel,
  intervention/image, laravel/telescope (dev only), Pest, Pint

---

## Requirements

- PHP 8.3+ with extensions: bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, zip
- Composer 2
- MySQL 8 (production). MariaDB 10.4+ also works for local development (XAMPP): set `DB_CONNECTION=mariadb`

---

## Installation (local)

```bash
git clone https://github.com/testinggmail637-art/dairyErpSystem.git dairy-erp && cd dairy-erp   # private repo
composer install
cp .env.example .env && php artisan key:generate
# create databases: dairy_erp and dairy_erp_test
cp .env.example .env.testing   # set DB_DATABASE=dairy_erp_test
php artisan key:generate --env=testing
php artisan migrate --seed
php artisan storage:link
php artisan db:seed --class=DemoSeeder   # optional demo data
php artisan serve
```

Open http://127.0.0.1:8000 — or, with XAMPP, put the project in `htdocs` and open
http://localhost/<folder>/public (set `APP_URL` to the same). Tests: keep `APP_URL=http://localhost` in `.env.testing`.

### Default login (local only — change immediately)

| Role | Email / Phone | Password |
|---|---|---|
| Owner | value of `OWNER_EMAIL` / `OWNER_PHONE` in `.env` | value of `OWNER_PASSWORD` |
| _TODO: demo users created by DemoSeeder_ | | |

---

## Important `.env` settings

| Key | Example | Purpose |
|---|---|---|
| `APP_TIMEZONE` | `Asia/Kolkata` | All dates in IST |
| `APP_URL` | `http://localhost/dairyErpSystem/public` | Base URL (XAMPP sub-folder locally) |
| `DB_CONNECTION` | `mysql` (prod) / `mariadb` (XAMPP) | Database driver |
| `DB_DATABASE` | `dairy_erp` | Main database |
| `OWNER_NAME`, `OWNER_EMAIL`, `OWNER_PHONE`, `OWNER_PASSWORD` | | First owner user created by seeder |
| `QUEUE_CONNECTION` | `database` | Background jobs |
| `PULSE_ENABLED` | `true` | Monitoring dashboard |
| `PULSE_SLOW_REQUESTS_THRESHOLD` | `1000` | ms |
| `PULSE_SLOW_QUERIES_THRESHOLD` | `500` | ms |
| `SENTRY_LARAVEL_DSN` | _(empty = disabled)_ | Error alerts |
| `DB_DUMP_BINARY_PATH` | `C:/xampp/mysql/bin` (XAMPP) / empty (Linux) | Folder with `mysqldump`, for backups |
| `BACKUP_DISKS`, `BACKUP_PATH`, `BACKUP_ARCHIVE_PASSWORD` | `backups`, empty, empty | Where backups go / optional zip password — see `docs/BACKUP_RESTORE.md` |
| _TODO: any other keys added during build_ | | |

---

## Running background processes

```bash
php artisan schedule:work     # local; in production use cron: * * * * * php artisan schedule:run
php artisan queue:work        # production: under Supervisor
php artisan pulse:check       # production: under Supervisor (server stats)
```

Scheduled jobs (IST, defined in `routes/console.php`):

| Time | Command | What |
|---|---|---|
| 02:00 | `backup:run` | Database + uploads backup to `storage/app/backups` (kept 14 days) |
| 02:30 | `backup:clean` | Delete backups older than 14 days |
| 03:00 | `backup:monitor` | Health check; owners get a bell notification if a backup failed or is too old |

_More jobs (delivery sheet generation, reminders) are added in later phases._ Restore steps: `docs/BACKUP_RESTORE.md`.

---

## Monitoring

| Tool | URL / where | Who |
|---|---|---|
| Laravel Pulse | `/pulse` (Settings → System Monitor) | Owner only (`monitoring.view`); admin/staff get 403. Slow requests > 1000 ms, slow queries > 500 ms, exceptions, queues, users, server stats (needs `php artisan pulse:check` running); 7 days of data |
| Sentry | sentry.io project | Active only when `SENTRY_LARAVEL_DSN` is set; no personal data sent; 20 % tracing |
| Telescope | `/telescope` | Local development only (`APP_ENV=local`); never installed in production (`composer install --no-dev`) |

---

## Running tests

```bash
php artisan test        # uses dairy_erp_test (MySQL), never the main DB
./vendor/bin/pint
```

Current: 182 tests passing (Phase 0 · Task 12).

---

## Roles & permissions (summary)

| Role | Can do |
|---|---|
| Owner | Everything, including roles, farms, monitoring |
| Admin | Everything except roles, farms, monitoring |
| Accountant | Expenses, vendors, bills, payments, farmer payments, salary, reports |
| Herd staff | Milk entry, health, breeding, feed, add expenses |
| Collection operator | Farmer milk collection entry |
| Delivery boy | Own route deliveries and payment collection |

Full list: `docs/DAIRY_ERP_SPEC.md` Section 5; in code `app/Support/Permissions.php` (permissions) and
`app/Enums/RoleName.php` (default role permissions). Permissions are editable from Settings → Roles.
After adding a permission in code run `php artisan db:seed --class=RolesAndPermissionsSeeder` (keeps role edits).

---

## Customizing the look

Settings → **Appearance**: app name, logo, favicon, colors (primary, sidebar, top bar, background, status
colors), font, font size, corner radius, light/dark/auto theme, login page, PDF header/footer. Includes live
preview, presets (Green Dairy, Blue Professional, Warm Orange, Dark Slate) and "Reset to defaults". No code changes needed.
Permission: `appearance.manage` (owner + admin). Each user can pick their own theme in **My profile**.
"Sample PDF" shows the print branding. Developers: colours/fonts come only from the CSS variables printed by
`App\Services\AppearanceService` — never add colour literals to Blade, `app.css`, JS charts (`App.chartColors()`) or PDFs.
Uploaded branding files live in `storage/app/public/branding/{farm_id}/` (run `php artisan storage:link` once).

---

## Deployment

Summary: Linux VPS, Nginx, PHP-FPM, MySQL, cron for scheduler, Supervisor for queue and Pulse.
Full steps: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) · Backups & restore: [`docs/BACKUP_RESTORE.md`](docs/BACKUP_RESTORE.md)

---

## Folder structure

_TODO: short tree of `app/` modules, `resources/views/`, `public/vendor/`, `docs/` once they exist._

---

## Documentation index

| File | What it contains |
|---|---|
| `CLAUDE.md` | Rules for the AI developer |
| `docs/DAIRY_ERP_SPEC.md` | Full specification (tables, rules, phases) |
| `docs/PROGRESS.md` | Phase-wise checklist and work log |
| `docs/DECISIONS.md` | Assumptions and decisions taken during build |
| `docs/DEPLOYMENT.md` | Production deployment |
| `docs/BACKUP_RESTORE.md` | Backup and restore steps |
| `CHANGELOG.md` | Version history per phase |
