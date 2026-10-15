# Changelog

All notable changes are recorded here, one version per phase (Phase n → `0.n.0`).
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- Project specification (`docs/DAIRY_ERP_SPEC.md`), AI developer rules (`CLAUDE.md`), progress log, README template.
- Phase 0 · Task 1: Laravel 12 on PHP 8.4 with MySQL/MariaDB (`dairy_erp`, tests on `dairy_erp_test`), Pest 4, Pint;
  Vite/npm removed; Bootstrap 5, Bootstrap Icons, jQuery, Select2, Flatpickr, DataTables, Chart.js, SweetAlert2
  in `public/vendor/` (see `public/vendor/VERSIONS.md`); `public/css/app.css`, `public/js/app.js`, `asset_v()` helper;
  hand-built login by email or phone with rate limiting and inactive-user block; owner user seeder.
- Phase 0 · Task 2: app layout with permission-filtered sidebar (`config/menu.php`, off-canvas on mobile), top bar
  with notifications bell and user menu, flash messages, own profile page (name, change password), notifications list,
  Indian money/quantity/date formatting (`App\Support\Format`, `inr()`, `fmt_date()`, …).
- Phase 0 · Task 3: `App\Support\Money` (bcmath, half-up rounding, rejects floats); `app/Enums` with `HasOptions`
  (`MilkType`, `MilkSource`); `farms` + `farm_user` tables, `Farm` model with settings defaults, default farm seeder
  (My Dairy Farm / MDF, owner linked); `BelongsToFarm` (farm global scope + auto `farm_id`) and `TracksUser`
  (`created_by` / `updated_by`) traits; `CurrentFarm` service; `EnsureFarmSelected` middleware (`farm`) on all app
  routes (403 when not linked, other farm's records 404); farm name / switcher in the top bar.
- Phase 0 · Task 4: `sections` and `payment_modes` tables, models, factories and per-farm default seeding
  (HERD, COLLECTION, DELIVERY, TRADING, OFFICE · CASH, UPI, BANK, CHEQUE, CARD); `document_sequences` table,
  `DocumentType` enum and `DocumentNumberService` (BILL/2026-27/0001, locked, per farm and financial year);
  `App\Support\FinancialYear`; Blueprint macros `farmScoped()` and `userStamps()`.
- Phase 0 · Task 5: `spatie/laravel-permission` tables and config; all 60 permissions (`App\Support\Permissions`) and
  the six default roles (`RoleName` enum: owner, admin, accountant, herd_staff, collection_operator, delivery_boy)
  seeded by `RolesAndPermissionsSeeder`; seeded owner user gets the owner role.
- Phase 0 · Task 6: `EnsureUserIsActive` middleware — a user deactivated while logged in is signed out on the next
  request (login by email/phone, inactive block and `last_login_at` were built in Task 1).
- Phase 0 · Task 7: Settings screens — Users (list with search/role/status filters, create, edit, deactivate,
  password reset that signs the user out, owner-role protection), Roles (permission matrix grouped by module, custom
  roles, locked owner role, delete when unused), Farm settings (details + settings keys with validation), Sections and
  Payment Modes (list, add, edit, activate/deactivate); `data-confirm` confirmation dialog in `app.js`.
- Phase 0 · Task 8: audit log with `spatie/laravel-activitylog` — users, roles (incl. permission changes) and farm
  settings logged with old → new values, user and reason; required reason on user edit and role edit/delete
  (`RequiresReason`, `<x-reason-field />`, `data-confirm-reason`); Settings → Audit Log screen with user, record type,
  action and date filters; `activity_log.farm_id` keeps farms apart.
- Phase 0 · Task 9: daily backups with `spatie/laravel-backup` — database + uploads at 02:00 IST, kept 14 days
  (cleanup 02:30, health check 03:00) on the `backups` disk; owners get a bell notification when a backup fails or is
  too old (no email); `DB_DUMP_BINARY_PATH` + `mysqldump` fallback for XAMPP MariaDB; `docs/BACKUP_RESTORE.md`.
- Phase 0 · Task 10: common Blade components — `x-form.input`, `textarea`, `select` (Select2, remote search), `money`
  (live ₹ preview), `quantity`, `date` (DD-MM-YYYY), `switch`, `reason`, `group`; `x-confirm-form` (confirm with optional
  reason), `x-data-table` (server-side or client-side DataTables), `x-money`; `App.reloadTable()`; Users, Roles and
  Sections/Payment Modes forms now use them.
- Phase 0 · Task 11: monitoring — Laravel Pulse at `/pulse` (Settings → System Monitor, `monitoring.view` only;
  slow requests > 1000 ms, slow queries > 500 ms, slow jobs > 1000 ms, 7-day retention), Sentry wired into exception
  handling (inactive without `SENTRY_LARAVEL_DSN`, no personal data, 20 % tracing), Telescope for local development
  only; `docs/DEPLOYMENT.md` (Nginx, PHP-FPM, cron, Supervisor for `queue:work` and `pulse:check`, `.env` checklist).

### Changed
- `/dashboard` requires `dashboard.view`; after login users go to `/` (`home`), which opens the dashboard or the first
  screen they are allowed to see.

### Fixed
- Sidebar invisible on desktop (Bootstrap `.offcanvas-lg` forced a transparent background from 992px up).
- Sidebar groups (e.g. Settings) opened with a jumpy sideways animation (collapse moved off the flex `.nav` list).
- `/pulse` crashed with "Table pulse_aggregates doesn't exist" (Pulse migrations were not published yet).
