# WBPMS — InfinityFree Deployment Guide

This guide walks through every step required to deploy WBPMS on
[InfinityFree](https://www.infinityfree.com) free hosting. Read it fully
before you start — several steps must be done **locally on your own machine**
before uploading anything.

---

## Before you begin — know the limits

InfinityFree is a shared free host. WBPMS works on it, but you need to be
aware of these constraints up front:

| Constraint | Detail |
|---|---|
| PHP version | 8.2 – 8.3 (WBPMS requires ≥ 8.2 ✓) |
| PHP file size limit | 1 MB per `.php` file — files over 1 MB are silently deleted |
| `.htaccess` size limit | 10 KB |
| Other file size limit | 10 MB |
| Document root | Always `htdocs/` — cannot be changed |
| Symlinks | Not supported |
| Shell / SSH access | Not available |
| Cron jobs | Not available |
| Background queue workers | Not available |
| MySQL host | NOT `localhost` — a specific hostname from your control panel |
| SMTP / email | No built-in server — use an external SMTP provider |
| `composer install` on server | Not possible — run it locally before uploading |
| Phinx migrations on server | Not possible — export a SQL dump locally and import via phpMyAdmin |

---

## Part 1 — Prepare files locally (do this on your own computer)

### Step 1 — Run `composer install --no-dev`

InfinityFree has no shell access, so you must produce the `vendor/` folder on
your machine before uploading.

Open a Command Prompt or PowerShell in the project root and run:

```bat
composer install --no-dev --optimize-autoloader
```

The `--no-dev` flag skips PHPUnit, PHPStan, and other dev tools — they serve
no purpose on the live server and would inflate the file count.

> **Verify the autoloader file sizes before uploading.** InfinityFree silently
> deletes PHP files larger than 1 MB. Check:
>
> ```bat
> dir vendor\composer\autoload_classmap.php vendor\composer\autoload_static.php
> ```
>
> Both files should be well under 1 MB. If either exceeds 1 MB, regenerate
> without the optimized class map:
>
> ```bat
> composer dump-autoload --no-dev --optimize=false
> ```

### Step 2 — Export the database to a SQL file

InfinityFree gives you no way to run Phinx on the server. You must generate
the complete schema (and optional seed data) locally and import it via
phpMyAdmin.

Run all migrations against your local database:

```bat
vendor\bin\phinx migrate -c phinx.php
```

Then (optional, for demo/UAT data) run the seeders:

```bat
vendor\bin\phinx seed:run -c phinx.php
```

Now export the populated database to a `.sql` file using phpMyAdmin or the
MySQL CLI:

**Option A — phpMyAdmin (easiest)**

1. Open `http://localhost/phpmyadmin`.
2. Select your `wbpms` database in the left panel.
3. Click the **Export** tab.
4. Choose **Custom** export method.
5. Under "Output", tick **Save output to a file** and set compression to
   **None** (InfinityFree's phpMyAdmin handles plain SQL best).
6. Under "Format-specific options", make sure **Add DROP TABLE** is ticked so
   a re-import is clean.
7. Click **Export**. Save the file as `wbpms_production.sql`.

**Option B — MySQL CLI**

```bat
"C:\xampp\mysql\bin\mysqldump.exe" -u root -p wbpms > wbpms_production.sql
```

Keep `wbpms_production.sql` handy — you will import it in Part 2.

### Step 3 — Create the production `.env` file

1. Find the file `.env.infinityfree` in the project root.
2. Make a copy and rename the copy to `.env`.
3. Open `.env` in a text editor. You will fill in the real values during
   Part 2, Steps 6 and 7 below.

> Do **not** upload `.env.infinityfree` — only upload the filled-in `.env`.

### Step 4 — Verify the `.htaccess` files are present

Two `.htaccess` files must exist before you upload:

| File | Purpose |
|---|---|
| `.htaccess` (project root) | Rewrites all requests into `public/` so app code and `.env` are never web-accessible |
| `public/.htaccess` | Routes non-file requests through the front controller (`index.php`) |

Both are already in the repository. Do not delete or modify them.

### Step 5 — Confirm the file exclusion list

Do **not** upload these directories or files — they either serve no purpose
on the server or would expose sensitive data:

| Path | Reason |
|---|---|
| `node_modules/` | Does not exist in this project, but skip if present |
| `tests/` | Test suite — not needed in production |
| `docs/` | Documentation — not needed in production |
| `.kiro/` | Dev tooling — not needed in production |
| `.git/` | Version control history |
| `.env.example` | Template only — upload `.env` instead |
| `.env.infinityfree` | Template only |
| `*.bat` (RUNME.bat, setup.bat, etc.) | Windows batch scripts — useless on Linux |
| `phpunit.xml`, `phpstan.neon` | Dev config only |
| `wbpms_prod.sql`, `clean_database.sql` | Raw SQL files — keep off the server |
| `diag_payroll.php`, `check_db.php`, `read_xls*.php` | Diagnostic scripts |
| `database/backups/` | Local backups |
| `nixpacks.toml` | Railway/Nixpacks config — not for InfinityFree |

---

## Part 2 — Set up your InfinityFree account

### Step 6 — Create an InfinityFree account and hosting account

1. Go to [https://www.infinityfree.com](https://www.infinityfree.com) and sign
   up for a free account.
2. After logging in, click **Create Account** on your dashboard.
3. Choose a free subdomain (e.g. `yourname.free.nf`) or connect a custom
   domain if you have one.
4. Wait for the account to be activated (usually instant to a few minutes).

### Step 7 — Create the MySQL database

1. In your InfinityFree client area, click **Hosting Accounts**, then click
   **Manage** next to your hosting account.
2. In the control panel (VistaPanel), find the **MySQL Databases** icon.
3. Click it and create a new database. Use a name like `wbpms` — the panel
   will automatically add your account prefix (e.g. `epiz_12345678_wbpms`).
4. Write down these values — you need them for `.env`:
   - **MySQL Host** (looks like `sql200.infinityfree.com`) — this is your
     `DB_HOST`. It is **not** `localhost`.
   - **Database name** (e.g. `epiz_12345678_wbpms`)
   - **Username** (e.g. `epiz_12345678`)
   - **Password** (the one you set when creating the DB)

### Step 8 — Import the SQL dump via phpMyAdmin

1. Still in VistaPanel, click the **phpMyAdmin** icon.
2. Log in with the database credentials from Step 7.
3. Select your new database in the left panel.
4. Click the **Import** tab.
5. Click **Choose File** and select `wbpms_production.sql` (from Step 2).
6. Leave all other settings at their defaults.
7. Click **Import** / **Go**.
8. Confirm that all tables were created by clicking the database name in the
   left panel — you should see 20+ tables listed.

> **Large SQL file?** If `wbpms_production.sql` exceeds InfinityFree's phpMyAdmin
> upload limit, split it using a tool like BigDump or MySQLDumper, or split the
> file manually into smaller chunks and import each one.

### Step 9 — Fill in the `.env` file

Open the `.env` file you created in Step 3 and fill in the values you
collected in Step 7:

```ini
APP_ENV=production
APP_DEBUG=false
APP_BASE_URL=https://yourname.free.nf
APP_KEY=<generate with: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;">

DB_HOST=sql200.infinityfree.com      ← exact hostname from control panel
DB_PORT=3306
DB_NAME=epiz_12345678_wbpms          ← exact name from control panel
DB_USER=epiz_12345678                ← exact username from control panel
DB_PASSWORD=yourpassword

MAIL_DRIVER=log
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@yourname.free.nf
MAIL_FROM_NAME=WBPMS
```

Generate a secure `APP_KEY` locally:

```bat
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Copy the output and paste it as the value of `APP_KEY`.

> Leave `MAIL_DRIVER=log` for now. This writes password-reset emails to
> `storage/private/mail.log` on the server instead of actually sending them.
> Once the site is working, configure a real SMTP provider (Brevo, Gmail App
> Password, etc.) and change `MAIL_DRIVER=smtp`.

---

## Part 3 — Upload files

### Step 10 — Connect to InfinityFree via FTP

1. In VistaPanel, find the **FTP Accounts** icon and note your FTP credentials:
   - **FTP Host**: shown in the panel (looks like `ftpupload.net`)
   - **Username**: your hosting account username
   - **Password**: your hosting account password
   - **Port**: 21
2. Open [FileZilla](https://filezilla-project.org/) (free) or any FTP client.
3. Connect using the credentials above.
4. Navigate to the `htdocs/` folder on the server (this is your web root).

### Step 11 — Upload the project files

Upload the following directories and files from your local project root into
the `htdocs/` folder on the server. Upload only the items listed — skip
everything in the exclusion list from Step 5.

```
htdocs/          ← FTP destination (already exists on server)
├── app/
├── bootstrap/
├── config/
├── database/
│   └── migrations/      (upload even though phinx is not run — harmless)
│   └── seeds/           (same)
├── public/
│   ├── assets/
│   ├── index.php
│   └── .htaccess        ← must be uploaded (FTP clients hide dotfiles by default!)
├── resources/
├── routes/
├── storage/
│   └── private/         ← must exist and be writable; upload the empty folder
├── vendor/
├── .env                 ← must be uploaded (dotfile — check FTP client settings)
├── .htaccess            ← the root one (dotfile — check FTP client settings)
├── composer.json
├── composer.lock
├── phinx.php
└── router.php
```

> **Dotfiles warning**: FTP clients (especially FileZilla on Windows) sometimes
> hide dotfiles. In FileZilla, go to **Server → Force showing hidden files** to
> make sure `.htaccess` and `.env` are visible and upload correctly. Verify they
> appear on the server after upload.

> **Upload speed**: InfinityFree free accounts can be slow to upload to because
> of connection throttling. The `vendor/` folder contains thousands of small
> files and can take 15–30 minutes over FTP. Be patient.

### Step 12 — Verify dotfiles arrived on the server

After uploading, use FileZilla's remote file browser to confirm these files
exist in `htdocs/`:

- `htdocs/.htaccess`
- `htdocs/.env`
- `htdocs/public/.htaccess`

If any are missing, re-upload them individually (right-click → Upload in
FileZilla).

### Step 13 — Set directory permissions

InfinityFree runs as a web server user that needs write access to
`storage/private/`. In FileZilla, right-click the `storage/private/` folder
on the server → **File permissions** → set to `755`. This allows the web
server to write session files and uploaded documents.

If you get a "Permission denied" error writing files, try `775`.

---

## Part 4 — Verify the deployment

### Step 14 — Open the site in a browser

Navigate to your InfinityFree domain:

```
https://yourname.free.nf/health
```

You should see a plain text or JSON health-check response (HTTP 200). If you
get a 404 or 500, see the Troubleshooting section below.

Then open the login page:

```
https://yourname.free.nf/login
```

You should see the WBPMS login form.

### Step 15 — Log in and verify

If you ran the demo seeders in Step 2, use these credentials to verify the
deployment:

| Role | Email | Password |
|---|---|---|
| Business Owner | `owner@demo.test` | `owner-demo-pass` |
| HR Head | `hrhead@demo.test` | `hrhead-demo-pass` |
| Employee | `employee@demo.test` | `employee-demo-pass` |

> **Change or delete all demo accounts before sharing access with real users.**
> Use HR Head → User Management to change passwords or deactivate accounts.

---

## Part 5 — After deployment checklist

- [ ] `APP_ENV=production` and `APP_DEBUG=false` in `.env`
- [ ] `APP_KEY` is a unique random value (not the example placeholder)
- [ ] Demo account passwords are changed or accounts are deactivated
- [ ] Site loads over HTTPS (InfinityFree provides free SSL — enable it in
      VistaPanel → SSL Certificates)
- [ ] `MAIL_DRIVER` switched to `smtp` with real credentials once you have
      an SMTP provider configured
- [ ] No test or diagnostic PHP files (`check_db.php`, `diag_payroll.php`,
      `read_xls.php`) were uploaded
- [ ] `storage/private/` is not directly accessible (test:
      `https://yourname.free.nf/storage/private/mail.log` should return 404)

---

## Troubleshooting

### 500 Internal Server Error on every page

The most common cause on InfinityFree is a bad `.htaccess` directive.

1. Check that the root `htdocs/.htaccess` file was uploaded and contains only
   the `RewriteEngine On` and `RewriteRule` lines — no `Options` directives.
2. Check that `public/.htaccess` does **not** contain `Options -Indexes` (the
   version in this repository already has it removed).
3. Open phpMyAdmin and confirm the database tables exist (from Step 8).
4. Verify `.env` was uploaded and all required values are filled in.

### 404 on every page including `/login`

The root `.htaccess` is either missing or the rewrite is not firing.

1. In FileZilla, confirm `htdocs/.htaccess` exists on the server.
2. Open the file on the server and confirm it starts with `RewriteEngine On`.
3. Wait a few minutes — InfinityFree sometimes has a brief propagation delay
   after uploading `.htaccess` changes.

### Database connection error

- Confirm `DB_HOST` in `.env` is the exact hostname from VistaPanel (e.g.
  `sql200.infinityfree.com`), not `localhost` or `127.0.0.1`.
- Confirm `DB_NAME`, `DB_USER`, and `DB_PASSWORD` match exactly what is shown
  in VistaPanel under MySQL Databases.
- Confirm the database tables exist (Step 8 — phpMyAdmin import).

### Login works but password reset email is never received

- By default `MAIL_DRIVER=log` — emails are written to
  `storage/private/mail.log` instead of being sent. This is intentional until
  you configure SMTP.
- To enable real email: create a free [Brevo](https://www.brevo.com/) or
  Gmail App Password SMTP account, update the `MAIL_*` variables in `.env`,
  and set `MAIL_DRIVER=smtp`.

### PHP files seem to be missing / Class not found errors

InfinityFree silently deletes PHP files larger than 1 MB. The most likely
culprit is a large Composer autoloader file. Regenerate locally without
the optimized class map:

```bat
composer dump-autoload --no-dev --optimize=false
```

Then re-upload the `vendor/composer/` directory.

### `.htaccess` or `.env` not visible in FileZilla

In FileZilla: **Server** menu → **Force showing hidden files**. Then
re-upload any missing dotfiles.

### Uploaded employee documents (XLS) fail or disappear

InfinityFree has a 10 MB limit on non-PHP files. Biometric XLS workbooks
are typically 40–50 KB so this should not be an issue. If uploads fail,
check that `storage/private/attendance/` exists on the server and has `755`
permissions.

---

## What does NOT work on InfinityFree

These WBPMS features cannot run on free hosting and require a VPS or
paid shared host if needed:

| Feature | Reason |
|---|---|
| Phinx migrations on the server | No shell access — use SQL dump import instead |
| Automated schema updates after a code change | Export new SQL dump locally, import via phpMyAdmin |
| Scheduled/background tasks | No cron jobs on InfinityFree |
| PHPUnit test suite | No shell access |

For a production payroll system handling real employee data, a paid host
with shell access (e.g. InfinityFree Premium, a DigitalOcean Droplet, or
any shared host with SSH) is strongly recommended.
