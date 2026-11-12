@echo off
title Dream Roots - Hikvision Attendance Bridge Agent
echo ============================================================
echo   Dream Roots Millennium School - Biometric Bridge Agent
echo   Terminal: Hikvision DS-K1A340FWX
echo ============================================================
echo.

set PHP_BIN=C:\xampp\php\php.exe
if not exist "%PHP_BIN%" (
    set PHP_BIN=php
)

:loop
echo [%date% %time%] Starting Hikvision Bridge Agent...
"%PHP_BIN%" "%~dp0hikvision-bridge.php"
echo.
echo [%date% %time%] Bridge Agent stopped or crashed. Restarting in 10 seconds...
timeout /t 10 /nobreak >nul
goto loop
