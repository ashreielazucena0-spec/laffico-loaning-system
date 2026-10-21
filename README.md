# LAFFICO-MPC Loaning System

CodeIgniter 4 + MySQL/MariaDB. Staff (Administrator / Manager / Employee) work in the main system; clients
have their own portal at `/portal/login`.

## Setup (XAMPP / any PHP 8.2+ with MySQL)

1. Create the database (or run `setup.bat` on Windows):  `CREATE DATABASE laffico CHARACTER SET utf8mb4;`
2. Check the `database.default.*` lines in `.env` (default: user `root`, no password, database `laffico`).
3. `php spark migrate`
4. Optional demo data: `php spark db:seed DemoSeeder`  (users admin / irisa / maria / linne, password `ChangeMe#2026`;
   clients sign in at `/portal/login` with their name, e.g. `Reyes, Marilou`, same password; changing it is optional)
5. `php spark serve`  then open http://localhost:8080
6. For production: set `CI_ENVIRONMENT = production` in `.env`, point the web root at `public/`, and run
   `php spark loans:remind` daily from cron/Task Scheduler (reminders + late-payment penalties also refresh whenever the
   dashboard is opened).

Without demo data there are no users yet. Create the first administrator with:
`php spark db:seed AdminSeeder`  (username `admin`, password `ChangeMe#2026`, changing it is optional).

## How a loan moves through the system

See **LOANING_PROCESS.md** for the full lifecycle, the rules, and where to change each setting
(`app/Config/Loaning.php`).
