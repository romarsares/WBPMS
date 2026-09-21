@echo off
setlocal EnableDelayedExpansion
title WBPMS Setup — Step 8: Apache mod_rewrite

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -Command "Start-Process -FilePath 'cmd.exe' -ArgumentList '/c \"%~f0\"' -Verb RunAs -Wait"
    exit /b
)

call "%~dp0_common.bat" || goto :FAIL

echo.
echo ============================================================
echo   STEP 8 — Enable mod_rewrite + AllowOverride
echo ============================================================
echo.

set "HTTPD_CONF=!XAMPP_ROOT!\apache\conf\httpd.conf"
if not exist "!HTTPD_CONF!" (
    echo  [WARN] Cannot find !HTTPD_CONF! — skipping.
    pause
    exit /b 0
)

set "_changed=0"

:: Backup (once only)
set "HTTPD_BAK=!HTTPD_CONF!.wbpms.bak"
if not exist "!HTTPD_BAK!" (
    copy "!HTTPD_CONF!" "!HTTPD_BAK!" >nul
    echo  Backed up httpd.conf to: !HTTPD_BAK!
)

:: --- mod_rewrite ---
findstr /I /R "^[^#]*LoadModule rewrite_module" "!HTTPD_CONF!" >nul 2>&1
if errorlevel 1 (
    echo  mod_rewrite is disabled — enabling...
    powershell -NoProfile -Command "(Get-Content '!HTTPD_CONF!') -replace '^#(LoadModule rewrite_module)', '$1' | Set-Content '!HTTPD_CONF!'"
    findstr /I /R "^[^#]*LoadModule rewrite_module" "!HTTPD_CONF!" >nul 2>&1
    if errorlevel 1 (
        echo  [ERROR] Could not auto-enable mod_rewrite.
        echo          Manually remove # before: LoadModule rewrite_module modules/mod_rewrite.so
        goto :FAIL
    )
    echo  [FIXED] mod_rewrite enabled.
    set "_changed=1"
) else (
    echo  [OK] mod_rewrite already enabled.
)

:: --- AllowOverride ---
findstr /I "AllowOverride All" "!HTTPD_CONF!" >nul 2>&1
if errorlevel 1 (
    echo  AllowOverride is not All — fixing...
    powershell -NoProfile -Command "(Get-Content '!HTTPD_CONF!') -replace 'AllowOverride None', 'AllowOverride All' | Set-Content '!HTTPD_CONF!'"
    findstr /I "AllowOverride All" "!HTTPD_CONF!" >nul 2>&1
    if errorlevel 1 (
        echo  [ERROR] Could not set AllowOverride All.
        echo          Manually change AllowOverride None to AllowOverride All
        echo          in the ^<Directory "C:/xampp/htdocs"^> block.
        goto :FAIL
    )
    echo  [FIXED] AllowOverride set to All.
    set "_changed=1"
) else (
    echo  [OK] AllowOverride All already set.
)

:: --- Restart Apache if config changed ---
if "!_changed!"=="1" (
    echo  Restarting Apache...
    net stop apache >nul 2>&1
    net stop apache2.4 >nul 2>&1
    timeout /t 2 /nobreak >nul
    net start apache >nul 2>&1
    if errorlevel 1 net start apache2.4 >nul 2>&1
    if errorlevel 1 (
        if exist "!APACHE_BIN!" start /B "" "!APACHE_BIN!" -k restart >nul 2>&1
    )
    timeout /t 3 /nobreak >nul
    tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
    if errorlevel 1 (
        echo  [WARN] Apache may not have restarted — check XAMPP Control Panel.
    ) else (
        echo  [OK] Apache restarted.
    )
) else (
    echo  No Apache config changes needed.
)

echo.
pause
exit /b 0

:FAIL
echo.
pause
exit /b 1
