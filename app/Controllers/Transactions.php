<?php

namespace App\Controllers;

use App\Libraries\ApprovalService;
use App\Libraries\Audit;
use App\Libraries\DocumentService;
use App\Libraries\LoanService;

class Transactions extends BaseController
{
    public function index(): string
    {
        $db   = db_connect();
        $svc  = new LoanService();
        $date = (string) $this->request->getGet('date');
        $d    = \DateTime::createFromFormat('Y-m-d', $date);
        if (! $d || $d->format('Y-m-d') !== $date) {
            $date = date('Y-m-d');
        }

        $svc->assessAllPenalties(); // so the balances offered below already include any new late charges
        $rows = $db->query(
            "SELECT t.*, c.name AS client_name, c.acct_no, l.loan_no, l.loan_type
               FROM transactions t
               LEFT JOIN clients c ON c.id = t.client_id
               LEFT JOIN loans l ON l.id = t.loan_id
              WHERE t.txn_date = ? ORDER BY t.id DESC",
            [$date]
        )->getResultArray();

        $in = 0.0;
        $out = 0.0;
        $by = [];
        foreach ($rows as $r) {
            if ($r['status'] !== 'posted') {
                continue;
            }
            if ($r['direction'] === 'in') {
                $in += (float) $r['amount'];
            } elseif ($r['direction'] === 'out') {
                $out += (float) $r['amount'];
            }
            $by[$r['category']] = ($by[$r['category']] ?? 0) + (float) $r['amount'];
        }

        $clients = $db->table('clients')->select('id, acct_no, name')->orderBy('name')->get()->getResultArray();
        $loansBy = [];
        foreach ($svc->allLoans() as $l) {
            if (in_array($l['status'], ['pastdue', 'restructured', 'current'], true)) {
                $loansBy[$l['client_id']][] = [
                    'id' => (int) $l['id'], 'label' => $l['loan_no'] . ' · ' . $l['loan_type'] . ' · bal ' . peso($l['outstanding']) . ($l['penalty_due'] > 0 ? ' + penalty ' . peso($l['penalty_due']) : ''),
                    'monthly' => (float) $l['monthly_due'], 'balance' => (float) $l['payoff'],
                ];
            }
        }

        return view('transactions', [
            'page_title' => 'Daily transactions',
            'active'     => 'transactions',
            'date'       => $date,
            'rows'       => $rows,
            'in'         => $in,
            'out'        => $out,
            'byCat'      => $by,
            'clients'    => $clients,
            'loansBy'    => $loansBy,
        ]);
    }

    public function record()
    {
        $svc = new LoanService();
        $res = $svc->recordTransaction($this->request->getPost(), current_user_name());
        $date = (string) $this->request->getPost('txn_date');
        $back = 'transactions' . ($date ? '?date=' . urlencode($date) : '');
        if (! $res['ok']) {
            return $this->fail($back, $res['error']);
        }

        $file = $this->request->getFile('document');
        $msg  = 'Recorded ' . $res['txn_no'] . '. Balances updated.';
        if ($file && $file->isValid()) {
            $up = (new DocumentService())->store($file, [
                'client_id'      => (int) $this->request->getPost('client_id'),
                'transaction_id' => $res['id'],
                'doc_type'       => (string) $this->request->getPost('doc_type'),
            ], current_user_name());
            $msg .= $up['ok'] ? ' Supporting document attached.' : ' (Document not attached: ' . $up['error'] . ')';
        }

        return $this->done($back, $msg);
    }

    /** Printable official receipt / cash voucher for one posted transaction. */
    public function receipt(int $id)
    {
        $db = db_connect();
        $t  = $db->query(
            "SELECT t.*, c.name AS client_name, c.acct_no, c.address, l.loan_no, l.loan_type
               FROM transactions t LEFT JOIN clients c ON c.id = t.client_id LEFT JOIN loans l ON l.id = t.loan_id WHERE t.id = ?",
            [$id]
        )->getRowArray();
        if (! $t || $t['direction'] === 'none') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Receipt not found.');
        }
        $m = null;
        if ($t['loan_id']) {
            $loan = $db->table('loans')->where('id', $t['loan_id'])->get()->getRowArray();
            $m    = LoanService::metrics($loan);
        }

        return view('receipt', ['page_title' => 'Receipt ' . ($t['reference'] ?: $t['txn_no']), 'active' => 'transactions', 't' => $t, 'm' => $m]);
    }

    public function cancel()
    {
        $db     = db_connect();
        $id     = (int) $this->request->getPost('txn_id');
        $reason = trim((string) $this->request->getPost('reason'));
        $t      = $db->table('transactions')->where('id', $id)->get()->getRowArray();
        $back   = 'transactions' . ($t ? '?date=' . $t['txn_date'] : '');

        if (! $t || $t['status'] !== 'posted') {
            return $this->fail('transactions', 'That transaction cannot be cancelled.');
        }
        if ((int) $t['cancel_requested'] === 1) {
            return $this->fail($back, 'A cancellation request for this transaction is already waiting for approval.');
        }
        if (strlen($reason) < 10) {
            return $this->fail($back, 'State the valid reason for cancelling (at least 10 characters).');
        }
        $file = $this->request->getFile('document');
        if (! $file || ! $file->isValid()) {
            return $this->fail($back, 'Attach the supporting document that proves why the client is cancelling.');
        }

        $by = current_user_name();
        $up = (new DocumentService())->store($file, [
            'client_id' => (int) $t['client_id'], 'transaction_id' => $id, 'doc_type' => 'cancellation_support', 'title' => 'Cancellation support for ' . $t['txn_no'],
        ], $by);
        if (! $up['ok']) {
            return $this->fail($back, $up['error']);
        }

        $approvals = new ApprovalService();
        $aid = $approvals->request('txn_cancel', $id, (int) $t['client_id'], 'Cancel ' . $t['txn_no'] . ' (' . txn_label($t['category']) . ', ' . peso($t['amount']) . '). Reason: ' . $reason, (float) $t['amount'], 'manager', ['reason' => $reason]);
        $db->table('documents')->where('id', $up['id'])->update(['approval_id' => $aid]);

        // A Manager can cancel straight away; an Employee's request needs sign-off.
        if (can('approve.manager')) {
            $svc = new LoanService();
            $res = $svc->cancelTransaction($id, $reason, $by);
            if (! $res['ok']) {
                $db->table('approvals')->where('id', $aid)->delete();

                return $this->fail($back, $res['error']);
            }
            $db->table('approvals')->where('id', $aid)->update([
                'status' => 'approved', 'decided_by' => $by, 'decided_by_id' => session()->get('user_id'),
                'decided_role' => session()->get('role'), 'decided_at' => date('Y-m-d H:i:s'), 'decision_note' => 'Cancelled by authorized approver.',
            ]);

            return $this->done($back, 'Transaction cancelled and balances restored.');
        }

        $db->table('transactions')->where('id', $id)->update(['cancel_requested' => 1]);
        Audit::log('Requested transaction cancellation', $t['txn_no'] . ' — ' . $reason);

        return $this->done($back, 'Cancellation request sent for approval. The transaction stays in effect until it is approved.');
    }
}
