@echo off
title NPRU Room Booking System - Server
cd /d "%~dp0"

echo ========================================================
echo   NPRU Room Booking System - อาคาร C มหาวิทยาลัยราชภัฏนครปฐม
echo   รหัสนักศึกษา: 674230008 ^& 674230036
echo ========================================================

REM Auto-detect PHP binary
set PHP_BIN=php
if exist "C:\xampp\php\php.exe" (
    set PHP_BIN="C:\xampp\php\php.exe"
) else if exist "C:\xampp\php82\php.exe" (
    set PHP_BIN="C:\xampp\php82\php.exe"
)

REM Check if MySQL is running
echo [*] กำลังตรวจสอบบริการ MySQL (Port 3306)...
powershell -Command "if (!(Test-NetConnection -ComputerName 127.0.0.1 -Port 3306 -WarningAction SilentlyContinue).TcpTestSucceeded) { Write-Host '[!] คำเตือน: ยังไม่ได้เปิด MySQL ใน XAMPP Control Panel กรุณากด Start ที่ MySQL' -ForegroundColor Yellow }"

echo ========================================================
echo   Starting Web Server at http://localhost:8000 ...
echo   - ผู้ใช้งาน (นักศึกษา): http://localhost:8000/public/
echo   - เจ้าหน้าที่/แอดมิน:  http://localhost:8000/public/login.php
echo ========================================================
start http://localhost:8000/public/
%PHP_BIN% -S localhost:8000
pause
