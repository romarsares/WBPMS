@echo off
setlocal EnableDelayedExpansion
title WBPMS Setup

:: ================================================================
::  WBPMS — Web-Based Payroll Management System
::  Client Setup Script
::
::  Run this ONCE on the target machine before first use.
::  It is safe to run again — every step checks if it is already
::  done and skips it.
::
::  Requirements:
::    - Windows 10/11
::    - XAMPP 8.x installed (Apache + MySQL)
::    - Run from the folder that contains this file
:: ================================================================

:: ================================================================
:: ADMIN CHECK — re-launch elevated if needed
:: ================================================================
net session >nul 2>&1
if errorlevel 1 (
    echo  Not running as Administrator.
    echo  Re-launching with elevated privileges...
    echo  ^(A UAC prompt will appear — click Yes to continue.^)
    echo.
    powershell -NoProfile -Command ^
        "Start-Process -FilePath 'cmd.exe' -ArgumentList '/c \"%~f0\"' -Verb RunAs -Wait"
    exit /b
)

echo  Running as Administrator.  [OK]

:: Resolve the directory this script lives in (project root).
set "PROJECT_ROOT=%~dp0"
if "!PROJECT_ROOT:~-1!"=="\" set "PROJECT_ROOT=!PROJECT_ROOT:~0,-1!"

echo.
echo ================================================================
echo   WBPMS — Web-Based Payroll Management System
echo   First-Time Setup
echo ================================================================
echo   Installing to : !PROJECT_ROOT!
echo ================================================================
echo.

:: ================================================================
:: STEP 1 — Locate XAMPP
:: ================================================================
echo [1/8] Locating XAMPP...

set "XAMPP_ROOT="
set "PROJECT_DRIVE=!PROJECT_ROOT:~0,2!"
for %%D in ("!PROJECT_DRIVE!" "C:" "D:" "E:") do (
    if exist "%%~D\xampp\xampp-control.exe" (
        if "!XAMPP_ROOT!"=="" set "XAMPP_ROOT=%%~D\xampp"
    )
)

if "!XAMPP_ROOT!"=="" (
    echo.
    echo  ERROR: XAMPP not found on this machine.
    echo.
    echo  WBPMS requires XAMPP ^(Apache + MySQL^).
    echo  Download and install XAMPP 8.x from:
    echo    https://www.apachefriends.org/
    echo  then run this script again.
    echo.
    goto :FAIL
)

echo  Found XAMPP at : !XAMPP_ROOT!  [OK]

set "PHP_BIN=!XAMPP_ROOT!\php\php.exe"
set "MYSQL_BIN=!XAMPP_ROOT!\mysql\bin\mysql.exe"
set "MYSQLADMIN_BIN=!XAMPP_ROOT!\mysql\bin\mysqladmin.exe"
set "APACHE_BIN=!XAMPP_ROOT!\apache\bin\httpd.exe"
set "HTTPD_CONF=!XAMPP_ROOT!\apache\conf\httpd.conf"
set "VHOSTS_CONF=!XAMPP_ROOT!\apache\conf\extra\httpd-vhosts.conf"

if not exist "!PHP_BIN!" (
    echo.
    echo  ERROR: php.exe not found at !PHP_BIN!
    echo  Your XAMPP installation may be incomplete.
    echo  Re-install XAMPP 8.x from https://www.apachefriends.org/
    echo.
    goto :FAIL
)

:: ================================================================
:: STEP 2 — Check PHP version
:: ================================================================
echo.
echo [2/8] Checking PHP version...

set "_php_tmp=%TEMP%\wbpms_phpver.txt"
"!PHP_BIN!" -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" > "!_php_tmp!" 2>nul

set "PHP_VER="
set "PHP_MAJOR=0"
set "PHP_MINOR=0"
for /f "usebackq tokens=*" %%V in ("!_php_tmp!") do set "PHP_VER=%%V"
del "!_php_tmp!" >nul 2>&1

if "!PHP_VER!"=="" (
    echo.
    echo  ERROR: Could not read PHP version. Check your XAMPP installation.
    echo.
    goto :FAIL
)

for /f "tokens=1,2 delims=." %%A in ("!PHP_VER!") do (
    set "PHP_MAJOR=%%A"
    set "PHP_MINOR=%%B"
)

echo  PHP version : !PHP_VER!

if !PHP_MAJOR! LSS 8 goto :PHP_TOO_OLD
if !PHP_MAJOR! EQU 8 if !PHP_MINOR! LSS 2 goto :PHP_TOO_OLD
echo  PHP !PHP_VER! is supported.  [OK]
goto :PHP_OK

:PHP_TOO_OLD
echo.
echo  ERROR: PHP !PHP_VER! is too old. WBPMS requires PHP 8.2 or higher.
echo  Please upgrade XAMPP to version 8.2 or later.
echo.
goto :FAIL

:PHP_OK

:: ================================================================
:: STEP 3 — Start Apache and MySQL
:: ================================================================
echo.
echo [3/8] Starting Apache and MySQL...

:: --- MySQL ---
"!MYSQLADMIN_BIN!" -u root --connect-timeout=3 ping >nul 2>&1
if errorlevel 1 (
    echo  MySQL is not running. Starting...
    net start mysql >nul 2>&1
    if errorlevel 1 (
        if exist "!XAMPP_ROOT!\mysql\bin\mysqld.exe" (
            start /B "" "!XAMPP_ROOT!\mysql\bin\mysqld.exe" ^
                --defaults-file="!XAMPP_ROOT!\mysql\bin\my.ini" >nul 2>&1
        )
    )
    set "_mwait=0"
    :MYSQL_WAIT
    timeout /t 2 /nobreak >nul
    "!MYSQLADMIN_BIN!" -u root --connect-timeout=3 ping >nul 2>&1
    if not errorlevel 1 goto :MYSQL_READY
    set /a "_mwait+=1"
    if !_mwait! LSS 10 goto :MYSQL_WAIT
    echo.
    echo  ERROR: MySQL did not start within 20 seconds.
    echo  Please start MySQL from XAMPP Control Panel, then run this script again.
    echo.
    goto :FAIL
    :MYSQL_READY
    echo  MySQL started.  [OK]
) else (
    echo  MySQL is already running.  [SKIP]
)

:: --- Apache ---
tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
if errorlevel 1 (
    echo  Apache is not running. Starting...
    net start apache >nul 2>&1
    if errorlevel 1 net start apache2.4 >nul 2>&1
    if errorlevel 1 (
        if exist "!APACHE_BIN!" (
            start /B "" "!APACHE_BIN!" -k start >nul 2>&1
        )
    )
    timeout /t 4 /nobreak >nul
    tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
    if errorlevel 1 (
        echo.
        echo  WARNING: Could not confirm Apache started.
        echo  Please start Apache from XAMPP Control Panel.
        echo.
    ) else (
        echo  Apache started.  [OK]
    )
) else (
    echo  Apache is already running.  [SKIP]
)

:: ================================================================
:: STEP 4 — Apache configuration (mod_rewrite + virtual host)
:: ================================================================
echo.
echo [4/8] Configuring Apache...

set "_apache_changed=0"

:: Backup httpd.conf once
if not exist "!HTTPD_CONF!.wbpms.bak" (
    copy "!HTTPD_CONF!" "!HTTPD_CONF!.wbpms.bak" >nul
    echo  Backed up httpd.conf  [OK]
)

:: --- Enable mod_rewrite ---
findstr /I /R "^[^#]*LoadModule rewrite_module" "!HTTPD_CONF!" >nul 2>&1
if errorlevel 1 (
    echo  Enabling mod_rewrite...
    powershell -NoProfile -Command ^
        "(Get-Content '!HTTPD_CONF!') -replace '^#(LoadModule rewrite_module)', '$1' | Set-Content '!HTTPD_CONF!'"
    findstr /I /R "^[^#]*LoadModule rewrite_module" "!HTTPD_CONF!" >nul 2>&1
    if errorlevel 1 (
        echo.
        echo  ERROR: Could not enable mod_rewrite automatically.
        echo  Open: !HTTPD_CONF!
        echo  Find: #LoadModule rewrite_module modules/mod_rewrite.so
        echo  Remove the # at the start of that line, save, then run this script again.
        echo.
        goto :FAIL
    )
    echo  mod_rewrite enabled.  [FIXED]
    set "_apache_changed=1"
) else (
    echo  mod_rewrite is enabled.  [OK]
)

:: --- Enable Virtual Hosts include ---
findstr /I /R "^[^#]*Include.*httpd-vhosts" "!HTTPD_CONF!" >nul 2>&1
if errorlevel 1 (
    echo  Enabling virtual hosts include...
    powershell -NoProfile -Command ^
        "(Get-Content '!HTTPD_CONF!') -replace '^#(.*Include.*httpd-vhosts.*)', '$1' | Set-Content '!HTTPD_CONF!'"
    set "_apache_changed=1"
    echo  Virtual hosts include enabled.  [FIXED]
) else (
    echo  Virtual hosts include is enabled.  [OK]
)

:: --- Add WBPMS virtual host block if not already present ---
findstr /I "wbpms.local" "!VHOSTS_CONF!" >nul 2>&1
if errorlevel 1 (
    echo  Adding WBPMS virtual host to !VHOSTS_CONF!...

    :: Normalise path separators for Apache (backslash -> forward slash)
    set "APACHE_ROOT=!PROJECT_ROOT:\=/!"

    >> "!VHOSTS_CONF!" echo.
    >> "!VHOSTS_CONF!" echo ## WBPMS — added by setup.bat
    >> "!VHOSTS_CONF!" echo ^<VirtualHost *:80^>
    >> "!VHOSTS_CONF!" echo     ServerName wbpms.local
    >> "!VHOSTS_CONF!" echo     DocumentRoot "!APACHE_ROOT!/public"
    >> "!VHOSTS_CONF!" echo     ^<Directory "!APACHE_ROOT!/public"^>
    >> "!VHOSTS_CONF!" echo         Options -Indexes +FollowSymLinks
    >> "!VHOSTS_CONF!" echo         AllowOverride All
    >> "!VHOSTS_CONF!" echo         Require all granted
    >> "!VHOSTS_CONF!" echo     ^</Directory^>
    >> "!VHOSTS_CONF!" echo ^</VirtualHost^>

    echo  Virtual host block added.  [OK]
    set "_apache_changed=1"
) else (
    echo  WBPMS virtual host already configured.  [SKIP]
)

:: --- Add wbpms.local to Windows hosts file ---
set "HOSTS_FILE=C:\Windows\System32\drivers\etc\hosts"
findstr /I "wbpms.local" "!HOSTS_FILE!" >nul 2>&1
if errorlevel 1 (
    echo  Adding wbpms.local to hosts file...
    echo 127.0.0.1 wbpms.local>> "!HOSTS_FILE!"
    echo  Hosts file updated.  [OK]
    set "_apache_changed=1"
) else (
    echo  wbpms.local already in hosts file.  [SKIP]
)

:: --- Restart Apache if config changed ---
if "!_apache_changed!"=="1" (
    echo  Restarting Apache to apply changes...
    net stop apache >nul 2>&1
    net stop apache2.4 >nul 2>&1
    timeout /t 2 /nobreak >nul
    net start apache >nul 2>&1
    if errorlevel 1 net start apache2.4 >nul 2>&1
    if errorlevel 1 (
        if exist "!APACHE_BIN!" start /B "" "!APACHE_BIN!" -k restart >nul 2>&1
    )
    timeout /t 4 /nobreak >nul
    tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
    if errorlevel 1 (
        echo.
        echo  WARNING: Apache may not have restarted automatically.
        echo  Please restart Apache from XAMPP Control Panel.
        echo.
    ) else (
        echo  Apache restarted.  [OK]
    )
)

:: ================================================================
:: STEP 5 — Environment file (.env)
:: ================================================================
echo.
echo [5/8] Setting up environment configuration...

set "ENV_FILE=!PROJECT_ROOT!\.env"
set "ENV_EXAMPLE=!PROJECT_ROOT!\.env.example"

:: Initialize DB credential variables
set "DB_HOST=127.0.0.1"
set "DB_PORT=3307"
set "DB_NAME=wbpms"
set "DB_USER=root"
set "DB_PASSWORD="
set "APP_BASE_URL=http://wbpms.local"

if exist "!ENV_FILE!" (
    echo  .env already exists — loading existing credentials.
    echo.

    :: Read existing values
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

    echo  Current DB user     : !DB_USER!
    echo  Current DB name     : !DB_NAME!
    echo  Current APP_BASE_URL: !APP_BASE_URL!
    echo.
    set "_reenter=N"
    set /p "_reenter=  Re-enter MySQL credentials? [y/N]: "
    if /I "!_reenter!"=="Y" goto :ASK_CREDENTIALS
    goto :CREDENTIALS_DONE

) else (
    if not exist "!ENV_EXAMPLE!" (
        echo.
        echo  ERROR: .env.example not found. The installation files may be incomplete.
        echo.
        goto :FAIL
    )
    echo  .env not found — creating from template.
    copy "!ENV_EXAMPLE!" "!ENV_FILE!" >nul
)

:: ----------------------------------------------------------------
:: Prompt the user for MySQL credentials
:: ----------------------------------------------------------------
:ASK_CREDENTIALS
echo.
echo  ---------------------------------------------------------------
echo   MySQL Database Credentials
echo   These are used to connect WBPMS to your MySQL server.
echo.
echo   For a default XAMPP installation:
echo     Host     : 127.0.0.1
echo     Port     : 3307  ^(standard XAMPP uses 3306, this system uses 3307^)
echo     Username : root
echo     Password : ^(leave blank — press Enter^)
echo  ---------------------------------------------------------------
echo.

:: --- Host ---
set "_input_host="
set /p "_input_host=  MySQL host [!DB_HOST!]: "
if "!_input_host!"=="" set "_input_host=!DB_HOST!"
set "DB_HOST=!_input_host!"

:: --- Port ---
set "_input_port="
set /p "_input_port=  MySQL port [!DB_PORT!]: "
if "!_input_port!"=="" set "_input_port=!DB_PORT!"
set "DB_PORT=!_input_port!"

:: --- Username ---
set "_input_user="
set /p "_input_user=  MySQL username [!DB_USER!]: "
if "!_input_user!"=="" set "_input_user=!DB_USER!"
set "DB_USER=!_input_user!"

:: --- Password ---
echo.
set "DB_PASSWORD="
set /p "DB_PASSWORD=  MySQL password (leave blank if none): "

:: ----------------------------------------------------------------
:: Write the collected credentials and other defaults into .env
:: ----------------------------------------------------------------
powershell -NoProfile -Command ^
    "(Get-Content '!ENV_FILE!') -replace 'APP_ENV=.*','APP_ENV=production' | Set-Content '!ENV_FILE!'"
powershell -NoProfile -Command ^
    "(Get-Content '!ENV_FILE!') -replace 'APP_BASE_URL=.*','APP_BASE_URL=http://wbpms.local' | Set-Content '!ENV_FILE!'"

:: Generate a fresh random APP_KEY using PowerShell (avoids cmd quoting issues)
for /f "delims=" %%K in ('powershell -NoProfile -Command ^
    "$bytes = New-Object byte[] 32; " ^
    "(New-Object Security.Cryptography.RNGCryptoServiceProvider).GetBytes($bytes); " ^
    "($bytes | ForEach-Object { $_.ToString('x2') }) -join ''"') do set "_APP_KEY=%%K"
powershell -NoProfile -Command ^
    "(Get-Content '!ENV_FILE!') -replace 'APP_KEY=.*',('APP_KEY=!_APP_KEY!') | Set-Content '!ENV_FILE!'"

powershell -NoProfile -Command ^
    "(Get-Content '!ENV_FILE!') -replace 'DB_HOST=.*','DB_HOST=!DB_HOST!' | Set-Content '!ENV_FILE!'"
powershell -NoProfile -Command ^
    "(Get-Content '!ENV_FILE!') -replace 'DB_PORT=.*','DB_PORT=!DB_PORT!' | Set-Content '!ENV_FILE!'"
powershell -NoProfile -Command ^
    "(Get-Content '!ENV_FILE!') -replace 'DB_NAME=.*','DB_NAME=wbpms' | Set-Content '!ENV_FILE!'"
powershell -NoProfile -Command ^
    "(Get-Content '!ENV_FILE!') -replace 'DB_USER=.*','DB_USER=!DB_USER!' | Set-Content '!ENV_FILE!'"
powershell -NoProfile -Command ^
    "(Get-Content '!ENV_FILE!') -replace 'DB_PASSWORD=.*','DB_PASSWORD=!DB_PASSWORD!' | Set-Content '!ENV_FILE!'"

echo  Credentials saved to .env.  [OK]

:CREDENTIALS_DONE

:: Write a temporary MySQL options file so the password never touches the
:: command line — this safely handles spaces, !, ^, = and other special chars.
set "MYSQL_OPT=%TEMP%\wbpms_my.cnf"
(
    echo [client]
    echo host=!DB_HOST!
    echo port=!DB_PORT!
    echo user=!DB_USER!
    echo password=!DB_PASSWORD!
) > "!MYSQL_OPT!"

:: Build the mysql command — keep --defaults-file quoting outside the variable
set "MYSQL_CMD=!MYSQL_BIN!"
set "MYSQL_OPT_ARG=--defaults-file=!MYSQL_OPT!"

:: ================================================================
:: STEP 6 — Database setup
:: ================================================================
echo.
echo [6/8] Setting up the database...

:: Test connection
::Test connection
!MYSQL_CMD! "!MYSQL_OPT_ARG!" -e "SELECT 1;" >nul 2>&1
if errorlevel 1 (
    echo.
    echo  ERROR: Cannot connect to MySQL with the credentials you entered.
    echo    Host : !DB_HOST!
    echo    Port : !DB_PORT!
    echo    User : !DB_USER!
    echo.
    echo  Please run setup.bat again and enter the correct MySQL username
    echo  and password when prompted.
    echo.
    goto :FAIL
)
echo  MySQL connection OK.  [OK]

:: Create database if it does not exist
!MYSQL_CMD! "!MYSQL_OPT_ARG!" -e "USE \`!DB_NAME!\`;" >nul 2>&1
if errorlevel 1 (
    echo  Creating database "!DB_NAME!"...
    !MYSQL_CMD! "!MYSQL_OPT_ARG!" -e "CREATE DATABASE \`!DB_NAME!\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >nul 2>&1
    if errorlevel 1 (
        echo.
        echo  ERROR: Could not create database "!DB_NAME!".
        echo  Make sure MySQL user "!DB_USER!" has CREATE privileges.
        echo.
        goto :FAIL
    )
    echo  Database "!DB_NAME!" created.  [OK]
) else (
    echo  Database "!DB_NAME!" already exists.  [SKIP]
)

:: ================================================================
:: STEP 7 — Run migrations then seed demo data
:: ================================================================
echo.
echo [7/8] Running database migrations...
echo  ^(Already-applied migrations are skipped automatically.^)

set "PHINX=!PHP_BIN! !PROJECT_ROOT!\vendor\bin\phinx"

cd /d "!PROJECT_ROOT!"
!PHINX! migrate -c phinx.php -e production
if errorlevel 1 (
    echo.
    echo  ERROR: Database migrations failed. See output above.
    echo.
    goto :FAIL
)
echo  Migrations complete.  [OK]

:: Seed — only if demo owner account does not exist yet
echo.
echo  Checking for demo data...
!MYSQL_CMD! "!MYSQL_OPT_ARG!" "!DB_NAME!" -e "SELECT 1 FROM users WHERE username='owner' LIMIT 1;" 2>nul | find "1" >nul
if not errorlevel 1 (
    echo  Demo accounts already present.  [SKIP]
) else (
    echo  Loading demo data...
    !PHINX! seed:run -c phinx.php -e production
    if errorlevel 1 (
        echo.
        echo  ERROR: Seeding failed. See output above.
        echo.
        goto :FAIL
    )
    echo  Demo data loaded.  [OK]
)

:: ================================================================
:: STEP 8 — Verify the application responds
:: ================================================================
echo.
echo [8/8] Verifying the application...

if "!APP_BASE_URL:~-1!"=="/" set "APP_BASE_URL=!APP_BASE_URL:~0,-1!"
set "HEALTH_URL=!APP_BASE_URL!/health"
set "LOGIN_URL=!APP_BASE_URL!/login"

set "_status="
set "_attempt=0"
:HC_RETRY
set /a "_attempt+=1"
for /f "delims=" %%S in ('powershell -NoProfile -Command ^
    "(try{(Invoke-WebRequest -Uri '!HEALTH_URL!' -UseBasicParsing -TimeoutSec 6).StatusCode}catch{$_.Exception.Response.StatusCode.value__})" ^
    2^>nul') do set "_status=%%S"

if "!_status!"=="200" goto :HC_PASS
if !_attempt! LSS 3 (
    echo  No response yet — waiting 5 seconds and retrying ^(!_attempt!/3^)...
    timeout /t 5 /nobreak >nul
    goto :HC_RETRY
)

echo.
echo  WARNING: Could not reach !HEALTH_URL! ^(status: !_status!^)
echo.
echo  The application may still work — try opening the browser manually.
echo  If you see a blank page or error, check:
echo    !XAMPP_ROOT!\apache\logs\error.log
echo.
goto :DONE

:HC_PASS
echo  Application is running.  [HTTP 200]

:: ================================================================
:: ALL DONE
:: ================================================================
:DONE
echo.
echo ================================================================
echo   WBPMS SETUP COMPLETE
echo ================================================================
echo.
echo   Open the application at:
echo     !LOGIN_URL!
echo.
echo   First-time login accounts:
echo     Business Owner : owner     ^|  owner-demo-pass
echo     HR Head        : hrhead    ^|  hrhead-demo-pass
echo     Employee       : employee  ^|  employee-demo-pass
echo.
echo   IMPORTANT — Before using WBPMS with real employees:
echo     1. Log in as HR Head and change all demo passwords.
echo     2. Enter your actual company branches and employee records.
echo     3. Import your biometric attendance log ^(.xls file^).
echo.
echo   To open WBPMS next time, double-click  start.bat
echo   ^(no re-setup needed^).
echo ================================================================
echo.

:: Create start.bat beside setup.bat for future launches
if not exist "!PROJECT_ROOT!\start.bat" (
    (
        echo @echo off
        echo setlocal EnableDelayedExpansion
        echo title WBPMS
        echo.
        echo :: Locate XAMPP
        echo set "XAMPP_ROOT="
        echo for %%%%D in ^("C:" "D:" "E:"^) do ^(
        echo     if exist "%%%%~D\xampp\xampp-control.exe" ^(
        echo         if "^^!XAMPP_ROOT^^!"=="" set "XAMPP_ROOT=%%%%~D\xampp"
        echo     ^)
        echo ^)
        echo if "^^!XAMPP_ROOT^^!"=="" ^( echo XAMPP not found. ^& pause ^& exit /b 1 ^)
        echo.
        echo :: Start MySQL
        echo "^^!XAMPP_ROOT^^!\mysql\bin\mysqladmin.exe" -u root --connect-timeout=3 ping ^>nul 2^>^&1
        echo if errorlevel 1 ^( net start mysql ^>nul 2^>^&1 ^& timeout /t 3 /nobreak ^>nul ^)
        echo.
        echo :: Start Apache
        echo tasklist /FI "IMAGENAME eq httpd.exe" 2^>nul ^| find /I "httpd.exe" ^>nul
        echo if errorlevel 1 ^( net start apache ^>nul 2^>^&1 ^& timeout /t 3 /nobreak ^>nul ^)
        echo.
        echo :: Open browser
        echo start "" "http://wbpms.local/login"
        echo exit /b 0
    ) > "!PROJECT_ROOT!\start.bat"
    echo   start.bat created — use it to launch WBPMS next time.
    echo.
)

if defined MYSQL_OPT if exist "!MYSQL_OPT!" del /f /q "!MYSQL_OPT!" >nul 2>&1
pause
exit /b 0

:: ================================================================
:FAIL
echo.
echo ================================================================
echo   SETUP FAILED — See the error message above.
echo   Fix the issue then run setup.bat again.
echo ================================================================
echo.
if defined MYSQL_OPT if exist "!MYSQL_OPT!" del /f /q "!MYSQL_OPT!" >nul 2>&1
pause
exit /b 1
