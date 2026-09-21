@echo off
setlocal EnableDelayedExpansion
title WBPMS Setup — Step 5: Composer Install

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -Command "Start-Process -FilePath 'cmd.exe' -ArgumentList '/c \"%~f0\"' -Verb RunAs -Wait"
    exit /b
)

call "%~dp0_common.bat" || goto :FAIL

echo.
echo ============================================================
echo   STEP 5 — Install Composer Dependencies
echo ============================================================
echo.

if exist "%PROJECT_ROOT%\vendor\autoload.php" (
    echo  [SKIP] vendor\ already installed.
    echo         To force a fresh install, delete the vendor\ folder and re-run.
    echo.
    pause
    exit /b 0
)

:: Find Composer
set "COMPOSER_CMD="
where composer >nul 2>&1
if not errorlevel 1 ( set "COMPOSER_CMD=composer" & goto :COMPOSER_FOUND )

if exist "!XAMPP_ROOT!\php\composer.phar" (
    set "COMPOSER_CMD=!PHP_BIN! !XAMPP_ROOT!\php\composer.phar"
    goto :COMPOSER_FOUND
)
if exist "%APPDATA%\Composer\vendor\bin\composer.bat" (
    set "COMPOSER_CMD=%APPDATA%\Composer\vendor\bin\composer.bat"
    goto :COMPOSER_FOUND
)
if exist "C:\ProgramData\ComposerSetup\bin\composer.bat" (
    set "COMPOSER_CMD=C:\ProgramData\ComposerSetup\bin\composer.bat"
    goto :COMPOSER_FOUND
)
if exist "%PROJECT_ROOT%\composer.phar" (
    set "COMPOSER_CMD=!PHP_BIN! %PROJECT_ROOT%\composer.phar"
    goto :COMPOSER_FOUND
)

echo  [ERROR] Composer not found.
echo          Install from https://getcomposer.org/ then re-run.
goto :FAIL

:COMPOSER_FOUND
echo  Composer : !COMPOSER_CMD!
echo  Running composer install...
echo.
cd /d "%PROJECT_ROOT%"
!COMPOSER_CMD! install --no-interaction --optimize-autoloader
if errorlevel 1 (
    echo.
    echo  [ERROR] composer install failed.
    echo          Check your internet connection and try again.
    goto :FAIL
)

if not exist "%PROJECT_ROOT%\vendor\autoload.php" (
    echo  [ERROR] vendor\autoload.php still missing after install.
    goto :FAIL
)

echo.
echo  [OK] Dependencies installed.
echo.
pause
exit /b 0

:FAIL
echo.
pause
exit /b 1
