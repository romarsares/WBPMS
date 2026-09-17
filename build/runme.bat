@echo off
setlocal EnableDelayedExpansion
title WBPMS

:: ================================================================
::  WBPMS — Web-Based Payroll Management System
::  Daily Launcher
::
::  Double-click this to start Apache + MySQL and open the app.
::  Run setup.bat ONCE first if you have not done so already.
:: ================================================================

:: Resolve the directory this script lives in.
set "PROJECT_ROOT=%~dp0"
if "!PROJECT_ROOT:~-1!"=="\" set "PROJECT_ROOT=!PROJECT_ROOT:~0,-1!"

echo.
echo ================================================================
echo   WBPMS — Web-Based Payroll Management System
echo ================================================================
echo.

:: ================================================================
:: 1. Check setup has been completed
:: ================================================================
set "ENV_FILE=!PROJECT_ROOT!\.env"

if not exist "!PROJECT_ROOT!\vendor\autoload.php" (
    echo  ERROR: vendor\ folder not found.
    echo.
    echo  It looks like setup has not been completed yet.
    echo  Please run setup.bat first.
    echo.
    goto :FAIL
)

if not exist "!ENV_FILE!" (
    echo  ERROR: .env file not found.
    echo.
    echo  Please run setup.bat first to configure the application.
    echo.
    goto :FAIL
)

:: ================================================================
:: 2. Read settings from .env
:: ================================================================
set "DB_PORT=3307"
set "APP_BASE_URL=http://wbpms.local"

for /f "usebackq eol=# tokens=1,* delims==" %%K in ("!ENV_FILE!") do (
    set "_K=%%K"
    set "_V=%%L"
    for /f "tokens=*" %%T in ("!_K!") do set "_K=%%T"
    if /I "!_K!"=="DB_PORT"       set "DB_PORT=!_V!"
    if /I "!_K!"=="APP_BASE_URL"  set "APP_BASE_URL=!_V!"
)

if "!APP_BASE_URL:~-1!"=="/" set "APP_BASE_URL=!APP_BASE_URL:~0,-1!"
set "LOGIN_URL=!APP_BASE_URL!/login"
set "HEALTH_URL=!APP_BASE_URL!/health"

:: ================================================================
:: 3. Locate XAMPP
:: ================================================================
set "XAMPP_ROOT="
set "PROJECT_DRIVE=!PROJECT_ROOT:~0,2!"
for %%D in ("!PROJECT_DRIVE!" "C:" "D:" "E:") do (
    if exist "%%~D\xampp\xampp-control.exe" (
        if "!XAMPP_ROOT!"=="" set "XAMPP_ROOT=%%~D\xampp"
    )
)

if "!XAMPP_ROOT!"=="" (
    echo  ERROR: XAMPP not found.
    echo  Please install XAMPP from https://www.apachefriends.org/
    echo.
    goto :FAIL
)

set "MYSQLADMIN_BIN=!XAMPP_ROOT!\mysql\bin\mysqladmin.exe"
set "APACHE_BIN=!XAMPP_ROOT!\apache\bin\httpd.exe"

:: ================================================================
:: 4. Start MySQL
:: ================================================================
echo  Checking MySQL...

"!MYSQLADMIN_BIN!" --port=!DB_PORT! -u root --connect-timeout=3 ping >nul 2>&1
if errorlevel 1 (
    echo  MySQL is not running. Starting...
    net start mysql >nul 2>&1
    if errorlevel 1 net start mysql57 >nul 2>&1
    if errorlevel 1 net start mariadb >nul 2>&1
    if errorlevel 1 (
        if exist "!XAMPP_ROOT!\mysql\bin\mysqld.exe" (
            start /B "" "!XAMPP_ROOT!\mysql\bin\mysqld.exe" ^
                --defaults-file="!XAMPP_ROOT!\mysql\bin\my.ini" >nul 2>&1
        )
    )
    set "_wait=0"
    :MYSQL_WAIT
    timeout /t 2 /nobreak >nul
    "!MYSQLADMIN_BIN!" --port=!DB_PORT! -u root --connect-timeout=3 ping >nul 2>&1
    if not errorlevel 1 goto :MYSQL_OK
    set /a "_wait+=1"
    if !_wait! LSS 10 goto :MYSQL_WAIT
    echo.
    echo  ERROR: MySQL did not start in time.
    echo  Please open XAMPP Control Panel and start MySQL manually:
    echo    !XAMPP_ROOT!\xampp-control.exe
    echo  Then run RUNME.bat again.
    echo.
    goto :FAIL
    :MYSQL_OK
    echo  MySQL started.  [OK]
) else (
    echo  MySQL is running.  [OK]
)

:: ================================================================
:: 5. Start Apache
:: ================================================================
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
    timeout /t 4 /nobreak >nul
    tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
    if errorlevel 1 (
        echo.
        echo  ERROR: Apache did not start.
        echo  Please open XAMPP Control Panel and start Apache manually:
        echo    !XAMPP_ROOT!\xampp-control.exe
        echo  Then run RUNME.bat again.
        echo.
        goto :FAIL
    )
    echo  Apache started.  [OK]
) else (
    echo  Apache is running.  [OK]
)

:: ================================================================
:: 6. Health check
:: ================================================================
echo  Verifying application...

set "_status="
set "_attempt=0"
:HC_RETRY
set /a "_attempt+=1"
for /f "delims=" %%S in ('powershell -NoProfile -Command ^
    "(try{(Invoke-WebRequest -Uri '!HEALTH_URL!' -UseBasicParsing -TimeoutSec 6).StatusCode}catch{$_.Exception.Response.StatusCode.value__})" ^
    2^>nul') do set "_status=%%S"

if "!_status!"=="200" goto :OPEN_BROWSER
if !_attempt! LSS 3 (
    timeout /t 3 /nobreak >nul
    goto :HC_RETRY
)

echo.
echo  WARNING: No response from !HEALTH_URL!
echo  The browser will open anyway. If you see an error page, check:
echo    !XAMPP_ROOT!\apache\logs\error.log
echo.

:: ================================================================
:: 7. Open browser
:: ================================================================
:OPEN_BROWSER
echo.
echo ================================================================
echo   WBPMS is ready.
echo   Opening : !LOGIN_URL!
echo ================================================================
echo.
echo   Login accounts:
echo     Business Owner : owner     ^|  owner-demo-pass
echo     HR Head        : hrhead    ^|  hrhead-demo-pass
echo     Employee       : employee  ^|  employee-demo-pass
echo.

start "" "!LOGIN_URL!"

echo   Press any key to close this window.
pause >nul
exit /b 0

:: ================================================================
:FAIL
echo ================================================================
echo   WBPMS could not start. See the error above.
echo ================================================================
echo.
pause
exit /b 1
