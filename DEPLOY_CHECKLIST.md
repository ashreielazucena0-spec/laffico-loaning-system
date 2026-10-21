# Pre-launch checklist (already applied unless marked TODO)

- [x] `.env` set to `CI_ENVIRONMENT = production` (hides errors + debug toolbar)
- [x] Hardcoded DB root password removed from `app/Config/Database.php`; credentials now come from `.env`
- [x] Resident data (`writable/data/db.json`) and sample uploaded document removed from the package
- [x] `.gitignore` added so `.env`, logs, sessions and uploads are never committed
- [x] Insecure plaintext `secretary / secretary123` account NOT included in `barangay_secretary_CLEAN.sql`
- [ ] TODO: edit `.env` -> `app.baseURL` and the 4 `database.default.*` CHANGE_ME values
- [ ] TODO: import `barangay_secretary_CLEAN.sql` into the hosting MySQL database
- [ ] TODO: log in as `admin`, change the password immediately, then create real secretary/staff accounts
- [ ] TODO: make sure `writable/` (and subfolders) is writable by the web server (chmod 755 or 775)
- [ ] TODO: once HTTPS works, uncomment `app.forceGlobalSecureRequests = true` in `.env`

## Known limitation
CSRF protection is not enabled (forms don't include CSRF tokens). Fine for a school/demo project;
add `csrf_field()` to the forms and enable the `csrf` filter before using with real resident data.
