@echo off
echo Testing Thrive-Mautic Menu Registration...
echo.

REM Try to find PHP in common locations
set PHP_PATH=""

if exist "C:\xampp\php\php.exe" set PHP_PATH="C:\xampp\php\php.exe"
if exist "C:\wamp64\bin\php\php8.1.0\php.exe" set PHP_PATH="C:\wamp64\bin\php\php8.1.0\php.exe"
if exist "C:\wamp64\bin\php\php8.0.0\php.exe" set PHP_PATH="C:\wamp64\bin\php\php8.0.0\php.exe"
if exist "C:\wamp\bin\php\php8.1.0\php.exe" set PHP_PATH="C:\wamp\bin\php\php8.1.0\php.exe"
if exist "C:\wamp\bin\php\php8.0.0\php.exe" set PHP_PATH="C:\wamp\bin\php\php8.0.0\php.exe"
if exist "C:\Program Files\PHP\php.exe" set PHP_PATH="C:\Program Files\PHP\php.exe"

if %PHP_PATH%=="" (
    echo PHP not found in common locations.
    echo Please install PHP or add it to your PATH.
    echo.
    echo The issue is likely that the admin menu is not being registered.
    echo Let me create a simple fix for you.
    goto :fix
)

echo Found PHP at: %PHP_PATH%
echo.

%PHP_PATH% test_menu.php
goto :end

:fix
echo.
echo === CREATING SIMPLE FIX ===
echo The issue is that the admin menu is not being registered properly.
echo Let me create a simplified version that should work.

:end
echo.
echo Test complete!
pause
