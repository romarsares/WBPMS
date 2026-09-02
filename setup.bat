@echo off
setlocal EnableDelayedExpansion
title WBPMS Setup

:: ============================================================
::  WBPMS — Setup Script
::  Safe to run multiple times. Each step checks whether it is
::  already complete before doing any work and skips if so.
::  Run from the project root directory.
:: ============================================================

:: Resolve the directory this script lives in (project root).
set "PROJECT_ROOT=%~dp0"
if "%PROJECT_ROOT:~-1%"=="\" set "PROJECT_ROOT=%PROJECT_ROOT:~0,-1%"

echo.
echo ============================================================
echo   WBPMS — Web-Based Payroll Management System
echo   Setup  ^(safe to re-run^)
echo ============================================================
echo   Project root : %PROJECT_ROOT%
echo ============================================================
echo.

:: ============================================================
:: STEP 1 — Locate XAMPP
:: ============================================================
echo [1/8] Checking for XAMPP...

set "PROJECT_DRIVE=%PROJECT_ROOT:~0,2%"
set "XAMPP_ROOT="
for %%D in ("%PROJECT_DRIVE%" "C:" "D:") do (
    if exist "%%~D\xampp\xampp-control.exe" (
        if "!XAMPP_ROOT!"=="" set "XAMPP_ROOT=%%~D\xampp"
    )
)

if "!XAMPP_ROOT!"=="" (
    echo.
    echo  ERROR: XAMPP not found.
    echo  Download and install XAMPP 8.x from https://www.apachefriends.org/
    echo  then re-run this script.
    echo.
    goto :FAIL
)

echo  FOUND: !XAMPP_ROOT!

set "MYSQL_BIN=!XAMPP_ROOT!\mysql\bin\mysql.exe"
set "MYSQLADMIN_BIN=!XAMPP_ROOT!\mysql\bin\mysqladmin.exe"
set "PHP_BIN=!XAMPP_ROOT!\php\php.exe"
set "APACHE_BIN=!XAMPP_ROOT!\apache\bin\httpd.exe"

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

for /f "tokens=*" %%V in ('"!PHP_BIN!" -r "echo PHP_MAJOR_VERSION.\".\".PHP_MINOR_VERSION;" 2^>nul') do set "PHP_VER=%%V"

for /f "tokens=1,2 delims=." %%A in ("!PHP_VER!") do (
    set "PHP_MAJOR=%%A"
    set "PHP_MINOR=%%B"
)

echo  Found PHP !PHP_VER!

if !PHP_MAJOR! LSS 8 (
    echo.
    echo  ERROR: PHP !PHP_VER! is too old. WBPMS requires PHP 8.2 or higher.
    echo.
    goto :FAIL
)
if !PHP_MAJOR! EQU 8 if !PHP_MINOR! LSS 2 (
    echo.
    echo  ERROR: PHP !PHP_VER! is too old. WBPMS requires PHP 8.2 or higher.
    echo.
    goto :FAIL
)

echo  PHP !PHP_VER! OK.

:: ============================================================
:: STEP 3 — Start Apache and MySQL if not already running
:: ============================================================
echo.
echo [3/8] Checking Apache and MySQL services...

:: --- MySQL ---
"!MYSQLADMIN_BIN!" -u root --connect-timeout=3 ping >nul 2>&1
if errorlevel 1 (
    echo  MySQL is not running. Attempting to start...
    net start mysql >nul 2>&1
    if errorlevel 1 (
        if exist "!XAMPP_ROOT!\mysql\bin\mysqld.exe" (
            start /B "" "!XAMPP_ROOT!\mysql\bin\mysqld.exe" --defaults-file="!XAMPP_ROOT!\mysql\bin\my.ini" >nul 2>&1
        )
    )
    echo  Waiting for MySQL to start...
    set "_mwait=0"
    :MYSQL_WAIT_LOOP
    timeout /t 2 /nobreak >nul
    "!MYSQLADMIN_BIN!" -u root --connect-timeout=3 ping >nul 2>&1
    if not errorlevel 1 goto :MYSQL_STARTED
    set /a _mwait+=1
    if !_mwait! GEQ 10 (
        echo.
        echo  ERROR: MySQL did not start within 20 seconds.
        echo  Please start MySQL from XAMPP Control Panel then re-run this script.
        echo.
        goto :FAIL
    )
    goto :MYSQL_WAIT_LOOP
    :MYSQL_STARTED
    echo  MySQL started.
) else (
    echo  MySQL is already running.  [SKIP]
)

:: --- Apache ---
tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
if errorlevel 1 (
    echo  Apache is not running. Attempting to start...
    net start apache >nul 2>&1
    if errorlevel 1 net start apache2.4 >nul 2>&1
    if errorlevel 1 (
        if exist "!APACHE_BIN!" (
            start /B "" "!APACHE_BIN!" -k start >nul 2>&1
        )
    )
    timeout /t 3 /nobreak >nul
    tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
    if errorlevel 1 (
        echo.
        echo  WARNING: Could not confirm Apache started automatically.
        echo  Please start Apache from XAMPP Control Panel and re-run if needed.
        echo.
    ) else (
        echo  Apache started.
    )
) else (
    echo  Apache is already running.  [SKIP]
)

:: ============================================================
:: STEP 4 — Environment configuration (.env)
:: ============================================================
echo.
echo [4/8] Checking environment configuration (.env)...

set "ENV_FILE=%PROJECT_ROOT%\.env"
set "ENV_EXAMPLE=%PROJECT_ROOT%\.env.example"

if exist "!ENV_FILE!" (
    echo  .env already exists.  [SKIP]
) else (
    if not exist "!ENV_EXAMPLE!" (
        echo.
        echo  ERROR: Neither .env nor .env.example found in %PROJECT_ROOT%
        echo  The project files may be incomplete. Please re-copy the project folder.
        echo.
        goto :FAIL
    )
    echo  .env not found — creating from .env.example...
    copy "!ENV_EXAMPLE!" "!ENV_FILE!" >nul
    echo  Created .env from template.
    echo.
    echo  IMPORTANT: Edit .env now if your MySQL password differs from the template.
    echo  File: !ENV_FILE!
    echo.
    pause
)

:: Parse .env for the values we need (skip comment lines starting with #)
set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"
set "DB_NAME=wbpms"
set "DB_USER=root"
set "DB_PASSWORD="
set "APP_BASE_URL=http://localhost/wbpms/public"

for /f "usebackq eol=# tokens=1,* delims==" %%K in ("!ENV_FILE!") do (
    set "_K=%%K"
    set "_V=%%L"
    for /f "tokens=*" %%T in ("!_K!") do set "_K=%%T"
    if /I "!_K!"=="DB_HOST"       set "DB_HOST=!_V!"
    if /I "!_K!"=="DB_PORT"       set "DB_PORT=!_V!"
    if /I "!_K!"=="DB_NAME"       set "DB_NAME=!_V!"
    if /I "!_K!"=="DB_USER"       set "DB_USER=!_V!"
    if /I "!_K!"=="DB_PASSWORD"   set "DB_PASSWORD=!_V!"
    if /I "!_K!"=="APP_BASE_URL"  set "APP_BASE_URL=!_V!"
)

echo  DB_HOST     : !DB_HOST!
echo  DB_PORT     : !DB_PORT!
echo  DB_NAME     : !DB_NAME!
echo  DB_USER     : !DB_USER!
echo  APP_BASE_URL: !APP_BASE_URL!

:: ============================================================
:: STEP 5 — Database connection, creation, and schema check
:: ============================================================
echo.
echo [5/8] Verifying database connection and schema...

if "!DB_PASSWORD!"=="" (
    set "MYSQL_CMD=!MYSQL_BIN! -h !DB_HOST! -P !DB_PORT! -u !DB_USER!"
) else (
    set "MYSQL_CMD=!MYSQL_BIN! -h !DB_HOST! -P !DB_PORT! -u !DB_USER! -p!DB_PASSWORD!"
)

:: Test connection
!MYSQL_CMD! -e "SELECT 1;" >nul 2>&1
if errorlevel 1 (
    echo.
    echo  ERROR: Cannot connect to MySQL.
    echo  Host: !DB_HOST!  Port: !DB_PORT!  User: !DB_USER!
    echo  Check DB_USER and DB_PASSWORD in .env then re-run.
    echo.
    goto :FAIL
)
echo  Connection OK.

:: Create database only if it does not exist
!MYSQL_CMD! -e "USE \`!DB_NAME!\`;" >nul 2>&1
if errorlevel 1 (
    echo  Database "!DB_NAME!" not found. Creating...
    !MYSQL_CMD! -e "CREATE DATABASE \`!DB_NAME!\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >nul 2>&1
    if errorlevel 1 (
        echo.
        echo  ERROR: Failed to create database "!DB_NAME!".
        echo  Ensure MySQL user "!DB_USER!" has CREATE privileges.
        echo.
        goto :FAIL
    )
    echo  Database "!DB_NAME!" created.
) else (
    echo  Database "!DB_NAME!" already exists.  [SKIP creation]
)

:: ============================================================
:: STEP 6 — Composer dependencies
:: ============================================================
echo.
echo [6/8] Checking Composer dependencies...

set "PHINX_BAT=%PROJECT_ROOT%\vendor\bin\phinx.bat"
set "PHINX_BIN=%PROJECT_ROOT%\vendor\bin\phinx"

if exist "!PHINX_BAT!" (
    echo  vendor\ dependencies already installed.  [SKIP]
    goto :DEPS_DONE
)
if exist "%PROJECT_ROOT%\vendor\autoload.php" (
    echo  vendor\ dependencies already installed.  [SKIP]
    goto :DEPS_DONE
)

echo  vendor\ not found. Locating Composer...

set "COMPOSER_CMD="
where composer >nul 2>&1
if not errorlevel 1 ( set "COMPOSER_CMD=composer" & goto :COMPOSER_FOUND )

if exist "!XAMPP_ROOT!\php\composer.phar" (
    set "COMPOSER_CMD=!PHP_BIN! !XAMPP_ROOT!\php\composer.phar"
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
if exist "%PROJECT_ROOT%\composer.phar" (
    set "COMPOSER_CMD=!PHP_BIN! %PROJECT_ROOT%\composer.phar"
    goto :COMPOSER_FOUND
)

echo.
echo  ERROR: Composer not found. Install from https://getcomposer.org/
echo  then re-run this script.
echo.
goto :FAIL

:COMPOSER_FOUND
echo  Composer: !COMPOSER_CMD!
echo  Running composer install...
cd /d "%PROJECT_ROOT%"
!COMPOSER_CMD! install --no-interaction --optimize-autoloader
if errorlevel 1 (
    echo.
    echo  ERROR: composer install failed. Check your internet connection.
    echo.
    goto :FAIL
)

if not exist "!PHINX_BAT!" if not exist "!PHINX_BIN!" (
    echo.
    echo  ERROR: Phinx still not found after composer install.
    echo.
    goto :FAIL
)
echo  Dependencies installed.

:DEPS_DONE

:: Resolve the Phinx command to use
if exist "!PHINX_BAT!" (
    set "PHINX=!PHINX_BAT!"
) else (
    set "PHINX=!PHP_BIN! !PHINX_BIN!"
)

:: ============================================================
:: STEP 6a — Phinx migrations (idempotent — Phinx skips already-run migrations)
:: ============================================================
echo.
echo  Running Phinx migrations (already-run migrations are skipped automatically)...
cd /d "%PROJECT_ROOT%"
!PHINX! migrate -c phinx.php -e development
if errorlevel 1 (
    echo.
    echo  ERROR: Migrations failed. Check output above.
    echo  Common causes: missing DB privileges, partially applied migration.
    echo  Run:  !PHINX! status -c phinx.php -e development
    echo.
    goto :FAIL
)
echo  Migrations OK.

:: ============================================================
:: STEP 6b — Seeders
::   Seeders are skipped if the demo data is already present.
::   We check for the demo owner account as the sentinel.
:: ============================================================
echo.
echo  Checking seed data...

!MYSQL_CMD! "!DB_NAME!" -e "SELECT 1 FROM users WHERE username='owner' LIMIT 1;" 2>nul | find "1" >nul
if not errorlevel 1 (
    echo  Demo seed data already present.  [SKIP]
    goto :SEEDS_DONE
)

echo  Seed data not found. Running seeders...
!PHINX! seed:run -c phinx.php -e development
if errorlevel 1 (
    echo.
    echo  ERROR: Seeding failed. Check output above.
    echo.
    goto :FAIL
)
echo  Seeders completed.

:SEEDS_DONE

:: ============================================================
:: STEP 7 — Apache mod_rewrite check
:: ============================================================
echo.
echo [7/8] Checking Apache mod_rewrite...

set "HTTPD_CONF=!XAMPP_ROOT!\apache\conf\httpd.conf"
if not exist "!HTTPD_CONF!" (
    echo  WARNING: Cannot find !HTTPD_CONF! — skipping mod_rewrite check.
    goto :SKIP_REWRITE
)

findstr /I /R "^[^#]*LoadModule rewrite_module" "!HTTPD_CONF!" >nul 2>&1
if errorlevel 1 (
    echo.
    echo  WARNING: mod_rewrite is not enabled in httpd.conf.
    echo  WBPMS requires mod_rewrite for URL routing.
    echo.
    echo  To enable:
    echo    1. Open: !HTTPD_CONF!
    echo    2. Remove the # before:  LoadModule rewrite_module modules/mod_rewrite.so
    echo    3. Save and restart Apache.
    echo.
) else (
    echo  mod_rewrite is enabled.  [OK]
)

findstr /I "AllowOverride All" "!HTTPD_CONF!" >nul 2>&1
if errorlevel 1 (
    echo.
    echo  WARNING: "AllowOverride All" not found in httpd.conf.
    echo  The public\.htaccess rewrite rules may not be honoured.
    echo.
    echo  To fix:
    echo    1. Open: !HTTPD_CONF!
    echo    2. In the ^<Directory "C:/xampp/htdocs"^> block,
    echo       change  AllowOverride None  to  AllowOverride All
    echo    3. Save and restart Apache.
    echo.
) else (
    echo  AllowOverride All is set.  [OK]
)

:SKIP_REWRITE

:: ============================================================
:: STEP 8 — Health check
:: ============================================================
echo.
echo [8/8] Verifying application is reachable...

if "!APP_BASE_URL:~-1!"=="/" set "APP_BASE_URL=!APP_BASE_URL:~0,-1!"
set "HEALTH_URL=!APP_BASE_URL!/health"

set "_status="
for /f "delims=" %%S in ('powershell -NoProfile -Command ^
    "(try{(Invoke-WebRequest -Uri '!HEALTH_URL!' -UseBasicParsing -TimeoutSec 5).StatusCode}catch{$_.Exception.Response.StatusCode.value__})" ^
    2^>nul') do set "_status=%%S"

if "!_status!"=="200" (
    echo  Health check passed  [HTTP 200]  !HEALTH_URL!
) else if "!_status!"=="" (
    echo.
    echo  WARNING: No response from !HEALTH_URL!
    echo  Apache may still be starting, or mod_rewrite may need to be enabled.
    echo  Try opening manually: !APP_BASE_URL!/login
    echo.
) else (
    echo.
    echo  WARNING: Health check returned HTTP !_status!
    echo  Check: !XAMPP_ROOT!\apache\logs\error.log
    echo.
)

:: ============================================================
:: SUCCESS
:: ============================================================
echo.
echo ============================================================
echo   WBPMS SETUP COMPLETE
echo ============================================================
echo.
echo   Application URL : !APP_BASE_URL!/login
echo.
echo   Demo accounts ^(change before going live^):
echo     Business Owner : owner    / owner-demo-pass
echo     HR Head        : hrhead   / hrhead-demo-pass
echo     Employee       : employee / employee-demo-pass
echo.
echo   Run RUNME.bat to launch the application next time.
echo ============================================================
echo.
pause
exit /b 0

:: ============================================================
:FAIL
echo.
echo ============================================================
echo   SETUP FAILED — See the error message above.
echo ============================================================
echo.
pause
exit /b 1
