# CLAUDE.md — Dairy ERP (Laravel)

You are building a Dairy ERP for an Indian dairy business that has (1) its own cow/buffalo herd,
(2) a milk collection center that buys milk from farmers, (3) milk sales (B2C home delivery + B2B bulk),
and (4) cow/buffalo buying & selling.

**The full specification is in `docs/DAIRY_ERP_SPEC.md`. Read it fully before writing any code.**
It is the single source of truth. Every table, column, business rule, permission and phase is defined there.

## How to work (autonomous mode)

1. **Do not stop to ask questions.** The spec answers almost everything. If something is genuinely
   unspecified, choose the simplest reasonable option that is consistent with the spec, implement it,
   and record it in `docs/DECISIONS.md` (date, question, decision, reason). Then continue.
2. Work **phase by phase, in order** (Phase 0 → 1 → 2 → ...). Do not start a phase until the previous
   phase's acceptance criteria pass. Within a phase, follow the task order given in the spec.
3. Keep `docs/PROGRESS.md` updated: a checklist of every task in every phase, ticked when done,
   plus a short "Current status / next task" section at the top. Create it at the start if missing.
   If you are resumed in a new session, read `docs/PROGRESS.md` and `docs/DECISIONS.md` first and continue
   from the next unticked task.
4. After each task: run `php artisan test` and `./vendor/bin/pint`. Fix failures before moving on.
   Commit with a clear message (`Phase 1: expense CRUD with receipts`). Small, frequent commits.
5. **Never build anything listed under "Out of scope" in the spec**, even if it seems useful.
   In particular: **there is NO approval workflow for expenses** (no pending/approved/rejected status,
   no approved_by/approved_at fields, no approval inbox).
6. If a migration from an earlier phase needs a change, create a **new migration** — never edit a
   migration that has been committed in an earlier phase.

## Documentation you must keep up to date (part of "done")

A task or phase is **not complete** until these files are updated in the same commit:

1. **`docs/PROGRESS.md` — phase-wise work log (update after EVERY task).**
   - It already contains the full checklist of every phase. Tick `[x]` each task as it is finished.
   - Keep the "Current status" block at the top accurate: current phase, last completed task, next task,
     overall % (ticked tasks ÷ total tasks), test count passing, last updated date/time (IST).
   - When a **phase** is finished, fill that phase's "Phase log" section: completed date, what was built
     (in plain language), screens/URLs added, tables/migrations added, services/commands added, tests added
     and passing, decisions taken (link to `docs/DECISIONS.md`), known issues / pending items, and
     "How to check it manually" (3–8 steps the owner can follow in the browser).
   - Never delete history; only append or tick.
2. **`README.md` — complete project README (update at the end of EVERY phase, and whenever setup changes).**
   - Keep all sections of the template filled and correct: overview, feature list with per-module status,
     tech stack, requirements, installation, `.env` settings, default login, scheduler/queue/Pulse commands,
     monitoring URLs, running tests, deployment summary, folder structure, roles & permissions summary,
     docs index, and the phase status table (must match `docs/PROGRESS.md`).
   - Replace every `_TODO_` / placeholder as soon as the information exists. By the end of Phase 7 the README
     must contain no placeholders and must let a new developer install and run the project without help.
3. **`docs/DECISIONS.md`** — every assumption you made (date, question, decision, reason).
4. **`docs/DEPLOYMENT.md`** and **`docs/BACKUP_RESTORE.md`** — created in Phase 0, updated when infra changes.
5. **`CHANGELOG.md`** — one entry per phase (Keep a Changelog style: Added / Changed / Fixed), version
   `0.<phase>.0` (Phase 0 → 0.0.0, Phase 1 → 0.1.0 … Phase 7 → 0.7.0).

Before every commit, quickly check: is PROGRESS ticked, is README still true, is any new decision logged?

## Non-negotiable technical rules

- Money: `decimal(12,2)`. Quantities (litre/kg): `decimal(10,2)`. Fat/SNF: `decimal(5,2)`. Rates: `decimal(10,2)`.
  **Never use float/double** for money, quantity, fat or SNF — in DB, PHP math, or JS. Use `bcmath`
  (or a small `Money` helper using integer paise) for calculations; cast decimals as strings in models.
- Every business table has `farm_id` and uses the `BelongsToFarm` trait (global scope + auto-fill).
- Any write that touches more than one table runs inside `DB::transaction()`.
- Authorization is enforced on the backend with Policies / `can` middleware. Hiding a menu is not security.
- All validation in Form Request classes. Business logic in `app/Services/*`, not in controllers or Blade.
- PHP backed enums in `app/Enums/*` for every status/type column; store the enum value as a string column.
- Snapshots: rates, amounts and balances are copied onto transaction rows at the time of entry.
  Changing a rate chart or product price must never change historical records.
- Finalized/locked records (finalized bills, finalized farmer payment statements, finalized salary runs,
  billed deliveries, collections included in a statement) cannot be edited or deleted.
- Critical models are audit-logged (old values, new values, user, reason). Edit/delete forms of critical
  records require a `reason` field.
- Dates are stored as `date`; timezone `Asia/Kolkata`; display format `DD-MM-YYYY`; currency `₹` with
  Indian digit grouping (₹1,25,000.00). Financial year April–March.
- Idempotency: forms that create milk entries, collections, deliveries or payments carry a hidden
  `client_uuid`; a unique index rejects duplicates and the controller returns the existing record.
- **Frontend: Blade + Bootstrap 5 + jQuery only. No Vite, no npm, no Tailwind, no Breeze.** Libraries live as
  committed minified files in `public/vendor/`; app code in `public/css/app.css` and `public/js/app.js`
  (see spec Section 2.1).
- Database is MySQL 8. Tests run on a separate MySQL database `dairy_erp_test` (`.env.testing`), never the main DB.
- **No hard-coded colors, fonts or logos.** Everything visual (app name, logo, favicon, primary/secondary/status
  colors, sidebar/topbar/body colors, font, font size, border radius, light/dark theme, login page, PDF
  header/footer) is managed by the owner/admin from **Settings → Appearance** and applied through CSS variables
  (spec Section 4.11). Blade, `app.css`, Chart.js and PDF templates read only those variables/settings.
- **Monitoring:** Laravel Pulse at `/pulse` (owner only, slow request 1000 ms / slow query 500 ms thresholds),
  Sentry for error alerts (inactive when `SENTRY_LARAVEL_DSN` is empty), Telescope in local only
  (spec Section 4.10). Pulse/Telescope disabled in tests.

## Commands

```bash
composer install                    # no npm / no build step
cp .env.example .env && php artisan key:generate
cp .env.example .env.testing        # set DB_DATABASE=dairy_erp_test, then: php artisan key:generate --env=testing
php artisan migrate --seed          # seeds roles, permissions, owner user, master data
php artisan db:seed --class=DemoSeeder   # optional realistic demo data
php artisan test
./vendor/bin/pint
php artisan schedule:work           # local scheduler (delivery sheets, reminders, backups)
php artisan pulse:check             # server stats for Pulse (run under Supervisor in production)
# Monitoring: /pulse (owner only) · /telescope (local only)
```
