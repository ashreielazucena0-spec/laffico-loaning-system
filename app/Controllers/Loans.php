<?php

namespace App\Controllers;

use App\Libraries\ApprovalService;
use App\Libraries\Audit;
use App\Libraries\DocumentService;
use App\Libraries\LoanService;

class Loans extends BaseController
{
    public function apply()
    {
        $svc = new LoanService();
        $cid = (int) $this->request->getPost('client_id');
        $res = $svc->createLoan($this->request->getPost(), current_user_name());
        $acct = db_connect()->table('clients')->select('acct_no')->where('id', $cid)->get()->getRowArray()['acct_no'] ?? '';
        $back = 'accounts/detail/' . $acct;

        if (! $res['ok']) {
            return $this->fail($back, $res['error']);
        }

        $msg  = 'Loan ' . $res['loan_no'] . ' passed the credit checks and was sent to a Manager for approval.';
        if (! empty($res['flags'])) {
            $msg .= ' ' . count($res['flags']) . ' item' . (count($res['flags']) === 1 ? '' : 's') . ' flagged for the Manager to review.';
        }
        $file = $this->request->getFile('application_file');
        if ($file && $file->isValid()) {
            $up = (new DocumentService())->store($file, [
                'client_id' => $cid, 'loan_id' => $res['id'], 'doc_type' => 'application_for_loan', 'title' => 'Application for Loan ' . $res['loan_no'],
            ], current_user_name());
            $msg .= $up['ok'] ? ' Application for Loan attached.' : ' (File not attached: ' . $up['error'] . ')';
        } else {
            $msg .= ' Attach the signed Application for Loan so it can be approved.';
        }

        return $this->done($back, $msg);
    }

    /** Printable amortization schedule + loan disclosure. */
    public function schedule(int $id)
    {
        $data = $this->loanDoc($id);
        if ($data === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Loan not found, or it has not been released yet.');
        }

        return view('loan_schedule', $data + ['layout' => 'layout', 'portal' => false]);
    }

    /** Printable disbursement voucher for a released loan. */
    public function voucher(int $id)
    {
        $data = $this->loanDoc($id);
        if ($data === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Loan not found, or it has not been released yet.');
        }

        return view('loan_voucher', $data);
    }

    /** Manager: write off part or all of a loan's unpaid penalty. */
    public function waive()
    {
        $db   = db_connect();
        $loan = $db->table('loans')->where('id', (int) $this->request->getPost('loan_id'))->get()->getRowArray();
        if (! $loan) {
            return $this->fail('accounts', 'Loan not found.');
        }
        $client = $db->table('clients')->where('id', $loan['client_id'])->get()->getRowArray();
        $back   = 'accounts/detail/' . $client['acct_no'];
        $res    = (new LoanService())->waivePenalties(
            (int) $loan['id'],
            (float) str_replace(',', '', (string) $this->request->getPost('amount')),
            (string) $this->request->getPost('reason'),
            current_user_name()
        );

        return $res['ok'] ? $this->done($back, 'Penalty waived.') : $this->fail($back, $res['error']);
    }

    /** Shared by the schedule and voucher pages (and the client portal). */
    public function loanDoc(int $id, ?int $onlyClient = null): ?array
    {
        $db   = db_connect();
        $svc  = new LoanService();
        $loan = $db->table('loans')->where('id', $id)->get()->getRowArray();
        if (! $loan || $loan['approval_status'] !== 'approved' || ($onlyClient !== null && (int) $loan['client_id'] !== $onlyClient)) {
            return null;
        }
        $svc->assessPenalties($id);
        $loan = $db->table('loans')->where('id', $id)->get()->getRowArray();

        return [
            'page_title' => 'Loan ' . $loan['loan_no'],
            'active'     => 'accounts',
            'loan'       => $loan,
            'm'          => LoanService::metrics($loan),
            'rows'       => $svc->schedule($loan),
            'client'     => $db->table('clients')->where('id', $loan['client_id'])->get()->getRowArray(),
            'cfg'        => config('Loaning'),
        ];
    }

    public function restructure()
    {
        $db   = db_connect();
        $svc  = new LoanService();
        $loan = $db->table('loans')->where('id', (int) $this->request->getPost('loan_id'))->get()->getRowArray();
        if (! $loan) {
            return $this->fail('accounts', 'Loan not found.');
        }
        $client = $db->table('clients')->where('id', $loan['client_id'])->get()->getRowArray();
        $back   = 'accounts/detail/' . $client['acct_no'];

        $m = LoanService::metrics($loan);
        if ($loan['approval_status'] !== 'approved' || $m['outstanding'] <= 0.005) {
            return $this->fail($back, 'Only loans with a balance can be restructured.');
        }
        $term   = (int) $this->request->getPost('new_term');
        $first  = (string) $this->request->getPost('first_due');
        $reason = trim((string) $this->request->getPost('reason'));
        if ($term < 1 || $term > 120) {
            return $this->fail($back, 'New term must be between 1 and 120 months.');
        }
        $d = \DateTime::createFromFormat('Y-m-d', $first);
        if (! $d || $d->format('Y-m-d') !== $first || $first <= date('Y-m-d')) {
            return $this->fail($back, 'The first due date of the new schedule must be a future date.');
        }
        if (strlen($reason) < 10) {
            return $this->fail($back, 'Explain why the loan is being restructured (at least 10 characters).');
        }
        $pending = $db->table('approvals')->where('entity_type', 'loan_restructure')->where('entity_id', $loan['id'])->where('status', 'pending')->countAllResults();
        if ($pending) {
            return $this->fail($back, 'A restructuring request for this loan is already waiting for approval.');
        }

        (new ApprovalService())->request(
            'loan_restructure',
            (int) $loan['id'],
            (int) $loan['client_id'],
            'Restructure ' . $loan['loan_no'] . ' (' . $client['name'] . ') — balance ' . peso($m['outstanding']) . ' over ' . $term . ' months. Reason: ' . $reason,
            $m['outstanding'],
            'manager',
            ['new_term' => $term, 'first_due' => $first, 'reason' => $reason]
        );
        Audit::log('Requested loan restructuring', $loan['loan_no'] . ' · ' . $client['name']);

        return $this->done($back, 'Restructuring request sent to the Manager for approval.');
    }
}
