# WBPMS — Client Setup Guide

Follow these steps in order. The whole process takes about 10–15 minutes on a fresh machine.

---

## 1. Prerequisites

Install these tools before you start. Each link goes to the official download page.

| Tool | Version | Download |
|---|---|---|
| XAMPP | 8.x (includes Apache, MySQL, PHP 8.x) | https://www.apachefriends.org/download.html |
| Composer | 2.x | https://getcomposer.org/download/ |

> **PHP version note:** The project requires PHP 8.1 or higher. XAMPP 8.x bundles PHP 8.1–8.2.
> Verify after installation: open a command prompt and run `php -v`.

---

## 2. Place the project files

Copy the entire `wbpms` folder into:

```
C:\xampp\htdocs\wbpms\
```

Your folder structure should look like this when done:

```
C:\xampp\htdocs\wbpms\
    app\
    bootstrap\
    config\
    database\
    docs\
    public\
    resources\
    routes\
    storage\
    tests\
    vendor\           ← must be present (included in the delivery package)
    composer.json
    phinx.php
    .env.example
    RUNME.bat
    ...
```

> **Important:** The `vendor\` folder is already included in the delivery package.
> You do NOT need to run `composer install` unless `vendor\` is missing.
> If it is missing, see Step 3a below.

---

## 3. Install PHP dependencies (only if `vendor\` folder is missing)

If the `vendor\` folder was not included or got deleted:

1. Open a **Command Prompt** (press `Win + R`, type `cmd`, press Enter).
2. Navigate to the project folder:
   ```bat
   cd C:\xampp\htdocs\wbpms
   ```
3. Run:
   ```bat
   composer install
   ```

This downloads all required libraries into the `vendor\` folder. It requires an internet connection.

---

## 4. Create the environment configuration file

1. In the project folder, find the file named `.env.example`.
2. Make a copy of it and rename the copy to `.env` (no `.example` extension).
3. Open `.env` with Notepad or any text editor.
4. Fill in your database credentials (see Step 5 for how to create the database):

```ini
# Application
APP_ENV=development
APP_BASE_URL=http://localhost/wbpms/public
APP_KEY=change-this-to-any-random-32-character-string

# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=wbpms
DB_USER=root
DB_PASSWORD=          ← your MySQL root password (blank if you never set one)

# Test database (only needed if you run automated tests)
TEST_DB_HOST=127.0.0.1
TEST_DB_PORT=3306
TEST_DB_NAME=wbpms_test
TEST_DB_USER=root
TEST_DB_PASSWORD=     ← same as DB_PASSWORD
```

> **APP_KEY:** Generate any random string of at least 32 characters, for example:
> `wbpms-prod-key-AbCdEfGhIjKlMnOpQrStUv12`
> This key is used for security. Change it from the default before going live.

---

## 5. Create the MySQL database

### Option A — Using phpMyAdmin (easiest)

1. Start XAMPP and turn on **Apache** and **MySQL**.
2. Open your browser and go to: `http://localhost/phpmyadmin`
3. Click **New** in the left panel.
4. Type `wbpms` as the database name.
5. Set collation to `utf8mb4_unicode_ci`.
6. Click **Create**.

### Option B — Using the MySQL command line

1. Open a Command Prompt.
2. Run:
   ```bat
   "C:\xampp\mysql\bin\mysql.exe" -u root -p
   ```
3. Enter your MySQL root password when prompted (press Enter if blank).
4. Run this SQL command:
   ```sql
   CREATE DATABASE wbpms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   EXIT;
   ```

---

## 6. Run database migrations (create all tables)

1. Open a Command Prompt.
2. Navigate to the project folder:
   ```bat
   cd C:\xampp\htdocs\wbpms
   ```
3. Run the migrations:
   ```bat
   vendor\bin\phinx migrate -c phinx.php
   ```

You should see output like:

```
Phinx by CakePHP - https://phinx.org.
...
using migration paths
...
== 20260831000001 CreateIdentityAccessTables: migrating
== 20260831000001 CreateIdentityAccessTables: migrated 0.1234s
...
All Done. Took 2.3456s
```

This creates all the database tables automatically.

---

## 7. Load demo/initial data (seeders)

After migrations, run the seeders to populate reference data and demo accounts:

```bat
vendor\bin\phinx seed:run -c phinx.php
```

This creates:
- The three application roles (BusinessOwner, HRHead, Employee)
- Leave/request types
- Demo branches
- Demo employees
- Government contribution brackets (SSS, PhilHealth, Pag-IBIG)
- Three demo login accounts (see below)

---

## 8. Start the application

### Using RUNME.bat (recommended for first-time use)

Double-click `RUNME.bat` in the project folder. It will:
1. Open XAMPP Control Panel
2. Open the login page in your browser

### Manual start

1. Open **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`).
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Open your browser and go to:
   ```
   http://localhost/wbpms/public/login
   ```

---

## 9. Demo login accounts

Use these accounts to log in and explore the system.
**Change or delete these before going live.**

| Role | Username | Password |
|---|---|---|
| Business Owner | `owner` | `owner-demo-pass` |
| HR Head | `hrhead` | `hrhead-demo-pass` |
| Employee | `employee` | `employee-demo-pass` |

---

## 10. Troubleshooting

### "Page not found" or blank page
- Make sure Apache is running in XAMPP.
- Make sure the project is in `C:\xampp\htdocs\wbpms\` (not inside a subfolder like `wbpms\wbpms\`).
- Check `C:\xampp\apache\logs\error.log` for PHP errors.

### Database connection error
- Make sure MySQL is running in XAMPP.
- Double-check the `DB_PASSWORD` in your `.env` file matches your MySQL root password.
- If you never set a MySQL password in XAMPP, leave `DB_PASSWORD=` blank (nothing after the equals sign).

### "vendor not found" / Composer errors
- Run `composer install` from inside `C:\xampp\htdocs\wbpms\` (see Step 3).
- Make sure you have internet access and Composer is installed.

### Migration errors ("table already exists")
- The database may have been partially migrated. Run:
  ```bat
  vendor\bin\phinx status -c phinx.php
  ```
  to see which migrations ran. If needed, drop and recreate the database (Step 5) and re-run migrations.

### White screen / 500 error
- Open `C:\xampp\apache\logs\error.log` and look at the last few lines for the PHP error message.
- Make sure `.env` exists and is filled in (not `.env.example`).

---

## Quick-reference command summary

Run all of these from `C:\xampp\htdocs\wbpms\` in a Command Prompt:

```bat
REM 1. Install dependencies (only if vendor\ is missing)
composer install

REM 2. Run all database migrations
vendor\bin\phinx migrate -c phinx.php

REM 3. Load demo data
vendor\bin\phinx seed:run -c phinx.php

REM 4. (Optional) Start PHP's built-in dev server instead of XAMPP Apache
php -S localhost:8000 -t public/
REM Then visit: http://localhost:8000/login
```

---

## Production checklist (before going live)

- [ ] Change `APP_KEY` to a unique random string.
- [ ] Set `APP_ENV=production` in `.env`.
- [ ] Set a strong `DB_PASSWORD`.
- [ ] Delete or change the demo accounts (`owner`, `hrhead`, `employee`).
- [ ] Make sure `storage\private\` is not publicly accessible (the `.htaccess` handles this for Apache).
- [ ] Use HTTPS (configure SSL in XAMPP or your web host's control panel).
