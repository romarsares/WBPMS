@echo off
setlocal EnableDelayedExpansion
title WBPMS Setup

:: ============================================================
::  WBPMS — One-Time Setup Script
::  Run this ONCE from the project root before first use.
::  Do NOT run as Administrator unless absolutely required.
:: ============================================================

:: Resolve the directory this script lives in (project root).
:: Works regardless of where the user double-clicks it from.
set "PROJECT_ROOT=%~dp0"
:: Remove trailing backslash
if "%PROJECT_ROOT:~-1%"=="\" set "PROJECT_ROOT=%PROJECT_ROOT:~0,-1%"

echo.
echo ============================================================
echo   WBPMS — Web-Based Payroll Management System
echo   One-Time Setup
echo ============================================================
echo   Project root : %PROJECT_ROOT%
echo ============================================================
echo.

:: ============================================================
:: STEP 1 — Locate XAMPP
:: ============================================================
echo [1/8] Checking for XAMPP...

:: Prefer the drive that contains this project file.
set "PROJECT_DRIVE=%PROJECT_ROOT:~0,2%"

:: Search order: same drive as project, then C:\, then D:\
set "XAMPP_ROOT="
for %%D in ("%PROJECT_DRIVE%" "C:" "D:") do (
    if exist "%%~D\xampp\xampp-control.exe" (
        if "!XAMPP_ROOT!"=="" set "XAMPP_ROOT=%%~D\xampp"
    )
)

if "!XAMPP_ROOT!"=="" (
    echo.
    echo  ERROR: XAMPP not found.
    echo.
    echo  WBPMS requires XAMPP to provide Apache and MySQL.
    echo  Please download and install XAMPP 8.x from:
    echo    https://www.apachefriends.org/download.html
    echo.
    echo  Install to the default location  C:\xampp\
    echo  then re-run this setup script.
    echo.
    goto :FAIL
)

echo  FOUND: !XAMPP_ROOT!

:: Derive key XAMPP paths
set "MYSQL_BIN=!XAMPP_ROOT!\mysql\bin\mysql.exe"
set "MYSQLADMIN_BIN=!XAMPP_ROOT!\mysql\bin\mysqladmin.exe"
set "PHP_BIN=!XAMPP_ROOT!\php\php.exe"
set "APACHE_BIN=!XAMPP_ROOT!\apache\bin\httpd.exe"
set "XAMPP_NET=!XAMPP_ROOT!\xampp_start.exe"

:: ============================================================
:: STEP 2 — Check PHP version
:: ============================================================
echo.
echo [2/8] Checking PHP...

if not exist "!PHP_BIN!" (
    echo.
    echo  ERROR: PHP not found at !PHP_BIN!
    echo  Your XAMPP installation may be incomplete.
    echo  Re-install XAMPP 8.x from https://www.apachefriends.org/
    echo.
    goto :FAIL
)

:: Get the version string, e.g. "PHP 8.2.12"
for /f "tokens=2 delims= " %%V in ('"!PHP_BIN!" -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2^>nul') do set "PHP_VER=%%V"

:: Parse major.minor
for /f "tokens=1,2 delims=." %%A in ("!PHP_VER!") do (
    set "PHP_MAJOR=%%A"
    set "PHP_MINOR=%%B"
)

echo  Found PHP !PHP_VER!

:: Require PHP 8.1+
if !PHP_MAJOR! LSS 8 (
    echo.
    echo  ERROR: PHP !PHP_VER! is too old. WBPMS requires PHP 8.1 or higher.
    echo  Please upgrade XAMPP to version 8.x.
    echo.
    goto :FAIL
)
if !PHP_MAJOR! EQU 8 if !PHP_MINOR! LSS 1 (
    echo.
    echo  ERROR: PHP !PHP_VER! is too old. WBPMS requires PHP 8.1 or higher.
    echo  Please upgrade XAMPP to version 8.x.
    echo.
    goto :FAIL
)

:: Warn if below 8.4 (Pdo\Mysql namespaced constant used in config/database.php)
if !PHP_MAJOR! EQU 8 if !PHP_MINOR! LSS 4 (
    echo.
    echo  WARNING: PHP !PHP_VER! detected.
    echo  WBPMS uses the Pdo\Mysql namespaced PDO constant introduced in PHP 8.4.
    echo  The application may throw errors at runtime on PHP versions below 8.4.
    echo  Recommended: upgrade XAMPP to a version that bundles PHP 8.4+.
    echo  Continuing setup, but please note this limitation.
    echo.
)

:: ============================================================
:: STEP 3 — Start Apache and MySQL if not already running
:: ============================================================
echo.
echo [3/8] Checking Apache and MySQL services...

:: --- MySQL ---
"!MYSQLADMIN_BIN!" -u root --connect-timeout=3 ping >nul 2>&1
if errorlevel 1 (
    echo  MySQL is not running. Attempting to start...
    :: Try net start first (if installed as a service), then xampp_start
    net start mysql >nul 2>&1
    if errorlevel 1 (
        :: Fall back to xampp's own start mechanism
        if exist "!XAMPP_ROOT!\mysql\bin\mysqld.exe" (
            start /B "!XAMPP_ROOT!\mysql\bin\mysqld.exe" --defaults-file="!XAMPP_ROOT!\mysql\bin\my.ini" >nul 2>&1
        )
    )
    echo  Waiting for MySQL to start...
    set "MYSQL_WAIT=0"
    :MYSQL_WAIT_LOOP
    timeout /t 2 /nobreak >nul
    "!MYSQLADMIN_BIN!" -u root --connect-timeout=3 ping >nul 2>&1
    if not errorlevel 1 goto :MYSQL_STARTED
    set /a MYSQL_WAIT+=1
    if !MYSQL_WAIT! GEQ 10 (
        echo.
        echo  ERROR: MySQL did not start within 20 seconds.
        echo.
        echo  Please start MySQL manually:
        echo    1. Open XAMPP Control Panel: !XAMPP_ROOT!\xampp-control.exe
        echo    2. Click "Start" next to MySQL.
        echo    3. Re-run this setup script.
        echo.
        goto :FAIL
    )
    goto :MYSQL_WAIT_LOOP
    :MYSQL_STARTED
    echo  MySQL is now running.
) else (
    echo  MySQL is already running.
)

:: --- Apache ---
tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
if errorlevel 1 (
    echo  Apache is not running. Attempting to start...
    net start apache >nul 2>&1
    if errorlevel 1 (
        net start apache2.4 >nul 2>&1
    )
    if errorlevel 1 (
        if exist "!APACHE_BIN!" (
            start /B "!APACHE_BIN!" -k start >nul 2>&1
        )
    )
    :: Give Apache a moment
    timeout /t 3 /nobreak >nul
    tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
    if errorlevel 1 (
        echo.
        echo  WARNING: Could not confirm Apache started automatically.
        echo  Please open XAMPP Control Panel and start Apache manually,
        echo  then re-run this script if the setup does not complete.
        echo.
    ) else (
        echo  Apache is now running.
    )
) else (
    echo  Apache is already running.
)

:: ============================================================
:: STEP 4 — Read credentials from .env (create from template if missing)
:: ============================================================
echo.
echo [4/8] Checking environment configuration (.env)...

set "ENV_FILE=%PROJECT_ROOT%\.env"
set "ENV_EXAMPLE=%PROJECT_ROOT%\.env.example"

if not exist "!ENV_FILE!" (
    if not exist "!ENV_EXAMPLE!" (
        echo.
        echo  ERROR: Neither .env nor .env.example found in %PROJECT_ROOT%
        echo  The project files may be incomplete. Please re-copy the project folder.
        echo.
        goto :FAIL
    )
    echo  .env not found. Creating from .env.example...
    copy "!ENV_EXAMPLE!" "!ENV_FILE!" >nul
    echo  Created .env from template.
    echo.
    echo  IMPORTANT: The default password in .env.example is a placeholder.
    echo  If your MySQL root password is different, edit .env before continuing.
    echo  File: !ENV_FILE!
    echo.
    pause
)

:: Parse .env for the values we need (skip comment lines)
set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"
set "DB_NAME=wbpms"
set "DB_USER=root"
set "DB_PASSWORD="
set "APP_BASE_URL=http://localhost/wbpms/public"

for /f "usebackq eol=# tokens=1,* delims==" %%K in ("!ENV_FILE!") do (
    set "KEY=%%K"
    set "VAL=%%L"
    :: Trim leading/trailing spaces from key
    for /f "tokens=*" %%T in ("!KEY!") do set "KEY=%%T"
    if /I "!KEY!"=="DB_HOST"       set "DB_HOST=!VAL!"
    if /I "!KEY!"=="DB_PORT"       set "DB_PORT=!VAL!"
    if /I "!KEY!"=="DB_NAME"       set "DB_NAME=!VAL!"
    if /I "!KEY!"=="DB_USER"       set "DB_USER=!VAL!"
    if /I "!KEY!"=="DB_PASSWORD"   set "DB_PASSWORD=!VAL!"
    if /I "!KEY!"=="APP_BASE_URL"  set "APP_BASE_URL=!VAL!"
)

echo  DB_HOST     : !DB_HOST!
echo  DB_PORT     : !DB_PORT!
echo  DB_NAME     : !DB_NAME!
echo  DB_USER     : !DB_USER!
echo  APP_BASE_URL: !APP_BASE_URL!

:: ============================================================
:: STEP 5 — Verify database connection and create DB if missing
:: ============================================================
echo.
echo [5/8] Verifying database connection and database...

:: Build mysql command base (handle blank password)
if "!DB_PASSWORD!"=="" (
    set "MYSQL_CMD=!MYSQL_BIN! -h !DB_HOST! -P !DB_PORT! -u !DB_USER!"
) else (
    set "MYSQL_CMD=!MYSQL_BIN! -h !DB_HOST! -P !DB_PORT! -u !DB_USER! -p!DB_PASSWORD!"
)

:: Test connection
!MYSQL_CMD! -e "SELECT 1;" >nul 2>&1
if errorlevel 1 (
    echo.
    echo  ERROR: Cannot connect to MySQL with the credentials in .env.
    echo.
    echo  Host    : !DB_HOST!
    echo  Port    : !DB_PORT!
    echo  User    : !DB_USER!
    echo  Password: (see .env)
    echo.
    echo  Please check:
    echo    1. MySQL is running (open XAMPP Control Panel).
    echo    2. DB_USER and DB_PASSWORD in .env match your MySQL credentials.
    echo       - If you never set a MySQL password in XAMPP, set DB_PASSWORD= (blank).
    echo    File: !ENV_FILE!
    echo.
    goto :FAIL
)
echo  Connection OK.

:: Check if the database already exists
!MYSQL_CMD! -e "USE `!DB_NAME!`;" >nul 2>&1
if errorlevel 1 (
    echo  Database "!DB_NAME!" not found. Creating...
    !MYSQL_CMD! -e "CREATE DATABASE `!DB_NAME!` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >nul 2>&1
    if errorlevel 1 (
        echo.
        echo  ERROR: Failed to create database "!DB_NAME!".
        echo  Make sure the MySQL user "!DB_USER!" has CREATE privileges.
        echo.
        goto :FAIL
    )
    echo  Database "!DB_NAME!" created.
) else (
    echo  Database "!DB_NAME!" already exists.
)

:: ============================================================
:: STEP 6 — SQL dump vs Phinx migrations
::   There is NO .sql dump in this project.
::   The schema is built entirely via Phinx migrations + seeders.
:: ============================================================
echo.
echo [6/8] Running database migrations and seeders...

:: Ensure the Phinx binary exists (vendor should be present)
set "PHINX_BIN=%PROJECT_ROOT%\vendor\bin\phinx"
set "PHINX_BAT=%PROJECT_ROOT%\vendor\bin\phinx.bat"

if not exist "!PHINX_BAT!" if not exist "!PHINX_BIN!" (
    echo  Phinx not found in vendor\bin\. Checking Composer...
    goto :RUN_COMPOSER
)
goto :RUN_MIGRATIONS

:RUN_COMPOSER
:: ============================================================
:: STEP 6a — Install Composer dependencies (vendor missing)
:: ============================================================
echo.
echo  vendor\ folder is incomplete. Checking for Composer...

:: Look for composer in PATH first, then common locations
set "COMPOSER_CMD="
where composer >nul 2>&1
if not errorlevel 1 (
    set "COMPOSER_CMD=composer"
    goto :COMPOSER_FOUND
)
if exist "%APPDATA%\Composer\vendor\bin\composer.bat" (
    set "COMPOSER_CMD=%APPDATA%\Composer\vendor\bin\composer.bat"
    goto :COMPOSER_FOUND
)
if exist "C:\ProgramData\ComposerSetup\bin\composer.bat" (
    set "COMPOSER_CMD=C:\ProgramData\ComposerSetup\bin\composer.bat"
    goto :COMPOSER_FOUND
)

:: Try running composer.phar with XAMPP's PHP
if exist "%PROJECT_ROOT%\composer.phar" (
    set "COMPOSER_CMD=!PHP_BIN! %PROJECT_ROOT%\composer.phar"
    goto :COMPOSER_FOUND
)

echo.
echo  ERROR: Composer is not installed and the vendor\ folder is incomplete.
echo.
echo  WBPMS requires Composer to install its PHP dependencies.
echo  Please install Composer from https://getcomposer.org/download/
echo  then re-run this setup script.
echo.
goto :FAIL

:COMPOSER_FOUND
echo  Composer found: !COMPOSER_CMD!
echo  Running composer install (this may take a minute)...
cd /d "%PROJECT_ROOT%"
!COMPOSER_CMD! install --no-dev --no-interaction --optimize-autoloader
if errorlevel 1 (
    echo.
    echo  ERROR: composer install failed.
    echo  Check your internet connection and try again.
    echo.
    goto :FAIL
)
echo  Dependencies installed.

:: Re-check Phinx
if not exist "!PHINX_BAT!" if not exist "!PHINX_BIN!" (
    echo.
    echo  ERROR: Phinx still not found after composer install.
    echo  Please check composer.json and try again.
    echo.
    goto :FAIL
)

:RUN_MIGRATIONS
cd /d "%PROJECT_ROOT%"

:: Prefer the .bat wrapper on Windows
if exist "!PHINX_BAT!" (
    set "PHINX=!PHINX_BAT!"
) else (
    set "PHINX=!PHP_BIN! !PHINX_BIN!"
)

echo  Running Phinx migrations...
!PHINX! migrate -c phinx.php -e development
if errorlevel 1 (
    echo.
    echo  ERROR: Database migrations failed.
    echo  Check the output above for details.
    echo  Common causes:
    echo    - MySQL user lacks ALTER/CREATE TABLE privileges.
    echo    - A migration was already partially applied (run: !PHINX! status -c phinx.php).
    echo    - DB credentials in .env are wrong.
    echo.
    goto :FAIL
)
echo  Migrations completed.

echo  Running seeders...
!PHINX! seed:run -c phinx.php -e development
if errorlevel 1 (
    echo.
    echo  ERROR: Database seeding failed.
    echo  Check the output above for details.
    echo  If the database already has data, seeders use INSERT IGNORE and are safe to re-run.
    echo.
    goto :FAIL
)
echo  Seeders completed.

:: ============================================================
:: STEP 7 — Verify Apache mod_rewrite is enabled
:: ============================================================
echo.
echo [7/8] Checking Apache mod_rewrite...

set "HTTPD_CONF=!XAMPP_ROOT!\apache\conf\httpd.conf"
if not exist "!HTTPD_CONF!" (
    echo  WARNING: Cannot find !HTTPD_CONF!
    echo  Skipping mod_rewrite check. Ensure mod_rewrite is enabled manually.
    goto :SKIP_REWRITE
)

findstr /I "LoadModule rewrite_module" "!HTTPD_CONF!" >nul 2>&1
if errorlevel 1 (
    echo.
    echo  WARNING: mod_rewrite does not appear to be loaded in httpd.conf.
    echo  WBPMS requires mod_rewrite for URL routing to work.
    echo.
    echo  To enable it:
    echo    1. Open: !HTTPD_CONF!
    echo    2. Find the line:  #LoadModule rewrite_module modules/mod_rewrite.so
    echo    3. Remove the leading #  so it reads: LoadModule rewrite_module modules/mod_rewrite.so
    echo    4. Save the file and restart Apache in XAMPP Control Panel.
    echo.
    echo  Continuing setup, but the application will NOT work until mod_rewrite is enabled.
    echo.
    goto :SKIP_REWRITE
)

:: Check it's not commented out
findstr /I "^[^#]*LoadModule rewrite_module" "!HTTPD_CONF!" >nul 2>&1
if errorlevel 1 (
    echo.
    echo  WARNING: mod_rewrite line exists in httpd.conf but appears to be commented out.
    echo.
    echo  To enable it:
    echo    1. Open: !HTTPD_CONF!
    echo    2. Find the line:  #LoadModule rewrite_module modules/mod_rewrite.so
    echo    3. Remove the leading #
    echo    4. Save and restart Apache.
    echo.
) else (
    echo  mod_rewrite is enabled.
)

:SKIP_REWRITE

:: Also verify AllowOverride is set (needed for .htaccess to work)
findstr /I "AllowOverride All" "!HTTPD_CONF!" >nul 2>&1
if errorlevel 1 (
    echo.
    echo  WARNING: "AllowOverride All" was not found in httpd.conf.
    echo  The .htaccess file in public\ may not be honoured by Apache.
    echo.
    echo  To fix:
    echo    1. Open: !HTTPD_CONF!
    echo    2. Find the <Directory "C:/xampp/htdocs"> block.
    echo    3. Change   AllowOverride None   to   AllowOverride All
    echo    4. Save and restart Apache.
    echo.
)

:: ============================================================
:: STEP 8 — Verify the application is reachable
:: ============================================================
echo.
echo [8/8] Verifying application accessibility...

:: Derive the health-check URL from APP_BASE_URL
set "HEALTH_URL=!APP_BASE_URL!/health"
:: Remove any trailing slash from base url
if "!APP_BASE_URL:~-1!"=="/" set "APP_BASE_URL=!APP_BASE_URL:~0,-1!"

:: Use PowerShell's Invoke-WebRequest for a quick HTTP check (available on all modern Windows)
set "HTTP_STATUS="
for /f "delims=" %%S in ('powershell -NoProfile -Command "(try{(Invoke-WebRequest -Uri '!HEALTH_URL!' -UseBasicParsing -TimeoutSec 5).StatusCode}catch{$_.Exception.Response.StatusCode.value__})" 2^>nul') do set "HTTP_STATUS=%%S"

if "!HTTP_STATUS!"=="200" (
    echo  Health check passed  [HTTP 200]  !HEALTH_URL!
) else if "!HTTP_STATUS!"=="" (
    echo.
    echo  WARNING: Could not reach !HEALTH_URL!
    echo  Apache may still be starting, or mod_rewrite may need to be enabled.
    echo  Try opening the URL manually once Apache is fully started:
    echo    !APP_BASE_URL!/login
    echo.
) else (
    echo.
    echo  WARNING: Health check returned HTTP !HTTP_STATUS! for !HEALTH_URL!
    echo  The application may have a configuration issue.
    echo  Check the Apache error log: !XAMPP_ROOT!\apache\logs\error.log
    echo.
)

:: ============================================================
:: SUCCESS
:: ============================================================
echo.
echo ============================================================
echo   WBPMS SETUP COMPLETED SUCCESSFULLY!
echo ============================================================
echo.
echo   Application URL : !APP_BASE_URL!/login
echo.
echo   Demo login accounts (change before going live):
echo     Business Owner : owner       / owner-demo-pass
echo     HR Head        : hrhead      / hrhead-demo-pass
echo     Employee       : employee    / employee-demo-pass
echo.
echo   Please run the RUNME.bat file to start the WBPMS system.
echo.
echo ============================================================
echo.
pause
exit /b 0

:: ============================================================
:: FAIL — centralised error exit
:: ============================================================
:FAIL
echo.
echo ============================================================
echo   SETUP FAILED — See the error message above.
echo ============================================================
echo.
pause
exit /b 1
