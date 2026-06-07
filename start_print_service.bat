@echo off

:: ─── Prevent infinite loop: only spawn new window once ───────────────────────
if defined SHEBA_SERVICE_RUNNING goto :main

:: First run: set flag, open persistent CMD window, exit this one
set SHEBA_SERVICE_RUNNING=1
start "Sheba POS Print Service" cmd /k ""%~f0""
exit

:: ─────────────────────────────────────────────────────────────────────────────
:main
cd /d "%~dp0"
title Sheba POS Auto Print Service
color 0B

echo.
echo ===================================================
echo    Sheba Restaurant Auto Print Service
echo    Press Ctrl+C to stop.
echo ===================================================
echo.

set "PHP_BIN="

if exist "C:\xampp3\php\php.exe" set "PHP_BIN=C:\xampp3\php\php.exe" & goto found
if exist "C:\xampp\php\php.exe"  set "PHP_BIN=C:\xampp\php\php.exe"  & goto found
if exist "D:\xampp\php\php.exe"  set "PHP_BIN=D:\xampp\php\php.exe"  & goto found
if exist "D:\xampp3\php\php.exe" set "PHP_BIN=D:\xampp3\php\php.exe" & goto found
if exist "E:\xampp\php\php.exe"  set "PHP_BIN=E:\xampp\php\php.exe"  & goto found
if exist "E:\xampp3\php\php.exe" set "PHP_BIN=E:\xampp3\php\php.exe" & goto found
if exist "C:\php\php.exe"        set "PHP_BIN=C:\php\php.exe"        & goto found

where php >nul 2>nul
if %ERRORLEVEL% equ 0 set "PHP_BIN=php" & goto found

echo.
echo  *** خطأ: لم يتم العثور على PHP ***
echo.
echo  تحقق من:
echo    1. هل XAMPP مثبت؟ (C:\xampp او C:\xampp3 او D:\xampp)
echo    2. ما هو القرص الذي فيه XAMPP؟
echo.
pause
goto :eof

:found
echo [+] PHP: %PHP_BIN%
echo.

if not exist "local_print_worker.php" (
    echo.
    echo  *** خطأ: ملف local_print_worker.php غير موجود ***
    echo  المجلد الحالي: %CD%
    echo.
    pause
    goto :eof
)

echo [+] جاهز - بدء مراقبة الطباعة...
echo.

:loop
"%PHP_BIN%" -f local_print_worker.php
echo.
echo [!] توقف السكربت. اعادة التشغيل بعد 5 ثوانٍ...
ping 127.0.0.1 -n 6 >nul
goto loop
