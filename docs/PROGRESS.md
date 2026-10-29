# Dairy ERP — Progress & Phase-wise Work Log

> Maintained by Claude Code. Update after **every task** (tick it) and after **every phase** (fill the Phase log).
> Never delete history. Source of truth for tasks: `docs/DAIRY_ERP_SPEC.md` Section 8.

## Current status

| Item | Value |
|---|---|
| Current phase | Phase 3 — Customers, Delivery, Billing, Payments (🟡 in progress) |
| Last completed task | 3.1 — Routes CRUD |
| Next task | 3.2 — Products CRUD + seeder |
| Overall progress | 35 / 88 tasks (40%) |
| Tests passing | 426 |
| Last updated (IST) | 2026-10-29 (task/3.1-routes-crud) |

## Phase overview

| Phase | Name | Status | Started | Completed | Tag |
|---|---|---|---|---|---|
| 0 | Foundation | ✅ Done | 2026-10-15 | 2026-10-15 | `phase-0-done` |
| 1 | Expense Management (no approval) | ✅ Done | 2026-10-16 | 2026-10-18 | `phase-1-done` |
| 2 | Animals, Milk Production, Milk Stock | ✅ Done | 2026-10-19 | 2026-10-28 | `phase-2-done` |
| 3 | Customers, Delivery, Billing, Payments | 🟡 In progress | 2026-10-29 | | |
| 4 | Farmers & Collection Center | ⬜ Not started | | | |
| 5 | Health, Breeding, Feed | ⬜ Not started | | | |
| 6 | Animal Trading & Staff | ⬜ Not started | | | |
| 7 | Dashboard & Reports | ⬜ Not started | | | |
| 8 | Integrations (only on explicit instruction) | ⏸ Not planned | | | |

Status legend: ⬜ Not started · 🟡 In progress · ✅ Done · ⏸ On hold

---

## Phase 0 — Foundation

### Checklist
- [x] 0.1 Laravel project, MySQL (app + `dairy_erp_test`), Pest, Pint, remove Vite/npm, `public/vendor/` libraries, hand-built login
- [x] 0.2 Base layout: sidebar (permission-filtered), top bar, flash messages, responsive, Indian number/date helpers
- [x] 0.3 Money helper, Enums, BelongsToFarm + TracksUser traits, CurrentFarm, EnsureFarmSelected middleware (also `farms` + `farm_user` tables, default farm seeder, farm switcher — moved here from 0.4, Decision #19)
- [x] 0.4 farms, farm_user, sections, payment_modes, document_sequences (migrations, models, seeders) — farms & farm_user already done in 0.3
- [x] 0.5 Spatie permissions + roles seeder, owner user from env
- [x] 0.6 Login by email or phone, inactive user block, last_login_at (login itself built in 0.1; 0.6 added sign-out of users deactivated mid-session)
- [x] 0.7 Users CRUD, roles & permission matrix, farm settings, sections & payment modes CRUD
- [x] 0.8 Activity log + audit log screen + reason capture
- [x] 0.9 Backups (daily 02:00, 14 days) + `docs/BACKUP_RESTORE.md`
- [x] 0.10 Common Blade components (fields, money, date, Select2, DataTable, confirm-with-reason)
- [x] 0.11 Monitoring: Pulse (`/pulse` owner only), Sentry (DSN-gated), Telescope (local), `docs/DEPLOYMENT.md`
- [x] 0.12 Appearance & branding: AppearanceService, CSS variables, Settings → Appearance with live preview, presets, uploads, user theme
- [x] 0.13 Phase 0 acceptance tests pass + README/CHANGELOG updated

### Phase log
- **Completed on:** 2026-10-15 (PR for Task 0.13; tag `phase-0-done` is added after it is merged)
- **What was built (plain language):** The base of the ERP. Staff log in with email or mobile number; inactive users
  are blocked (even mid-session). Every screen works inside a farm and never shows another farm's data. The owner
  manages users, roles (permission matrix), farm settings, sections and payment modes; every change to users, roles
  and settings is recorded in the audit log with old → new values and a reason. Money, quantities, dates and
  document numbers (BILL/2026-27/0001) follow Indian conventions. The database and uploads are backed up every night
  (kept 14 days) with owner alerts on failure. Pulse shows slow pages and errors to the owner; Sentry is ready for
  production alerts. The look (logo, colours, font, theme, login page, PDF header) is fully managed from Settings →
  Appearance, with a live preview, presets and reset. Reusable form components are ready for all later modules.
- **Screens / URLs added:** `/login`, `/` (landing), `/dashboard`, `/profile` (incl. own theme), `/notifications`,
  `/settings/farm`, `/settings/users` (+ create/edit), `/settings/roles` (+ create/edit/delete), `/settings/sections`,
  `/settings/payment-modes`, `/settings/audit-log`, `/settings/appearance` (+ reset, sample PDF), `/pulse` (owner),
  `/telescope` (local only), farm switcher (top bar, when a user has several farms).
- **Tables / migrations added:** users (email/phone/is_active/last_login_at/preferences), cache, jobs, notifications,
  permission tables (Spatie), activity_log (+ event, batch_uuid, farm_id), farms, farm_user, sections, payment_modes,
  document_sequences, pulse_* tables, telescope_entries.
- **Services / commands / jobs added:** `Money`, `Format`, `FinancialYear`, `Color`, `SvgSanitizer`, `CurrentFarm`,
  `AuditReason`, `Permissions`; services `DocumentNumberService`, `UserService`, `RoleService`, `FarmSettingsService`,
  `AppearanceService`; traits `BelongsToFarm`, `TracksUser`, `Auditable`, `HasFarmDefaults`, `RequiresReason`;
  middleware `EnsureFarmSelected` (`farm`), `EnsureUserIsActive` (`active`); listener `NotifyOwnersOfBackupProblem`;
  scheduled `backup:run` 02:00, `backup:clean` 02:30, `backup:monitor` 03:00 IST; seeders `RolesAndPermissionsSeeder`,
  `DefaultFarmSeeder`, `OwnerUserSeeder`, `FarmMasterDataSeeder`; Blade components `x-form.*`, `x-confirm-form`,
  `x-data-table`, `x-money`.
- **Tests added (count, all passing?):** 199 tests, 716 assertions, all passing (incl. `tests/Feature/Phase0AcceptanceTest.php`
  covering every Phase 0 acceptance criterion). Pint clean.
- **Decisions taken:** #5–#79 in [`docs/DECISIONS.md`](DECISIONS.md) — notably farms built in Task 3 (#19), no owner
  super-user bypass (#31), owner-role protection (#35), audit log farm isolation (#46), backups in-app alerts (#52),
  appearance via CSS variables (#63).
- **Known issues / pending:** Demo users/data arrive with `DemoSeeder` in a later phase. Backups stay on the same
  server until an off-site copy is set up (`docs/BACKUP_RESTORE.md` §4). Pulse/Telescope dashboards load their font from
  fonts.bunny.net (#62). Sentry needs a DSN from sentry.io before it reports anything.
- **How to check it manually:**
  1. `php artisan migrate --seed`, open http://127.0.0.1:8000/login and log in as `owner@dairy.test` / `password`.
  2. Settings → Users → Add user: role *Herd Staff*, a mobile number and password. Log out and log in with that mobile
     number: only Dashboard is in the menu; opening `/settings/users` shows 403.
  3. As owner, edit that user (change the name, give a reason) → Settings → Audit Log shows old → new and the reason.
  4. Settings → Farm: change "Bill due after" and save.
  5. Settings → Appearance: choose "Dark Slate", upload a logo, Save → sidebar, buttons, login page and *Sample PDF*
     change; "Reset to defaults" brings the green theme back.
  6. Open `/pulse` as owner (works) and as an admin user (403).
  7. Run `php artisan backup:run` → a zip appears in `storage/app/backups/dairy-erp/`.

---

## Phase 1 — Expense Management (no approval)

### Checklist
- [x] 1.1 Expense categories & subcategories CRUD (tree view) + seeder
- [x] 1.2 Vendors CRUD
- [x] 1.3 Expenses CRUD (direct save, no approval) with receipts, filters, totals, Excel export
- [x] 1.4 Staff same-day edit rule + reason on edit/delete
- [x] 1.5 Vendor payments + vendor ledger with running balance
- [x] 1.6 Expense reports (daily, monthly category × section, category-wise, vendor outstanding) + Excel/PDF
- [x] 1.7 Excel template download
- [x] 1.8 Excel upload + row validation + preview (errors & duplicate warnings)
- [x] 1.9 Import valid rows + error-rows download
- [x] 1.10 Import history + batch revert
- [x] 1.11 Phase 1 acceptance tests pass + README/CHANGELOG updated

### Phase log
- **Completed on:** 2026-10-18 (v0.1.0)
- **What was built (plain language):** Every rupee spent can now be recorded. Expenses go into two-level categories
  (9 defaults such as Feed › Cattle Feed) and a section (herd, collection center, delivery, trading, office). An expense
  is either paid now (cash/UPI/bank…) or on credit from a vendor — credit adds to what we owe that vendor, a vendor
  payment reduces it, and each vendor has a ledger with a running balance. Expenses are saved and counted at once — there
  is **no approval step**. Staff who may only add expenses can change their own entry on the same day; everyone else
  needs edit rights, and every edit/delete asks for a reason that goes to the audit log. Four reports (daily, monthly
  category × section, category-wise, vendor outstanding) open on screen and download as Excel or PDF. A whole sheet of
  expenses can be imported from Excel: download the template, upload up to 5,000 rows, see errors per cell and possible
  duplicates, import the good rows, download the bad rows to fix and upload again, and undo an import within 7 days.
- **Screens / URLs added:** `/expenses` (list, filters, totals, export) · `/expenses/create`, `/expenses/{id}`,
  `/expenses/{id}/edit` · `/expenses/categories` · `/vendors`, `/vendors/{id}` (ledger), `/vendor-payments/create` ·
  `/reports` and `/reports/expenses/{daily|monthly|category|vendors}` (+ `?format=excel|pdf`) · `/expenses/import`
  (template, upload, history) · `/expenses/import/{batch}` (preview, import, error rows, cancel, revert).
- **Tables / migrations added:** `expense_categories`, `vendors`, `expenses` (+ covering/report indexes), `vendor_payments`,
  `expense_import_batches`, `expense_import_rows` (+ FK `expenses.import_batch_id`), revert columns on import batches.
- **Services / commands / jobs added:** ExpenseCategoryService, VendorService, ExpenseService, VendorPaymentService,
  VendorLedgerService, ExpenseReportService, ExpenseImportService (+ ExpenseImportReader); ReportTable + ReportTableExport +
  report PDF; Exporter (queued exports above 5,000 rows → NotifyExportReady job + bell link; `exports:prune` daily);
  ReceiptStorage; ExpensePolicy / VendorPaymentPolicy with the OwnEntrySameDay rule; DemoSeeder (local demo year).
- **Tests added (count, all passing?):** 109 new (199 → 308), all passing; `tests/Feature/Phase1AcceptanceTest.php`
  covers the six acceptance criteria end to end.
- **Decisions taken:** [DECISIONS](DECISIONS.md) #76–#109 (category tree, vendor balances, paid vs credit, staff edit rule,
  report layouts and indexes, import reading/validation/duplicates, bulk import + batch audit, revert rules and 7-day window,
  merge permission for 1.7–1.9, DemoSeeder started early).
- **Known issues / pending:** `expenses.animal_id` select arrives in Phase 2 (task 2.4); auto-created expenses from
  treatments/feed/salary arrive with those phases. Imported expenses are audit-logged as one batch entry, not per row.
- **How to check it manually:**
  1. `php artisan db:seed --class=DemoSeeder`, log in as `accountant@dairy.test` / `password`.
  2. Expenses → add a ₹10,000 **credit** expense for a vendor; Vendors → open that vendor → Record payment ₹4,000 →
     the ledger shows the balance up by ₹6,000.
  3. Log in as `herd@dairy.test`, add an expense; it shows at once in the accountant's list and in Reports → Monthly.
  4. Reports → each expense report → change dates → download Excel and PDF.
  5. Expenses → Import → download the template, fill 3 good + 2 bad rows, upload → preview shows 2 errors → Import →
     3 expenses added → Download error rows → fix → upload again.
  6. In Import history press Revert (↺) with a reason → that upload's expenses disappear; after 7 days the button is gone.

---

## Phase 2 — Animals, Milk Production, Milk Stock

### Checklist
- [x] 2.1 Animal breeds + seeder
- [x] 2.2 Animals CRUD (photo, filters, profile page with tabs)
- [x] 2.3 Status / lifecycle change actions + status history (observer)
- [x] 2.4 `expenses.animal_id` FK + animal select in expense form
- [x] 2.5 Milk production bulk entry (date + shift grid) + single entry + animal history chart
- [x] 2.6 MilkStockService + milk_stock_movements
- [x] 2.7 Daily stock sheet, usage (wastage/home use/calf feeding) and adjustment forms, ledger view
- [x] 2.8 Production reports (daily, monthly, animal-wise)
- [x] 2.9 Milk drop alerts
- [x] 2.10 Phase 2 acceptance tests pass + README/CHANGELOG updated

### Phase log
- **Completed on:** 2026-10-28 (v0.2.0, tag `phase-2-done` after the last PR is merged)
- **What was built (plain language):** The herd is now in the system. Each cow or buffalo has a tag (unique in the farm),
  breed, mother, photo, stage (calf, heifer, milking, dry …), status and ownership; every change of stage, status or
  ownership is kept as history with date and reason, and the profile page shows details, calves, milk, expenses and
  history. Expenses can be linked to an animal. Milk is entered on one grid per date and shift that lists every
  milking female (with her last 3 entries as a hint) and works on a phone; single entries add fat/SNF; changing saved
  milk needs a reason. Every litre goes into a **milk stock ledger** kept separately for own-farm and collection milk,
  cow and buffalo: the Milk Stock page shows each day's opening, milk in, wastage, home use, calf feeding,
  adjustments and closing, and milk cannot go out beyond what is in stock (unless the farm allows it). Wastage, home
  use and calf feeding are recorded with a reason; adjustments need a separate permission. Reports show daily
  (animal × shift), monthly (day by day) and animal-wise milk on screen, in Excel and PDF. **Milk drop alerts** warn
  when an animal gives 20 % (farm setting) less than her usual — "Check health — this is a signal, not a diagnosis."
- **Screens / URLs added:** `/animals/breeds` · `/animals` (list, filters), `/animals/create`, `/animals/{id}` (profile,
  `?tab=milk`), `/animals/{id}/edit` · `/milk-production` (entry grid), `/milk-production/entries`,
  `/milk-production/create`, `/milk-production/{id}/edit` · `/milk-production/alerts` (Herd → Milk Alerts) ·
  `/milk-stock` (daily sheet), `/milk-stock/usage/create`, `/milk-stock/adjustments/create`, `/milk-stock/ledger` ·
  `/reports/milk/{daily|monthly|animals}` (+ `?format=excel|pdf`).
- **Tables / migrations added:** `animal_breeds`, `animals`, `animal_status_histories`, FK `expenses.animal_id` (+ index),
  `milk_production_records`, `milk_stock_movements` (with back-fill of existing milk entries), `milk_stock_movements.client_uuid`.
- **Services / commands / jobs added:** AnimalService (+ AnimalObserver, AnimalPhotoStorage), MilkProductionService,
  MilkStockService (+ MovesMilkStock contract, InsufficientMilkStockException), MilkReportService, MilkDropAlertService;
  MilkProductionRecordPolicy (OwnEntrySameDay); FarmMasterDataSeeder seeds breeds; DemoSeeder adds 30 animals, a year of
  milk and its stock movements. No new scheduled commands (milk drop bell notifications arrive with `reminders:daily`, Phase 5).
- **Tests added (count, all passing?):** 106 new (308 → 414), all passing; `tests/Feature/Phase2AcceptanceTest.php`
  covers the six acceptance criteria end to end; `QueryCountTest` covers the animal, milk entry, stock sheet and ledger pages.
- **Decisions taken:** [DECISIONS](DECISIONS.md) #110–#126 (breeds, animal rules and photos, status history observer,
  animal on expenses, milk grid rules and speed, stock ledger sync and balance check, usage/adjustment permissions and
  append-only ledger, stock screens, report definitions and shared report screen, drop alert rule and placement).
- **Known issues / pending:** milk drop alerts are not yet bell notifications (Phase 5 `reminders:daily`) nor a dashboard
  card (Phase 7); collection and delivery rows of the stock sheet stay at 0 until Phases 3 and 4; lactation-wise milk
  report is a Phase 7 report; usage/adjustment entries cannot be edited — correct them with an adjustment.
- **Performance (demo year: 30 animals, 16,436 milk entries / stock movements):** milk grid ~70 ms, entries list for a year
  ~250 ms, stock sheet ~30 ms, stock ledger for a year ~150 ms, milk reports 15–60 ms, milk alerts ~40 ms.
- **How to check it manually:**
  1. `php artisan migrate` then `php artisan db:seed --class=DemoSeeder`; log in as the owner.
  2. Herd → Animals → add a milking cow; try the same tag again → refused. Open her profile → change stage to Dry with a
     reason → History tab shows it.
  3. Log in as `herd@dairy.test` / `password` → Herd → Milk Entry → type litres for a few animals → Save; the dry cow is
     not in the grid.
  4. Milk Stock → today's own-farm cow "In" equals what you saved; Record usage → 5 L wastage → closing drops by 5; try
     more than the closing → error on Litres.
  5. Change a saved grid value (needs a reason) → Milk Stock and the Ledger follow.
  6. Herd → Milk Alerts → yesterday's date shows the demo herd's drops with average and %.
  7. Reports → Milk production → Daily / Monthly / Animal-wise → download Excel and PDF.

---

## Phase 3 — Customers, Delivery, Billing, Payments

### Checklist
- [x] 3.1 Routes CRUD
- [ ] 3.2 Products CRUD + seeder
- [ ] 3.3 Customers CRUD + customer rates (PricingService)
- [ ] 3.4 Subscriptions + pauses
- [ ] 3.5 Delivery sheet generation (command + button + schedule)
- [ ] 3.6 Delivery boy mobile screen (deliver/skip/qty/collect payment)
- [ ] 3.7 Admin delivery grid + bulk mark delivered
- [ ] 3.8 One-off / B2B dispatch form (with fat/snf and buyer readings)
- [ ] 3.9 Billing: generate (single/bulk), draft review, finalize, PDF, cancel
- [ ] 3.10 Customer payments + FIFO allocation
- [ ] 3.11 Customer adjustments
- [ ] 3.12 Customer ledger (screen + Excel/PDF)
- [ ] 3.13 Sales reports
- [ ] 3.14 Phase 3 acceptance tests pass + README/CHANGELOG updated

### Phase log
- **Completed on:** _TODO_
- **What was built (plain language):** _TODO_
- **Screens / URLs added:** _TODO_
- **Tables / migrations added:** _TODO_
- **Services / commands / jobs added:** _TODO_
- **Tests added (count, all passing?):** _TODO_
- **Decisions taken:** _TODO_
- **Known issues / pending:** _TODO_
- **How to check it manually:** _TODO_

---

## Phase 4 — Farmers & Collection Center

### Checklist
- [ ] 4.1 Farmers CRUD
- [ ] 4.2 Rate charts (fixed, fat_rate, fat_snf_rate, slab) + slabs + rate preview calculator
- [ ] 4.3 Collection entry screen (fast, keyboard-only)
- [ ] 4.4 Collection list / edit with lock rules
- [ ] 4.5 Farmer advances
- [ ] 4.6 Payment cycles: create, draft edit, finalize, mark paid
- [ ] 4.7 Farmer statement PDF + bank sheet Excel
- [ ] 4.8 Collection reports + B2B dairy plant reconciliation report
- [ ] 4.9 Phase 4 acceptance tests pass + README/CHANGELOG updated

### Phase log
- **Completed on:** _TODO_
- **What was built (plain language):** _TODO_
- **Screens / URLs added:** _TODO_
- **Tables / migrations added:** _TODO_
- **Services / commands / jobs added:** _TODO_
- **Tests added (count, all passing?):** _TODO_
- **Decisions taken:** _TODO_
- **Known issues / pending:** _TODO_
- **How to check it manually:** _TODO_

---

## Phase 5 — Health, Breeding, Feed

### Checklist
- [ ] 5.1 Treatments (with linked expense, withdrawal date)
- [ ] 5.2 Vaccine types + vaccinations (single & bulk)
- [ ] 5.3 Breeding events + expected calving date
- [ ] 5.4 Calvings (calf creation, lifecycle + lactation updates)
- [ ] 5.5 Dry-off action + heifer suggestion
- [ ] 5.6 Feed items + feed transactions (purchase creates expense) + stock screen
- [ ] 5.7 Reminders command + in-app notifications (bell)
- [ ] 5.8 Withdrawal warning in milk entry
- [ ] 5.9 Phase 5 acceptance tests pass + README/CHANGELOG updated

### Phase log
- **Completed on:** _TODO_
- **What was built (plain language):** _TODO_
- **Screens / URLs added:** _TODO_
- **Tables / migrations added:** _TODO_
- **Services / commands / jobs added:** _TODO_
- **Tests added (count, all passing?):** _TODO_
- **Decisions taken:** _TODO_
- **Known issues / pending:** _TODO_
- **How to check it manually:** _TODO_

---

## Phase 6 — Animal Trading & Staff

### Checklist
- [ ] 6.1 Parties (buyers/sellers) CRUD
- [ ] 6.2 Animal purchase with inline animal creation + cost allocation
- [ ] 6.3 Animal sale (status update) + cancel
- [ ] 6.4 Animal payments / installments + party ledger
- [ ] 6.5 Sale receipt PDF
- [ ] 6.6 Animal P&L + trading summary report
- [ ] 6.7 Staff members CRUD
- [ ] 6.8 Attendance (bulk screen)
- [ ] 6.9 Staff advances
- [ ] 6.10 Salary runs (draft → finalize → expenses) + salary slips
- [ ] 6.11 Attendance & salary registers
- [ ] 6.12 Phase 6 acceptance tests pass + README/CHANGELOG updated

### Phase log
- **Completed on:** _TODO_
- **What was built (plain language):** _TODO_
- **Screens / URLs added:** _TODO_
- **Tables / migrations added:** _TODO_
- **Services / commands / jobs added:** _TODO_
- **Tests added (count, all passing?):** _TODO_
- **Decisions taken:** _TODO_
- **Known issues / pending:** _TODO_
- **How to check it manually:** _TODO_

---

## Phase 7 — Dashboard & Reports

### Checklist
- [ ] 7.1 Dashboard cards (operational + financial, permission-aware)
- [ ] 7.2 Dashboard charts (30-day litres, month-wise income vs expenses)
- [ ] 7.3 Milk production, collection, balance/reconciliation reports
- [ ] 7.4 Sales, receivables aging, payables, party balances reports
- [ ] 7.5 Expense matrix, cost per litre, profit per animal, collection center margin
- [ ] 7.6 Simple P&L (management summary) + trading & staff reports
- [ ] 7.7 Milk forecast (7/30/90 days)
- [ ] 7.8 Excel/PDF export on all reports
- [ ] 7.9 Performance: indexes, eager loading, dashboard cache, DemoSeeder timing check
- [ ] 7.10 Phase 7 acceptance tests pass + final README (no placeholders) + CHANGELOG 0.7.0

### Phase log
- **Completed on:** _TODO_
- **What was built (plain language):** _TODO_
- **Screens / URLs added:** _TODO_
- **Tables / migrations added:** _TODO_
- **Services / commands / jobs added:** _TODO_
- **Tests added (count, all passing?):** _TODO_
- **Decisions taken:** _TODO_
- **Known issues / pending:** _TODO_
- **How to check it manually:** _TODO_

---

## Task-by-task history (append newest at the bottom)

| Date & time (IST) | Task | Summary | Commit |
|---|---|---|---|
| | | | |
