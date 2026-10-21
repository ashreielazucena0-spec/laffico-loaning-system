<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\ClientPortalService;
use App\Libraries\LoanService;

class ClientAccounts extends BaseController
{
    public function index(): string
    {
        return view('client_accounts', [
            'page_title' => 'Client accounts',
            'active'     => 'accounts',
            'accounts'   => (new LoanService())->clientsOverview(),
        ]);
    }

    public function add()
    {
        $res = (new LoanService())->createClient($this->request->getPost(), current_user_name());
        if (! $res['ok']) {
            return $this->fail('accounts', $res['error']);
        }

        // every new client gets a portal login automatically
        $portal = (new ClientPortalService())->createAccount((int) $res['id'], current_user_name());
        $msg    = 'Client account ' . $res['acct_no'] . ' created. You can now add ledger accounts and a loan application.';
        $r      = redirect()->to(site_url('accounts/detail/' . $res['acct_no']))->with('success', $msg);
        if ($portal['ok']) {
            $r = $r->with('portal_credentials', ['username' => $portal['username'], 'password' => $portal['password']]);
        }

        return $r;
    }

    public function portalCreate()
    {
        $cid  = (int) $this->request->getPost('client_id');
        $res  = (new ClientPortalService())->createAccount($cid, current_user_name());
        $back = 'accounts/detail/' . $this->acctOf($cid);
        if (! $res['ok']) {
            return $this->fail($back, $res['error']);
        }

        return redirect()->to($this->to($back))->with('success', 'Portal account created.')
            ->with('portal_credentials', ['username' => $res['username'], 'password' => $res['password']]);
    }

    public function portalReset()
    {
        $cid  = (int) $this->request->getPost('client_id');
        $res  = (new ClientPortalService())->resetPassword($cid, current_user_name());
        $back = 'accounts/detail/' . $this->acctOf($cid);
        if (! $res['ok']) {
            return $this->fail($back, $res['error']);
        }

        return redirect()->to($this->to($back))->with('success', 'New temporary password generated. The client must change it at next sign-in.')
            ->with('portal_credentials', ['username' => $res['username'], 'password' => $res['password']]);
    }

    public function portalToggle()
    {
        $cid  = (int) $this->request->getPost('client_id');
        $res  = (new ClientPortalService())->toggle($cid, current_user_name());
        $back = 'accounts/detail/' . $this->acctOf($cid);
        if (! $res['ok']) {
            return $this->fail($back, $res['error']);
        }

        return $this->done($back, $res['active'] ? 'Portal access enabled.' : 'Portal access disabled.');
    }

    public function detail(?string $acct = null)
    {
        $db  = db_connect();
        $svc = new LoanService();
        $cfg = config('Loaning');

        $client = $acct ? $db->table('clients')->where('acct_no', strtoupper($acct))->get()->getRowArray() : null;
        if (! $client) {
            return redirect()->to(site_url('accounts'))->with('info', 'Choose a client to open its account detail.');
        }
        $cid   = (int) $client['id'];
        $loans = $svc->allLoans($cid);
        $sum   = LoanService::summarize($loans);

        // ledger accounts + balances
        $ledgers = [];
        foreach ($db->table('ledger_accounts')->where('client_id', $cid)->orderBy('id')->get()->getResultArray() as $l) {
            $l['balance'] = $svc->ledgerBalance($cid, $l['ledger_type']);
            $ledgers[]    = $l;
        }
        $haveTypes = array_column($ledgers, 'ledger_type');

        // daily / weekly / monthly monitor (clickable buckets)
        $period = (string) $this->request->getGet('period');
        $period = in_array($period, ['daily', 'weekly', 'monthly'], true) ? $period : 'daily';
        $keyExpr = [
            'daily'   => 'txn_date',
            'weekly'  => 'DATE_SUB(txn_date, INTERVAL WEEKDAY(txn_date) DAY)',
            'monthly' => "DATE_FORMAT(txn_date, '%Y-%m')",
        ][$period];
        $buckets = $db->query(
            "SELECT $keyExpr AS k, COUNT(*) AS n,
                    SUM(CASE WHEN direction = 'in'  THEN amount ELSE 0 END) AS money_in,
                    SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END) AS money_out
               FROM transactions WHERE client_id = ? AND status = 'posted'
              GROUP BY k ORDER BY k DESC LIMIT 60",
            [$cid]
        )->getResultArray();

        $selected = (string) $this->request->getGet('bucket');
        $bucketTx = [];
        if ($selected !== '' && preg_match($period === 'monthly' ? '/^\d{4}-\d{2}$/' : '/^\d{4}-\d{2}-\d{2}$/', $selected)) {
            if ($period === 'daily') {
                $where = ['txn_date = ?', [$selected]];
            } elseif ($period === 'weekly') {
                $where = ['txn_date BETWEEN ? AND ?', [$selected, add_days($selected, 6)]];
            } else {
                $where = ["DATE_FORMAT(txn_date, '%Y-%m') = ?", [$selected]];
            }
            $bucketTx = $db->query(
                "SELECT t.*, l.loan_no FROM transactions t LEFT JOIN loans l ON l.id = t.loan_id
                  WHERE t.client_id = ? AND t.status = 'posted' AND {$where[0]} ORDER BY t.txn_date, t.id",
                array_merge([$cid], $where[1])
            )->getResultArray();
        } else {
            $selected = '';
        }

        $history = $db->query(
            "SELECT t.*, l.loan_no, l.loan_type FROM transactions t LEFT JOIN loans l ON l.id = t.loan_id
              WHERE t.client_id = ? ORDER BY t.txn_date DESC, t.id DESC LIMIT 200",
            [$cid]
        )->getResultArray();

        $docs = $db->query(
            "SELECT d.*, l.loan_no FROM documents d LEFT JOIN loans l ON l.id = d.loan_id
              WHERE d.client_id = ? ORDER BY d.id DESC",
            [$cid]
        )->getResultArray();

        $notes = $db->query(
            "SELECT n.*, l.loan_type FROM notifications n JOIN loans l ON l.id = n.loan_id
              WHERE n.client_id = ? ORDER BY n.sent_at DESC, n.id DESC LIMIT 15",
            [$cid]
        )->getResultArray();

        return view('account_detail', [
            'page_title'  => $client['name'],
            'active'      => 'accounts',
            'client'      => $client,
            'loans'       => $loans,
            'sum'         => $sum,
            'ledgers'     => $ledgers,
            'missingLedgers' => array_diff(array_keys($cfg->ledgerTypes), $haveTypes),
            'period'      => $period,
            'buckets'     => $buckets,
            'selected'    => $selected,
            'bucketTx'    => $bucketTx,
            'history'     => $history,
            'docs'        => $docs,
            'notes'       => $notes,
            'tab'         => (string) $this->request->getGet('tab'),
            'portal'      => (new ClientPortalService())->forClient($cid),
            'portalPay'   => $db->table('online_payments')->where('client_id', $cid)->orderBy('id', 'DESC')->limit(10)->get()->getResultArray(),
        ]);
    }

    public function addLedger()
    {
        $cid = (int) $this->request->getPost('client_id');
        $res = (new LoanService())->addLedgerAccount(
            $cid,
            (string) $this->request->getPost('ledger_type'),
            (float) str_replace(',', '', (string) $this->request->getPost('opening')),
            current_user_name()
        );
        $acct = $this->acctOf($cid);
        if (! $res['ok']) {
            return $this->fail('accounts/detail/' . $acct . '?tab=ledger', $res['error']);
        }

        return $this->done('accounts/detail/' . $acct . '?tab=ledger', 'Ledger account added.');
    }

    /**
     * Manager only. A client can be deleted only while it has no financial history
     * (loans, transactions, ledger accounts, approvals, online payments).
     */
    public function delete()
    {
        $db     = db_connect();
        $client = $db->table('clients')->where('id', (int) $this->request->getPost('client_id'))->get()->getRowArray();
        if (! $client) {
            return $this->fail('accounts', 'Client not found.');
        }
        $back = 'accounts/detail/' . $client['acct_no'];

        $has = [];
        foreach (['loans' => 'loans', 'transactions' => 'transactions', 'ledger_accounts' => 'ledger accounts', 'approvals' => 'approval requests', 'online_payments' => 'online payments'] as $table => $label) {
            $n = $db->table($table)->where('client_id', (int) $client['id'])->countAllResults();
            if ($n > 0) {
                $has[] = $n . ' ' . $label;
            }
        }
        if ($has) {
            return $this->fail($back, 'This client cannot be deleted because it already has ' . implode(', ', $has) . '. Records with financial history must be kept.', false);
        }

        $docs = $db->table('documents')->where('client_id', (int) $client['id'])->get()->getResultArray();

        $db->transStart();
        $db->table('client_users')->where('client_id', (int) $client['id'])->delete();
        $db->table('notifications')->where('client_id', (int) $client['id'])->delete();
        $db->table('documents')->where('client_id', (int) $client['id'])->delete();
        $db->table('clients')->where('id', (int) $client['id'])->delete();
        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->fail($back, 'The client could not be deleted. Nothing was changed.', false);
        }
        foreach ($docs as $d) {
            @unlink(WRITEPATH . 'uploads/' . $d['stored_name']);
        }
        Audit::log('Deleted client account', $client['name'] . ' (' . $client['acct_no'] . ')');

        return $this->done('accounts', 'Client ' . $client['name'] . ' (' . $client['acct_no'] . ') was deleted.');
    }

    private function acctOf(int $clientId): string
    {
        $c = db_connect()->table('clients')->select('acct_no')->where('id', $clientId)->get()->getRowArray();

        return $c['acct_no'] ?? '';
    }
}
