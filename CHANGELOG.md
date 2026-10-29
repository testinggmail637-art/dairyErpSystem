# Changelog

All notable changes are recorded here, one version per phase (Phase n → `0.n.0`).
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- Appearance presets shown as preview cards (mini sidebar, cards and chart in the preset's colours, description, tick on the active one) and three new presets: Cream & Butter, Teal Breeze, Forest Night; dark presets also switch the theme to dark.
- Dashboard with cards and charts for the modules built so far: milk today / this month, milk in stock, active animals, milk drop alerts, expenses today / this month, vendor payable; charts for milk per day (30 days, cow vs buffalo), herd by stage, expenses per month (12 months) and this month by category. Each block follows the user's permissions; numbers are cached for 5 minutes and refresh on every change.

### Changed
- Fresh look: new **Fresh Dairy** colour preset (now the default), Figtree font, light sidebar with a green active pill, white cards on a pale green page, tinted table headers and filter bars, split login page. Everything stays adjustable in Settings → Appearance, which gains a **Card background** colour. Remote-search dropdowns in filter bars now match the small field height.

## [0.2.0] - 2026-10-28 — Phase 2: Animals, Milk Production, Milk Stock

### Added
- Phase 2 · Task 1: animal breeds (`animal_breeds`: cow/buffalo, name, active; unique per farm and type) — Herd → Breeds
  with type/status/search filters (AJAX), add/edit, deactivate instead of delete; the 9 cow and 7 buffalo breeds of the
  spec are seeded for every farm. New `AnimalType` enum.
- Phase 2 · Task 2: animals (`animals`, soft delete, audit-logged) — Herd → Animals: active herd counts per type and stage,
  list with search and type/stage/status/ownership/gender/breed filters (AJAX, opens on active animals), add/edit with
  photo (resized to 1200 px + 200 px thumbnail, public disk), breed list following the type, mother picker (Select2 search
  of females of the same type), "Save & add another"; profile page with Details (incl. calves), Milk, Health, Breeding,
  Expenses and History (audit log) tabs; delete only without records, reason required. Select2 `data-ajax-with` sends
  other fields with the search. DemoSeeder adds 30 demo animals.
- Phase 2 · Task 3: animal status history (`animal_status_histories`, written by `AnimalObserver` for every change of
  status, stage or ownership — from the new profile actions, the edit form or later modules; a starting row per field for
  a new animal). Profile actions "Change stage / status / ownership" (modal: new value, date, reason; `status_changed_on`
  set; "Sold" left to the sale screens of Phase 6; a sold animal cannot be made active by hand). History tab shows the
  timeline above the audit log. Modals reopen with their errors after a failed save (`data-show-on-load`).
- Phase 2 · Task 4: expenses can be linked to an animal — FK `expenses.animal_id`, optional Animal search box on the expense
  form, Animal filter on the expense list, animal shown on list/detail, Animal column in the Excel export, "All expenses of
  this animal" link on the animal's Expenses tab. Animal search is available to expense users without animal screens.
- Phase 2 · Task 5: milk production (`milk_production_records`, one per animal / date / shift, audit-logged) — Herd →
  Milk Entry grid for a date and shift (all active milking females, saved litres pre-filled, last 3 entries of the shift
  as a hint, live cow / buffalo / shift totals, Enter jumps to the next box, sticky Save bar for phones, reason when saved
  litres change, staff same-day rule); entries list with date/shift/type/animal filters and litres per type; single entry
  with fat / SNF (idempotent `client_uuid`), edit and delete with a reason; animal profile Milk tab with 60-day table,
  7-day average and line chart. DemoSeeder adds a year of milk (~16,000 entries).
- Phase 2 · Task 6: milk stock ledger (`milk_stock_movements`, per source own farm / collection × milk type cow / buffalo)
  and `MilkStockService` — `recordIn` / `recordOut` (outgoing milk refused beyond the balance up to that date unless the
  farm allows negative stock; reason required for wastage, home use, calf feeding and adjustments), `syncForReference` /
  `deleteForReference`, `balance`, `dailySummary`. Every milk entry (grid, single, edit, delete) now moves own-farm stock
  in the same transaction; existing entries were back-filled by the migration and DemoSeeder adds the movements of its
  bulk-inserted milk. New `MilkMovementType` enum and `InsufficientMilkStockException` (shown as a form error).
- Phase 2 · Task 7: Milk Stock screens — daily stock sheet per date (own farm / collection × cow / buffalo plus combined:
  opening, in, delivered, wastage, home use, calf feeding, net adjustment, closing) with the day's usage and adjustments;
  "Record usage" form (wastage / home use / calf feeding, reason, `milk_stock.view`) and "Adjustment" form (in / out,
  reason, `milk_stock.adjust`), both idempotent on `client_uuid` (new column) and refused beyond the balance unless
  negative stock is allowed; ledger with AJAX filters (dates, stock, milk type, movement) and a running balance.
- Phase 2 · Task 8: milk production reports (Reports → Milk production, screen + Excel + PDF, optional milk type filter) —
  daily (each animal's morning / evening / total with a subtotal per milk type), monthly (every day of the month up to
  today: cow, buffalo, morning, evening, total, animals milked), animal-wise for any period (days milked, litres, average
  per day, largest first). The report screen and `ReportTable` are now shared by all modules (litre columns, bold
  subtotal rows, per-report empty text).
- Phase 2 · Task 9: milk drop alerts — Herd → Milk Alerts lists, for a date, every animal and shift whose milk is at least
  the farm's alert % (default 20 %) below the average of its previous 7 entries of that shift (min 3, last 30 days), with
  litres, average, drop % and "Check health — this is a signal, not a diagnosis."; the milk entry grid shows the alerts
  of its date and shift. Animal profile tabs can be opened by link (`?tab=milk`).
- Phase 2 · Task 10: `tests/Feature/Phase2AcceptanceTest.php` walks through the six Phase 2 acceptance criteria end to end
  (incl. a failed stock write rolling back the milk entry change).

### Changed
- Reports screen and PDF are shared by all modules; `ReportTable` has litre columns, bold subtotal rows and per-report
  empty text (expense category-wise report now bolds categories through it).

### Fixed
- Expense Excel export test no longer fails when the clock ticks to the next second during the test.

## [0.1.0] - 2026-10-18 — Phase 1: Expense Management (no approval)

### Added
- Phase 1 · Task 1: expense categories and subcategories (`expense_categories`, two levels) with a tree view
  (quick search, add subcategory, edit, deactivate, delete when unused) under Expenses → Categories; 9 default
  categories with 25 subcategories seeded for every farm.
- Phase 1 · Task 2: vendors (`vendors`: name, phone, address, GSTIN, opening balance, notes, active, soft delete) —
  Expenses → Vendors with AJAX search/status filter and pagination, add/edit/delete, and a Select2 search endpoint
  (`vendors.search`) for the expense form.
- Phase 1 · Task 3: expenses (`expenses`, no approval columns) — list with filters, dependent subcategory, vendor
  remote search, totals bar (count / total / paid / credit) and Excel export (queued with a bell link above 5 000 rows);
  add/edit with paid-or-credit toggle, "add new vendor" modal, quantity + unit, receipt upload (private, images scaled to
  1600 px), "Save & add another"; detail page with receipt preview; soft delete; audit-logged. Opens on the current month;
  covering indexes keep totals and paging fast on large data.
- Phase 1 · Task 4: staff edit rule (spec 4.5) — `ExpensePolicy` with the reusable `OwnEntrySameDay` rule (own entry, same day,
  for create-only users; edit/delete permission for any unlocked entry; locked auto-created expenses for nobody, with a
  lock icon); "My expense entries" list for create-only staff; reason required on expense edit and delete.
- Phase 1 · Task 5: vendor payments (`vendor_payments`, idempotent with `client_uuid`, audit-logged, reason on edit/delete,
  staff same-day rule) and the vendor ledger — opening balance, credit purchases, payments and running balance (SQL window
  function), period filter opening on the current financial year, "Record payment"; vendors list shows what we owe each.
- Phase 1 · Task 6: Reports home and four expense reports — daily summary, monthly category × section matrix, category-wise
  with subcategories and share %, vendor outstanding — each on screen (AJAX filters) and as Excel and PDF; a covering index
  keeps them fast on large data.
- Phase 1 · Task 7: Expenses → Import page and `expenses_template.xlsx` download (header row with help notes and dropdowns,
  plus a Lists sheet of this farm's active categories, subcategories, sections, payment modes and vendors).
- Phase 1 · Task 8: expense sheet upload (xlsx/xls/csv, up to 5 000 rows / 5 MB; `expense_import_batches`, `expense_import_rows`)
  — every row checked like the expense form (names case-insensitive, Excel dates accepted), preview page with counts,
  all / errors / duplicates / ready filter (AJAX), errors highlighted in their cells, possible-duplicate warnings, cancel upload.
- Phase 1 · Task 9: "Import valid rows" (one transaction, bulk inserts, rows re-checked at import, never twice, each row linked
  to its expense, batch audit-logged) and "Download error rows" (the invalid rows + an `errors` column, ready to fix and upload again).
- Phase 1 · Task 10: import history on Expenses → Import (newest first, counts, status, who/when, AJAX paging), "Expenses"
  link that opens the expense list filtered to one import, and "Revert upload" (reason required; soft-deletes the upload's
  expenses unless one was edited after the import, only within 7 days of the import — `IMPORT_REVERT_DAYS`; new status
  `reverted` with `reverted_at` / `reverted_by`).
- Phase 1 · Task 11: `Phase1AcceptanceTest` (the six spec acceptance criteria end to end on the production seed data) and
  `DemoSeeder` (local only: demo users for each role, 25 vendors, ~2,700 expenses over a year, month-end vendor payments).

### Changed
- Expense list: new `import_batch_id` filter; "Clear" on AJAX lists also removes hidden `data-clearable` filters.
- Expense import batches are audit-logged (Settings → Audit log, record type "Expense Import Batch").

### Performance
- Phase 1 screens measured on the demo year (local, no OPcache): expense list 135 ms, vendor ledger 92 ms, reports 60–90 ms,
  import page 51 ms — all far below the 1000 ms slow-request threshold. 5,000-row import: read + check ≈ 0.3 s, 10 bulk inserts.

## [0.0.0] - 2026-10-15 — Phase 0: Foundation

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
- Phase 0 · Task 12: appearance & branding — `AppearanceService` (defaults, 4 presets, cache per farm, CSS variables
  incl. hover/active shades, readable text and dark-mode palette, contrast warnings), Settings → Appearance with live
  preview, presets, reset, logo / dark logo / favicon / login background uploads (resized; SVG sanitized), sample PDF;
  branded sidebar, browser title, favicon, login page and shared PDF layout; per-user theme in My profile;
  self-hosted fonts (Inter, Roboto, Poppins, Noto Sans); `App.chartColors()`.
- Performance rules for large data in `CLAUDE.md` (owner requirement) and server tuning in `docs/DEPLOYMENT.md`.
- AJAX lists: Users, Audit Log, Sections and Payment Modes filter, search and paginate without reloading the page;
  clean URLs without empty filters (`Controller::listView()`, `data-ajax-list`, `@fragment('results')`).
- Phase 0 · Task 13: `tests/Feature/Phase0AcceptanceTest.php` (every Phase 0 acceptance criterion end to end), Phase 0 log in
  `docs/PROGRESS.md`, README completed for Phase 0.


### Changed
- `public/css/app.css` contains no colour values any more; buttons, badges and table density follow the appearance variables.
- `/dashboard` requires `dashboard.view`; after login users go to `/` (`home`), which opens the dashboard or the first
  screen they are allowed to see.

### Fixed
- Sidebar invisible on desktop (Bootstrap `.offcanvas-lg` forced a transparent background from 992px up).
- Sidebar groups (e.g. Settings) opened with a jumpy sideways animation (collapse moved off the flex `.nav` list).
- `/pulse` crashed with "Table pulse_aggregates doesn't exist" (Pulse migrations were not published yet).
- Current farm could carry over between requests in long-running processes; it is now resolved per request.
- Logging in as another user in the same browser could 403 on the first page (previous user's farm kept in the session).
- Audit log was very slow with large data (16–47 s at 200 000 entries): filter lists no longer scan the log, new composite indexes with index hints, Previous/Next paging; now under 1 s.
- The user's farms were loaded twice on every page.
