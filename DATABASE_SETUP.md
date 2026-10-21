# Connecting the app to your MySQL database

This project used to store everything in a flat JSON file
(`writable/data/db.json`, via `App\Libraries\DataStore`). It now talks to a
real MySQL/MariaDB database instead. Nothing in the Controllers or Views
changed — `DataStore` still has the exact same methods
(`all`, `find`, `insert`, `update`, `delete`, `count`, `log`, `export`,
`import`, `reset`), they just run SQL now.

## What changed

1. **`barangay_secretary_database.sql`** — your uploaded schema, with one bug
   fixed: `activity_logs.time` was typed `DATETIME` but the data uses
   12-hour `AM/PM` strings (e.g. `2026-09-01 06:58 AM`), which MySQL
   rejects. It's now `VARCHAR(30)`, since the app only ever displays this
   value as text — it never parses it as a real date.
2. **`app/Libraries/DataStore.php`** — rewritten to run on MySQL via
   CodeIgniter's Query Builder instead of reading/writing the JSON file.
3. **`app/Controllers/Barangay.php`** — the delete action now shows a
   friendly "this record is still linked elsewhere" message instead of a
   fatal error when you try to delete a resident who's still referenced by
   a household/certificate/request/case/payment (the schema enforces this
   with real foreign keys now — the JSON version silently allowed it and
   left broken references behind).
4. **`app/Config/Database.php` / `.env`** — default connection set to
   `localhost` / `root` / *(no password)* / database `barangay_secretary`,
   the standard XAMPP defaults.

## One-time setup

1. **Import the database.** In phpMyAdmin (or `mysql -u root < barangay_secretary_database.sql`),
   run the included `barangay_secretary_database.sql`. It creates the
   `barangay_secretary` database and all 11 tables, with your existing data
   (3 residents, 3 users, certificates, requests, etc.).
2. **Check your DB credentials.** If your MySQL isn't XAMPP-default
   (root / no password), edit the `database.default.*` lines near the
   bottom of `.env`, or `app/Config/Database.php`.
3. **Drop the project into `htdocs`** (or wherever you serve it) and open
   it — login page first. Existing accounts from your data still work,
   e.g. `wailhi` / `maogani`.

## Notes

- `writable/data/db.json` is no longer used — I removed it. If you ever
  want to go back to the JSON version, restore the DataStore.php from your
  git history and delete/rename the MySQL one.
- **Backup & Restore** (Settings → Backup) still works — Export downloads a
  JSON snapshot of every table, Import restores from one, and Reset wipes
  everything and reloads the small built-in demo dataset (3 sample
  residents) rather than your real data.
- I tested login, registration, every module's add/edit/delete, certificate
  printing, user activation toggling, activity logs, and backup
  export/import/reset against a live MySQL instance before packaging this.

---

## Resident Portal update (run this after the steps above)

1. In phpMyAdmin, select `barangay_secretary` and import **`migration_resident_portal.sql`** (once).
   Your existing data is kept.
2. Upload folder `writable/uploads/documents` is created automatically; make sure `writable/` is writable.
3. Log in as admin/secretary, open **Residents** and press the key icon on a resident
   to create that resident's login (new residents get one automatically).
   The username + temporary password appear once on screen - give them to the resident.

4. Then import **`migration_receipts.sql`** (once). It links payments to requests so releasing a certificate records the payment and enables the official receipt.
