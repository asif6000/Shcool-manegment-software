@echo off
setlocal
cd /d "%~dp0"
title Dream Roots Millennium School - cPanel Deployment

:menu
cls
echo =====================================================================
echo   Dream Roots Millennium School - cPanel Deployment Tool
echo   Host: dreamrootsmilleniumschool.com  User: irvrzogs
echo =====================================================================
echo.
echo   [1] Test FTP Connection
echo   [2] Deploy Recent Updates (Face Recognition + SEO) [Full with Backup]
echo   [3] Deploy Recent Updates ONLY (Fast, no backup)
echo   [4] Backup Live Server Files
echo   [5] Build Production ZIP Packages (.zip)
echo   [6] Build & Upload ZIP Packages to cPanel
echo   [0] Exit
echo.
echo =====================================================================
set /p choice="Select an option (0-6): "

if "%choice%"=="1" (
    powershell -ExecutionPolicy Bypass -File .\deploy.ps1 -Action Test
    pause
    goto menu
)
if "%choice%"=="2" (
    powershell -ExecutionPolicy Bypass -File .\deploy.ps1 -Action Full
    pause
    goto menu
)
if "%choice%"=="3" (
    powershell -ExecutionPolicy Bypass -File .\deploy.ps1 -Action Deploy
    pause
    goto menu
)
if "%choice%"=="4" (
    powershell -ExecutionPolicy Bypass -File .\deploy.ps1 -Action Backup
    pause
    goto menu
)
if "%choice%"=="5" (
    powershell -ExecutionPolicy Bypass -File .\deploy.ps1 -Action Package
    pause
    goto menu
)
if "%choice%"=="6" (
    powershell -ExecutionPolicy Bypass -File .\deploy.ps1 -Action UploadZip
    pause
    goto menu
)
if "%choice%"=="0" (
    exit /b 0
)

echo Invalid choice.
pause
goto menu
