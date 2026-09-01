@echo off
setlocal EnableDelayedExpansion
title WBPMS Launcher

:: ============================================================
::  WBPMS — System Launcher
::  Run this after setup.bat has been completed successfully.
::  Does NOT reinstall, recreate, or seed the database.
:: ============================================================

:: Resolve project root from wherever this script lives.
set "PROJECT_ROOT=%~dp0"
if "!PROJECT_ROOT:~-1!"=="\" set "PROJECT_ROOT=!PROJECT_ROOT:~0,-1!"

echo.
echo ============================================================
echo   WBPMS — Web-Based Payroll Management System
echo   System Launcher
echo ============================================================
echo.

:: ============================================================
:: 1. Locate XAMPP
:: ============================================================
set "PROJECT_DRIVE=!PROJECT_ROOT:~0,2!"
set "XAMPP_ROOT="
for %%D in ("!PROJECT_DRIVE!" "C:" "D:") do (
    if exist "%%~D\xampp\xampp-control.exe" (
        if "!XAMPP_ROOT!"=="" set "XAMPP_ROOT=%%~D\xampp"
    )
)

if "!XAMPP_ROOT!"=="" (
    echo  ERROR: XAMPP not found on this machine.
    echo.
    echo  WBPMS requires XAMPP ^(Apache + MySQL^).
    echo  Please install XAMPP from https://www.apachefriends.org/
    echo  and run setup.bat first.
    echo.
    goto :FAIL
)

set "MYSQL_BIN=!XAMPP_ROOT!\mysql\bin\mysql.exe"
set "MYSQLADMIN_BIN=!XAMPP_ROOT!\mysql\bin\mysqladmin.exe"
set "PHP_BIN=!XAMPP_ROOT!\php\php.exe"
set "APACHE_BIN=!XAMPP_ROOT!\apache\bin\httpd.exe"

:: ============================================================
:: 2. Read APP_BASE_URL from .env (fall back to known default)
:: ============================================================
set "ENV_FILE=!PROJECT_ROOT!\.env"
set "APP_BASE_URL=http://localhost/wbpms/public"

if exist "!ENV_FILE!" (
    for /f "usebackq eol=# tokens=1,* delims==" %%K in ("!ENV_FILE!") do (
        set "_K=%%K"
        for /f "tokens=*" %%T in ("!_K!") do set "_K=%%T"
        if /I "!_K!"=="APP_BASE_URL" set "APP_BASE_URL=%%L"
    )
)

:: Strip trailing slash
if "!APP_BASE_URL:~-1!"=="/" set "APP_BASE_URL=!APP_BASE_URL:~0,-1!"

set "LOGIN_URL=!APP_BASE_URL!/login"
set "HEALTH_URL=!APP_BASE_URL!/health"

:: ============================================================
:: 3. Verify setup.bat has been run (vendor\ and .env must exist)
:: ============================================================
if not exist "!PROJECT_ROOT!\vendor\autoload.php" (
    echo  ERROR: vendor\autoload.php not found.
    echo.
    echo  The application dependencies are not installed.
    echo  Please run setup.bat first before using RUNME.bat.
    echo.
    goto :FAIL
)

if not exist "!ENV_FILE!" (
    echo  ERROR: .env file not found.
    echo.
    echo  The environment has not been configured yet.
    echo  Please run setup.bat first before using RUNME.bat.
    echo.
    goto :FAIL
)

:: ============================================================
:: 4. Ensure MySQL is running
:: ============================================================
echo  Checking MySQL...
"!MYSQLADMIN_BIN!" -u root --connect-timeout=3 ping >nul 2>&1
if errorlevel 1 (
    echo  MySQL is not running. Starting...

    :: Try as a Windows service first
    net start mysql >nul 2>&1
    if errorlevel 1 net start mysql57 >nul 2>&1
    if errorlevel 1 net start mariadb >nul 2>&1

    :: Fall back to launching mysqld directly
    if errorlevel 1 (
        if exist "!XAMPP_ROOT!\mysql\bin\mysqld.exe" (
            start /B "" "!XAMPP_ROOT!\mysql\bin\mysqld.exe" --defaults-file="!XAMPP_ROOT!\mysql\bin\my.ini" >nul 2>&1
        )
    )

    :: Wait up to 20 seconds
    set "_wait=0"
    :MYSQL_WAIT
    timeout /t 2 /nobreak >nul
    "!MYSQLADMIN_BIN!" -u root --connect-timeout=3 ping >nul 2>&1
    if not errorlevel 1 goto :MYSQL_OK
    set /a "_wait+=1"
    if !_wait! LSS 10 goto :MYSQL_WAIT

    echo.
    echo  ERROR: MySQL did not start in time.
    echo.
    echo  Please open XAMPP Control Panel and start MySQL manually:
    echo    !XAMPP_ROOT!\xampp-control.exe
    echo  Then re-run RUNME.bat.
    echo.
    goto :FAIL
    :MYSQL_OK
    echo  MySQL started.
) else (
    echo  MySQL is running.
)

:: ============================================================
:: 5. Ensure Apache is running
:: ============================================================
echo  Checking Apache...
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

    :: Give Apache a moment to initialise
    timeout /t 4 /nobreak >nul

    tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
    if errorlevel 1 (
        echo.
        echo  ERROR: Apache did not start.
        echo.
        echo  Please open XAMPP Control Panel and start Apache manually:
        echo    !XAMPP_ROOT!\xampp-control.exe
        echo  Then re-run RUNME.bat.
        echo.
        goto :FAIL
    )
    echo  Apache started.
) else (
    echo  Apache is running.
)

:: ============================================================
:: 6. Quick health-check before opening the browser
:: ============================================================
echo  Verifying application is reachable...

set "_status="
for /f "delims=" %%S in ('powershell -NoProfile -Command ^
    "(try{(Invoke-WebRequest -Uri '!HEALTH_URL!' -UseBasicParsing -TimeoutSec 6).StatusCode}catch{$_.Exception.Response.StatusCode.value__})" ^
    2^>nul') do set "_status=%%S"

if "!_status!"=="200" (
    echo  Application responded OK  [HTTP 200]
    goto :OPEN_BROWSER
)

if "!_status!"=="" (
    echo.
    echo  WARNING: No response from !HEALTH_URL!
    echo  Apache may still be warming up. Waiting 5 more seconds...
    timeout /t 5 /nobreak >nul

    :: One retry
    for /f "delims=" %%S in ('powershell -NoProfile -Command ^
        "(try{(Invoke-WebRequest -Uri '!HEALTH_URL!' -UseBasicParsing -TimeoutSec 6).StatusCode}catch{$_.Exception.Response.StatusCode.value__})" ^
        2^>nul') do set "_status=%%S"

    if "!_status!"=="200" (
        echo  Application responded OK  [HTTP 200]
        goto :OPEN_BROWSER
    )

    echo.
    echo  WARNING: Application still not responding at:
    echo    !HEALTH_URL!
    echo.
    echo  Possible causes:
    echo    - mod_rewrite is not enabled in Apache  ^(run setup.bat to check^)
    echo    - A PHP error on startup   ^(check: !XAMPP_ROOT!\apache\logs\error.log^)
    echo    - The .env file has wrong DB credentials
    echo.
    echo  The browser will open anyway. If you see a blank page or 500 error,
    echo  check the Apache error log for details.
    echo.
    goto :OPEN_BROWSER
)

echo  Application check returned HTTP !_status!
if "!_status!"=="500" (
    echo.
    echo  ERROR: The application returned HTTP 500 ^(Internal Server Error^).
    echo  Check the Apache error log:
    echo    !XAMPP_ROOT!\apache\logs\error.log
    echo  and the .env file for correct DB credentials.
    echo.
)
:: Still open the browser so the developer can see the error page
goto :OPEN_BROWSER

:: ============================================================
:: 7. Open the login page
:: ============================================================
:OPEN_BROWSER
echo.
echo ============================================================
echo   WBPMS is ready.
echo.
echo   Opening: !LOGIN_URL!
echo ============================================================
echo.

start "" "!LOGIN_URL!"

echo   Login accounts:
echo     Business Owner : owner     / owner-demo-pass
echo     HR Head        : hrhead    / hrhead-demo-pass
echo     Employee       : employee  / employee-demo-pass
echo.
echo   Press any key to close this window.
echo ============================================================
echo.
pause >nul
exit /b 0

:: ============================================================
:FAIL
echo ============================================================
echo   WBPMS could not start. See the error above.
echo ============================================================
echo.
pause
exit /b 1
