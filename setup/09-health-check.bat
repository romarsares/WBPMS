@echo off
setlocal EnableDelayedExpansion
title WBPMS Setup — Step 9: Health Check

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -Command "Start-Process -FilePath 'cmd.exe' -ArgumentList '/c \"%~f0\"' -Verb RunAs -Wait"
    exit /b
)

call "%~dp0_common.bat" || goto :FAIL

echo.
echo ============================================================
echo   STEP 9 — Health Check
echo ============================================================

if "!APP_BASE_URL:~-1!"=="/" set "APP_BASE_URL=!APP_BASE_URL:~0,-1!"
set "HEALTH_URL=!APP_BASE_URL!/health"
set "LOGIN_URL=!APP_BASE_URL!/login"

echo   Checking: !HEALTH_URL!
echo ============================================================
echo.

set "_attempts=0"
:RETRY
set "_status="
for /f "delims=" %%S in ('powershell -NoProfile -Command ^
    "(try{(Invoke-WebRequest -Uri '!HEALTH_URL!' -UseBasicParsing -TimeoutSec 6).StatusCode}catch{$_.Exception.Response.StatusCode.value__})" ^
    2^>nul') do set "_status=%%S"

if "!_status!"=="200" (
    echo  [OK] Application is responding  (HTTP 200)
    echo.
    echo  Open in your browser:
    echo    !LOGIN_URL!
    echo.
    set /p "_open=Open in browser now? [Y/N]: "
    if /I "!_open!"=="Y" start "" "!LOGIN_URL!"
    echo.
    pause
    exit /b 0
)

set /a _attempts+=1
if !_attempts! LSS 2 (
    echo  No response yet — waiting 5 seconds and retrying...
    timeout /t 5 /nobreak >nul
    goto :RETRY
)

if "!_status!"=="" (
    echo  [WARN] No response from !HEALTH_URL!
    echo.
    echo  Possible causes:
    echo    - Apache is not running  (run 02-start-services.bat)
    echo    - APP_BASE_URL in .env does not match your XAMPP path
    echo    - .htaccess / mod_rewrite not configured (run 08-apache-rewrite.bat)
) else (
    echo  [WARN] Received HTTP !_status! — check:
    echo    !XAMPP_ROOT!\apache\logs\error.log
)
echo.
pause
exit /b 1

:FAIL
echo.
pause
exit /b 1
