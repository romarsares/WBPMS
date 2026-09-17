# WBPMS — Deployment Guide

This `build/` folder is the clean, production-ready copy of the
Web-Based Payroll Management System (WBPMS). It contains only production
source files and production Composer dependencies. Dev tools (PHPUnit,
PHPStan), tests, docs, and development scaffolding are excluded.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.2 or 8.5 |
| MySQL | 8.0+ (8.4 LTS recommended) |
| PHP extensions | `pdo_mysql`, `mbstring`, `zip`, `gd` or `imagick` (for PhpSpreadsheet) |
| Web server | Apache 2.4+ with `mod_rewrite`, or Nginx with try_files rewrite |
| Composer | 2.x (only needed if you re-run `composer install`) |

---

## Folder structure

```
build/
├── app/                  PHP application (controllers, services, domain, infra)
├── bootstrap/            App bootstrap (env load, session, router init)
├── config/               Database and app config files
├── database/
│   ├── migrations/       Phinx migration files
│   └── seeds/            Demo/reference data seeders
├── public/               WEB ROOT — point your server here
│   ├── index.php         Front controller (sole entry point)
│   ├── assets/           CSS, images
│   └── .htaccess         Apache rewrite rules
├── resources/views/      PHP templates
├── routes/web.php        Explicit route table
├── storage/private/      Runtime uploads and generated files (writable)
├── vendor/               Production Composer dependencies (no dev packages)
├── phinx.php             Phinx CLI config
├── composer.json         Production-only dependencies
├── composer.lock         Locked dependency versions
├── .env.example          Environment variable template
└── BUILD-README.md       This file
```

---

## Deployment steps

### 1. Copy files to your server

Upload the entire `build/` folder contents to your server's deployment
directory (e.g. `/var/www/wbpms`). Do **not** expose the root; only
`public/` should be web-accessible.

### 2. Create the MySQL database

```sql
CREATE DATABASE wbpms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'wbpms_user'@'localhost' IDENTIFIED BY 'strong-password-here';
GRANT ALL PRIVILEGES ON wbpms.* TO 'wbpms_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Configure environment variables

```bash
cp .env.example .env
```

Edit `.env` and set:
- `APP_ENV=production`
- `APP_BASE_URL` — your public URL (e.g. `https://payroll.lightdiamond.com`)
- `APP_KEY` — generate with: `php -r "echo bin2hex(random_bytes(32));"`
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`

### 4. Set directory permissions

```bash
# The storage directory must be writable by the web server process
chmod -R 775 storage/
chown -R www-data:www-data storage/
```

On Windows/XAMPP: right-click `storage/` → Properties → Security →
give `IIS_IUSRS` or `NETWORK SERVICE` write permission.

### 5. Run database migrations

```bash
php vendor/bin/phinx migrate -c phinx.php -e production
```

### 6. (Optional) Seed demo data

> ⚠️ **Development/testing only.** Do NOT run on a production database that
> already has real employee data.

```bash
php vendor/bin/phinx seed:run -c phinx.php -e production
```

Demo accounts (remove or change passwords before real use):

| Role | Username | Password |
|---|---|---|
| Business Owner | `owner` | `owner-demo-pass` |
| HR Head | `hrhead` | `hrhead-demo-pass` |
| Employee | `employee` | `employee-demo-pass` |

### 7. Configure your web server

#### Apache (`public/.htaccess` is already included)

Point `DocumentRoot` to the `public/` subdirectory:

```apache
<VirtualHost *:80>
    ServerName payroll.lightdiamond.com
    DocumentRoot /var/www/wbpms/public

    <Directory /var/www/wbpms/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### XAMPP (Windows local)

In `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    ServerName wbpms.local
    DocumentRoot "C:/xampp/htdocs/wbpms/build/public"
    <Directory "C:/xampp/htdocs/wbpms/build/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add `127.0.0.1 wbpms.local` to `C:\Windows\System32\drivers\etc\hosts`,
then restart Apache.

#### Nginx

```nginx
server {
    listen 80;
    server_name payroll.lightdiamond.com;
    root /var/www/wbpms/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 8. PHP built-in server (quick local test only)

```bash
php -S localhost:8000 -t public/
```

---

## Post-deployment checklist

- [ ] `.env` file exists and all variables are set
- [ ] `APP_ENV=production` (hides error traces from users)
- [ ] `APP_KEY` is a unique 32+ char secret (not the example value)
- [ ] Database credentials are production-specific
- [ ] `storage/` directory is writable by the web server
- [ ] Migrations have been run (`phinx migrate`)
- [ ] Demo accounts changed or removed before real staff use
- [ ] HTTPS configured (recommended for production)
- [ ] `public/` is the only directory exposed via the web server

---

## Updating

To deploy a new version:
1. Back up your database.
2. Replace source files (keep your `.env`).
3. Run `php vendor/bin/phinx migrate -c phinx.php -e production` to apply
   any new migrations.

---

## Support

Refer to the project repository's `docs/` folder for the full design
documents, ADRs, and canonical database schema.
