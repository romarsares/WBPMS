```bat
@echo off
title WBPMS Launcher

echo Starting XAMPP Control Panel...
start "" "C:\xampp\xampp-control.exe"

timeout /t 3 /nobreak >nul

echo Opening WBPMS Login...
start "" "http://localhost/wbpms/public/login"

echo.
echo ========================================
echo WBPMS Login:
echo http://localhost/wbpms/public/login
echo ========================================
echo.
pause
```