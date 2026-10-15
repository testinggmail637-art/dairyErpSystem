# Dairy ERP — Progress & Phase-wise Work Log

> Maintained by Claude Code. Update after **every task** (tick it) and after **every phase** (fill the Phase log).
> Never delete history. Source of truth for tasks: `docs/DAIRY_ERP_SPEC.md` Section 8.

## Current status

| Item | Value |
|---|---|
| Current phase | Phase 0 — Foundation (🟡 in progress) |
| Last completed task | 0.9 — Daily backups (02:00 IST, 14 days, DB + uploads), failure alerts in the bell, docs/BACKUP_RESTORE.md |
| Next task | 0.10 — Common Blade components (fields, money, date, Select2, DataTable, confirm-with-reason) |
| Overall progress | 9 / 88 tasks (10%) |
| Tests passing | 149 |
| Last updated (IST) | 2026-10-15 |

## Phase overview

| Phase | Name | Status | Started | Completed | Tag |
|---|---|---|---|---|---|
| 0 | Foundation | 🟡 In progress | 2026-10-15 | | |
| 1 | Expense Management (no approval) | ⬜ Not started | | | |
| 2 | Animals, Milk Production, Milk Stock | ⬜ Not started | | | |
| 3 | Customers, Delivery, Billing, Payments | ⬜ Not started | | | |
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
- [ ] 0.10 Common Blade components (fields, money, date, Select2, DataTable, confirm-with-reason)
- [ ] 0.11 Monitoring: Pulse (`/pulse` owner only), Sentry (DSN-gated), Telescope (local), `docs/DEPLOYMENT.md`
- [ ] 0.12 Appearance & branding: AppearanceService, CSS variables, Settings → Appearance with live preview, presets, uploads, user theme
- [ ] 0.13 Phase 0 acceptance tests pass + README/CHANGELOG updated

### Phase log
- **Completed on:** _TODO_
- **What was built (plain language):** _TODO_
- **Screens / URLs added:** _TODO_
- **Tables / migrations added:** _TODO_
- **Services / commands / jobs added:** _TODO_
- **Tests added (count, all passing?):** _TODO_
- **Decisions taken:** _TODO_ (see `docs/DECISIONS.md`)
- **Known issues / pending:** _TODO_
- **How to check it manually:** _TODO_

---

## Phase 1 — Expense Management (no approval)

### Checklist
- [ ] 1.1 Expense categories & subcategories CRUD (tree view) + seeder
- [ ] 1.2 Vendors CRUD
- [ ] 1.3 Expenses CRUD (direct save, no approval) with receipts, filters, totals, Excel export
- [ ] 1.4 Staff same-day edit rule + reason on edit/delete
- [ ] 1.5 Vendor payments + vendor ledger with running balance
- [ ] 1.6 Expense reports (daily, monthly category × section, category-wise, vendor outstanding) + Excel/PDF
- [ ] 1.7 Excel template download
- [ ] 1.8 Excel upload + row validation + preview (errors & duplicate warnings)
- [ ] 1.9 Import valid rows + error-rows download
- [ ] 1.10 Import history + batch revert
- [ ] 1.11 Phase 1 acceptance tests pass + README/CHANGELOG updated

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

## Phase 2 — Animals, Milk Production, Milk Stock

### Checklist
- [ ] 2.1 Animal breeds + seeder
- [ ] 2.2 Animals CRUD (photo, filters, profile page with tabs)
- [ ] 2.3 Status / lifecycle change actions + status history (observer)
- [ ] 2.4 `expenses.animal_id` FK + animal select in expense form
- [ ] 2.5 Milk production bulk entry (date + shift grid) + single entry + animal history chart
- [ ] 2.6 MilkStockService + milk_stock_movements
- [ ] 2.7 Daily stock sheet, usage (wastage/home use/calf feeding) and adjustment forms, ledger view
- [ ] 2.8 Production reports (daily, monthly, animal-wise)
- [ ] 2.9 Milk drop alerts
- [ ] 2.10 Phase 2 acceptance tests pass + README/CHANGELOG updated

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

## Phase 3 — Customers, Delivery, Billing, Payments

### Checklist
- [ ] 3.1 Routes CRUD
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
