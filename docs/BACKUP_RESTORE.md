# Backup & Restore

How Dairy ERP data is backed up, how to check that backups are working, and how to restore them.
Package: [`spatie/laravel-backup`](https://spatie.be/docs/laravel-backup). Settings: `config/backup.php`.

---

## 1. What is backed up, when, and where

| Item | Details |
|---|---|
| **What** | The whole database (MySQL/MariaDB dump) + uploaded files: `public/uploads` (logos, animal photos) and `storage/app/private` (receipts, imports). Code is **not** in the backup — it lives in git. |
| **When** | Every day at **02:00 IST** (`backup:run`). Old backups are removed at **02:30** (`backup:clean`). A health check runs at **03:00** (`backup:monitor`). |
| **Where** | One zip per day in `storage/app/backups/dairy-erp/`, e.g. `2026-10-15-02-00-01.zip` (disk `backups`). |
| **How long** | **14 days.** Older zips are deleted automatically. |
| **Alerts** | If a backup or cleanup fails, or the newest backup is older than 1 day, every active **owner** gets a notification in the bell (top bar). No emails are sent. |

The schedule only runs when the Laravel scheduler runs — on the server a cron entry is required
(see `docs/DEPLOYMENT.md`):

```cron
* * * * * cd /var/www/dairy-erp && php artisan schedule:run >> /dev/null 2>&1
```

Inside each zip:

```
db-dumps/mysql-dairy_erp.sql   ← full database dump
uploads/…                      ← public/uploads
private/…                      ← storage/app/private
```

---

## 2. Settings (`.env`)

| Key | Default | Meaning |
|---|---|---|
| `DB_DUMP_BINARY_PATH` | _(empty)_ | Folder that contains `mysqldump`. Empty = found on the PATH (normal on Linux). XAMPP on Windows: `C:/xampp/mysql/bin` |
| `BACKUP_NAME` | `dairy-erp` | Folder name inside the backup disk |
| `BACKUP_DISKS` | `backups` | Comma-separated disks to copy each backup to, e.g. `backups,s3` |
| `BACKUP_PATH` | _(empty)_ | Folder for the `backups` disk. Empty = `storage/app/backups`. Point it to another drive if you like, e.g. `/mnt/backup-disk/dairy-erp` |
| `BACKUP_ARCHIVE_PASSWORD` | _(empty)_ | If set, zips are password-protected (AES-256). **Keep this password safe — without it the backup cannot be opened.** |

After changing `.env` in production run `php artisan config:cache`.

---

## 3. Everyday commands

```bash
php artisan backup:run            # make a backup now (database + files)
php artisan backup:run --only-db  # database only (quick, e.g. before a risky change)
php artisan backup:list           # list backups, newest date, size, healthy or not
php artisan backup:monitor        # run the health check now
php artisan backup:clean          # remove backups older than 14 days now
php artisan schedule:list         # confirm the 02:00 / 02:30 / 03:00 jobs are registered
```

**Before every deployment or migration**, run `php artisan backup:run --only-db`.

---

## 4. Keep a copy OFF the server (strongly recommended)

A backup on the same server is lost if the disk dies or the server is hacked. Use at least one of:

1. **Copy the folder every night** (simplest). Example with `rclone` to Google Drive / any cloud, after the
   03:00 check:
   ```cron
   30 3 * * * rclone sync /var/www/dairy-erp/storage/app/backups remote:dairy-erp-backups
   ```
2. **Second disk in Laravel.** Add an S3-compatible disk (AWS S3, DigitalOcean Spaces, Backblaze B2, Wasabi) in
   `config/filesystems.php` (the `s3` disk is already defined), install the S3 driver
   (`composer require league/flysystem-aws-s3-v3`), fill the `AWS_*` keys and set `BACKUP_DISKS=backups,s3`.
3. At least once a month, download the latest zip to a PC / pen drive.

---

## 5. Restore

> Restoring **replaces** the current data with the data from the backup. Everything entered after that backup
> is lost. Make a fresh backup first (`php artisan backup:run --only-db`) so you can undo the restore.

### 5.1 Pick and unzip the backup

```bash
cd /var/www/dairy-erp
php artisan backup:list
ls storage/app/backups/dairy-erp/
mkdir -p /tmp/restore && cd /tmp/restore
unzip /var/www/dairy-erp/storage/app/backups/dairy-erp/2026-10-15-02-00-01.zip
# password-protected zip: 7z x <file>.zip   (asks for BACKUP_ARCHIVE_PASSWORD)
```

### 5.2 Put the app in maintenance mode

```bash
cd /var/www/dairy-erp
php artisan down
```

### 5.3 Restore the database

**Linux (production):**

```bash
mysql -u dairy_user -p -e "DROP DATABASE dairy_erp; CREATE DATABASE dairy_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u dairy_user -p dairy_erp < /tmp/restore/db-dumps/mysql-dairy_erp.sql
```

**Windows / XAMPP (local):** open a terminal in the unzipped folder:

```bat
C:\xampp\mysql\bin\mysql -u root -e "DROP DATABASE dairy_erp; CREATE DATABASE dairy_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:\xampp\mysql\bin\mysql -u root dairy_erp < db-dumps\mysql-dairy_erp.sql
```

(or phpMyAdmin → database `dairy_erp` → Import → choose the `.sql` file).

### 5.4 Restore uploaded files

```bash
cd /var/www/dairy-erp
rsync -a --delete /tmp/restore/uploads/ public/uploads/
rsync -a --delete /tmp/restore/private/ storage/app/private/
chown -R www-data:www-data public/uploads storage/app/private
```

On Windows copy the `uploads` folder over `public\uploads` and the `private` folder over `storage\app\private`.

### 5.5 Bring the app back

```bash
php artisan migrate --force   # only needed if the code is newer than the backup
php artisan optimize:clear
php artisan up
rm -rf /tmp/restore
```

Log in, open a few recent records (milk entries, bills, payments) and check the totals look right.

---

## 6. Test a restore every month

A backup you have never restored is only a hope. Once a month:

1. Copy the newest zip to a test machine (or a local XAMPP).
2. Restore it into a **separate** database, e.g. `dairy_erp_restore_test` (steps 5.1 and 5.3 with that name).
3. Point a copy of the app at that database and log in; check the latest day's entries are there.
4. Delete the test database.

---

## 7. Troubleshooting

| Problem | Fix |
|---|---|
| `'mysqldump' is not recognized` / `mysqldump: not found` | Set `DB_DUMP_BINARY_PATH` to the folder containing `mysqldump` (XAMPP: `C:/xampp/mysql/bin`), then `php artisan config:clear` |
| `mariadb-dump` not found (MariaDB) | Handled automatically: if `mariadb-dump` is missing from `DB_DUMP_BINARY_PATH`, `mysqldump` is used |
| `Access denied` while dumping | The DB user needs `SELECT`, `SHOW VIEW`, `TRIGGER`, `LOCK TABLES` (and `PROCESS` on MySQL 8) |
| Bell says "There are no backups" / backup too old | The scheduler is not running: check the cron entry and `php artisan schedule:list` |
| Disk full | Backups are kept 14 days; move `BACKUP_PATH` to a bigger disk or add an off-site disk |
