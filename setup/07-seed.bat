@echo off
setlocal EnableDelayedExpansion
title WBPMS Setup — Step 7: Run Seeders

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -Command "Start-Process -FilePath 'cmd.exe' -ArgumentList '/c \"%~f0\"' -Verb RunAs -Wait"
    exit /b
)

call "%~dp0_common.bat" || goto :FAIL

echo.
echo ============================================================
echo   STEP 7 — Run Seeders
echo ============================================================
echo   DB : !DB_NAME! on !DB_HOST!:!DB_PORT!
echo ============================================================
echo.

if "!PHINX!"=="" (
    echo  [ERROR] Phinx not found.  Run step 5 first: 05-composer-install.bat
    goto :FAIL
)

:: Check if seed data already present (demo owner account as sentinel)
!MYSQL_CMD! "!DB_NAME!" -e "SELECT 1 FROM users WHERE account_email='owner@demo.test' LIMIT 1;" 2>nul | find "1" >nul
if not errorlevel 1 (
    echo  [SKIP] Demo seed data already present.
    echo         To re-seed, truncate the tables manually then re-run.
    echo.
    pause
    exit /b 0
)

echo  Running seeders...
echo.
cd /d "%PROJECT_ROOT%"
!PHINX! seed:run -c phinx.php -e development
if errorlevel 1 (
    echo.
    echo  [ERROR] Seeding failed — see output above.
    goto :FAIL
)

echo.
echo  [OK] Seeders completed.
echo.
echo  Demo accounts (change before going live):
echo    Business Owner : owner@demo.test    / owner-demo-pass
echo    HR Head        : hrhead@demo.test   / hrhead-demo-pass
echo    Employee       : employee@demo.test / employee-demo-pass
echo.
pause
exit /b 0

:FAIL
echo.
pause
exit /b 1
