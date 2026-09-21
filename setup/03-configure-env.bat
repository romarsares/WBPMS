@echo off
setlocal EnableDelayedExpansion
title WBPMS Setup — Step 3: Configure .env

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -Command "Start-Process -FilePath 'cmd.exe' -ArgumentList '/c \"%~f0\"' -Verb RunAs -Wait"
    exit /b
)

call "%~dp0_common.bat" || goto :FAIL

echo.
echo ============================================================
echo   STEP 3 — Configure Environment (.env)
echo ============================================================
echo.

set "ENV_FILE=%PROJECT_ROOT%\.env"
set "ENV_EXAMPLE=%PROJECT_ROOT%\.env.example"

if exist "!ENV_FILE!" (
    echo  [SKIP] .env already exists at:
    echo         !ENV_FILE!
    echo.
    echo  To re-create it, delete the file and run this step again.
    echo.
    pause
    exit /b 0
)

if not exist "!ENV_EXAMPLE!" (
    echo  [ERROR] .env.example not found in project root.
    goto :FAIL
)

echo  Creating .env from .env.example...
copy "!ENV_EXAMPLE!" "!ENV_FILE!" >nul
echo  [OK] .env created.
echo.
echo  IMPORTANT: Open the file below and fill in your local values
echo  (especially DB_PASSWORD and APP_BASE_URL if different):
echo.
echo    !ENV_FILE!
echo.
set /p "_open=Open .env in Notepad now? [Y/N]: "
if /I "!_open!"=="Y" notepad "!ENV_FILE!"

echo.
pause
exit /b 0

:FAIL
echo.
pause
exit /b 1
