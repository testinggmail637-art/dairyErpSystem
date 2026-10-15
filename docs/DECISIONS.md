# Decisions Log

> Claude Code: add one row for every assumption or choice not explicitly covered by `docs/DAIRY_ERP_SPEC.md`.
> Newest at the bottom. Never delete rows; if a decision is reversed, add a new row that references the old one.

| # | Date (IST) | Phase · Task | Question | Decision | Reason |
|---|---|---|---|---|---|
| 1 | 2026-10-15 | Planning | Database | MySQL 8 | Fixed decision in spec Section 2 |
| 2 | 2026-10-15 | Planning | Frontend build | No Vite/npm; libraries in `public/vendor/` | Fixed decision in spec Section 2 |
| 3 | 2026-10-15 | Planning | Expense approval | Not built in v1 | Owner's instruction |
| 4 | 2026-10-15 | Planning | Git commits & phase tags (CLAUDE.md rule 4, spec Section 8) | No `git init`, commits or tags until the owner asks; everything else in "done" still applies | Owner's instruction |
| 5 | 2026-10-15 | Planning | PHP version on local XAMPP | Upgraded to PHP 8.4.26 (TS, VS17); `composer.json` requires `^8.3` | Spec needs PHP 8.3+; Apache build is VS17 |
| 6 | 2026-10-15 | Phase 0 · Task 1 | Package not in spec Section 3 | Added `pestphp/pest-plugin-laravel` (dev) | Pest's official Laravel plugin, needed for Laravel test helpers (`actingAs`, `get`, `artisan`) in Pest syntax |
| 7 | 2026-10-15 | Phase 0 · Task 1 | Local DB is XAMPP MariaDB 10.4, spec says MySQL 8 | Local `.env` uses `DB_CONNECTION=mariadb`; production `.env.example` uses `mysql`. Write only SQL that runs on both (no MySQL-8-only features such as functional indexes or `JSON_TABLE`) | Owner's local stack is XAMPP; Laravel supports both drivers |
| 8 | 2026-10-15 | Phase 0 · Task 1 | `users` table shape | Edited the default (never-run) users migration: `email` and `phone` both nullable + unique, `is_active`, `last_login_at`, `preferences` json; dropped `email_verified_at` and `password_reset_tokens` | Spec: login by email or phone, no email verification/reset in v1 |
| 9 | 2026-10-15 | Phase 0 · Task 1 | Phone format for login | Phones stored as digits only; `+91`/leading `0` stripped (`User::normalizePhone`) so any common format logs in | Staff type numbers in many formats |
| 10 | 2026-10-15 | Phase 0 · Task 1 | Login rate limit | 5 failed attempts per login+IP → lockout (`LoginRequest`), plus 20 requests/min per IP on the route (`throttle:login`) | Spec says "rate-limited" without numbers |
| 11 | 2026-10-15 | Phase 0 · Task 1 | Owner seed values | `config/dairy.php` reads `OWNER_*` env; seeder refuses the local defaults in production | `env()` is unavailable once config is cached |
| 12 | 2026-10-15 | Phase 0 · Task 1 | `APP_URL` in tests | `.env.testing` uses `http://localhost` (local `.env` points to `/dairyErpSystem/public`) | Sub-folder URL breaks route URLs in HTTP tests |
| 13 | 2026-10-15 | Phase 0 · Task 2 | How the sidebar is filtered | Menu defined in `config/menu.php`; an item shows only if its route exists **and** the user `can()` its permission, so modules appear automatically as they are built. Until roles exist (Task 0.5) the menu is empty for everyone | Spec: "module menu filtered by permission"; avoids dead links |
| 14 | 2026-10-15 | Phase 0 · Task 2 | Own profile page (spec puts it in Task 0.1) | `/profile`: change own name and password (current password required; other sessions signed out). Email/phone/role/active only via Settings → Users | Spec 0.1 "every user can change their own password from the profile page" |
| 15 | 2026-10-15 | Phase 0 · Task 2 | Notifications bell before reminders exist | Laravel `notifications` table now; bell shows unread count + latest 5, list page, open (marks read, follows internal `url` only), mark all read. Data keys: `message`, `url`, `icon` | Bell is part of the base layout; reminders (Phase 5) only need to create notifications |
| 16 | 2026-10-15 | Phase 0 · Task 2 | Display formatting | `App\Support\Format` (+ `inr()`, `fmt_qty()`, `fmt_date()`, `fmt_datetime()` helpers) with bcmath round-half-up; JS `App.formatMoney` uses BigInt to give identical results | Spec: ₹1,25,000.00 and DD-MM-YYYY everywhere, no floats |
| 17 | 2026-10-15 | Planning | Git (supersedes #4) | Project has its own repo inside `dairyErpSystem` (branch `main`), remote `git@github.com-me:testinggmail637-art/dairyErpSystem.git` on the owner's personal GitHub; author identity set per folder via `includeIf` | Owner gave the personal repo; the old repo at `C:\xampp\htdocs` covers other sites |
