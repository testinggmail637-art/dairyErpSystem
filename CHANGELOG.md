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
