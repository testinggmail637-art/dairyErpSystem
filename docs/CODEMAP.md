# CODEMAP — where things are (read this instead of exploring the code)

Keep this file short. Update it in the same commit when you add a new **shared** building block
(helper, trait, component, base pattern) — not for every new controller/model.

## Reference module — copy its pattern for any new CRUD screen
**Expenses** is the canonical example. For a new module, open only the matching expense file:

| Layer | Example file |
|---|---|
| Route (+ `can:` middleware) | `routes/web.php` → `Route::prefix('expenses')` block |
| Controller (thin, uses `listView`) | `app/Http/Controllers/Expenses/ExpenseController.php` |
| Form Request / index filters | `app/Http/Requests/Expenses/ExpenseRequest.php`, `ExpenseIndexRequest.php` |
| Delete with reason | `app/Http/Requests/Expenses/DeleteExpenseRequest.php` (uses `Requests/Concerns/RequiresReason`) |
| Service (business logic, `DB::transaction`) | `app/Services/ExpenseService.php` |
| Policy | `app/Policies/ExpensePolicy.php` (+ `Policies/Concerns`) |
| Model | `app/Models/Expense.php` |
| Views: list / form / show | `resources/views/expenses/expenses/{index,form,show}.blade.php` |
| Feature test | `tests/Feature/Expenses/ExpenseTest.php`, edit rules: `ExpenseEditRulesTest.php` |
| Report (SQL totals + export) | `app/Services/ExpenseReportService.php`, `Controllers/Reports/ExpenseReportController.php` |
| Excel import (chunked, queued) | `app/Services/ExpenseImportService.php`, `app/Imports`, `app/Jobs` |

## Shared building blocks (use these, don't re-create)
- **Model traits** `app/Models/Concerns/`: `BelongsToFarm` (farm scope + auto-fill, scope in `Models/Scopes/FarmScope`),
  `Auditable` (audit log with reason), `TracksUser` (created_by/updated_by), `HasFarmDefaults`.
- **Enums** `app/Enums/*` use `Enums/Concerns/HasOptions` for select options.
- **Money/qty math** `app/Support/Money.php`: `add sub mul div sum round compare isZero isNegative format normalize` (bcmath strings).
- **Display** `app/Support/Format.php` + `app/Support/helpers.php`: `inr()`, `fmt_qty()`, `fmt_date()`, `fmt_datetime()`, `asset_v()`.
- **Current farm** `app/Support/CurrentFarm.php` (`id()`, `farm()`).
- **Audit reason** `app/Support/AuditReason.php`; Form Requests use `RequiresReason`.
- **Numbering** `app/Services/DocumentNumberService.php` + `Enums/DocumentType`.
- **Financial year** `app/Support/FinancialYear.php`. **Permissions list** `app/Support/Permissions.php`. **Menu** `app/Support/Menu.php`.
- **Exports** `app/Support/Exporter.php::download()` (queues big ones), `app/Reports/ReportTable.php` (report table + PDF/Excel).
- **Milk stock** models moving milk implement `app/Contracts/MovesMilkStock.php`; ledger in `Services/MilkStockService.php`.
- **AJAX lists** `Controller::listView($request, $view, $data)` + `@fragment('results')` + `data-ajax-list` (see CLAUDE.md).
- **Blade components** `resources/views/components/`: `data-table`, `confirm-form`, `money`,
  `form/{input,select,date,money,quantity,textarea,switch,reason,group}`.
- **Layout** `resources/views/layouts/`; PDF templates `resources/views/pdf/`.
- **Tests** query-count guard: `tests/Feature/Performance/QueryCountTest.php`; phase acceptance: `tests/Feature/Phase<N>AcceptanceTest.php`.

## Modules done so far
Phase 0 foundation (auth, farms, roles, settings, appearance, audit) · Phase 1 expenses, vendors, vendor payments, import ·
Phase 2 animals, breeds, milk production, milk stock, milk reports, milk-drop alerts.
