<?php

namespace App\Libraries;

/**
 * Automated reminders — nobody presses a "send" button.
 *
 * For each released loan that still has an unpaid installment the engine
 * makes sure the right notification exists:
 *   · heads-up        N days BEFORE the due date
 *   · 1st reminder    15 days overdue
 *   · 2nd reminder    30 days overdue
 *   · 1st collection letter  45 days overdue
 *   · 2nd collection letter  60 days overdue
 * (all thresholds live in Config\Loaning). It is idempotent — running it
 * twice never duplicates a notice (unique key loan + kind + installment).
 *
 * It runs whenever the dashboard / reminders page opens and from cron:
 *     php spark loans:remind
 */
class ReminderEngine
{
    public const KINDS = [
        'due_soon'     => 'Payment due soon',
        'reminder_1'   => '1st reminder',
        'reminder_2'   => '2nd reminder',
        'collection_1' => '1st collection letter',
        'collection_2' => '2nd collection letter',
        'manual'       => 'Manual reminder',
    ];

    protected $db;

    public function __construct()
    {
        helper('loaning');
        $this->db = db_connect();
    }

    public function run(): int
    {
        $cfg     = config('Loaning');
        $today   = date('Y-m-d');
        $created = 0;

        $steps = [
            'reminder_1'   => $cfg->reminder1Days,
            'reminder_2'   => $cfg->reminder2Days,
            'collection_1' => $cfg->collection1Days,
            'collection_2' => $cfg->collection2Days,
        ];

        $svc = new LoanService();
        $svc->assessAllPenalties($today); // late-payment penalties are charged here too

        foreach ($svc->allLoans(null, $today) as $l) {
            if (! in_array($l['status'], ['pastdue', 'restructured', 'current'], true) || ! $l['next_due']) {
                continue;
            }
            if ($l['days_overdue'] > 0) {
                foreach ($steps as $kind => $threshold) {
                    if ($l['days_overdue'] >= $threshold) {
                        $created += $this->notify($l, $kind, add_days($l['next_due'], $threshold) . ' 08:00:00');
                    }
                }
            } elseif (days_between($today, $l['next_due']) <= $cfg->reminderDueSoonDays) {
                $created += $this->notify($l, 'due_soon', date('Y-m-d H:i:s'));
            }
        }

        return $created;
    }

    protected function notify(array $loan, string $kind, string $sentAt): int
    {
        $exists = $this->db->table('notifications')
            ->where('loan_id', $loan['id'])->where('kind', $kind)->where('installment_due', $loan['next_due'])
            ->countAllResults();
        if ($exists) {
            return 0;
        }
        if ($sentAt > date('Y-m-d H:i:s')) {
            $sentAt = date('Y-m-d H:i:s');
        }

        $what = $loan['loan_type'] . ' installment of ' . peso($loan['monthly_due']) . ' due ' . fdate($loan['next_due']);
        switch ($kind) {
            case 'due_soon':
                $msg = 'Heads-up for ' . $loan['client_name'] . ': ' . $what . '.';
                break;
            case 'reminder_1':
                $msg = '1st reminder to ' . $loan['client_name'] . ': ' . $what . ' is now ' . $loan['days_overdue'] . ' days overdue.';
                break;
            case 'reminder_2':
                $msg = '2nd reminder to ' . $loan['client_name'] . ': ' . $what . ' is now ' . $loan['days_overdue'] . ' days overdue.';
                break;
            case 'collection_1':
                $msg = '1st collection letter to ' . $loan['client_name'] . ': ' . $what . ' remains unpaid (' . $loan['days_overdue'] . ' days).';
                break;
            default:
                $msg = '2nd collection letter to ' . $loan['client_name'] . ': ' . $what . ' remains unpaid (' . $loan['days_overdue'] . ' days). Next step: remedial action.';
        }

        $this->db->table('notifications')->insert([
            'loan_id'         => $loan['id'],
            'client_id'       => $loan['client_id'],
            'kind'            => $kind,
            'installment_due' => $loan['next_due'],
            'days_overdue'    => $loan['days_overdue'],
            'message'         => $msg,
            'channel'         => 'system',
            'is_auto'         => 1,
            'status'          => 'sent',
            'sent_at'         => $sentAt,
        ]);

        $this->maybeEmail($loan, $msg);

        return 1;
    }

    protected function maybeEmail(array $loan, string $message): void
    {
        if (! config('Loaning')->emailReminders || empty($loan['client_email'])) {
            return;
        }

        try {
            $email = service('email');
            $email->setTo($loan['client_email']);
            $email->setSubject('LAFFICO-MPC loan notice');
            $email->setMessage($message . "\n\nPlease settle your account at the cooperative office. Thank you.");
            $email->send(false);
        } catch (\Throwable $e) {
            log_message('error', 'Reminder e-mail failed: ' . $e->getMessage());
        }
    }
}
