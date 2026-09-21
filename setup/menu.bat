@echo off
setlocal EnableDelayedExpansion
title WBPMS — Setup Menu

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -Command "Start-Process -FilePath 'cmd.exe' -ArgumentList '/c \"%~f0\"' -Verb RunAs -Wait"
    exit /b
)

:MENU
cls
echo.
echo  ============================================================
echo    WBPMS — Setup Menu
echo    Web-Based Payroll Management System
echo  ============================================================
echo.
echo    Individual steps  (safe to re-run — each checks first):
echo.
echo     [1]  Check Requirements     (PHP version, XAMPP, composer.json)
echo     [2]  Start Services         (Apache + MySQL)
echo     [3]  Configure .env         (create from .env.example)
echo     [4]  Create Database        (creates DB if not exists)
echo     [5]  Composer Install       (install PHP dependencies)
echo     [6]  Run Migrations         (apply pending Phinx migrations)
echo     [7]  Run Seeders            (insert demo/reference data)
echo     [8]  Apache mod_rewrite     (enable rewrite + AllowOverride)
echo     [9]  Health Check           (verify app is reachable)
echo.
echo    Shortcuts:
echo.
echo     [A]  Run ALL steps in order (full fresh setup)
echo     [M]  Migrate only           (quick shortcut for step 6)
echo     [S]  Seed only              (quick shortcut for step 7)
echo     [R]  Migrate + Seed         (steps 6 and 7 together)
echo.
echo     [Q]  Quit
echo.
echo  ============================================================
set /p "_choice=  Your choice: "

if /I "!_choice!"=="1" call "%~dp001-check-requirements.bat"  & goto :MENU
if /I "!_choice!"=="2" call "%~dp002-start-services.bat"       & goto :MENU
if /I "!_choice!"=="3" call "%~dp003-configure-env.bat"        & goto :MENU
if /I "!_choice!"=="4" call "%~dp004-create-database.bat"      & goto :MENU
if /I "!_choice!"=="5" call "%~dp005-composer-install.bat"     & goto :MENU
if /I "!_choice!"=="6" call "%~dp006-migrate.bat"              & goto :MENU
if /I "!_choice!"=="7" call "%~dp007-seed.bat"                 & goto :MENU
if /I "!_choice!"=="8" call "%~dp008-apache-rewrite.bat"       & goto :MENU
if /I "!_choice!"=="9" call "%~dp009-health-check.bat"         & goto :MENU

if /I "!_choice!"=="M" (
    call "%~dp006-migrate.bat"
    goto :MENU
)
if /I "!_choice!"=="S" (
    call "%~dp007-seed.bat"
    goto :MENU
)
if /I "!_choice!"=="R" (
    call "%~dp006-migrate.bat"
    call "%~dp007-seed.bat"
    goto :MENU
)

if /I "!_choice!"=="A" (
    echo.
    echo  Running all steps in order...
    echo  ============================================================
    call "%~dp001-check-requirements.bat"
    if errorlevel 1 goto :ALL_FAIL
    call "%~dp002-start-services.bat"
    if errorlevel 1 goto :ALL_FAIL
    call "%~dp003-configure-env.bat"
    if errorlevel 1 goto :ALL_FAIL
    call "%~dp004-create-database.bat"
    if errorlevel 1 goto :ALL_FAIL
    call "%~dp005-composer-install.bat"
    if errorlevel 1 goto :ALL_FAIL
    call "%~dp006-migrate.bat"
    if errorlevel 1 goto :ALL_FAIL
    call "%~dp007-seed.bat"
    if errorlevel 1 goto :ALL_FAIL
    call "%~dp008-apache-rewrite.bat"
    if errorlevel 1 goto :ALL_FAIL
    call "%~dp009-health-check.bat"
    goto :MENU

    :ALL_FAIL
    echo.
    echo  [ERROR] A step failed. Fix the issue and re-run that step individually.
    echo.
    pause
    goto :MENU
)

if /I "!_choice!"=="Q" goto :QUIT
if /I "!_choice!"=="0" goto :QUIT

echo.
echo  Invalid choice. Press any key and try again.
pause >nul
goto :MENU

:QUIT
echo.
echo  Bye!
echo.
exit /b 0
