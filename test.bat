@echo off
echo Testing Thrive-Mautic Plugin...
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
    echo You can also test the plugin by uploading it to your WordPress site.
    pause
    exit /b 1
)

echo Found PHP at: %PHP_PATH%
echo.

%PHP_PATH% validate_plugin.php

echo.
echo Test complete!
pause
