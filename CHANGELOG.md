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

### Changed
- `/dashboard` requires `dashboard.view`; after login users go to `/` (`home`), which opens the dashboard or the first
  screen they are allowed to see.
