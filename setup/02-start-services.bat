@echo off
setlocal EnableDelayedExpansion
title WBPMS Setup — Step 2: Start Services

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -Command "Start-Process -FilePath 'cmd.exe' -ArgumentList '/c \"%~f0\"' -Verb RunAs -Wait"
    exit /b
)

call "%~dp0_common.bat" || goto :FAIL

echo.
echo ============================================================
echo   STEP 2 — Start Apache + MySQL
echo ============================================================
echo.

:: --- MySQL ---
"!MYSQLADMIN_BIN!" -u root --connect-timeout=3 ping >nul 2>&1
if not errorlevel 1 (
    echo  [SKIP] MySQL is already running.
    goto :MYSQL_OK
)

echo  Starting MySQL...
net start mysql >nul 2>&1
if errorlevel 1 (
    if exist "!XAMPP_ROOT!\mysql\bin\mysqld.exe" (
        start /B "" "!XAMPP_ROOT!\mysql\bin\mysqld.exe" --defaults-file="!XAMPP_ROOT!\mysql\bin\my.ini" >nul 2>&1
    )
)

set "_w=0"
:MYSQL_WAIT
timeout /t 2 /nobreak >nul
"!MYSQLADMIN_BIN!" -u root --connect-timeout=3 ping >nul 2>&1
if not errorlevel 1 goto :MYSQL_STARTED
set /a _w+=1
if !_w! GEQ 10 (
    echo  [ERROR] MySQL did not start within 20 s.
    echo          Start it manually from XAMPP Control Panel then re-run.
    goto :FAIL
)
goto :MYSQL_WAIT

:MYSQL_STARTED
echo  [OK] MySQL is running.

:MYSQL_OK

:: --- Apache ---
tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
if not errorlevel 1 (
    echo  [SKIP] Apache is already running.
    goto :APACHE_OK
)

echo  Starting Apache...
net start apache >nul 2>&1
if errorlevel 1 net start apache2.4 >nul 2>&1
if errorlevel 1 (
    if exist "!APACHE_BIN!" start /B "" "!APACHE_BIN!" -k start >nul 2>&1
)
timeout /t 3 /nobreak >nul
tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
if errorlevel 1 (
    echo  [WARN] Could not confirm Apache started — start it from XAMPP Control Panel.
) else (
    echo  [OK] Apache is running.
)

:APACHE_OK
echo.
echo  Services check complete.
echo.
pause
exit /b 0

:FAIL
echo.
pause
exit /b 1
