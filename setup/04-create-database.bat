@echo off
setlocal EnableDelayedExpansion
title WBPMS Setup — Step 4: Create Database

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -Command "Start-Process -FilePath 'cmd.exe' -ArgumentList '/c \"%~f0\"' -Verb RunAs -Wait"
    exit /b
)

call "%~dp0_common.bat" || goto :FAIL

echo.
echo ============================================================
echo   STEP 4 — Create Database
echo ============================================================
echo   Host : !DB_HOST!:!DB_PORT!
echo   User : !DB_USER!
echo   DB   : !DB_NAME!
echo ============================================================
echo.

:: Test connection
!MYSQL_CMD! -e "SELECT 1;" >nul 2>&1
if errorlevel 1 (
    echo  [ERROR] Cannot connect to MySQL.
    echo          Check DB_USER / DB_PASSWORD in .env and make sure MySQL is running.
    echo          Run step 2 first: 02-start-services.bat
    goto :FAIL
)
echo  [OK] MySQL connection successful.

:: Create DB if missing
!MYSQL_CMD! -e "USE \`!DB_NAME!\`;" >nul 2>&1
if not errorlevel 1 (
    echo  [SKIP] Database "!DB_NAME!" already exists.
    echo.
    pause
    exit /b 0
)

echo  Creating database "!DB_NAME!"...
!MYSQL_CMD! -e "CREATE DATABASE \`!DB_NAME!\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
    echo  [ERROR] Failed to create database.
    echo          Ensure user "!DB_USER!" has CREATE DATABASE privileges.
    goto :FAIL
)

echo  [OK] Database "!DB_NAME!" created.
echo.
pause
exit /b 0

:FAIL
echo.
pause
exit /b 1
