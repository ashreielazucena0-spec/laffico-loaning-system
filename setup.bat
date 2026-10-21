@echo off
REM One-click setup for Windows + XAMPP/MySQL (user root, no password). Edit .env if yours differs.
cd /d "%~dp0"
set MYSQL=mysql
where mysql >nul 2>nul
if errorlevel 1 set MYSQL="C:\xampp\mysql\bin\mysql.exe"

echo Creating database laffico ...
%MYSQL% -uroot -e "CREATE DATABASE IF NOT EXISTS laffico CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
if errorlevel 1 (
  echo Could not connect to MySQL. Start MySQL in XAMPP and try again.
  pause
  exit /b 1
)
php spark migrate
echo.
set /p DEMO=Load demo data (clients, loans, users)? [y/N]: 
if /i "%DEMO%"=="y" (php spark db:seed DemoSeeder) else (php spark db:seed AdminSeeder)
echo.
echo Done. Start the system with:  php spark serve   then open http://localhost:8080
pause
