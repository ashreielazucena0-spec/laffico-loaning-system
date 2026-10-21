<?php

namespace App\Controllers;

use App\Libraries\LoanService;
use App\Libraries\ReminderEngine;

class Dashboard extends BaseController
{
    public function index(): string
    {
        (new ReminderEngine())->run(); // keeps automated reminders current

        $db      = db_connect();
        $clients = (new LoanService())->clientsOverview();
        $groups  = ['pastdue' => [], 'restructured' => [], 'current' => []];
        foreach ($clients as $c) {
            $groups[$c['status']][] = $c;
        }

        $today = date('Y-m-d');
        $rec   = $db->query(
            "SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total
               FROM transactions WHERE status = 'posted' AND direction IN ('in','out') AND txn_date = ?",
            [$today]
        )->getRowArray();

        $reminders = $db->query(
            "SELECT n.*, c.name AS client_name, c.acct_no
               FROM notifications n JOIN clients c ON c.id = n.client_id
              WHERE n.status = 'sent' ORDER BY n.sent_at DESC, n.id DESC LIMIT 6"
        )->getResultArray();

        $activity = $db->table('audit_logs')->orderBy('id', 'DESC')->limit(6)->get()->getResultArray();
        $pending  = (int) $db->table('approvals')->where('status', 'pending')->countAllResults();

        return view('dashboard', [
            'page_title' => 'Dashboard',
            'active'     => 'dashboard',
            'groups'     => $groups,
            'recorded'   => $rec,
            'reminders'  => $reminders,
            'activity'   => $activity,
            'pending'    => $pending,
        ]);
    }
}
