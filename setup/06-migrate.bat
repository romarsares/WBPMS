@echo off
setlocal EnableDelayedExpansion
title WBPMS Setup — Step 6: Run Migrations

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -Command "Start-Process -FilePath 'cmd.exe' -ArgumentList '/c \"%~f0\"' -Verb RunAs -Wait"
    exit /b
)

call "%~dp0_common.bat" || goto :FAIL

echo.
echo ============================================================
echo   STEP 6 — Run Database Migrations
echo ============================================================
echo   DB : !DB_NAME! on !DB_HOST!:!DB_PORT!
echo ============================================================
echo   Already-run migrations are skipped automatically by Phinx.
echo.

if "!PHINX!"=="" (
    echo  [ERROR] Phinx not found.  Run step 5 first: 05-composer-install.bat
    goto :FAIL
)

cd /d "%PROJECT_ROOT%"
echo  Running: !PHINX! migrate -c phinx.php -e development
echo.
!PHINX! migrate -c phinx.php -e development
if errorlevel 1 (
    echo.
    echo  [ERROR] Migrations failed — see output above.
    echo          Tip: run the status command to see which migration failed:
    echo            !PHINX! status -c phinx.php -e development
    goto :FAIL
)

echo.
echo  [OK] All migrations applied.
echo.
pause
exit /b 0

:FAIL
echo.
pause
exit /b 1
