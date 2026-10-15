# Dairy ERP — Complete Build Specification

Version 1.0 · Target: Laravel monolith · Audience: the developer (Claude Code) building it end to end.

---

## 1. Business overview

One dairy business with four activities, all in one application:

| Unit | What happens | Example |
|---|---|---|
| Own herd | Cows/buffaloes are fed, treated, bred; milk is recorded morning/evening per animal | Cow C-101 gave 8 L morning, 6 L evening |
| Collection center | Village farmers bring milk; quantity, Fat%, SNF% recorded; paid every N days by rate chart | Farmer F-001: 25 L × ₹40 = ₹1,000 |
| Milk sales | B2C homes get daily subscription delivery; B2B (hotels, shops, sweet shops, dairy plants) get bulk supply; monthly bills; payments | Sharma: 2 L/day @ ₹60; ABC Hotel: 100 L/day @ ₹48 |
| Animal trading | Cows/buffaloes bought (for own herd or for resale) and sold, with payments and profit | Heifer bought ₹35,000, sold ₹65,000 |

Core principle: **every litre and every rupee must be traceable.**
If 100 L were available, 70 L sold, 5 L wasted, the system must show 25 L remaining, and where each litre went.

---

## 2. Fixed decisions (do not ask about these)

| Topic | Decision |
|---|---|
| Framework | Latest stable Laravel (12.x or newer), PHP 8.3+ |
| Database | MySQL 8 (InnoDB, utf8mb4) |
| Architecture | Single modular monolith. No microservices, no SPA, no API-first |
| Frontend | Blade + Bootstrap 5 + jQuery (AJAX where useful). Select2 for searchable dropdowns, Flatpickr for dates, DataTables (yajra/laravel-datatables) for big lists |
| Farms | Start with one farm, but every business table has `farm_id`. A user can be linked to multiple farms (`farm_user`). A farm switcher appears only if the user has >1 farm |
| Milk stock | Separate stock per **source** (`own_farm`, `collection`) **and per milk type** (`cow`, `buffalo`). Reports show each and the combined total. No mixing/transfer between sources in v1 except via a manual `adjustment` |
| Users | Owner/Admin + staff roles with granular permissions (Section 5) |
| Expenses | **No manager approval.** Expense is saved and immediately counts in reports |
| Animal trading | Included (Phase 6) |
| Products | Only raw milk in v1 (Cow Milk, Buffalo Milk). Paneer/ghee/packaged products are out of scope |
| Tax | Bills support a `tax_amount` field defaulting to 0. No GST computation logic in v1 |
| Language | UI in English, all strings via `__()` / lang files so Hindi can be added later |
| Hosting target | Linux VPS, Nginx, PHP-FPM, MySQL, cron for scheduler, queue driver `database` |

### 2.1 What these decisions mean in practice
- **No Vite, no Node build step.** Do not use `npm`, `vite.config.js`, `@vite` or Tailwind. Remove them if the
  Laravel skeleton creates them (`package.json`, `vite.config.js`, `resources/css`, `resources/js`).
- Front-end libraries are stored as minified files in `public/vendor/<library>/` and committed to git:
  Bootstrap 5 (CSS + bundle JS), Bootstrap Icons, jQuery 3, Select2 (+ Bootstrap 5 theme), Flatpickr,
  DataTables (+ Bootstrap 5 integration), Chart.js, SweetAlert2 (for confirm modals, optional).
  Download them once from their official releases/CDN. Record versions in `public/vendor/VERSIONS.md`.
- Application assets are plain files: `public/css/app.css`, `public/js/app.js` (global init: CSRF header for
  AJAX, Select2, Flatpickr, DataTables defaults, money/date formatting) and per-module files in
  `public/js/modules/<module>.js` included only on pages that need them via `@push('scripts')`.
- Include assets with a cache-busting helper: `asset_v('css/app.css')` → `asset('css/app.css').'?v='.filemtime(...)`.
- **Tests run on MySQL**, using a separate database (`dairy_erp_test`) configured in `.env.testing` and
  `phpunit.xml`, with `RefreshDatabase`. MySQL-specific SQL is allowed.

---

## 3. Packages

| Package | Purpose |
|---|---|
| `spatie/laravel-permission` | Roles & permissions |
| `spatie/laravel-activitylog` | Audit log (old/new values, causer, `reason` in properties) |
| `spatie/laravel-backup` | Daily DB + storage backups |
| `maatwebsite/excel` | Excel import/export |
| `barryvdh/laravel-dompdf` | PDF bills, statements, receipts |
| `yajra/laravel-datatables-oracle` | Server-side DataTables |
| `pestphp/pest` | Tests |
| `laravel/pint` | Code style |
| `laravel/pulse` | Production monitoring dashboard: slow requests, slow queries, exceptions, usage (Section 4.10) |
| `sentry/sentry-laravel` | Error tracking + instant alerts (enabled only when `SENTRY_LARAVEL_DSN` is set) |
| `laravel/telescope` (`--dev`) | Local debugging only; never registered in production |
| `intervention/image` | Resizing uploaded logos, favicons, receipts and animal photos |

Do not add other packages unless strictly needed; record any addition in `docs/DECISIONS.md`.
Do not use Laravel Breeze/Jetstream/UI starter kits (they depend on Vite/Tailwind). Authentication is
hand-built (see Phase 0) with Bootstrap 5 Blade views.

---

## 4. Global conventions

### 4.1 Columns and types
- Primary keys: `id` (bigint unsigned auto-increment). Foreign keys: `foreignId()->constrained()`; use
  `restrictOnDelete()` by default (business data is never cascade-deleted).
- Money `decimal(12,2)`, quantity `decimal(10,2)`, fat/snf `decimal(5,2)`, rate `decimal(10,2)`.
- Every business table: `farm_id`, `created_by` (nullable FK users), `updated_by` (nullable FK users), timestamps.
- Soft deletes only on master data (farms, vendors, customers, farmers, animals, products, parties, staff)
  and on `expenses`. Transaction tables are not soft-deleted; deletion (where allowed) is audit-logged.
- Status/type columns: `string(30)` backed by PHP enums.
- Indexes on every FK, on `(farm_id, <date column>)` for every transaction table, and on unique business codes.

### 4.2 Farm scoping
- `app/Models/Concerns/BelongsToFarm.php`: global scope `where farm_id = current farm`; `creating` hook fills
  `farm_id` from `app(CurrentFarm::class)`.
- `CurrentFarm` service resolves farm from session (`current_farm_id`), defaulting to the user's first farm.
- Middleware `EnsureFarmSelected` on all app routes; it also aborts 403 if the user is not linked to the farm.
- Route model binding must respect the scope (a record of another farm returns 404).

### 4.3 Created/updated by
- Trait `TracksUser` sets `created_by` / `updated_by` from `auth()->id()`.

### 4.4 Audit logging
- Critical models use `LogsActivity` with `logOnlyDirty()` and all fillable attributes:
  expenses, vendor_payments, animals, milk_production_records, milk_stock_movements, milk_collections,
  rate_charts, farmer_payments, customers, customer_rates, deliveries, bills, customer_payments,
  customer_adjustments, animal_purchases, animal_sales, animal_payments, salary_slips, users, roles.
- Edit and delete forms of these models have a required `reason` (min 3 chars) stored in activity properties.
  Exception: creating records needs no reason.
- Audit log screen (Settings → Audit Log): filter by user, model, date range; shows old → new diff.

### 4.5 Edit rules for staff
- Users with only `*.create` (no `*.edit`) can edit/delete **their own** entries **on the same calendar day**.
- Users with `*.edit` can edit any entry that is not locked.
- Locked records cannot be edited by anyone (show a lock icon and the reason: "Included in Bill BILL/2026-27/0012").

### 4.6 Document numbering
- Table `document_sequences(id, farm_id, type, financial_year, last_number)`; unique `(farm_id, type, financial_year)`.
- `DocumentNumberService::next($type)` uses `lockForUpdate()` inside a transaction.
- Formats: `BILL/2026-27/0001`, `FPAY/2026-27/0001` (farmer payment cycle), `APUR/2026-27/0001`,
  `ASAL/2026-27/0001`, `SAL/2026-27/0001`.

### 4.7 Idempotency
- Tables `milk_production_records`, `milk_collections`, `deliveries`, `customer_payments`, `vendor_payments`
  have `client_uuid` (uuid, nullable, unique). Forms generate it in JS on page load (and regenerate after
  successful save). On duplicate, return the existing record with a success response.

### 4.8 Money helper
- `app/Support/Money.php` with static methods `add`, `sub`, `mul`, `div`, `round2`, `format` using bcmath
  (scale 4 internally, round half-up to 2 on output). All service calculations use it.
- Model casts: decimals cast as `'decimal:2'` (strings).

### 4.9 Project structure

```
app/
├── Enums/
├── Exports/            # maatwebsite exports
├── Imports/            # maatwebsite imports
├── Http/
│   ├── Controllers/<Module>/...
│   ├── Middleware/
│   └── Requests/<Module>/...
├── Models/ (+ Concerns/)
├── Notifications/
├── Policies/
├── Services/           # MilkStockService, BillingService, RateChartService, FarmerPaymentService, ...
├── Support/            # Money, CurrentFarm, helpers
└── Console/Commands/
resources/views/<module>/...
routes/web.php          # grouped by module, prefixed and named: expenses.index, milk-production.create ...
tests/Feature/<Module>/..., tests/Unit/...
```

### 4.10 Monitoring (Pulse, Sentry, Telescope)
- **Laravel Pulse** (production + local):
  - Install, publish config, run migrations; data stored in the same MySQL database.
  - Dashboard at `/pulse`, protected by gate `viewPulse` → only users with permission `monitoring.view`
    (owner by default). Add a "System Monitor" link under Settings for those users.
  - `.env`: `PULSE_ENABLED=true`, `PULSE_SLOW_REQUESTS_THRESHOLD=1000`, `PULSE_SLOW_QUERIES_THRESHOLD=500`,
    `PULSE_SLOW_JOBS_THRESHOLD=1000` (milliseconds). Keep enabled recorders: requests, slow requests,
    slow queries, exceptions, queues, slow jobs, cache, users, servers.
  - Use Pulse's built-in sampling/trimming defaults; data retention 7 days.
  - Document in `docs/DEPLOYMENT.md`: run `php artisan pulse:check` under Supervisor/systemd for server stats,
    and `php artisan pulse:work` if the Redis ingest driver is ever used (default ingest driver is fine).
- **Sentry** (production alerts): install `sentry/sentry-laravel`, wire it in `bootstrap/app.php` exception
  handling. If `SENTRY_LARAVEL_DSN` is empty, Sentry must do nothing (local/testing). Set
  `SENTRY_TRACES_SAMPLE_RATE=0.2`. Never send passwords, bank account numbers or tokens (`send_default_pii=false`).
  Alerts themselves are configured in Sentry's website, not in code.
- **Telescope**: `composer require laravel/telescope --dev`; register only when `app()->environment('local')`
  (do not auto-discover — add `laravel/telescope` to `dont-discover` in composer.json and register manually).
  Gate: local only.
- Tests: Pulse and Telescope disabled in `.env.testing` (`PULSE_ENABLED=false`, `TELESCOPE_ENABLED=false`).

### 4.11 Appearance & branding (managed from the admin panel)
Nothing visual is hard-coded. Colors, logo and branding are stored per farm and edited from
**Settings → Appearance** (permission `appearance.manage`, owner + admin by default).

Stored in `farms.settings.appearance` (JSON) with these keys and defaults:

| Key | Default | Used for |
|---|---|---|
| `app_name` | `Dairy ERP` | Browser title, login page, sidebar header |
| `logo_path` | null | Sidebar, login page, bills/statements/receipts PDF header |
| `logo_dark_path` | null | Logo for dark sidebar/theme (falls back to `logo_path`) |
| `favicon_path` | null | Browser tab icon (falls back to a default icon in `public/`) |
| `primary_color` | `#1F6F5C` | Buttons, links, active menu, headers, charts' first series |
| `secondary_color` | `#6C757D` | Secondary buttons, muted badges |
| `success_color` | `#198754` | Success messages, "paid/delivered" badges |
| `danger_color` | `#DC3545` | Errors, delete buttons, alerts |
| `warning_color` | `#FFC107` | Warnings, low stock, due reminders |
| `sidebar_bg` | `#12332B` | Sidebar background |
| `sidebar_text` | `#FFFFFF` | Sidebar text/icons |
| `topbar_bg` | `#FFFFFF` | Top bar background |
| `body_bg` | `#F5F7F6` | Page background |
| `font_family` | `Inter` | One of: Inter, Roboto, Poppins, Noto Sans, System default (fonts stored locally in `public/vendor/fonts/`, no Google CDN) |
| `base_font_size` | `14` | 13–16 px |
| `theme_mode` | `light` | `light`, `dark`, `auto` (follows device); each user can override in profile |
| `border_radius` | `6` | 0–12 px (cards, buttons, inputs) |
| `table_density` | `normal` | `compact` or `normal` |
| `login_background_path` | null | Login page background image |
| `login_welcome_text` | `Welcome to Dairy ERP` | Login page text |
| `print_header_text` | farm name + address | Top of PDFs |
| `print_footer_text` | null | Bottom of PDFs (e.g. bank details, "Thank you") |
| `pdf_accent_color` | same as primary | PDF table headers |

Implementation rules:
- `AppearanceService` returns the merged settings (defaults + saved) and is cached per farm
  (`appearance:{farm_id}`), cache cleared on save.
- The layout renders a `<style>` block from these values that sets **CSS variables**, including Bootstrap 5.3
  variables (`--bs-primary`, `--bs-primary-rgb`, `--bs-body-bg`, `--bs-body-font-family`, `--bs-border-radius`,
  etc.) plus app variables (`--sidebar-bg`, `--sidebar-text`, `--topbar-bg`). Generate hover/active shades
  (±10% lightness) in PHP.
- `public/css/app.css` and all Blade views use only these variables or Bootstrap classes — **never hard-coded
  hex colors**. Chart.js colors also read from the variables. PDF templates use the same settings.
- Dark mode uses Bootstrap 5.3 `data-bs-theme="dark"` on `<html>`; user override stored in
  `users.preferences` (json, nullable — add migration) → `{"theme_mode": "dark"}`.
- Uploads: logo (png/jpg/svg/webp ≤ 2 MB, resized to max 400px width), favicon (png/ico ≤ 512 KB, 64×64),
  login background (jpg/webp ≤ 3 MB, max 1920px). Stored on public disk under `branding/{farm_id}/`.
  SVG uploads are sanitized (strip scripts) or rejected if sanitizing isn't available.
- Validation: colors must be valid hex `#RRGGBB`. Show a contrast warning (not a block) if text/background
  contrast ratio < 4.5:1.
- Appearance screen: form on the left grouped into Branding / Colors / Typography / Layout / Login page /
  Print; **live preview** on the right (sample sidebar, buttons, table, badges, alert) updated via JS as values
  change; buttons **Save**, **Reset to defaults** (confirm), and 4 ready-made presets
  (Green Dairy — default, Blue Professional, Warm Orange, Dark Slate) that fill the form.
- Changes are audit-logged (activity log on settings change).

---

## 5. Roles and permissions

### 5.1 Permission names (`module.action`)

```
farms.manage, users.manage, roles.manage, settings.manage, appearance.manage, audit_logs.view, monitoring.view
dashboard.view, dashboard.financial
expenses.view, expenses.create, expenses.edit, expenses.delete, expenses.import
vendors.view, vendors.manage, vendor_payments.create, vendor_payments.edit
animals.view, animals.manage
milk_production.view, milk_production.create, milk_production.edit
milk_stock.view, milk_stock.adjust
customers.view, customers.manage, subscriptions.manage
deliveries.view, deliveries.update, deliveries.view_all_routes
bills.view, bills.generate, bills.finalize, bills.cancel
customer_payments.create, customer_payments.edit, customer_adjustments.create
farmers.view, farmers.manage, rate_charts.manage
collections.view, collections.create, collections.edit
farmer_payments.view, farmer_payments.manage, farmer_advances.manage
health.view, health.manage, breeding.manage
feed.view, feed.manage
animal_trading.view, animal_trading.manage
staff.view, staff.manage, attendance.manage, salary.manage
reports.view, reports.financial
```

### 5.2 Default roles (seeded; owner can edit permissions from UI)

| Role | Permissions |
|---|---|
| `owner` | All permissions. Cannot be deleted. At least one active owner must always exist |
| `admin` | All except `farms.manage`, `roles.manage`, `monitoring.view` |
| `accountant` | dashboard.*, expenses.*, vendors.*, vendor_payments.*, customers.view, bills.*, customer_payments.*, customer_adjustments.create, farmers.view, collections.view, farmer_payments.*, farmer_advances.manage, animal_trading.view, staff.view, salary.manage, reports.* |
| `herd_staff` | dashboard.view, animals.view, milk_production.view, milk_production.create, milk_stock.view, health.view, health.manage, breeding.manage, feed.view, feed.manage, expenses.create |
| `collection_operator` | dashboard.view, farmers.view, collections.view, collections.create, milk_stock.view |
| `delivery_boy` | deliveries.view, deliveries.update, customer_payments.create (sees only customers/deliveries of routes assigned to him) |

### 5.3 Users & roles UI
- Users list, create, edit, deactivate (no hard delete). Fields: name, phone, email, password, role(s), farms, active.
- Login with email **or** phone + password. Rate-limited. Inactive users cannot log in.
- Roles screen: list roles, edit permission matrix (grouped by module, checkboxes). Owner role locked.

---

## 6. Database schema

Columns listed without `id`, `farm_id`, `created_by`, `updated_by`, timestamps unless relevant — these
exist on every business table per Section 4.1. `?` = nullable.

### Phase 0 — Foundation

**farms**: name, code (unique), address?, phone?, gstin?, timezone (default `Asia/Kolkata`),
settings (json), is_active (bool), deleted_at?
(No farm_id on this table.)

`settings` JSON keys with defaults:
```json
{
  "milk_drop_alert_percent": 20,
  "allow_negative_milk_stock": false,
  "farmer_payment_cycle_days": 10,
  "cow_gestation_days": 280,
  "buffalo_gestation_days": 310,
  "vaccination_reminder_days": 7,
  "calving_reminder_days": 15,
  "staff_same_day_edit_only": true,
  "bill_due_days": 7,
  "delivery_sheet_generate_time": "04:00",
  "appearance": { "...": "see Section 4.11 for all keys and defaults" }
}
```

**users** also gets `preferences` (json?) for per-user theme override (Section 4.11).

**users**: name, email (unique?), phone (unique?), password, is_active, last_login_at?, remember_token.
At least one of email/phone required.

**farm_user**: farm_id, user_id, unique(farm_id, user_id).

Spatie permission tables (default, no teams). Spatie activity_log table.

**sections**: name, code (unique per farm), is_active.
Seed: HERD (Own Farm/Herd), COLLECTION (Collection Center), DELIVERY (Sales & Delivery),
TRADING (Animal Trading), OFFICE (Office/General).

**payment_modes**: name, code (unique per farm), is_active.
Seed: CASH, UPI, BANK (Bank Transfer), CHEQUE, CARD.

**document_sequences**: see 4.6.

### Phase 1 — Expenses & vendors

**expense_categories**: parent_id? (self FK; null = category, set = subcategory), name, code?, is_active,
sort_order. Unique (farm_id, parent_id, name).

Seed categories → subcategories:
- Feed → Green Fodder, Dry Fodder (Bhusa), Cattle Feed, Mineral Mixture, Silage
- Health → Medicine, Veterinary Fees, Vaccination, AI / Breeding
- Labour → Salary, Daily Wages
- Utilities → Electricity, Water, Diesel/Fuel
- Transport → Milk Transport, Animal Transport, Vehicle Maintenance
- Maintenance → Shed Repair, Equipment Repair
- Collection Center → Testing Chemicals, Cans & Equipment
- Office → Stationery, Phone/Internet, Rent
- Miscellaneous → Other

**vendors**: name, phone?, address?, gstin?, opening_balance (money, default 0; amount we owe at start),
notes?, is_active, deleted_at?.

**expenses**:
| Column | Type | Notes |
|---|---|---|
| expense_date | date | ≤ today |
| amount | money | > 0 |
| category_id | FK expense_categories | must be a top-level category |
| subcategory_id? | FK expense_categories | must be child of category_id |
| section_id | FK sections | |
| animal_id? | bigint unsigned | FK added in Phase 2 migration |
| vendor_id? | FK vendors | required when is_credit = true |
| paid_to_text? | string(150) | free text when no vendor |
| is_credit | bool | true = not paid yet, adds to vendor payable |
| payment_mode_id? | FK payment_modes | required when is_credit = false; must be null when true |
| quantity? | qty | |
| unit? | string(20) | enum: kg, litre, piece, bag, dose, hour, day, trip, other |
| receipt_path? | string | private disk |
| note? | text | |
| source_type? / source_id? | morph | set when auto-created by treatment, feed purchase, salary run (read-only in expense form; edit from source) |
| import_batch_id? | FK expense_import_batches | |
| deleted_at? | | |

**There are no approval columns.** Do not add status/approved_by/approved_at/reject_reason.

**vendor_payments**: vendor_id, payment_date, amount (>0), payment_mode_id, reference_no?, note?, client_uuid?.

**expense_import_batches**: original_filename, stored_path, total_rows, valid_rows, error_rows,
imported_rows, status (enum: previewed, imported, cancelled), error_file_path?, imported_at?.

**expense_import_rows**: import_batch_id, row_number, payload (json), errors (json?), warnings (json?),
is_valid (bool), expense_id? (after import).

### Phase 2 — Animals, milk production, milk stock

**animal_breeds**: animal_type (cow|buffalo), name, is_active. Unique (farm_id, animal_type, name).
Seed cow: HF, Jersey, Sahiwal, Gir, Tharparkar, Red Sindhi, Crossbred HF, Crossbred Jersey, Desi.
Seed buffalo: Murrah, Nili-Ravi, Jaffarabadi, Mehsana, Bhadawari, Surti, Desi.

**animals**:
| Column | Type | Notes |
|---|---|---|
| tag_no | string(30) | unique per farm (including soft-deleted) |
| name? | string(60) | |
| animal_type | enum | cow, buffalo |
| breed_id? | FK | breed.animal_type must match |
| gender | enum | female, male |
| date_of_birth? | date | |
| dob_is_estimated | bool | |
| source | enum | born_on_farm, purchased, opening (existing animal at go-live) |
| mother_id? | FK animals | |
| sire_info? | string | bull/semen details |
| ownership_type | enum | own_herd, resale |
| lifecycle_stage | enum | calf, heifer, pregnant_heifer, milking, dry, bull |
| status | enum | active, sold, deceased, culled, transferred |
| opening_value? | money | for source=opening only |
| color_marks? | string | |
| photo_path? | string | public disk |
| lactation_number | unsigned tinyint | default 0, +1 on each calving |
| status_changed_on? | date | |
| notes? | text | |
| deleted_at? | | |

Rules: only `female` + `lifecycle_stage = milking` + `status = active` animals appear in milk entry.
`male` animals can only be calf or bull. Purchase price is **not** stored on animals; it lives in
`animal_purchase_items` (Phase 6). Animals are never hard-deleted; soft delete only allowed if the animal
has no transactions.

**animal_status_histories**: animal_id, field (status | lifecycle_stage | ownership_type), old_value?,
new_value, changed_on (date), reason?, changed_by. Written automatically by `AnimalObserver` whenever
those fields change.

Add migration: FK `expenses.animal_id → animals.id`.

**milk_production_records**:
production_date (date), shift (morning|evening), animal_id, quantity_litre (qty ≥ 0, ≤ 60),
fat? (5,2), snf? (5,2), notes?, client_uuid?.
Unique (animal_id, production_date, shift). No soft delete.
milk_type is derived from the animal (not stored).

**milk_stock_movements** (the milk ledger — source of truth for stock):
| Column | Type | Notes |
|---|---|---|
| movement_date | date | |
| shift? | enum | morning, evening |
| stock_source | enum | own_farm, collection |
| milk_type | enum | cow, buffalo |
| movement_type | enum | production, collection, delivery, wastage, home_use, calf_feeding, adjustment_in, adjustment_out |
| quantity_in | qty | default 0 |
| quantity_out | qty | default 0 |
| reference_type? / reference_id? | morph | the record that caused it |
| reason? | string | required for wastage/home_use/calf_feeding/adjustment |

Exactly one of quantity_in / quantity_out is > 0. Index (farm_id, stock_source, milk_type, movement_date).

**MilkStockService** (all stock changes go through it — no other code writes this table):
- `recordIn(source, milkType, date, shift, type, qty, reference)`
- `recordOut(...)` — checks balance (unless `allow_negative_milk_stock`) and throws `InsufficientMilkStockException`
- `syncForReference(reference, qty)` — when a source record (production, collection, delivery) is edited,
  update its linked movement inside the same transaction; when deleted, delete the movement.
- `balance(source?, milkType?, upToDate?)` = Σ in − Σ out.
- `dailySummary(date)` per source × milk type: opening, production/collection in, delivered out,
  wastage/home use/calf feeding out, adjustments, closing.

Balance check for outgoing movements uses the balance **up to and including the movement date** (all shifts).

### Phase 3 — Customers, subscriptions, delivery, billing, payments

**routes**: name, delivery_user_id? (FK users), sort_order, is_active.

**products**: name, code, unit (litre), milk_type (cow|buffalo), default_rate (money), is_active.
Seed: Cow Milk (₹60), Buffalo Milk (₹70) — editable.

**customers**: code (unique per farm, auto `C-0001`), name, customer_type (b2c|b2b), b2b_category?
(hotel, shop, sweet_shop, dairy_plant, distributor, other), phone?, alt_phone?, address?, area?,
route_id?, route_sequence? (int), gstin?, opening_balance (money, + = customer owes us),
credit_limit?, is_active, notes?, deleted_at?.

**customer_rates**: customer_id, product_id, rate, effective_from (date). Unique (customer_id, product_id, effective_from).
`PricingService::rateFor(customer, product, date)`: latest customer_rate with effective_from ≤ date,
else product.default_rate.

**subscriptions**: customer_id, product_id, shift (morning|evening), quantity (qty > 0),
stock_source (own_farm|collection, default own_farm), start_date, end_date?, status (active|stopped), notes?.

**subscription_pauses**: subscription_id, from_date, to_date, reason?.

**deliveries** (one row per customer × product × date × shift):
| Column | Type | Notes |
|---|---|---|
| delivery_date | date | |
| shift | enum | |
| customer_id | FK | |
| product_id | FK | |
| route_id? | FK | snapshot of customer's route |
| subscription_id? | FK | null for one-off / B2B dispatch |
| stock_source | enum | |
| planned_qty | qty | from subscription (0 for one-off) |
| delivered_qty | qty | |
| rate | rate | snapshot from PricingService at generation, editable before billing |
| amount | money | delivered_qty × rate |
| status | enum | pending, delivered, skipped |
| skip_reason? | string | |
| delivered_by? / delivered_at? | | |
| fat? / snf? | | for dairy-plant dispatch |
| buyer_measured_qty? / buyer_fat? / buyer_snf? | | dairy plant's readings for reconciliation |
| bill_id? | FK bills | set when included in a bill → locked |
| client_uuid? | uuid | |
| notes? | | |

Unique (customer_id, product_id, delivery_date, shift).

Delivery rules:
- Command `deliveries:generate {date?}` (scheduled daily at `delivery_sheet_generate_time` for today, and a
  "Generate sheet" button for any date) creates `pending` rows for every active subscription valid on that
  date and not paused. Idempotent: existing rows are not touched.
- Marking `delivered` creates a stock out movement (type `delivery`) via MilkStockService; changing qty syncs it;
  marking `skipped` removes the movement and sets delivered_qty = 0, amount = 0.
- Extra quantity: delivery boy can set delivered_qty > planned_qty.
- One-off / B2B dispatch: create a delivery directly with status delivered.
- Delivery boy mobile screen: date + shift + his route(s) → list in `route_sequence` order, each row:
  customer, planned qty, buttons Delivered / Skip / qty edit, and "Collect payment" (amount + mode).
  Large touch targets, works on 360px width.

**bills**:
| Column | Type | Notes |
|---|---|---|
| bill_no? | string | assigned on finalize (draft has none) |
| customer_id | FK | |
| period_from / period_to | date | |
| bill_date | date | |
| due_date | date | bill_date + bill_due_days |
| subtotal | money | Σ bill_items.amount |
| tax_amount | money | default 0 |
| total | money | subtotal + tax |
| previous_balance | money | snapshot |
| payments_in_period | money | snapshot |
| adjustments_in_period | money | snapshot |
| closing_balance | money | snapshot |
| status | enum | draft, finalized, cancelled |
| finalized_at? / finalized_by? / cancelled_at? / cancelled_by? / cancel_reason? | | |
| pdf_path? | | |
| notes? | | |

**bill_items**: bill_id, delivery_id? (unique), product_id, item_date, description, quantity, rate, amount.

Billing rules (`BillingService`):
- Generate (single customer or bulk for all customers with unbilled delivered rows in period): creates a
  `draft` with items = all `delivered` deliveries in period with `bill_id IS NULL`; sets `deliveries.bill_id`
  immediately (so two drafts can't take the same delivery). Deleting a draft releases them.
- No overlapping periods: a customer cannot have two non-cancelled bills whose periods overlap.
- Balance snapshots at finalize:
  `previous_balance` = customer ledger balance as of (period_from − 1 day);
  `payments_in_period` = Σ customer_payments dated period_from … bill_date;
  `adjustments_in_period` = Σ customer_adjustments dated period_from … bill_date;
  `closing_balance` = previous_balance + total − payments_in_period + adjustments_in_period.
  Example: 500 + 3,600 − 1,000 = ₹3,100.
- Finalize: assigns bill_no, generates PDF, locks bill and its deliveries. **Finalized bills cannot be edited.**
- Cancel (permission `bills.cancel`, reason required): status cancelled, releases deliveries (bill_id = null).
  Corrections are done by cancel + regenerate, or by a customer_adjustment.
- Bill PDF: farm header, customer, period, day-wise table (date, morning qty, evening qty, rate, amount),
  totals, previous balance, payments, closing balance, due date.

**customer_payments**: customer_id, payment_date, amount (>0), payment_mode_id, reference_no?,
collected_by (FK users), client_uuid?, note?.

**payment_allocations**: customer_payment_id, bill_id, amount. Auto-allocated FIFO to oldest finalized
bills with unpaid amount, inside the payment transaction. Unallocated remainder stays as advance and is
allocated automatically when the next bill is finalized.
Bill paid status (computed, not stored): unpaid / partially_paid / paid.

**customer_adjustments**: customer_id, adjustment_date, amount (signed; + increases what customer owes,
− discount/correction), reason (required).

Customer ledger balance (as of date D) =
opening_balance + Σ finalized bills.total (bill_date ≤ D) + Σ adjustments (≤ D) − Σ payments (≤ D).
"Unbilled amount" = Σ delivered deliveries.amount with bill_id null — shown separately, not in balance.

Customer ledger screen: chronological list (opening, bills, payments, adjustments) with running balance;
export Excel/PDF.

### Phase 4 — Farmers & milk collection

**farmers**: code (unique per farm, auto `F-001`), name, father_name?, phone?, village?, address?,
default_milk_type (cow|buffalo), rate_chart_id? (override), bank_account_name?, bank_account_no?, ifsc?,
upi_id?, opening_balance (money, + = we owe farmer), is_active, deleted_at?.

**rate_charts**: name, milk_type, method (enum: fixed, fat_rate, fat_snf_rate, slab), fixed_rate?,
fat_rate? (₹ per 1 fat point per litre), snf_rate?, min_fat?, min_snf?, effective_from, effective_to?,
is_default (one default per milk_type active on a date), is_active.

**rate_chart_slabs**: rate_chart_id, fat_from, fat_to, snf_from?, snf_to?, rate_per_litre.

`RateChartService::calculate(rateChart, fat, snf)` → rate_per_litre:
- fixed → fixed_rate
- fat_rate → fat × fat_rate
- fat_snf_rate → fat × fat_rate + snf × snf_rate
- slab → first slab where fat_from ≤ fat ≤ fat_to and (snf range null or matches); none → exception
- If fat < min_fat or snf < min_snf → returns "below standard"; UI asks operator to Reject or Accept at
  calculated rate. Round rate to 2 decimals.
Chart resolution: farmer.rate_chart_id if set, else default chart for milk_type effective on collection date.

**milk_collections**: collection_date, shift, farmer_id, milk_type, quantity_litre (> 0, ≤ 500),
fat?, snf?, clr?, rate_chart_id?, rate_per_litre (snapshot), amount (snapshot = qty × rate),
quality_status (accepted|rejected), rejection_reason?, farmer_payment_id? (set when included → locked),
client_uuid?, notes?.
Unique (farmer_id, collection_date, shift, milk_type).
Accepted → stock in (source collection) via MilkStockService. Rejected → amount 0, no stock movement.

Collection entry screen (speed matters): date + shift at top (default today/current shift by time:
before 2 PM = morning), farmer code field with autocomplete (Enter moves to next field), quantity, fat, snf,
live rate & amount, Save (Enter). Below: today's entries for this shift with totals (litres, avg fat,
avg snf, amount). Keyboard-only usable.

**farmer_advances**: farmer_id, advance_date, amount, payment_mode_id, note?.

**farmer_payment_cycles**: cycle_no (FPAY/…), from_date, to_date, status (draft|finalized), finalized_at?, finalized_by?.
Cycles cannot overlap.

**farmer_payments** (one statement per farmer per cycle):
farmer_payment_cycle_id, farmer_id, total_qty, avg_fat?, avg_snf?, gross_amount, bonus (default 0),
advance_deduction (default 0), other_deduction (default 0), deduction_note?, net_payable,
status (pending|paid), paid_on?, payment_mode_id?, reference_no?.

Farmer payment rules (`FarmerPaymentService`):
- Create cycle for a date range (default: next `farmer_payment_cycle_days` after last cycle) → draft statements
  for every farmer with accepted, unlinked collections in range; links those collections (locks them).
- In draft: edit bonus / deductions per farmer. advance_deduction defaults to min(outstanding advance, gross).
  net_payable = gross + bonus − advance_deduction − other_deduction (must be ≥ 0).
- Delete draft cycle → unlink collections.
- Finalize → statements read-only. Then mark each statement paid (date, mode, reference). Bulk "mark all paid
  as cash" allowed.
- Example: gross 8,000 − advance 500 + bonus 200 = ₹7,700.
- Farmer balance (we owe) = opening_balance + Σ finalized (gross + bonus − other_deduction) − Σ advances given
  − Σ net_payable of paid statements.
- Outstanding advance = Σ advances − Σ advance_deduction on finalized statements.
- Farmer statement PDF (per farmer per cycle): day/shift-wise qty, fat, snf, rate, amount, totals, deductions, net.
- Print/Export bank sheet (Excel): farmer, account, IFSC, net amount.

### Phase 5 — Health, breeding, feed

**treatments**: animal_id, treatment_date, diagnosis, medicine?, dose?, vet_name?, cost (money, default 0),
milk_withdrawal_until? (date), expense_id?, notes?.
Option "Also record as expense" (default on when cost > 0) → creates expense (category Health/Medicine,
section HERD, animal_id set, source = treatment) in the same transaction. Editing cost updates the linked
expense. If unticked, cost is informational only. This is the only way treatment costs reach expenses → no double counting.
Milk entry screen shows a warning badge on animals under withdrawal.

**vaccine_types**: name, interval_days?, notes?. Seed: FMD, HS, BQ, Brucellosis, Theileriosis, Deworming
(interval_days left null — configured by owner per local vet advice).

**animal_vaccinations**: animal_id, vaccine_type_id, given_on, next_due_on? (default given_on + interval),
cost, expense_id?, notes?. Bulk entry: select vaccine + date + multiple animals.

**breeding_events**: animal_id, event_type (heat, ai, natural_service, pregnancy_check, abortion),
event_date, sire_info?, technician?, result? (positive|negative, for pregnancy_check),
expected_calving_date?, cost?, expense_id?, notes?.
- ai/natural_service → expected_calving_date = event_date + gestation days (by animal type).
- pregnancy_check positive → heifer becomes pregnant_heifer (milking cow stays milking, flagged pregnant).

**calvings**: mother_id, calving_date, outcome (alive|stillborn|died_after_birth), calf_gender?,
calf_id? (FK animals), notes?.
On save (transaction): mother lifecycle → milking, lactation_number + 1; if alive, create calf animal
(source born_on_farm, mother_id, lifecycle calf, tag_no entered by user).

Dry-off: action on animal → lifecycle dry (status history records it).
Auto lifecycle suggestion: female calf older than 12 months → dashboard suggestion "mark as heifer" (no auto change).

**feed_items**: name, unit (kg|bag|litre|piece), reorder_level (qty), is_active.

**feed_transactions**: feed_item_id, transaction_date, type (opening, purchase, consumption, adjustment_in,
adjustment_out), quantity (> 0), rate?, amount?, vendor_id?, is_credit?, payment_mode_id?, animal_id?,
expense_id?, notes?.
- purchase → also creates an expense (Feed category, section HERD, vendor, credit/paid) in the same transaction.
  Stock value never becomes a second expense.
- consumption → reduces stock only (no expense). Can be for the whole herd or one animal.
- Stock = Σ(opening, purchase, adjustment_in) − Σ(consumption, adjustment_out). Block consumption beyond stock.
- Average cost per unit (for reports) = Σ purchase amount / Σ purchase qty (last 90 days).

**Reminders** — command `reminders:daily` scheduled 07:00, sends database notifications (bell icon) to users
with the relevant permission: vaccinations due within N days or overdue, expected calvings within N days,
feed items at/below reorder level, milk drop alerts (Section 7.3), customer bills overdue.

### Phase 6 — Animal trading & staff

**parties** (animal traders, buyers, sellers): name, phone?, village?, address?, party_type
(seller|buyer|both), opening_balance (+ = they owe us, − = we owe them), is_active, deleted_at?.

**animal_purchases**: purchase_no, party_id, purchase_date, transport_cost, commission, other_cost,
total_animal_price, total_amount (= animal prices + costs), notes?.

**animal_purchase_items**: animal_purchase_id, animal_id, price, allocated_cost (share of transport +
commission + other, allocated proportionally by price, last item absorbs rounding), total_cost.

Purchase form: party, date, costs, and repeatable rows to create animals inline (tag_no, type, breed,
gender, approx age, lifecycle, ownership_type own_herd|resale, price). Saving creates animals
(source purchased) + purchase + items in one transaction. Optional initial payment.

**animal_sales**: sale_no, party_id, sale_date, total_price, selling_costs (commission/transport),
net_amount, notes?.

**animal_sale_items**: animal_sale_id, animal_id, price.
Only `active` animals can be sold; saving sets status sold (history row). Cancelling a sale (reason
required) reverts status to active.

**animal_payments**: party_id, direction (paid|received), payment_date, amount, payment_mode_id,
animal_purchase_id?, animal_sale_id?, reference_no?, note?. Installments = multiple payments.
Party balance = opening + Σ sales net_amount − Σ purchase total_amount − Σ received + Σ paid.

Animal sale receipt PDF.

**Animal P&L** (per animal, labelled "Estimated"):
- Cost: purchase total_cost (or opening_value), + Σ expenses with animal_id (includes linked treatments,
  vaccinations, breeding costs since those create expenses), + Σ feed consumption with animal_id × average
  feed cost.
- Income: sale price − proportional share of selling_costs; for own_herd also estimated milk value =
  Σ production litres × average realized selling rate for that milk type in that month.
- Profit = income − cost. Show breakdown. Trading summary report: per resale animal purchase cost, holding
  days, costs, sale, profit.

**staff_members**: user_id?, name, phone?, designation, monthly_salary, joining_date, leaving_date?, is_active.

**attendances**: staff_member_id, attendance_date, status (present|absent|half_day|leave|holiday), note?.
Unique (staff_member_id, attendance_date). Bulk screen: date → all active staff with status radio buttons.

**staff_advances**: staff_member_id, advance_date, amount, payment_mode_id, note?.

**salary_runs**: run_no, month (date, first of month), status (draft|finalized).
**salary_slips**: salary_run_id, staff_member_id, days_in_month, payable_days (present + holiday + 0.5 × half_day,
leave counts as unpaid unless settings say otherwise), base_salary, earned_salary (= base × payable_days /
days_in_month), bonus, advance_deduction, other_deduction, net_salary, paid_on?, payment_mode_id?, expense_id?.
Finalize run → one expense per slip (category Labour/Salary, section OFFICE unless staff mapped otherwise,
amount = earned_salary + bonus). Advances themselves are not expenses.

### Phase 7 — Dashboard & reports (no new core tables)

See Section 7.

---

## 7. Business logic details

### 7.1 Expense module (Phase 1)
- List: DataTable with filters (date range, category, subcategory, section, vendor, payment mode, paid/credit,
  created by), totals row, Excel export.
- Create/Edit form: date (default today), amount, category → dependent subcategory (AJAX), section, vendor
  (Select2 with "add new vendor" modal) or paid_to text, paid/credit toggle (credit hides payment mode,
  requires vendor), payment mode, quantity + unit, animal (from Phase 2), receipt (jpg/png/pdf ≤ 5 MB,
  stored on private disk, viewable via authorized route), note. Save + "Save & add another".
- Receipts stored at `storage/app/private/receipts/{farm_id}/{Y}/{m}/{uuid}.{ext}`; images over 1600px
  resized.
- Delete is soft delete with reason. Auto-created expenses (source_type set) cannot be edited/deleted from
  the expense screen — show link to source.
- Vendor screen: list with balance; vendor ledger = opening + credit expenses − vendor payments, running
  balance; "Record payment" button. Paid (non-credit) expenses do not affect vendor balance.
- Reports: daily summary, monthly summary by category × section, category-wise for a period, vendor
  outstanding list. All exportable to Excel and PDF.

### 7.2 Excel import (Phase 1)
1. Download template (`expenses_template.xlsx`) with header row and a second sheet listing valid categories,
   subcategories, sections, payment modes, vendors.
   Columns: `date (DD-MM-YYYY)`, `amount`, `category`, `subcategory`, `section`, `payment_mode`, `vendor`,
   `paid_to`, `credit (yes/no)`, `quantity`, `unit`, `note`.
2. Upload (xlsx/xls/csv, ≤ 5,000 rows, ≤ 5 MB) → batch + rows created, each row validated with the same
   rules as the form (names matched case-insensitive, trimmed). Also accept Excel serial dates.
3. Preview page: counts, table of rows with errors highlighted per cell; warnings (non-blocking) for
   possible duplicates (same date + amount + category + vendor/paid_to already exists).
4. "Import valid rows" → inserts all valid rows in one transaction with import_batch_id; batch status imported.
   "Download error rows" → Excel of invalid rows with an `errors` column, so user fixes and re-uploads.
5. Import history list with link to imported expenses; an imported batch can be reverted (soft-deletes its
   expenses, reason required) if none of them were edited.

### 7.3 Milk production (Phase 2)
- Bulk entry screen (primary): pick date + shift → grid of all eligible animals (tag, name, breed, last
  3 entries for this shift as hint) with quantity inputs; blanks are skipped; Save all in one transaction.
  Existing entries pre-filled for editing. Shows shift total live. Mobile friendly.
- Single entry and animal-wise history (table + simple line chart, Chart.js from `public/vendor/chartjs`).
- Daily report: animal × shift grid with totals by milk type.
- **Milk drop alert**: for each animal and shift, avg of previous 7 days with entries (min 3 entries required);
  drop% = (avg − today) / avg × 100; if ≥ milk_drop_alert_percent → alert. Message: "Check health — this is a
  signal, not a diagnosis."

### 7.4 Milk stock screen (Phase 2, extended in 3 & 4)
- Daily stock sheet per date: rows = own_farm-cow, own_farm-buffalo, collection-cow, collection-buffalo,
  combined; columns = opening, in, delivered, wastage, home use, calf feeding, adjustment, closing.
- "Record usage" form: wastage / home_use / calf_feeding with source, milk type, qty, reason.
- "Adjustment" (permission milk_stock.adjust): in/out with mandatory reason.
- Ledger view with filters and running balance.

### 7.5 Dashboard (progressively, complete in Phase 7)
Cards (each hidden if user lacks permission): today's own production (cow/buffalo), today's collection
(litres, amount), today's deliveries (litres, amount), wastage, current stock by source & type,
today's expenses, month-to-date sales/expenses, customer receivables, unbilled amount, farmer payable,
vendor payable, party balances, milk drop alerts, vaccinations due, expected calvings, low feed stock.
Charts: last 30 days production vs collection vs sales (litres); month-wise income vs expenses.
Financial cards require `dashboard.financial`.

### 7.6 Reports (Phase 7) — all with date filters, Excel + PDF export
1. Milk production: daily, monthly, animal-wise, lactation-wise.
2. Milk collection: daily shift summary, farmer-wise, avg fat/snf, rejected.
3. Milk balance / reconciliation: per source & type, period totals; flags negative balances.
4. Sales: customer-wise, route-wise, product-wise, B2B dairy plant reconciliation (our qty/fat/snf vs
   buyer's, difference %).
5. Receivables aging (0–30, 31–60, 61–90, 90+), farmer payables, vendor payables, party balances.
6. Expenses: category × month matrix, section-wise.
7. **Cost per litre** (own farm) = HERD section expenses in period ÷ own production litres in period.
8. **Profit per animal** (estimated contribution): milk value − feed − health − allocated labour/overhead
   (HERD section expenses without animal_id divided equally among milking animals by days in milk).
   Clearly labelled estimate.
9. Collection center margin = collection milk sales value (deliveries from collection source) −
   farmer purchase amount − COLLECTION section expenses.
10. Simple P&L (cash-basis summary, labelled "Management summary, not statutory accounts"):
    income (finalized bills, animal sales, direct deliveries) − farmer purchases − expenses − animal purchases.
11. Animal trading P&L; staff attendance & salary register.
12. **Milk forecast** (7/30/90 days): per milking animal, average of last 14 days daily yield; exclude dry
    animals; flag animals expected to dry off/calve in window. Labelled "Estimate — actual yield depends on
    health, feed, season, pregnancy." No ML in v1.

---

## 8. Phased build plan with tasks and acceptance criteria

Each phase ends with: all tests passing, Pint clean, seeders updated, `docs/PROGRESS.md` checklist ticked and
its "Phase log" filled, `README.md` updated (feature status, setup, phase table), `CHANGELOG.md` entry added,
`docs/DECISIONS.md` updated, commit + git tag `phase-<n>-done`.

### Phase 0 — Foundation
Tasks:
1. New Laravel project, MySQL config (app DB + `dairy_erp_test` for tests), Pest, Pint. Remove Vite/npm files.
   Download front-end libraries into `public/vendor/` (Section 2.1); create `public/css/app.css`, `public/js/app.js`.
   Hand-built auth: `Auth\LoginController` (show form, login, logout), Bootstrap 5 login view, `throttle` on login.
   No public registration and no email password reset in v1 — owner/admin resets staff passwords from Users screen;
   every user can change their own password from the profile page.
2. Base layout: sidebar (module menu filtered by permission), top bar (farm name/switcher, notifications bell,
   user menu), flash messages, responsive (collapsible sidebar on mobile), Indian number/date helpers.
3. Money helper, enums folder, BelongsToFarm + TracksUser traits, CurrentFarm, EnsureFarmSelected middleware.
4. farms, farm_user, sections, payment_modes, document_sequences migrations + models + seeders.
5. Spatie permission + seeder for all permissions & roles (Section 5); owner user from env
   `OWNER_NAME`, `OWNER_EMAIL`, `OWNER_PHONE`, `OWNER_PASSWORD` (defaults for local only).
6. Login by email or phone; inactive user block; last_login_at.
7. Users CRUD, roles & permission matrix UI, farm settings screen (settings JSON form), sections & payment
   modes CRUD.
8. Activity log setup + audit log screen; `reason` capture helper.
9. spatie/laravel-backup config, scheduled daily 02:00, keep 14 days; `docs/BACKUP_RESTORE.md` with restore steps.
10. Common components: Blade components for form fields, money input, date input, Select2 init, DataTable init,
    confirm-with-reason modal. All components styled only via CSS variables (Section 4.11).
11. Monitoring (Section 4.10): Pulse with `/pulse` gated to `monitoring.view`, Sentry wired but inactive without DSN,
    Telescope local-only; `docs/DEPLOYMENT.md` (Nginx, PHP-FPM, cron `schedule:run`, Supervisor for
    `queue:work` and `pulse:check`, `.env` production checklist).
12. Appearance & branding (Section 4.11): `AppearanceService`, CSS-variable layout, Settings → Appearance screen
    with live preview, presets, reset, uploads (logo, dark logo, favicon, login background), per-user theme
    override in profile, branding applied to login page and a sample PDF.
Acceptance: owner logs in, creates a staff user with herd_staff role, that user sees only permitted menus and
gets 403 on forbidden URLs; data of another farm is invisible; settings save; audit log shows user edits;
changing primary color / sidebar color / logo in Appearance changes the whole panel, login page and PDF
header after save without code changes; "Reset to defaults" restores defaults; `/pulse` opens for owner and
returns 403 for admin and staff; a request slower than the threshold appears in Pulse's Slow Requests card.

### Phase 1 — Expense management (no approval)
Tasks: categories/subcategories CRUD (tree view) → vendors CRUD → expenses CRUD with receipts & filters →
vendor payments & vendor ledger → reports → Excel template/upload/preview/import/error download/history/revert.
Acceptance:
- Expense saved by staff appears immediately in list and reports (no status/approval anywhere).
- Credit expense requires vendor, increases vendor balance; vendor payment reduces it; paid expense doesn't affect it.
  Test: ₹10,000 credit feed, ₹4,000 payment → vendor balance ₹6,000.
- Subcategory must belong to category (server-side).
- Staff without expenses.edit can edit own entry today only.
- Import: 3 valid + 2 invalid rows → preview shows 2 errors; import inserts exactly 3; error file has 2 rows.
- Monthly report totals equal Σ of expenses.

### Phase 2 — Animals, milk production, milk stock
Tasks: breeds → animals CRUD (with photo, filters by type/stage/status/ownership, profile page with tabs:
details, milk, health, breeding, expenses, history) → status/lifecycle change actions with history →
expenses.animal_id FK + animal select in expense form → milk production bulk/single entry → MilkStockService
+ stock sheet + usage/adjustment forms → production reports → milk drop alerts.
Acceptance:
- Tag unique per farm; only active milking females in entry grid.
- Bulk save of 3 animals (8+6, 10+8, 6+5) → daily total 43 L; stock own_farm/cow +43.
- Editing an entry from 10 to 7 updates stock by −3 in the same transaction; deleting removes the movement.
- Duplicate (animal, date, shift) rejected; same client_uuid twice creates one record.
- Wastage of 5 L reduces balance; outgoing beyond balance blocked when setting false.
- Drop alert fires for avg 10 → today 7 (30% ≥ 20%).

### Phase 3 — Customers, delivery, billing, payments
Tasks: routes → products → customers CRUD + customer rates → subscriptions + pauses → delivery sheet generation
(command + button + schedule) → delivery boy mobile screen → admin delivery grid (date/shift/route, bulk mark
delivered) → one-off/B2B dispatch form → billing (generate single/bulk, draft review, finalize, PDF, cancel) →
customer payments + FIFO allocation → adjustments → customer ledger → sales reports.
Acceptance:
- Paused subscription generates no delivery for paused dates; generation is idempotent.
- Delivered row creates stock out; skip removes it; extra qty works.
- A delivery can never be in two non-cancelled bills; overlapping bill periods rejected.
- Bill example: previous 500, charges 3,600, payments 1,000 → closing 3,100.
- Finalized bill not editable (403/validation); cancel releases deliveries.
- Payment ₹5,000 against bills 3,000 (older) and 4,000 → allocations 3,000 + 2,000.
- Delivery boy sees only his route's customers; cannot open other customers by URL.

### Phase 4 — Farmers & collection center
Tasks: farmers CRUD → rate charts (all 4 methods) + slabs + rate preview calculator → collection entry screen
→ collection list/edit with lock rules → advances → payment cycles (create/draft edit/finalize/mark paid) →
statements PDF + bank sheet → collection reports + B2B dairy plant reconciliation report.
Acceptance:
- 20 L @ fixed ₹40 → ₹800. fat_snf_rate chart computes correctly; below-standard flow works.
- Changing a rate chart does not change existing collection amounts.
- Accepted collection adds to collection stock (by milk type); rejected doesn't.
- Cycle example: gross 8,000, advance 500, bonus 200 → net 7,700; collections in a finalized cycle can't be edited.
- Collections can't be in two cycles; cycles can't overlap.

### Phase 5 — Health, breeding, feed
Tasks: treatments (with linked expense) → vaccine types + vaccinations (single & bulk) → breeding events with
expected calving → calvings (calf creation, lifecycle updates) → dry-off → feed items + transactions
(purchase creates expense) + stock screen → reminders command + notifications UI → withdrawal warning in milk entry.
Acceptance:
- Treatment ₹800 with "record as expense" creates exactly one expense; editing to ₹900 updates it; no duplicate.
- AI on cow on 01-01-2027 → expected calving 08-10-2027 (280 days).
- Calving creates calf with mother_id and increments lactation_number.
- Feed: opening 500 kg, consume 80 → 420; purchase creates one expense; consumption creates none.
- Reminder notifications generated for due vaccinations and low stock.

### Phase 6 — Animal trading & staff
Tasks: parties → animal purchase (inline animal creation, cost allocation) → animal sale (status update,
cancel) → payments/installments + party ledger → receipt PDF → animal P&L + trading report → staff members →
attendance (bulk) → advances → salary runs (draft/finalize → expenses) → registers.
Acceptance:
- Purchase of 2 animals priced 30,000 and 20,000 with 1,000 transport → allocated 600 / 400.
- Heifer example: cost 35,000 + 12,000 feed + 2,000 health + 1,000 transport = 50,000; sale 65,000 →
  estimated profit 15,000 in P&L report.
- Sold animal disappears from milk entry, keeps its full history.
- Salary: 30-day month, 26 present + 4 absent, base 15,000 → earned 13,000; finalize creates expense 13,000.

### Phase 7 — Dashboard & reports
Tasks: all dashboard cards & charts (7.5), all reports (7.6), Excel/PDF exports, performance (indexes,
eager loading, report queries via SQL aggregation; cache dashboard numbers 5 minutes, bust on writes).
Acceptance: dashboard loads < 1.5 s with DemoSeeder data (1 year, 30 animals, 50 farmers, 150 customers);
every report total reconciles with its source tables (feature tests).

### Phase 8 — Not now
Only after explicit instruction: WhatsApp/SMS, payment gateway, milk analyzer & weighing scale integration,
thermal printer receipts, customer portal, PWA/offline sync, Hindi UI, ML forecasting.

---

## 9. UI guidelines
- Bootstrap 5.3, clean admin look, consistent spacing. **All colors, fonts, logo and theme come from
  Settings → Appearance (Section 4.11)** — never hard-code colors in CSS, Blade, JS charts or PDFs.
  Default look: green/teal primary with dark green sidebar. Sidebar groups:
  Dashboard · Herd (Animals, Milk Entry, Health, Breeding, Feed) · Collection (Farmers, Collection Entry,
  Rate Charts, Farmer Payments) · Sales (Customers, Subscriptions, Deliveries, Bills, Payments) · Milk Stock ·
  Expenses (Expenses, Vendors, Import) · Trading (Purchases, Sales, Parties) · Staff · Reports ·
  Settings (Farm, Users, Roles, Appearance, Sections, Payment Modes, Audit Log, System Monitor).
- Screens used in the field (milk entry, collection entry, delivery boy screen) must work well at 360px width,
  large inputs, numeric keyboard (`inputmode="decimal"`), minimal taps.
- Every list: search, filters, pagination, Excel export, totals where meaningful.
- Every destructive action: confirmation modal (with reason for critical models).
- Show amounts as ₹1,25,000.00 and dates as DD-MM-YYYY everywhere, including PDFs and exports.
- Empty states with a clear "Add first …" button.

## 10. Testing requirements
- Pest feature tests for every acceptance criterion in Section 8, plus authorization tests (each role gets 403 on
  at least one forbidden action per module) and farm isolation tests.
- Unit tests for Money, RateChartService, PricingService, BillingService balance math, FarmerPaymentService,
  cost allocation, salary calculation, MilkStockService.
- Factories for every model. `DemoSeeder` generating realistic Indian data (names, villages, rates) for one year.
- Tests run against the MySQL test database `dairy_erp_test` (`.env.testing`, `RefreshDatabase`), never against
  the main database. Pulse and Telescope are disabled in tests; Sentry DSN is empty.
- Appearance tests: invalid hex rejected; saved colors appear as CSS variables in the rendered layout; reset
  restores defaults; only `appearance.manage` can open the screen.

## 11. Seeders summary
`DatabaseSeeder` (production-safe): permissions & roles, default farm ("My Dairy Farm", code MDF), owner user,
sections, payment modes, expense categories, breeds, vaccine types, products, document sequences.
`DemoSeeder` (local only, refuses to run in production): animals, 1 year milk records, farmers, collections,
rate charts, customers, subscriptions, deliveries, bills, payments, expenses, feed, staff.

## 12. Out of scope (do NOT build in this version)
- Expense approval workflow of any kind (pending/approved/rejected, approver, approval inbox, approval toggle).
- Petty cash accounts, recurring expenses.
- Double-entry accounting, chart of accounts, GST computation/e-invoicing, credit notes (use adjustments).
- Products other than raw cow/buffalo milk; manufacturing.
- Mixing/transfer between own_farm and collection stock (only manual adjustments).
- SaaS multi-tenancy billing, public signup.
- APIs, SPA frontend, native mobile app, PWA/offline.
- WhatsApp/SMS/email notifications (only in-app database notifications).
- Payment gateway, hardware integrations, ML predictions.

## 13. Defaults for anything not covered
- Prefer the simplest implementation consistent with this spec and existing code patterns.
- Prefer blocking invalid data with a clear validation message over silently correcting it.
- Prefer computed values from transaction tables over stored running totals (except explicit snapshots).
- Record the choice in `docs/DECISIONS.md` and continue without asking.
