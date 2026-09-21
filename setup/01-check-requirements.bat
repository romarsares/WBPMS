@echo off
setlocal EnableDelayedExpansion
title WBPMS Setup — Step 1: Check Requirements

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -Command "Start-Process -FilePath 'cmd.exe' -ArgumentList '/c \"%~f0\"' -Verb RunAs -Wait"
    exit /b
)

call "%~dp0_common.bat" || goto :FAIL

echo.
echo ============================================================
echo   STEP 1 — Check Requirements
echo ============================================================
echo   Project : %PROJECT_ROOT%
echo   XAMPP   : !XAMPP_ROOT!
echo ============================================================
echo.

:: PHP present?
if not exist "!PHP_BIN!" (
    echo  [ERROR] PHP not found at: !PHP_BIN!
    goto :FAIL
)

:: PHP version
set "_tmp=%TEMP%\wbpms_phpver.txt"
"!PHP_BIN!" -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" > "!_tmp!" 2>nul
set "PHP_VER="
for /f "usebackq tokens=*" %%V in ("!_tmp!") do set "PHP_VER=%%V"
del "!_tmp!" >nul 2>&1

if "!PHP_VER!"=="" (
    echo  [ERROR] Could not read PHP version.
    goto :FAIL
)

for /f "tokens=1,2 delims=." %%A in ("!PHP_VER!") do (
    set "PHP_MAJOR=%%A"
    set "PHP_MINOR=%%B"
)

if !PHP_MAJOR! LSS 8 (
    echo  [ERROR] PHP !PHP_VER! is too old.  Requires PHP 8.2+.
    goto :FAIL
)
if !PHP_MAJOR! EQU 8 if !PHP_MINOR! LSS 2 (
    echo  [ERROR] PHP !PHP_VER! is too old.  Requires PHP 8.2+.
    goto :FAIL
)

echo  [OK] PHP !PHP_VER!

:: MySQL binary present?
if not exist "!MYSQL_BIN!" (
    echo  [ERROR] mysql.exe not found at: !MYSQL_BIN!
    goto :FAIL
)
echo  [OK] mysql.exe found

:: composer.json present?
if not exist "%PROJECT_ROOT%\composer.json" (
    echo  [ERROR] composer.json not found in project root.
    echo         Are you running this from inside the wbpms folder?
    goto :FAIL
)
echo  [OK] composer.json found

echo.
echo  All requirements satisfied.
echo.
pause
exit /b 0

:FAIL
echo.
echo  Requirements check FAILED — see errors above.
echo.
pause
exit /b 1
