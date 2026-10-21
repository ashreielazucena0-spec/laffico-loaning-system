<?php

namespace App\Database\Seeds;

use App\Libraries\Audit;
use App\Libraries\LoanService;
use CodeIgniter\Database\Seeder;

/**
 * Demo data: 5 users, 15 clients (5 past due / 4 restructured / 6 current),
 * ledgers, payment history, bookkeeping and a few pending approvals.
 * Everything is posted through the same LoanService the app uses, and all
 * dates are relative to "today", so the statuses are always right.
 *
 *   php spark migrate
 *   php spark db:seed DemoSeeder
 */
class DemoSeeder extends Seeder
{
    private LoanService $svc;
    private array $uid = [];
    private int $docN = 0;

    private const STAFF   = 'Irisa May Abenes';
    private const MANAGER = 'Linne J. Villanueva';

    public function run(): void
    {
        helper('loaning');
        if ($this->db->table('users')->countAllResults() > 0) {
            echo "Database already has data — seeding skipped.\n";

            return;
        }
        $this->svc = new LoanService();
        $today     = date('Y-m-d');

        // ---------------- users ----------------
        $pw = password_hash('ChangeMe#2026', PASSWORD_DEFAULT);
        foreach ([
            ['admin', 'System Administrator', 'admin'],
            ['irisa', self::STAFF, 'employee'],
            ['linne', self::MANAGER, 'manager'],
            ['maria', 'Maria Santos (Employee)', 'employee'],
        ] as [$u, $n, $r]) {
            $this->db->table('users')->insert(['username' => $u, 'password_hash' => $pw, 'full_name' => $n, 'role' => $r, 'is_active' => 1, 'must_change_password' => 0, 'created_at' => date('Y-m-d H:i:s')]);
            $this->uid[$u] = (int) $this->db->insertID();
        }

        $this->svc->saveSetting('opening_cash_on_hand', '500000.00');
        $this->svc->saveSetting('opening_cash_on_bank', '500000.00');

        // ---------------- clients, loans, ledgers ----------------
        // [acct, name, contact, [ [type, principal, term, paidInstallments, overdueDays, restructure?[newTerm]] ... ]]
        $spec = [
            // PAST DUE
            ['CL-0231', 'Reyes, Marilou',      '0917-555-0231', [['Palay Production Loan', 15000, 12, 1, 1], ['Salary Loan', 5000, 10, 3, -3, [6]], ['Emergency Loan', 3000, 6, 2, -20]]],
            ['CL-0232', 'Bagares, Noel',       '0918-555-0232', [['Financial Loan', 30000, 12, 4, 46]]],
            ['CL-0233', 'Santos, Edgar',       '0919-555-0233', [['Vehicle Loan', 80000, 24, 6, 82]]],
            ['CL-0234', 'Ocampo, Nenita',      '0920-555-0234', [['Lending', 12000, 10, 2, 22]]],
            ['CL-0235', 'Mendoza, Carlo',      '0921-555-0235', [['Salary Loan', 8000, 8, 1, 8]]],
            // RESTRUCTURED
            ['CL-0236', 'Cabalquinto, Jen',    '0922-555-0236', [['Financial Loan', 25000, 12, 5, 0, [8]]]],
            ['CL-0237', 'Fernandez, Lea',      '0923-555-0237', [['Palay Production Loan', 40000, 10, 3, 5, [9]]]],
            ['CL-0238', 'Pascual, Ramon',      '0924-555-0238', [['Vehicle Loan', 60000, 24, 8, -20, [18]]]],
            ['CL-0239', 'Lim, Josefa',         '0925-555-0239', [['Lending', 10000, 10, 4, 10, [5]]]],
            // CURRENT
            ['CL-0240', 'Villaruel, Ana',      '0926-555-0240', [['Salary Loan', 12000, 12, 2, -15]]],
            ['CL-0241', 'Domingo, Rey',        '0927-555-0241', [['Emergency Loan', 4000, 4, 1, -10]]],
            ['CL-0242', 'Aquino, Marvin',      '0928-555-0242', [['Palay Production Loan', 35000, 8, 2, -25]]],
            ['CL-0243', 'Gonzales, Elena',     '0929-555-0243', [['Financial Loan', 20000, 12, 6, -5]]],
            ['CL-0244', 'Tan, Benjie',         '0930-555-0244', [['Lending', 9000, 6, 3, -18]]],
            ['CL-0245', 'Navarro, Liza',       '0931-555-0245', [['Vehicle Loan', 55000, 24, 10, -9]]],
        ];

        $ids = [];
        $nm  = [];
        foreach ($spec as $i => [$acct, $name, $contact, $loans]) {
            $res = $this->svc->createClient([
                'acct_no' => $acct, 'name' => $name, 'contact' => $contact, 'address' => 'Brgy. ' . ['Poblacion', 'San Isidro', 'Sta. Maria', 'Bagong Silang', 'Mabini'][$i % 5],
                'co_maker' => 'Spouse of ' . explode(',', $name)[0], 'occupation' => ['Rice farmer', 'Teacher', 'Sari-sari store owner', 'Tricycle driver', 'Barangay health worker'][$i % 5], 'monthly_income' => [14000, 22000, 18000, 16000, 12000][$i % 5], 'opened_at' => date('Y-m-d', strtotime('-' . (200 + $i * 17) . ' days')),
            ], self::STAFF);
            $cid = $res['id'];
            $ids[$acct] = $cid;
            $nm[$acct]  = $name;

            // ledgers with opening balances dated in the past
            $open = date('Y-m-d', strtotime('-' . (150 + $i * 5) . ' days'));
            foreach (['savings' => 800 + $i * 310, 'cbu' => 1500 + $i * 120] as $type => $amt) {
                $this->svc->addLedgerAccount($cid, $type, 0, self::STAFF);
                $this->svc->recordTransaction(['client_id' => $cid, 'category' => $this->svc_cfg()->ledgerTypes[$type]['deposit'], 'amount' => $amt, 'method' => 'cash', 'txn_date' => $open, 'remarks' => 'Opening balance'], self::STAFF);
            }
            if ($i % 3 === 0) {
                $this->svc->addLedgerAccount($cid, 'mortuary', 0, self::STAFF);
                $this->svc->recordTransaction(['client_id' => $cid, 'category' => 'mortuary_payment', 'amount' => 120, 'method' => 'cash', 'txn_date' => date('Y-m-d', strtotime('-40 days'))], self::STAFF);
            }
            if ($i % 4 === 1) {
                $this->svc->addLedgerAccount($cid, 'insurance', 0, self::STAFF);
                $this->svc->recordTransaction(['client_id' => $cid, 'category' => 'insurance_payment', 'amount' => 350, 'method' => 'check', 'txn_date' => date('Y-m-d', strtotime('-35 days')), 'reference' => 'CHK-INS-' . $acct], self::STAFF);
            }

            foreach ($loans as $j => $l) {
                $this->seedLoan($cid, $name, $l, ($i + $j) % 2 === 0);
            }
        }

        // client portal logins for the demo clients (username = the client's name, same demo password)
        $cpw = password_hash('ChangeMe#2026', PASSWORD_DEFAULT);
        foreach ($ids as $acct => $cid) {
            $this->db->table('client_users')->insert(['client_id' => $cid, 'username' => $nm[$acct], 'password_hash' => $cpw, 'is_active' => 1, 'must_change_password' => 0, 'created_by' => self::STAFF, 'created_at' => date('Y-m-d H:i:s')]);
        }

        // a couple of withdrawals and fees
        $this->svc->recordTransaction(['client_id' => $ids['CL-0243'], 'category' => 'savings_withdrawal', 'amount' => 500, 'method' => 'check', 'txn_date' => date('Y-m-d', strtotime('-12 days')), 'reference' => 'CHK-WD-0243'], self::STAFF);
        $this->svc->recordTransaction(['client_id' => $ids['CL-0245'], 'category' => 'service_fee', 'amount' => 250, 'method' => 'cash', 'txn_date' => date('Y-m-d', strtotime('-20 days')), 'remarks' => 'Membership fee'], self::STAFF);

        // ---------------- today's entries ----------------
        $this->svc->recordTransaction(['client_id' => $ids['CL-0240'], 'category' => 'savings_deposit', 'amount' => 1000, 'method' => 'cash', 'txn_date' => $today], 'Maria Santos (Employee)');
        $this->svc->recordTransaction(['client_id' => $ids['CL-0242'], 'category' => 'cbu_deposit', 'amount' => 500, 'method' => 'cash', 'txn_date' => $today], 'Maria Santos (Employee)');
        $this->svc->recordTransaction(['client_id' => $ids['CL-0244'], 'category' => 'savings_deposit', 'amount' => 750, 'method' => 'check', 'txn_date' => $today, 'reference' => 'CHK-TODAY-1'], 'Maria Santos (Employee)');
        $r = $this->svc->recordTransaction(['client_id' => $ids['CL-0241'], 'category' => 'savings_deposit', 'amount' => 300, 'method' => 'cash', 'txn_date' => $today, 'remarks' => 'Entered twice by mistake'], 'Maria Santos (Employee)');

        // pending cancellation (waiting for the Manager)
        $this->db->table('transactions')->where('id', $r['id'])->update(['cancel_requested' => 1]);
        $this->db->table('approvals')->insert([
            'entity_type' => 'txn_cancel', 'entity_id' => $r['id'], 'client_id' => $ids['CL-0241'], 'amount' => 300, 'required_role' => 'manager', 'status' => 'pending',
            'summary' => 'Cancel ' . $r['txn_no'] . ' (Savings deposit, ₱300.00). Reason: Duplicate entry — receipt was already recorded earlier.',
            'payload' => json_encode(['reason' => 'Duplicate entry — receipt was already recorded earlier.']),
            'requested_by' => 'Maria Santos (Employee)', 'requested_by_id' => $this->uid['maria'], 'requested_role' => 'employee', 'requested_at' => date('Y-m-d H:i:s'),
        ]);
        $aid = (int) $this->db->insertID();
        $this->doc($ids['CL-0241'], null, 'cancellation_support', 'Duplicate receipt copy', $aid, $r['id']);

        // ---------------- pending loan applications ----------------
        $p1 = $this->svc->createLoan(['client_id' => $ids['CL-0243'], 'loan_type' => 'Emergency Loan', 'principal' => 6000, 'interest_rate' => 5, 'term_months' => 6, 'release_date' => $today, 'release_method' => 'cash', 'purpose' => 'School fees for the semester', '_skip_checks' => true], self::STAFF);
        $this->doc($ids['CL-0243'], $p1['id'], 'application_for_loan', 'Application for Loan ' . $p1['loan_no']);
        $p2 = $this->svc->createLoan(['client_id' => $ids['CL-0241'], 'loan_type' => 'Vehicle Loan', 'principal' => 75000, 'interest_rate' => 5, 'term_months' => 24, 'release_date' => $today, 'release_method' => 'check', 'purpose' => 'Replace delivery tricycle', 'collateral' => 'Tricycle, plate no. ABC 1234', '_skip_checks' => true], self::STAFF);
        $this->fixRequester($p1['id']);
        $this->fixRequester($p2['id']);

        // ---------------- bookkeeping ----------------
        $this->seedBooks();

        Audit::log('Demo data loaded', '15 clients, ledgers, loans and bookkeeping', 'System (seeder)');
        echo "Demo data loaded. Sign in with admin / irisa (employee) / maria (employee) / linne (manager) — password ChangeMe#2026 (must be changed at first sign-in). Clients sign in at /portal/login with their name (e.g. Reyes, Marilou) and the same password.\n";
    }

    private function svc_cfg()
    {
        return config('Loaning');
    }

    /** Creates, approves and pays a loan so that its next installment falls $overdue days ago (negative = in the future). */
    private function seedLoan(int $cid, string $name, array $l, bool $useCheck): void
    {
        [$type, $principal, $term, $paid, $overdue] = $l;
        $restructure = $l[5] ?? null;
        $today = date('Y-m-d');

        $nextDue  = date('Y-m-d', strtotime($today . ' ' . sprintf('%+d', -$overdue) . ' days'));
        $firstDue = add_months($nextDue, -$paid);
        $release  = add_months($firstDue, -1);

        $res = $this->svc->createLoan([
            'client_id' => $cid, 'loan_type' => $type, 'principal' => $principal, 'interest_rate' => 5, 'term_months' => $term,
            'release_date' => $release, 'first_due_date' => $firstDue, 'release_method' => $useCheck ? 'check' : 'cash', '_skip_checks' => true,
        ], self::STAFF);
        $loanId = $res['id'];

        $this->doc($cid, $loanId, 'application_for_loan', 'Application for Loan ' . $res['loan_no']);
        $this->svc->releaseLoan($loanId, self::MANAGER);
        $this->fixRequester($loanId, true);

        $loan = $this->db->table('loans')->where('id', $loanId)->get()->getRowArray();
        for ($k = 1; $k <= $paid; $k++) {
            $r = $this->svc->recordTransaction([
                'client_id' => $cid, 'loan_id' => $loanId, 'category' => 'loan_payment', 'amount' => $loan['monthly_due'],
                'method' => ($k + $cid) % 3 === 0 ? 'check' : 'cash', 'txn_date' => add_months($firstDue, $k - 1),
                'reference' => ($k + $cid) % 3 === 0 ? 'CHK-' . $res['loan_no'] . '-' . $k : null,
            ], 'Maria Santos (Employee)');
            if (! $r['ok']) {
                throw new \RuntimeException('Seed payment failed for ' . $name . ': ' . $r['error']);
            }
        }

        if ($restructure) {
            $this->svc->applyRestructure($loanId, $restructure[0], $nextDue, self::MANAGER);
            $this->db->table('approvals')->insert([
                'entity_type' => 'loan_restructure', 'entity_id' => $loanId, 'client_id' => $cid, 'amount' => 0, 'required_role' => 'manager', 'status' => 'approved',
                'summary' => 'Restructure ' . $res['loan_no'] . ' (' . $name . ') over ' . $restructure[0] . ' months.',
                'requested_by' => self::STAFF, 'requested_by_id' => $this->uid['irisa'], 'requested_role' => 'employee', 'requested_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
                'decided_by' => self::MANAGER, 'decided_by_id' => $this->uid['linne'], 'decided_role' => 'manager', 'decided_at' => date('Y-m-d H:i:s', strtotime('-19 days')),
                'decision_note' => 'Client lost income; agreed to a longer schedule.',
            ]);
        }
    }

    /** Re-labels the approval row created by createLoan() with a realistic requester/approver trail. */
    private function fixRequester(int $loanId, bool $approved = false): void
    {
        $upd = ['requested_by' => self::STAFF, 'requested_by_id' => $this->uid['irisa'], 'requested_role' => 'employee'];
        if ($approved) {
            $upd += [
                'status' => 'approved', 'decided_by' => self::MANAGER, 'decided_by_id' => $this->uid['linne'],
                'decided_role' => 'manager', 'decided_at' => date('Y-m-d H:i:s'), 'decision_note' => 'Application complete. Approved for release.',
            ];
        }
        $this->db->table('approvals')->where('entity_type', 'loan_release')->where('entity_id', $loanId)->update($upd);
    }

    private function doc(int $cid, ?int $loanId, string $type, string $title, ?int $approvalId = null, ?int $txnId = null): void
    {
        $dir = WRITEPATH . 'uploads';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $stored = 'demo_' . (++$this->docN) . '.txt';
        file_put_contents($dir . DIRECTORY_SEPARATOR . $stored, "DEMO PLACEHOLDER\n" . $title . "\nReplace with a scan of the real document.\n");
        $this->db->table('documents')->insert([
            'client_id' => $cid, 'loan_id' => $loanId, 'transaction_id' => $txnId, 'approval_id' => $approvalId, 'doc_type' => $type, 'title' => $title,
            'original_name' => preg_replace('/[^A-Za-z0-9._-]+/', '_', $title) . '.txt', 'stored_name' => $stored, 'mime' => 'text/plain', 'size' => 80,
            'uploaded_by' => self::STAFF, 'uploaded_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedBooks(): void
    {
        $m0 = date('Y-m-01');
        $m1 = add_months($m0, -1);
        $cap = static fn (string $d) => min($d, date('Y-m-d'));
        $rows = [
            // [date, category, description, supplier, invoice, amount, status, via, due]
            [$cap(add_days($m1, 1)), 'Salaries and wages', 'Staff salaries', null, null, 38000, 'paid', 'cash', null],
            [$cap(add_days($m1, 4)), 'Office and admin', 'Office supplies', 'Alpha Office Supply', 'INV-2201', 3800, 'paid', 'cash', null],
            [$cap(add_days($m1, 6)), 'Utilities', 'Electricity', 'Electric Cooperative', 'EC-0911', 3300, 'paid', 'bank', null],
            [$cap(add_days($m0, 1)), 'Salaries and wages', 'Staff salaries', null, null, 38000, 'paid', 'cash', null],
            [$cap(add_days($m0, 2)), 'Office and admin', 'Office supplies', 'Alpha Office Supply', 'INV-2290', 4200, 'paid', 'cash', null],
            [$cap(add_days($m0, 3)), 'Utilities', 'Electricity', 'Electric Cooperative', 'EC-1010', 3150, 'paid', 'bank', null],
            [$cap(add_days($m0, 3)), 'Printing and supplies', 'Passbooks and forms', 'PrintHub', 'PH-8841', 1850, 'paid', 'cash', null],
            [date('Y-m-d', strtotime('-25 days')), 'Professional fees', 'Audit fee (partial)', 'R. Dizon & Associates', 'RD-044', 6500, 'unpaid', null, date('Y-m-d', strtotime('-10 days'))],
            [date('Y-m-d', strtotime('-3 days')), 'Utilities', 'Internet subscription', 'LAN Internet', 'LI-5531', 2400, 'unpaid', null, date('Y-m-d', strtotime('+10 days'))],
        ];
        foreach ($rows as [$d, $cat, $desc, $sup, $inv, $amt, $st, $via, $due]) {
            $this->db->table('expenses')->insert([
                'expense_date' => $d, 'category' => $cat, 'description' => $desc, 'supplier' => $sup, 'invoice_no' => $inv, 'amount' => $amt,
                'payment_status' => $st, 'due_date' => $due, 'paid_date' => $st === 'paid' ? $d : null, 'paid_via' => $via,
                'created_by' => self::STAFF, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        foreach ([$m1, $m0] as $m) {
            foreach (['Salaries and wages' => 40000, 'Office and admin' => 3000, 'Utilities' => 4000, 'Printing and supplies' => 2000, 'Transportation' => 1500, 'Professional fees' => 5000] as $c => $a) {
                $this->db->table('budgets')->insert(['month' => substr($m, 0, 7), 'category' => $c, 'amount' => $a]);
            }
        }

        // bank statement: match the first three checks, leave the rest (and a bank charge) unmatched
        $checks = $this->db->table('transactions')->where('method', 'check')->where('status', 'posted')->whereIn('direction', ['in', 'out'])->orderBy('txn_date')->limit(3)->get()->getResultArray();
        foreach ($checks as $t) {
            $this->db->table('bank_entries')->insert([
                'entry_date' => add_days($t['txn_date'], 1), 'reference' => $t['reference'], 'description' => 'Deposit ' . $t['txn_no'],
                'amount' => $t['direction'] === 'in' ? $t['amount'] : -$t['amount'], 'match_type' => 'txn', 'match_id' => $t['id'], 'created_by' => self::STAFF, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->db->table('bank_entries')->insert(['entry_date' => date('Y-m-d', strtotime('-5 days')), 'reference' => 'BANK-FEE', 'description' => 'Monthly service charge', 'amount' => -150, 'created_by' => self::STAFF, 'created_at' => date('Y-m-d H:i:s')]);
    }
}
