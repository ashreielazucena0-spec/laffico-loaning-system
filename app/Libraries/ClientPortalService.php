<?php

namespace App\Libraries;

/**
 * Client portal accounts (one login per client) and the figures the portal shows.
 *
 *  - username = the client's name exactly as on the account (e.g. "Reyes, Marilou"; not case-sensitive)
 *  - a random temporary password is generated; it is shown to staff ONCE and
 *    the client must change it at first sign-in
 */
class ClientPortalService
{
    protected $db;

    public function __construct()
    {
        helper('loaning');
        $this->db = db_connect();
    }

    // ---------------------------------------------------------------- accounts

    /** Random 10-char password: letters + digits, no look-alike characters. */
    public static function tempPassword(): string
    {
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
        $digits  = '23456789';
        $all     = $letters . $digits;
        $pw      = $letters[random_int(0, strlen($letters) - 1)] . $digits[random_int(0, strlen($digits) - 1)];
        for ($i = 0; $i < 8; $i++) {
            $pw .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($pw);
    }

    public static function validPassword(string $pw): bool
    {
        return strlen($pw) >= 8 && preg_match('/[A-Za-z]/', $pw) && preg_match('/\d/', $pw);
    }

    public function forClient(int $clientId): ?array
    {
        return $this->db->table('client_users')->where('client_id', $clientId)->get()->getRowArray();
    }

    /** @return array{ok:bool, error?:string, username?:string, password?:string} */
    public function createAccount(int $clientId, string $by): array
    {
        $client = $this->db->table('clients')->where('id', $clientId)->get()->getRowArray();
        if (! $client) {
            return ['ok' => false, 'error' => 'Client not found.'];
        }
        if ($this->forClient($clientId)) {
            return ['ok' => false, 'error' => $client['name'] . ' already has a portal account.'];
        }
        $pw = self::tempPassword();
        $this->db->table('client_users')->insert([
            'client_id'            => $clientId,
            'username'             => $client['name'],
            'password_hash'        => password_hash($pw, PASSWORD_DEFAULT),
            'is_active'            => 1,
            'must_change_password' => 0,
            'created_by'           => $by,
            'created_at'           => date('Y-m-d H:i:s'),
        ]);
        Audit::log('Created client portal account', $client['name'] . ' (' . $client['acct_no'] . ')', $by);

        return ['ok' => true, 'username' => $client['name'], 'password' => $pw];
    }

    public function resetPassword(int $clientId, string $by): array
    {
        $acc = $this->forClient($clientId);
        if (! $acc) {
            return ['ok' => false, 'error' => 'This client has no portal account yet.'];
        }
        $pw = self::tempPassword();
        $this->db->table('client_users')->where('id', $acc['id'])->update([
            'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'must_change_password' => 0,
            'failed_attempts' => 0, 'locked_until' => null,
        ]);
        Audit::log('Reset client portal password', $acc['username'], $by);

        return ['ok' => true, 'username' => $acc['username'], 'password' => $pw];
    }

    public function toggle(int $clientId, string $by): array
    {
        $acc = $this->forClient($clientId);
        if (! $acc) {
            return ['ok' => false, 'error' => 'This client has no portal account yet.'];
        }
        $now = $acc['is_active'] ? 0 : 1;
        $this->db->table('client_users')->where('id', $acc['id'])->update(['is_active' => $now, 'failed_attempts' => 0, 'locked_until' => null]);
        Audit::log($now ? 'Enabled client portal account' : 'Disabled client portal account', $acc['username'], $by);

        return ['ok' => true, 'active' => (bool) $now];
    }

    // ---------------------------------------------------------------- figures

    /**
     * Everything the portal dashboard needs for one client.
     * Amount due now = what must be paid so that every installment that is
     * already due (or, if none is due yet, the next installment) is covered.
     */
    public function overview(int $clientId): array
    {
        $svc   = new LoanService();
        $today = date('Y-m-d');
        $loans = [];
        $svc->assessAllPenalties($today); // late charges are up to date before the client looks

        foreach ($svc->allLoans($clientId) as $l) {
            if (! in_array($l['status'], ['pastdue', 'restructured', 'current'], true)) {
                continue; // pending / rejected / fully paid loans are not payable
            }
            $l['due_now']  = round(self::amountDueNow($l, $today) + (float) $l['penalty_due'], 2);
            $l['payable']  = $l['payoff'] > 0.005;
            $loans[]       = $l;
        }

        $ledgers = [];
        foreach ($this->db->table('ledger_accounts')->where('client_id', $clientId)->orderBy('id')->get()->getResultArray() as $a) {
            $a['balance'] = $svc->ledgerBalance($clientId, $a['ledger_type']);
            $ledgers[]    = $a;
        }

        return [
            'loans'   => $loans,
            'summary' => LoanService::summarize($loans),
            'ledgers' => $ledgers,
        ];
    }

    public static function amountDueNow(array $loan, ?string $today = null): float
    {
        $today = $today ?: date('Y-m-d');
        if (empty($loan['installments']) || $loan['outstanding'] <= 0.005) {
            return 0.0;
        }
        $monthly   = (float) $loan['monthly_due'];
        $schedPaid = (float) $loan['paid_total'] - (float) $loan['sched_offset'];
        $schedTot  = (float) $loan['sched_total'];

        $target = null; // last installment that is already due and still unpaid
        $first  = null; // first unpaid installment
        foreach ($loan['installments'] as $k => $i) {
            if ($i['state'] === 'paid') {
                continue;
            }
            $first = $first ?? $k;
            if ($i['due'] <= $today) {
                $target = $k;
            }
        }
        $k = $target ?? $first;
        if ($k === null) {
            return 0.0;
        }
        $need = min($k * $monthly, $schedTot) - $schedPaid;

        return round(min(max($need, 0.0), (float) $loan['outstanding']), 2);
    }

    public function recentPayments(int $clientId, int $limit = 25): array
    {
        return $this->db->query(
            "SELECT t.txn_date, t.txn_no, t.reference, t.method, t.amount, t.category, l.loan_no, l.loan_type
               FROM transactions t LEFT JOIN loans l ON l.id = t.loan_id
              WHERE t.client_id = ? AND t.status = 'posted' AND t.category = 'loan_payment'
              ORDER BY t.txn_date DESC, t.id DESC LIMIT " . (int) $limit,
            [$clientId]
        )->getResultArray();
    }

    public function upcomingSchedule(array $loan, int $max = 6): array
    {
        $out = [];
        foreach ($loan['installments'] as $k => $i) {
            if ($i['state'] === 'paid') {
                continue;
            }
            $out[] = ['no' => $k, 'due' => $i['due'], 'state' => $i['state']];
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }
}
