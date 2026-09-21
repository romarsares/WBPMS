:: =============================================================================
::  _common.bat — Shared helpers sourced by every WBPMS setup step script.
::  DO NOT run this file directly.  Call it with:
::    call "%~dp0_common.bat" || exit /b 1
::
::  Exports:
::    PROJECT_ROOT, XAMPP_ROOT, PHP_BIN, MYSQL_BIN, MYSQLADMIN_BIN,
::    APACHE_BIN, PHINX, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD,
::    APP_BASE_URL, MYSQL_CMD
:: =============================================================================

:: Derive project root = one level above the setup\ folder
set "_SD=%~dp0"
if "%_SD:~-1%"=="\" set "_SD=%_SD:~0,-1%"
for %%P in ("%_SD%\..") do set "PROJECT_ROOT=%%~fP"

:: ---------------------------------------------------------------------------
:: XAMPP detection
:: ---------------------------------------------------------------------------
set "XAMPP_ROOT="
set "_PD=%PROJECT_ROOT:~0,2%"
for %%D in ("%_PD%" "C:" "D:" "E:") do (
    if exist "%%~D\xampp\xampp-control.exe" (
        if "!XAMPP_ROOT!"=="" set "XAMPP_ROOT=%%~D\xampp"
    )
)
if "!XAMPP_ROOT!"=="" (
    echo  [ERROR] XAMPP not found.  Download from https://www.apachefriends.org/
    exit /b 1
)

set "PHP_BIN=!XAMPP_ROOT!\php\php.exe"
set "MYSQL_BIN=!XAMPP_ROOT!\mysql\bin\mysql.exe"
set "MYSQLADMIN_BIN=!XAMPP_ROOT!\mysql\bin\mysqladmin.exe"
set "APACHE_BIN=!XAMPP_ROOT!\apache\bin\httpd.exe"

:: ---------------------------------------------------------------------------
:: .env parsing  (defaults match .env.example)
:: ---------------------------------------------------------------------------
set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"
set "DB_NAME=wbpms"
set "DB_USER=root"
set "DB_PASSWORD="
set "APP_BASE_URL=http://localhost/wbpms/public"

set "ENV_FILE=%PROJECT_ROOT%\.env"
if exist "!ENV_FILE!" (
    for /f "usebackq eol=# tokens=1,* delims==" %%K in ("!ENV_FILE!") do (
        set "_K=%%K"
        set "_V=%%L"
        for /f "tokens=*" %%T in ("!_K!") do set "_K=%%T"
        if /I "!_K!"=="DB_HOST"      set "DB_HOST=!_V!"
        if /I "!_K!"=="DB_PORT"      set "DB_PORT=!_V!"
        if /I "!_K!"=="DB_NAME"      set "DB_NAME=!_V!"
        if /I "!_K!"=="DB_USER"      set "DB_USER=!_V!"
        if /I "!_K!"=="DB_PASSWORD"  set "DB_PASSWORD=!_V!"
        if /I "!_K!"=="APP_BASE_URL" set "APP_BASE_URL=!_V!"
    )
)

if "!DB_PASSWORD!"=="" (
    set "MYSQL_CMD=!MYSQL_BIN! -h !DB_HOST! -P !DB_PORT! -u !DB_USER!"
) else (
    set "MYSQL_CMD=!MYSQL_BIN! -h !DB_HOST! -P !DB_PORT! -u !DB_USER! -p!DB_PASSWORD!"
)

:: ---------------------------------------------------------------------------
:: Phinx command
:: ---------------------------------------------------------------------------
set "PHINX_BAT=%PROJECT_ROOT%\vendor\bin\phinx.bat"
set "PHINX_BIN=%PROJECT_ROOT%\vendor\bin\phinx"
if exist "!PHINX_BAT!" (
    set "PHINX=!PHINX_BAT!"
) else if exist "!PHINX_BIN!" (
    set "PHINX=!PHP_BIN! !PHINX_BIN!"
) else (
    set "PHINX="
)
