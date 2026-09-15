# Fresh Windows 11 Deployment with XAMPP

This guide installs WBPMS on a new Windows 11 computer for local development,
demonstration, or a private internal network. XAMPP is not a public-production
deployment platform.

## 1. Install prerequisites

1. Install [XAMPP for Windows](https://www.apachefriends.org/). Install it in
   the default `C:\xampp` location when possible.
2. Install [Composer for Windows](https://getcomposer.org/doc/00-intro.md).
   When its installer asks for the PHP executable, choose
   `C:\xampp\php\php.exe`.
3. Install Git for Windows if the project will be cloned from GitHub rather
   than copied from another computer.

Open the XAMPP Control Panel as Administrator and start **Apache** and
**MySQL**. XAMPP labels its MySQL-compatible database service as MySQL; recent
XAMPP packages ship MariaDB, which works with this application's PDO MySQL
connection.

Verify the XAMPP services at:

```text
http://localhost/dashboard/
http://localhost/phpmyadmin/
```

## 2. Check PHP extensions

Open `C:\xampp\php\php.ini` and make sure these extensions are enabled (not
prefixed with `;`):

```ini
extension=pdo_mysql
extension=mbstring
extension=zip
extension=xml
extension=gd
extension=fileinfo
```

Restart Apache after changing `php.ini`. Confirm the installed runtime from a
new PowerShell window:

```powershell
C:\xampp\php\php.exe -v
composer --version
```

WBPMS requires PHP 8.2 or later.

## 3. Obtain the source and install dependencies

Open PowerShell and run:

```powershell
cd C:\xampp\htdocs
git clone https://github.com/romarsares/WBPMS.git
cd WBPMS
composer install
```

If the project was supplied as a ZIP archive, extract it to
`C:\xampp\htdocs\WBPMS` instead, then run `composer install` in that folder.

## 4. Create the database and database account

Open phpMyAdmin, select the **SQL** tab, and run the following. Replace the
example password before executing it.

```sql
CREATE DATABASE wbpms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'wbpms_user'@'127.0.0.1'
  IDENTIFIED BY 'ChangeThisToAStrongPassword';

GRANT ALL PRIVILEGES ON wbpms.* TO 'wbpms_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

The broad database privilege is appropriate only for the local setup because
the same account runs schema migrations. A real production deployment should
use separate, least-privilege migration and application accounts.

## 5. Configure WBPMS

Create the local environment file:

```powershell
Copy-Item .env.example .env
notepad .env
```

Use these values, replacing the password and application key:

```ini
APP_ENV=development
APP_BASE_URL=http://wbpms.local
APP_KEY=replace-with-a-long-random-secret

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=wbpms
DB_USER=wbpms_user
DB_PASSWORD=ChangeThisToAStrongPassword
```

Generate an application key with:

```powershell
C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Paste the output into `APP_KEY`. Do not commit `.env` to source control.

## 6. Create the schema

From the project root, run:

```powershell
.\vendor\bin\phinx.bat migrate -c phinx.php -e development
```

For a demo or UAT database only, load the synthetic seed data:

```powershell
.\vendor\bin\phinx.bat seed:run -c phinx.php -e development
```

The full seed set creates sample users, employees, branches, and payroll data.
Never run it against a live employee/payroll database. For the seeded demo,
change the provided account passwords before sharing access.

## 7. Configure Apache to expose only `public/`

WBPMS is a front-controller application. Apache must use `public/` as its
document root; the repository root must not be web-accessible because it holds
`.env`, source files, and private uploads.

Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf` and add:

```apache
<VirtualHost *:80>
    ServerName wbpms.local
    DocumentRoot "C:/xampp/htdocs/WBPMS/public"

    <Directory "C:/xampp/htdocs/WBPMS/public">
        Require all granted
        AllowOverride None
        Options FollowSymLinks
        FallbackResource /index.php
    </Directory>

    ErrorLog "logs/wbpms-error.log"
    CustomLog "logs/wbpms-access.log" combined
</VirtualHost>
```

If `httpd.conf` does not already include the virtual-host file, uncomment or
add this line in `C:\xampp\apache\conf\httpd.conf`:

```apache
Include conf/extra/httpd-vhosts.conf
```

Then open Notepad as Administrator and add this entry to
`C:\Windows\System32\drivers\etc\hosts`:

```text
127.0.0.1 wbpms.local
```

Restart Apache from the XAMPP Control Panel. `FallbackResource /index.php` is
required because this repository does not include an `.htaccess` rewrite file;
it routes application URLs such as `/login` and `/hr/attendance` to the front
controller while still serving real files from `public/assets`.

## 8. Verify the installation

Open:

```text
http://wbpms.local/health
http://wbpms.local/login
```

Run the automated test suite from the project root:

```powershell
composer test
```

For a demo database, use the demo credentials listed in the repository
README. They must not be used in a shared or production environment.

## Troubleshooting

| Problem | Check |
| --- | --- |
| Apache will not start | Port 80 may be held by IIS, Skype, Docker, or another web server. Stop the conflicting service or change Apache's port and update `APP_BASE_URL`. |
| Database connection error | Confirm MySQL is running, the `.env` credentials match the SQL user, and `DB_HOST` is `127.0.0.1`. |
| `composer` is not recognized | Close and reopen PowerShell after installing Composer; confirm its installer used `C:\xampp\php\php.exe`. |
| Routes return 404 | Confirm the virtual host points to `.../WBPMS/public`, the hosts-file entry is present, and Apache was restarted. |
| Attendance/document upload fails | Ensure Apache can write to `storage/private/`; do not move that folder under `public/`. |
| Blank page or missing PHP module | Review `C:\xampp\apache\logs\error.log`, enable the required PHP extensions, and restart Apache. |

## Moving beyond a local XAMPP setup

Before a public or live payroll deployment, use a supported Apache/PHP/MySQL
server, set `APP_ENV=production`, use HTTPS, remove demo data, create real
least-privilege database credentials, back up the database and
`storage/private/`, and complete HR acceptance testing of statutory rates and
payroll rules.
