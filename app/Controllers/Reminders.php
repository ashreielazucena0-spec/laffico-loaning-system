<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\LoanService;
use App\Libraries\ReminderEngine;

class Reminders extends BaseController
{
    public function index(): string
    {
        (new ReminderEngine())->run();
        $db = db_connect();

        $loans = array_values(array_filter((new LoanService())->allLoans(), static fn ($l) => in_array($l['status'], ['pastdue', 'restructured', 'current'], true)));
        usort($loans, static fn ($a, $b) => [status_rank($a['status']), -$a['days_overdue'], $a['client_name']] <=> [status_rank($b['status']), -$b['days_overdue'], $b['client_name']]);

        $sent = [];
        foreach ($db->query("SELECT loan_id, kind, installment_due, sent_at FROM notifications WHERE is_auto = 1")->getResultArray() as $n) {
            $sent[$n['loan_id']][$n['kind']][$n['installment_due']] = $n['sent_at'];
        }

        $kind = (string) $this->request->getGet('kind');
        $b = $db->table('notifications n')->select('n.*, c.name AS client_name, c.acct_no, l.loan_type')
            ->join('clients c', 'c.id = n.client_id')->join('loans l', 'l.id = n.loan_id');
        if (isset(ReminderEngine::KINDS[$kind])) {
            $b->where('n.kind', $kind);
        }
        $log = $b->orderBy('n.sent_at', 'DESC')->orderBy('n.id', 'DESC')->limit(150)->get()->getResultArray();

        return view('reminders', [
            'page_title' => 'Reminders',
            'active'     => 'reminders',
            'loans'      => $loans,
            'sent'       => $sent,
            'log'        => $log,
            'kind'       => $kind,
        ]);
    }

    public function add()
    {
        $db   = db_connect();
        $loan = (new LoanService())->allLoans();
        $loan = array_values(array_filter($loan, fn ($l) => (int) $l['id'] === (int) $this->request->getPost('loan_id')))[0] ?? null;
        $note = trim((string) $this->request->getPost('note'));
        if (! $loan || ! in_array($loan['status'], ['pastdue', 'restructured', 'current'], true)) {
            return $this->fail('reminders', 'Select a loan with an open balance.');
        }
        if ($note === '') {
            $note = 'Please settle your ' . $loan['loan_type'] . ' installment of ' . peso($loan['monthly_due']) . '.';
        }
        $db->table('notifications')->insert([
            'loan_id' => $loan['id'], 'client_id' => $loan['client_id'], 'kind' => 'manual', 'installment_due' => null,
            'days_overdue' => $loan['days_overdue'], 'message' => 'Manual reminder to ' . $loan['client_name'] . ': ' . $note,
            'channel' => 'system', 'is_auto' => 0, 'status' => 'sent', 'created_by' => current_user_name(), 'sent_at' => date('Y-m-d H:i:s'),
        ]);
        Audit::log('Added manual reminder', $loan['client_name'] . ' · ' . $loan['loan_no']);

        return $this->done('reminders', 'Reminder added.');
    }

    public function ack(?int $id = null)
    {
        $db = db_connect();
        if ($id) {
            $db->table('notifications')->where('id', $id)->update(['status' => 'done']);
        } else {
            $db->table('notifications')->where('status', 'sent')->update(['status' => 'done']);
        }

        return $this->done('reminders', 'Marked as followed up.');
    }
}
