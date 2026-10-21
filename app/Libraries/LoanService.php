<?php

namespace App\Libraries;

use Config\Loaning;

/**
 * All of the money rules live here so that controllers, the seeder, the
 * reminder engine and the reports can never disagree with each other.
 *
 *  - Interest is a flat % of the principal for the whole term.
 *  - Monthly due = (principal + interest) / term.
 *  - Each loan product fixes rate, limits and release deductions (Config\Loaning::$loanProducts).
 *  - A loan application is credit-evaluated automatically (evaluate()): hard stops block it,
 *    soft flags go to the Manager with the approval request.
 *  - At release the service fee, insurance and CBU retention are deducted from the proceeds.
 *  - A late installment earns a penalty (assessPenalties()); a payment settles
 *    penalties first, then interest and principal.
 *  - A loan's balance, status, next due date and 1st/2nd/3rd installment
 *    states are always DERIVED from the posted transactions (see metrics()).
 */
class LoanService
{
    protected $db;
    protected Loaning $cfg;

    public function __construct()
    {
        helper('loaning');
        $this->db  = db_connect();
        $this->cfg = config('Loaning');
    }

    // =====================================================================
    //  Pure calculations
    // =====================================================================

    /** Automatic calculation of interest, total payable and monthly due. */
    public static function terms(float $principal, float $ratePct, int $term): array
    {
        $interest = round($principal * $ratePct / 100, 2);
        $total    = round($principal + $interest, 2);
        $monthly  = $term > 0 ? round($total / $term, 2) : $total;

        return ['interest' => $interest, 'total' => $total, 'monthly' => $monthly];
    }

    /**
     * Decorates a loans row with everything derived from it: outstanding
     * balance, status, next due date, days overdue, installment states,
     * payment/collection status and the collection stage.
     */
    public static function metrics(array $loan, ?string $today = null): array
    {
        helper('loaning');
        $cfg   = config('Loaning');
        $today = $today ?: date('Y-m-d');

        $paid        = (float) $loan['paid_total'];
        $adj         = (float) $loan['adjustment_total'];
        $total       = (float) $loan['total_payable'];
        $outstanding = round($total + $adj - $paid, 2);

        $m                      = $loan;
        $penDue                 = max(0.0, round((float) ($loan['penalty_total'] ?? 0) - (float) ($loan['penalty_paid'] ?? 0), 2));
        $m['outstanding']       = max($outstanding, 0.0);
        $m['penalty_due']       = $penDue;
        $m['payoff']            = round(max($outstanding, 0.0) + $penDue, 2);
        $m['status']            = $loan['approval_status'];
        $m['next_due']          = null;
        $m['next_k']            = null;
        $m['days_overdue']      = 0;
        $m['installments']      = [];
        $m['payment_status']    = '—';
        $m['collection_status'] = '—';
        $m['stage_detail']      = '';
        $m['stage_action']      = '';
        $m['r1_date']           = null;
        $m['r2_date']           = null;
        $m['c1_date']           = null;
        $m['c2_date']           = null;

        if ($loan['approval_status'] !== 'approved') {
            return $m;
        }

        $terms        = max(1, (int) $loan['sched_terms']);
        $monthly      = (float) $loan['monthly_due'];
        $schedPaid    = $paid - (float) $loan['sched_offset'];
        $schedTotal   = (float) $loan['sched_total'];
        $restructured = (int) $loan['is_restructured'] === 1;
        $grace        = $cfg->restructuredGraceDays;

        $firstUnpaid = null;
        $inst        = [];
        for ($k = 1; $k <= $terms; $k++) {
            $due    = add_months($loan['first_due_date'], $k - 1);
            $need   = $k === $terms ? $schedTotal : min($k * $monthly, $schedTotal);
            $isPaid = ($schedPaid + 0.005) >= $need;

            if ($isPaid) {
                $state = 'paid';
            } else {
                if ($firstUnpaid === null) {
                    $firstUnpaid = $k;
                }
                $late = days_between($due, $today);
                if ($late > 0) {
                    $state = ($restructured && $late <= $grace) ? 'restructured' : 'pastdue';
                } else {
                    $state = $restructured ? 'restructured' : 'current';
                }
            }
            $inst[$k] = ['due' => $due, 'state' => $state];
        }
        $m['installments'] = $inst;

        if ($outstanding <= 0.005 && $penDue > 0.005) {
            // principal + interest are settled but a penalty balance is still owed
            $m['status']            = 'current';
            $m['payment_status']    = 'Penalty balance';
            $m['collection_status'] = 'Unreceived';
            $m['stage_detail']      = 'Penalty balance';
            $m['stage_action']      = 'Collect penalty';

            return $m;
        }
        if ($outstanding <= 0.005) {
            $m['status']            = 'paid';
            $m['payment_status']    = 'Paid';
            $m['collection_status'] = 'Received';
            $m['stage_detail']      = 'Settled';
            $m['stage_action']      = 'Settled';

            return $m;
        }

        $k    = $firstUnpaid ?? $terms;
        $due  = $inst[$k]['due'];
        $days = max(0, days_between($due, $today));

        $m['next_due']     = $due;
        $m['next_k']       = $k;
        $m['days_overdue'] = $days;
        $m['status']       = $days > 0
            ? (($restructured && $days <= $grace) ? 'restructured' : 'pastdue')
            : ($restructured ? 'restructured' : 'current');

        $m['r1_date'] = add_days($due, $cfg->reminder1Days);
        $m['r2_date'] = add_days($due, $cfg->reminder2Days);
        $m['c1_date'] = add_days($due, $cfg->collection1Days);
        $m['c2_date'] = add_days($due, $cfg->collection2Days);

        if ($days > 0) {
            $m['payment_status']    = 'Unpaid';
            $m['collection_status'] = 'Unreceived';

            if ($days >= $cfg->remedialDays) {
                [$m['stage_detail'], $m['stage_action']] = ['Remedial', 'Small Claims'];
            } elseif ($days >= $cfg->collection2Days) {
                [$m['stage_detail'], $m['stage_action']] = ['Collection', '2nd collection letter'];
            } elseif ($days >= $cfg->collection1Days) {
                [$m['stage_detail'], $m['stage_action']] = ['Collection', '1st collection letter'];
            } elseif ($days >= $cfg->reminder2Days) {
                [$m['stage_detail'], $m['stage_action']] = ['Reminder', '2nd reminder sent'];
            } elseif ($days >= $cfg->reminder1Days) {
                [$m['stage_detail'], $m['stage_action']] = ['Reminder', '1st reminder sent'];
            } else {
                [$m['stage_detail'], $m['stage_action']] = ['Follow-up', 'Monitor'];
            }
        }

        return $m;
    }

    /** Rolls a client's loans up into one status / classification. */
    public static function summarize(array $loans): array
    {
        helper('loaning');
        $best   = 2; // 0 past due, 1 restructured, 2 current
        $days   = 0;
        $out    = 0.0;
        $next   = null;
        $active = 0;

        foreach ($loans as $l) {
            if (! in_array($l['status'], ['pastdue', 'restructured', 'current'], true)) {
                continue;
            }
            $active++;
            $out  += $l['outstanding'];
            $days  = max($days, $l['days_overdue']);
            $best  = min($best, status_rank($l['status']));
            if ($l['next_due'] && ($next === null || $l['next_due'] < $next)) {
                $next = $l['next_due'];
            }
        }
        $status = ['pastdue', 'restructured', 'current'][$best];

        return [
            'status'         => $status,
            'days_overdue'   => $days,
            'outstanding'    => $out,
            'next_due'       => $next,
            'active_loans'   => $active,
            'classification' => classification($status),
        ];
    }

    // =====================================================================
    //  Reading
    // =====================================================================

    public function allLoans(?int $clientId = null, ?string $today = null): array
    {
        $b = $this->db->table('loans l')
            ->select('l.*, c.name AS client_name, c.acct_no, c.email AS client_email')
            ->join('clients c', 'c.id = l.client_id');
        if ($clientId) {
            $b->where('l.client_id', $clientId);
        }
        $rows = $b->orderBy('l.id')->get()->getResultArray();

        return array_map(fn ($r) => self::metrics($r, $today), $rows);
    }

    /** Every client with status / classification, sorted past due → restructured → current. */
    public function clientsOverview(): array
    {
        $clients = $this->db->table('clients')->orderBy('name')->get()->getResultArray();
        $by      = [];
        foreach ($this->allLoans() as $l) {
            $by[$l['client_id']][] = $l;
        }
        $last = [];
        $rows = $this->db->query("SELECT client_id, MAX(txn_date) AS d FROM transactions WHERE category = 'loan_payment' AND status = 'posted' GROUP BY client_id")->getResultArray();
        foreach ($rows as $r) {
            $last[$r['client_id']] = $r['d'];
        }

        $out = [];
        foreach ($clients as $c) {
            $loans            = $by[$c['id']] ?? [];
            $row              = array_merge($c, self::summarize($loans));
            $row['loans']     = $loans;
            $row['last_payment'] = $last[$c['id']] ?? null;
            $out[]            = $row;
        }

        usort($out, static function ($a, $b) {
            return [status_rank($a['status']), -$a['days_overdue'], $a['name']]
               <=> [status_rank($b['status']), -$b['days_overdue'], $b['name']];
        });

        return $out;
    }

    public function ledgerBalance(int $clientId, string $type): float
    {
        $r = $this->db->query(
            "SELECT COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) AS bal
               FROM transactions
              WHERE client_id = ? AND ledger_type = ? AND status = 'posted' AND direction IN ('in', 'out')",
            [$clientId, $type]
        )->getRowArray();

        return round((float) $r['bal'], 2);
    }

    public function setting(string $name, string $default = '0'): string
    {
        $row = $this->db->table('settings')->where('name', $name)->get()->getRowArray();

        return $row ? (string) $row['value'] : $default;
    }

    public function saveSetting(string $name, string $value): void
    {
        $exists = $this->db->table('settings')->where('name', $name)->countAllResults();
        if ($exists) {
            $this->db->table('settings')->where('name', $name)->update(['value' => $value]);
        } else {
            $this->db->table('settings')->insert(['name' => $name, 'value' => $value]);
        }
    }

    // =====================================================================
    //  Clients & ledger accounts
    // =====================================================================

    public function nextAcctNo(): string
    {
        $row = $this->db->query("SELECT MAX(CAST(SUBSTRING(acct_no, 4) AS UNSIGNED)) AS n FROM clients")->getRowArray();
        $n   = (int) ($row['n'] ?? 0) + 1;

        return 'CL-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public function createClient(array $in, string $by): array
    {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) < 3) {
            return $this->fail('Enter the client\'s full name (Last name, First name).');
        }
        if ($this->db->table('clients')->where('name', $name)->countAllResults() > 0) {
            return $this->fail('A client named "' . $name . '" already exists.');
        }
        $email = trim((string) ($in['email'] ?? ''));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('That e-mail address does not look right.');
        }

        $income = trim(str_replace(',', '', (string) ($in['monthly_income'] ?? '')));
        if ($income !== '' && (! is_numeric($income) || (float) $income < 0)) {
            return $this->fail('Monthly income must be a number (or leave it blank).');
        }

        $acct = trim((string) ($in['acct_no'] ?? '')) ?: $this->nextAcctNo();
        $this->db->table('clients')->insert([
            'acct_no'    => $acct,
            'name'       => $name,
            'contact'    => trim((string) ($in['contact'] ?? '')) ?: null,
            'email'      => $email ?: null,
            'address'    => trim((string) ($in['address'] ?? '')) ?: null,
            'co_maker'   => trim((string) ($in['co_maker'] ?? '')) ?: null,
            'occupation' => trim((string) ($in['occupation'] ?? '')) ?: null,
            'monthly_income' => $income !== '' ? round((float) $income, 2) : null,
            'opened_at'  => $in['opened_at'] ?? date('Y-m-d'),
            'created_by' => $by,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $this->db->insertID();
        Audit::log('Added client account', $name . ' (' . $acct . ')', $by);

        return ['ok' => true, 'id' => $id, 'acct_no' => $acct];
    }

    public function ensureLedger(int $clientId, string $type, string $by): void
    {
        $exists = $this->db->table('ledger_accounts')->where('client_id', $clientId)->where('ledger_type', $type)->countAllResults();
        if ($exists) {
            return;
        }
        $client = $this->db->table('clients')->where('id', $clientId)->get()->getRowArray();
        $prefix = $this->cfg->ledgerTypes[$type]['prefix'];
        $this->db->table('ledger_accounts')->insert([
            'client_id'   => $clientId,
            'ledger_type' => $type,
            'account_no'  => $prefix . '-' . substr($client['acct_no'], 3),
            'opened_at'   => date('Y-m-d'),
            'created_by'  => $by,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function addLedgerAccount(int $clientId, string $type, float $opening, string $by): array
    {
        if (! isset($this->cfg->ledgerTypes[$type])) {
            return $this->fail('Choose a ledger account type.');
        }
        $client = $this->db->table('clients')->where('id', $clientId)->get()->getRowArray();
        if (! $client) {
            return $this->fail('Client not found.');
        }
        $label = $this->cfg->ledgerTypes[$type]['label'];
        if ($this->db->table('ledger_accounts')->where('client_id', $clientId)->where('ledger_type', $type)->countAllResults()) {
            return $this->fail($client['name'] . ' already has a ' . $label . ' ledger account.');
        }
        if ($opening < 0) {
            return $this->fail('Opening balance cannot be negative.');
        }

        $this->ensureLedger($clientId, $type, $by);
        if ($opening > 0) {
            $res = $this->recordTransaction([
                'client_id' => $clientId,
                'category'  => $this->cfg->ledgerTypes[$type]['deposit'],
                'amount'    => $opening,
                'method'    => 'cash',
                'remarks'   => 'Opening balance',
            ], $by);
            if (! $res['ok']) {
                return $res;
            }
        }
        Audit::log('Added ledger account', $label . ' for ' . $client['name'] . ($opening > 0 ? ' — opening ' . peso($opening) : ''), $by);

        return ['ok' => true];
    }

    // =====================================================================
    //  Loans
    // =====================================================================

    /**
     * Credit evaluation of a loan application.
     *   blocks = hard stops (the application cannot be filed)
     *   flags  = things the Manager must look at before approving
     *
     * @return array{blocks:list<string>, flags:list<string>, dsr:?float, monthly_dues:float}
     */
    public function evaluate(array $client, string $type, float $principal, int $term, string $collateral, float $newMonthly): array
    {
        $cfg     = $this->cfg;
        $product = $cfg->loanProducts[$type] ?? null;
        $blocks  = [];
        $flags   = [];
        if (! $product) {
            return ['blocks' => ['Choose a loan type.'], 'flags' => [], 'dsr' => null, 'monthly_dues' => 0.0];
        }

        $active  = [];
        $monthly = 0.0;
        foreach ($this->allLoans((int) $client['id']) as $l) {
            if ($l['status'] === 'pending' && $l['loan_type'] === $type) {
                $blocks[] = 'There is already a pending ' . $type . ' application (' . $l['loan_no'] . ') for this client.';
            }
            if (in_array($l['status'], ['pastdue', 'restructured', 'current'], true)) {
                $active[] = $l;
                $monthly += min((float) $l['monthly_due'], (float) $l['payoff']);
                if ($l['status'] === 'pastdue') {
                    $blocks[] = 'This client has a past-due loan (' . $l['loan_no'] . '). Settle or restructure it before a new loan.';
                }
            }
        }
        if (count($active) >= $cfg->maxActiveLoans) {
            $blocks[] = 'This client already has ' . count($active) . ' open loans (limit ' . $cfg->maxActiveLoans . ').';
        }
        if ($principal < $product['min'] || $principal > $product['max']) {
            $blocks[] = $type . ' principal must be between ' . peso($product['min']) . ' and ' . peso($product['max']) . '.';
        }
        if ($term > $product['max_term']) {
            $blocks[] = $type . ' can run for at most ' . $product['max_term'] . ' months.';
        }
        if (! empty($product['collateral']) && trim($collateral) === '') {
            $blocks[] = $type . ' needs a collateral description (e.g. unit, plate / chassis no.).';
        }

        if ($principal >= $cfg->coMakerThreshold && trim((string) ($client['co_maker'] ?? '')) === '') {
            $flags[] = 'No co-maker on file for a loan of ' . peso($principal) . ' or more.';
        }
        $dsr    = null;
        $income = (float) ($client['monthly_income'] ?? 0);
        if ($income > 0) {
            $dsr = round(($monthly + $newMonthly) / $income * 100, 1);
            if ($dsr > $cfg->maxDebtServicePct) {
                $flags[] = 'Monthly dues would be ' . $dsr . '% of the client\'s income (guideline ' . rtrim(rtrim(number_format($cfg->maxDebtServicePct, 1), '0'), '.') . '%).';
            }
        } else {
            $flags[] = 'No monthly income on file, so repayment capacity could not be checked.';
        }
        if ($cfg->loanToCbuMultiple > 0) {
            $cbu = $this->ledgerBalance((int) $client['id'], 'cbu');
            if ($principal > $cbu * $cfg->loanToCbuMultiple) {
                $flags[] = 'Principal is above ' . rtrim(rtrim(number_format($cfg->loanToCbuMultiple, 1), '0'), '.') . '× the client\'s Capital Build Up (' . peso($cbu) . ').';
            }
        }
        foreach ($active as $l) {
            if ((int) $l['is_restructured'] === 1) {
                $flags[] = 'Client has a restructured loan (' . $l['loan_no'] . ').';
                break;
            }
        }

        return ['blocks' => array_values(array_unique($blocks)), 'flags' => $flags, 'dsr' => $dsr, 'monthly_dues' => round($monthly, 2)];
    }

    public function createLoan(array $in, string $by): array
    {
        $clientId = (int) ($in['client_id'] ?? 0);
        $client   = $this->db->table('clients')->where('id', $clientId)->get()->getRowArray();
        if (! $client) {
            return $this->fail('Client not found.');
        }
        $type    = (string) ($in['loan_type'] ?? '');
        $product = $this->cfg->loanProducts[$type] ?? null;
        if (! $product) {
            return $this->fail('Choose a loan type.');
        }
        $skip      = ! empty($in['_skip_checks']); // demo seeder only
        $principal = round((float) str_replace(',', '', (string) ($in['principal'] ?? 0)), 2);
        // the rate belongs to the product; staff cannot quote their own
        $rate = ($skip && isset($in['interest_rate'])) ? (float) $in['interest_rate'] : (float) $product['rate'];
        $term = (int) ($in['term_months'] ?? 0);
        if ($principal <= 0) {
            return $this->fail('Enter the amount applied for.');
        }
        if ($term < 1 || $term > 120) {
            return $this->fail('Term must be between 1 and 120 months.');
        }
        $purpose    = trim((string) ($in['purpose'] ?? ''));
        $collateral = trim((string) ($in['collateral'] ?? ''));
        if (! $skip && mb_strlen($purpose) < 5) {
            return $this->fail('State the purpose of the loan.');
        }
        $release = (string) ($in['release_date'] ?? date('Y-m-d'));
        if (! $this->validDate($release)) {
            return $this->fail('Enter a valid release date.');
        }
        $first = (string) ($in['first_due_date'] ?? '');
        if ($first === '') {
            $first = add_months($release, 1);
        }
        if (! $this->validDate($first) || $first <= $release) {
            return $this->fail('The first due date must be after the release date.');
        }
        $method = ($in['release_method'] ?? 'cash') === 'check' ? 'check' : 'cash';

        $t  = self::terms($principal, $rate, $term);
        $ev = $this->evaluate($client, $type, $principal, $term, $collateral, $t['monthly']);
        if (! $skip && $ev['blocks']) {
            return $this->fail(implode(' ', $ev['blocks']));
        }

        // deductions taken from the proceeds at release
        $fee  = round($principal * (float) $product['service_fee'] / 100, 2);
        $ins  = round($principal * (float) $product['insurance'] / 100, 2);
        $cbu  = round($principal * (float) $product['cbu'] / 100, 2);
        $net  = round($principal - $fee - $ins - $cbu, 2);

        $required = 'manager';
        $now      = date('Y-m-d H:i:s');

        $this->db->transStart();
        $this->db->table('loans')->insert([
            'loan_no'         => 'TMP-' . uniqid(),
            'client_id'       => $clientId,
            'loan_type'       => $type,
            'purpose'         => $purpose ?: null,
            'collateral'      => $collateral ?: null,
            'principal'       => $principal,
            'interest_rate'   => $rate,
            'interest_amount' => $t['interest'],
            'term_months'     => $term,
            'total_payable'   => $t['total'],
            'monthly_due'     => $t['monthly'],
            'release_date'    => $release,
            'first_due_date'  => $first,
            'release_method'  => $method,
            'service_fee'     => $fee,
            'insurance_fee'   => $ins,
            'cbu_retention'   => $cbu,
            'net_proceeds'    => $net,
            'evaluation'      => json_encode(['flags' => $ev['flags'], 'dsr' => $ev['dsr'], 'monthly_dues' => $ev['monthly_dues']]),
            'sched_terms'     => $term,
            'sched_total'     => $t['total'],
            'sched_offset'    => 0,
            'paid_total'      => 0,
            'adjustment_total' => 0,
            'approval_status' => 'pending',
            'required_role'   => $required,
            'remarks'         => trim((string) ($in['remarks'] ?? '')) ?: null,
            'created_by'      => $by,
            'created_at'      => $now,
        ]);
        $loanId = (int) $this->db->insertID();
        $loanNo = 'LN-' . date('Y') . '-' . str_pad((string) $loanId, 4, '0', STR_PAD_LEFT);
        $this->db->table('loans')->where('id', $loanId)->update(['loan_no' => $loanNo]);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return $this->fail('Could not save the loan. Please try again.');
        }

        $approvals = new ApprovalService();
        $approvals->request(
            'loan_release',
            $loanId,
            $clientId,
            $type . ' for ' . $client['name'] . ' — ' . peso($principal) . ' / ' . $term . ' mo (' . $loanNo . ')' . ($ev['flags'] ? ' · ' . count($ev['flags']) . ' flag' . (count($ev['flags']) === 1 ? '' : 's') : ''),
            $principal,
            $required,
            ['flags' => $ev['flags'], 'purpose' => $purpose, 'collateral' => $collateral, 'net_proceeds' => $net, 'dsr' => $ev['dsr']]
        );
        Audit::log('Filed loan application', $loanNo . ' · ' . $type . ' · ' . $client['name'] . ' · ' . peso($principal), $by);

        return ['ok' => true, 'id' => $loanId, 'loan_no' => $loanNo, 'required_role' => $required, 'flags' => $ev['flags'], 'net_proceeds' => $net];
    }

    /** Called by the approval workflow once the right person signs off. */
    public function releaseLoan(int $loanId, string $approver): array
    {
        $loan = $this->db->table('loans')->where('id', $loanId)->get()->getRowArray();
        if (! $loan || $loan['approval_status'] !== 'pending') {
            return $this->fail('This loan is not waiting for approval.');
        }

        $this->db->transStart();
        $this->db->table('loans')->where('id', $loanId)->update([
            'approval_status' => 'approved',
            'approved_by'     => $approver,
            'approved_at'     => date('Y-m-d H:i:s'),
        ]);
        $this->insertTxn([
            'txn_date'    => $loan['release_date'],
            'client_id'   => (int) $loan['client_id'],
            'loan_id'     => $loanId,
            'category'    => 'loan_release',
            'direction'   => 'out',
            'amount'      => $loan['principal'],
            'method'      => $loan['release_method'],
            'reference'   => 'DV-' . $loan['loan_no'],
            'remarks'     => 'Loan release — ' . $loan['loan_type'],
            'recorded_by' => $approver,
        ]);
        // deductions come out of the proceeds: income, the client's Insurance ledger and Capital Build Up
        foreach ([
            ['service_fee', (float) $loan['service_fee'], null, 'Service fee'],
            ['insurance_payment', (float) $loan['insurance_fee'], 'insurance', 'Loan insurance'],
            ['cbu_deposit', (float) $loan['cbu_retention'], 'cbu', 'Capital Build Up retention'],
        ] as [$cat, $amt, $ledger, $label]) {
            if ($amt <= 0.004) {
                continue;
            }
            if ($ledger) {
                $this->ensureLedger((int) $loan['client_id'], $ledger, $approver);
            }
            $this->insertTxn([
                'txn_date'    => $loan['release_date'],
                'client_id'   => (int) $loan['client_id'],
                'loan_id'     => $loanId,
                'category'    => $cat,
                'ledger_type' => $ledger,
                'direction'   => 'in',
                'amount'      => $amt,
                'method'      => $loan['release_method'],
                'reference'   => 'DED-' . $loan['loan_no'] . '-' . strtoupper(substr($cat, 0, 3)),
                'remarks'     => $label . ' deducted from the proceeds of ' . $loan['loan_no'],
                'recorded_by' => $approver,
            ]);
        }
        $this->db->transComplete();

        return $this->db->transStatus() ? ['ok' => true] : $this->fail('Could not release the loan.');
    }

    public function rejectLoan(int $loanId): void
    {
        $this->db->table('loans')->where('id', $loanId)->update(['approval_status' => 'rejected']);
    }

    /** Re-bases the payment schedule on what is still owed (after Manager approval). */
    public function applyRestructure(int $loanId, int $newTerm, string $firstDue, string $by): array
    {
        $loan = $this->db->table('loans')->where('id', $loanId)->get()->getRowArray();
        if (! $loan || $loan['approval_status'] !== 'approved') {
            return $this->fail('Only released loans can be restructured.');
        }
        $m = self::metrics($loan);
        if ($m['outstanding'] <= 0.005) {
            return $this->fail('This loan is already fully paid.');
        }
        $this->db->table('loans')->where('id', $loanId)->update([
            'is_restructured' => 1,
            'restructured_at' => date('Y-m-d'),
            'sched_terms'     => $newTerm,
            'sched_total'     => $m['outstanding'],
            'sched_offset'    => $loan['paid_total'],
            'monthly_due'     => round($m['outstanding'] / $newTerm, 2),
            'first_due_date'  => $firstDue,
        ]);
        Audit::log('Restructured loan', $loan['loan_no'] . ' → ' . $newTerm . ' months, balance ' . peso($m['outstanding']), $by);

        return ['ok' => true];
    }

    public function refreshLoan(int $loanId): void
    {
        $p = $this->db->query(
            "SELECT COALESCE(SUM(amount - penalty_part), 0) AS paid, COALESCE(SUM(penalty_part), 0) AS pen, MAX(txn_date) AS last_date
               FROM transactions WHERE loan_id = ? AND category = 'loan_payment' AND status = 'posted'",
            [$loanId]
        )->getRowArray();
        $a  = $this->db->query("SELECT COALESCE(SUM(adj_sign * amount), 0) AS adj FROM transactions WHERE loan_id = ? AND category = 'adjustment' AND status = 'posted'", [$loanId])->getRowArray();
        $pt = $this->db->query('SELECT COALESCE(SUM(amount - waived_amount), 0) AS t FROM penalties WHERE loan_id = ?', [$loanId])->getRowArray();
        $this->db->table('loans')->where('id', $loanId)->update([
            'paid_total'       => round((float) $p['paid'], 2),
            'adjustment_total' => round((float) $a['adj'], 2),
            'penalty_total'    => round((float) $pt['t'], 2),
            'penalty_paid'     => round((float) $p['pen'], 2),
        ]);

        // closing the loan: the date it was fully settled (blank again if a payment is cancelled)
        $loan = $this->db->table('loans')->where('id', $loanId)->get()->getRowArray();
        if ($loan && $loan['approval_status'] === 'approved') {
            $m = self::metrics($loan);
            if ($m['status'] === 'paid' && ! $loan['closed_at']) {
                $this->db->table('loans')->where('id', $loanId)->update(['closed_at' => $p['last_date'] ?: date('Y-m-d')]);
                Audit::log('Loan fully settled', $loan['loan_no'], current_user_name());
            } elseif ($m['status'] !== 'paid' && $loan['closed_at']) {
                $this->db->table('loans')->where('id', $loanId)->update(['closed_at' => null]);
            }
        }
    }

    // =====================================================================
    //  Schedule, penalties
    // =====================================================================

    /** What the schedule must have collected by the end of installment $k (the last one absorbs rounding). */
    protected static function needBy(int $k, int $terms, float $monthly, float $schedTotal): float
    {
        return $k >= $terms ? $schedTotal : min($k * $monthly, $schedTotal);
    }

    /**
     * Amortization schedule of a released loan: every installment with its principal/interest split,
     * what was paid toward it, any penalty assessed on it, and its state.
     */
    public function schedule(array $loan, ?string $today = null): array
    {
        $today     = $today ?: date('Y-m-d');
        $m         = self::metrics($loan, $today);
        $terms     = max(1, (int) $loan['sched_terms']);
        $monthly   = (float) $loan['monthly_due'];
        $schedTot  = (float) $loan['sched_total'];
        $schedPaid = max(0.0, (float) $loan['paid_total'] - (float) $loan['sched_offset']);
        $den       = (float) $loan['total_payable'];
        $ratio     = $den > 0 ? max(0.0, ($den - (float) $loan['principal']) / $den) : 0.0;

        $pen = [];
        foreach ($this->db->query('SELECT due_date, SUM(amount - waived_amount) AS p FROM penalties WHERE loan_id = ? GROUP BY due_date', [(int) $loan['id']])->getResultArray() as $r) {
            $pen[$r['due_date']] = round((float) $r['p'], 2);
        }

        $rows    = [];
        $balance = $schedTot;
        for ($k = 1; $k <= $terms; $k++) {
            $due     = add_months($loan['first_due_date'], $k - 1);
            $before  = $k === 1 ? 0.0 : self::needBy($k - 1, $terms, $monthly, $schedTot);
            $amount  = round(self::needBy($k, $terms, $monthly, $schedTot) - $before, 2);
            $interest = round($amount * $ratio, 2);
            $paid    = round(min($amount, max(0.0, $schedPaid - $before)), 2);
            $balance = round($balance - $amount, 2);
            $rows[]  = [
                'no'        => $k,
                'due'       => $due,
                'amount'    => $amount,
                'principal' => round($amount - $interest, 2),
                'interest'  => $interest,
                'paid'      => $paid,
                'balance'   => max(0.0, $balance),
                'penalty'   => $pen[$due] ?? 0.0,
                'state'     => $m['installments'][$k]['state'] ?? 'current',
            ];
        }

        return $rows;
    }

    /**
     * Charges the late-payment penalty. Every installment that is still unpaid more than
     * penaltyGraceDays after its due date earns penaltyRatePct % of the unpaid amount, and
     * again for every further 30 days it stays late. Safe to run any number of times:
     * a penalty month is only ever assessed once.
     *
     * @return int number of new penalty rows
     */
    public function assessPenalties(int $loanId, ?string $asOf = null): int
    {
        $rate = $this->cfg->penaltyRatePct;
        $loan = $this->db->table('loans')->where('id', $loanId)->get()->getRowArray();
        if ($rate <= 0 || ! $loan || $loan['approval_status'] !== 'approved') {
            return 0;
        }
        $asOf      = $asOf ?: date('Y-m-d');
        $terms     = max(1, (int) $loan['sched_terms']);
        $monthly   = (float) $loan['monthly_due'];
        $schedTot  = (float) $loan['sched_total'];
        $schedPaid = (float) $loan['paid_total'] - (float) $loan['sched_offset'];
        $grace     = $this->cfg->penaltyGraceDays;
        if ((int) $loan['is_restructured'] === 1) {
            $grace = max($grace, $this->cfg->restructuredGraceDays);
        }

        $new = 0;
        for ($k = 1; $k <= $terms; $k++) {
            $due    = add_months($loan['first_due_date'], $k - 1);
            $late   = days_between($due, $asOf);
            if ($late <= $grace) {
                break; // later installments are even less late
            }
            $before = $k === 1 ? 0.0 : self::needBy($k - 1, $terms, $monthly, $schedTot);
            $amount = self::needBy($k, $terms, $monthly, $schedTot) - $before;
            $unpaid = round(min($amount, max(0.0, self::needBy($k, $terms, $monthly, $schedTot) - $schedPaid)), 2);
            if ($unpaid <= 0.005) {
                continue;
            }
            $months = min($this->cfg->penaltyMaxMonths, (int) floor(($late - $grace - 1) / 30) + 1);
            for ($n = 1; $n <= $months; $n++) {
                $exists = $this->db->table('penalties')->where(['loan_id' => $loanId, 'due_date' => $due, 'month_no' => $n])->countAllResults();
                if ($exists) {
                    continue;
                }
                $this->db->table('penalties')->insert([
                    'loan_id'     => $loanId,
                    'due_date'    => $due,
                    'month_no'    => $n,
                    'base_amount' => $unpaid,
                    'amount'      => round($unpaid * $rate / 100, 2),
                    'assessed_on' => add_days($due, $grace + 1 + ($n - 1) * 30),
                ]);
                $new++;
            }
        }
        if ($new > 0) {
            $this->refreshLoan($loanId);
            Audit::log('Late-payment penalty assessed', $loan['loan_no'] . ' · ' . $new . ' charge' . ($new === 1 ? '' : 's'), 'System');
        }

        return $new;
    }

    /** Assess penalties on every open loan (dashboard, reminders page, nightly cron). */
    public function assessAllPenalties(?string $asOf = null): int
    {
        $n = 0;
        foreach ($this->db->table('loans')->select('id')->where('approval_status', 'approved')->get()->getResultArray() as $r) {
            $n += $this->assessPenalties((int) $r['id'], $asOf);
        }

        return $n;
    }

    /**
     * Manager-only. Writes off part or all of the penalty still owed on a loan. The newest charges
     * are waived first, because payments always settle the oldest charges first.
     */
    public function waivePenalties(int $loanId, float $amount, string $reason, string $by): array
    {
        $loan = $this->db->table('loans')->where('id', $loanId)->get()->getRowArray();
        if (! $loan || $loan['approval_status'] !== 'approved') {
            return $this->fail('Loan not found.');
        }
        $due = max(0.0, round((float) $loan['penalty_total'] - (float) $loan['penalty_paid'], 2));
        if ($due <= 0.005) {
            return $this->fail('This loan has no unpaid penalty.');
        }
        $amount = round($amount, 2);
        if ($amount <= 0 || $amount > $due + 0.005) {
            return $this->fail('Enter an amount between ₱0.01 and ' . peso($due) . '.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            return $this->fail('Give the reason for waiving (at least 10 characters).');
        }

        $left = $amount;
        $rows = $this->db->table('penalties')->where('loan_id', $loanId)->orderBy('due_date', 'DESC')->orderBy('month_no', 'DESC')->get()->getResultArray();
        $this->db->transStart();
        foreach ($rows as $r) {
            if ($left <= 0.004) {
                break;
            }
            $room = round((float) $r['amount'] - (float) $r['waived_amount'], 2);
            if ($room <= 0.004) {
                continue;
            }
            $take = min($room, $left);
            $this->db->table('penalties')->where('id', $r['id'])->update([
                'waived_amount' => round((float) $r['waived_amount'] + $take, 2),
                'waive_reason'  => trim($reason),
                'waived_by'     => $by,
                'waived_at'     => date('Y-m-d H:i:s'),
            ]);
            $left = round($left - $take, 2);
        }
        $this->db->transComplete();
        $this->refreshLoan($loanId);
        Audit::log('Waived penalty', $loan['loan_no'] . ' · ' . peso($amount) . ' — ' . trim($reason), $by);

        return ['ok' => true];
    }

    // =====================================================================
    //  Transactions
    // =====================================================================

    /** Low-level insert that also stamps txn_no / reference. */
    public function insertTxn(array $row): int
    {
        $row += [
            'client_id'        => null,
            'loan_id'          => null,
            'ledger_type'      => null,
            'direction'        => 'in',
            'adj_sign'         => 1,
            'method'           => null,
            'reference'        => null,
            'remarks'          => null,
            'principal_part'   => 0,
            'interest_part'    => 0,
            'penalty_part'     => 0,
            'status'           => 'posted',
            'cancel_requested' => 0,
            'recorded_by'      => current_user_name(),
            'created_at'       => date('Y-m-d H:i:s'),
        ];
        $row['txn_no'] = 'TMP-' . uniqid();
        $this->db->table('transactions')->insert($row);
        $id = (int) $this->db->insertID();

        $upd = ['txn_no' => 'TX-' . date('ymd', strtotime($row['txn_date'])) . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT)];
        if (empty($row['reference'])) {
            $prefix = $row['direction'] === 'out' ? 'CV-' : 'OR-';
            if ($row['direction'] !== 'none') {
                $upd['reference'] = $prefix . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            }
        }
        $this->db->table('transactions')->where('id', $id)->update($upd);

        return $id;
    }

    public function recordTransaction(array $in, string $by): array
    {
        $cats = $this->cfg->txnCategories;
        $cat  = (string) ($in['category'] ?? '');
        if (! isset($cats[$cat]) || $cat === 'loan_release') {
            return $this->fail('Choose a transaction type.');
        }
        $clientId = (int) ($in['client_id'] ?? 0);
        $client   = $this->db->table('clients')->where('id', $clientId)->get()->getRowArray();
        if (! $client) {
            return $this->fail('Select a client.');
        }
        $amount = round((float) str_replace(',', '', (string) ($in['amount'] ?? 0)), 2);
        if ($amount <= 0) {
            return $this->fail('Amount must be greater than zero.');
        }
        $date = (string) (($in['txn_date'] ?? '') ?: date('Y-m-d'));
        if (! $this->validDate($date)) {
            return $this->fail('Enter a valid transaction date.');
        }
        if ($date > date('Y-m-d')) {
            return $this->fail('Transaction date cannot be in the future.');
        }

        $dir    = $cats[$cat]['dir'];
        $ledger = $cats[$cat]['ledger'];
        $method = $dir === 'none' ? null : (in_array($in['method'] ?? 'cash', ['check', 'online'], true) ? $in['method'] : 'cash');

        $loanId = null;
        $pp     = 0.0;
        $ip     = 0.0;
        $pen    = 0.0;
        $sign   = 1;

        if (in_array($cat, ['loan_payment', 'adjustment'], true)) {
            $loanId = (int) ($in['loan_id'] ?? 0);
            $loan   = $this->db->table('loans')->where('id', $loanId)->where('client_id', $clientId)->get()->getRowArray();
            if (! $loan || $loan['approval_status'] !== 'approved') {
                return $this->fail('Select one of this client\'s released loans.');
            }
            $m = self::metrics($loan);
            if ($cat === 'loan_payment') {
                // bring the penalties up to the payment date first, then work from the fresh balance
                $this->assessPenalties($loanId, $date);
                $loan = $this->db->table('loans')->where('id', $loanId)->get()->getRowArray();
                $m    = self::metrics($loan);
                if ($m['payoff'] <= 0.005) {
                    return $this->fail('That loan is already fully paid.');
                }
                if ($amount > $m['payoff'] + 0.005) {
                    return $this->fail('Payment is more than the ' . peso($m['payoff']) . ' needed to settle this loan (balance ' . peso($m['outstanding']) . ($m['penalty_due'] > 0 ? ' + penalty ' . peso($m['penalty_due']) : '') . ').');
                }
                // a payment settles the penalty first, then interest and principal
                $pen   = round(min($amount, $m['penalty_due']), 2);
                $rest  = round($amount - $pen, 2);
                $den   = (float) $loan['total_payable'] + (float) $loan['adjustment_total'];
                $ratio = $den > 0 ? max(0.0, ($den - (float) $loan['principal']) / $den) : 0.0;
                $ip    = round($rest * $ratio, 2);
                $pp    = round($rest - $ip, 2);
            } else {
                $sign = (($in['adj_type'] ?? 'debit') === 'credit') ? -1 : 1;
                if ($sign < 0 && $amount > $m['outstanding'] + 0.005) {
                    return $this->fail('A credit adjustment cannot be more than the outstanding balance.');
                }
                if (trim((string) ($in['remarks'] ?? '')) === '') {
                    return $this->fail('Explain the adjustment in the remarks.');
                }
            }
        }

        if ($ledger) {
            $label = $this->cfg->ledgerTypes[$ledger]['label'];
            if ($dir === 'out') {
                $bal = $this->ledgerBalance($clientId, $ledger);
                if ($amount > $bal + 0.005) {
                    return $this->fail('Not enough ' . $label . ' balance. Available: ' . peso($bal) . '.');
                }
            } else {
                $this->ensureLedger($clientId, $ledger, $by);
            }
        }

        $id = $this->insertTxn([
            'txn_date'       => $date,
            'client_id'      => $clientId,
            'loan_id'        => $loanId,
            'category'       => $cat,
            'ledger_type'    => $ledger,
            'direction'      => $dir,
            'adj_sign'       => $sign,
            'amount'         => $amount,
            'method'         => $method,
            'reference'      => trim((string) ($in['reference'] ?? '')) ?: null,
            'remarks'        => trim((string) ($in['remarks'] ?? '')) ?: null,
            'principal_part' => $pp,
            'interest_part'  => $ip,
            'penalty_part'   => $pen,
            'recorded_by'    => $by,
        ]);
        if ($loanId) {
            $this->refreshLoan($loanId);
        }
        $txn = $this->db->table('transactions')->where('id', $id)->get()->getRowArray();
        Audit::log('Recorded ' . strtolower($cats[$cat]['label']), $txn['txn_no'] . ' · ' . $client['name'] . ' · ' . peso($amount), $by);

        return ['ok' => true, 'id' => $id, 'txn_no' => $txn['txn_no']];
    }

    public function cancelTransaction(int $id, string $reason, string $by): array
    {
        $t = $this->db->table('transactions')->where('id', $id)->get()->getRowArray();
        if (! $t || $t['status'] !== 'posted') {
            return $this->fail('That transaction is not active.');
        }
        if ($t['category'] === 'loan_release') {
            return $this->fail('A loan release cannot be cancelled here. Reject the loan application instead.');
        }
        if (strncmp((string) $t['reference'], 'DED-', 4) === 0) {
            return $this->fail('That deduction is part of the loan release. Reject the loan application instead.');
        }
        if ($t['ledger_type'] && $t['direction'] === 'in') {
            $bal = $this->ledgerBalance((int) $t['client_id'], $t['ledger_type']);
            if ($bal - (float) $t['amount'] < -0.005) {
                return $this->fail('Cannot cancel: later withdrawals already used this deposit.');
            }
        }

        $this->db->table('transactions')->where('id', $id)->update([
            'status'           => 'cancelled',
            'cancel_requested' => 0,
            'cancel_reason'    => $reason,
            'cancelled_by'     => $by,
            'cancelled_at'     => date('Y-m-d H:i:s'),
        ]);
        if ($t['loan_id']) {
            $this->refreshLoan((int) $t['loan_id']);
        }
        Audit::log('Cancelled transaction', $t['txn_no'] . ' · ' . peso($t['amount']) . ' — ' . $reason, $by);

        return ['ok' => true];
    }

    // =====================================================================

    public function fail(string $msg): array
    {
        return ['ok' => false, 'error' => $msg];
    }

    protected function validDate(string $d): bool
    {
        $x = \DateTime::createFromFormat('Y-m-d', $d);

        return $x && $x->format('Y-m-d') === $d;
    }
}
