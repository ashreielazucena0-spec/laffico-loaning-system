<?php

namespace App\Commands;

use App\Libraries\ReminderEngine;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Schedule daily with cron / Task Scheduler:  php spark loans:remind */
class RemindCommand extends BaseCommand
{
    protected $group       = 'Loans';
    protected $name        = 'loans:remind';
    protected $description = 'Creates any due-soon / overdue reminders and collection letters that are missing.';

    public function run(array $params)
    {
        $n = (new ReminderEngine())->run();
        CLI::write($n . ' notification(s) created.', 'green');
    }
}
