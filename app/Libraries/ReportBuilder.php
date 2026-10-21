<?php

namespace App\Libraries;

/**
 * Builds every report from live database figures.
 *
 * Each report is returned as:
 *   title, subtitle, period, tiles[ [label,value,tone?] ], alerts[ [level,text] ],
 *   sections[ [heading?, columns[], rows[][], totals[]?, row_class[idx=>css]?] ], notes[]
 */
class ReportBuilder
{
    public static function catalog(): array
    {
        return [
            'Receivables & payables' => [
                ['slug' => 'ar-aging', 'title' => 'Accounts receivable aging', 'desc' => 'Overdue loan payments by age, with the automated reminder already sent.'],
                ['slug' => 'ap-aging', 'title' => 'Accounts payable aging', 'desc' => 'Unpaid supplier bills and how overdue they are.'],
            ],
            'Financial statements' => [
                ['slug' => 'pnl', 'title' => 'Profit and loss statement', 'desc' => 'Interest and fee income less expenses for the month.'],
                ['slug' => 'balance-sheet', 'title' => 'Balance sheet', 'desc' => 'Reconciles cash on hand, cash in bank, loans receivable, payables and members\' equity to total assets.'],
                ['slug' => 'monthly-financial', 'title' => 'Monthly financial report', 'desc' => 'One-page summary of portfolio, income, cash and member funds.'],
            ],
            'Cash & budget' => [
                ['slug' => 'cash-flow', 'title' => 'Cash flow report', 'desc' => 'Cash received, cash paid and the resulting balance.'],
                ['slug' => 'budget-actual', 'title' => 'Budget vs. actual', 'desc' => 'Budget against actual spending, with automatic over/under-spend alerts.'],
                ['slug' => 'bank-recon', 'title' => 'Bank reconciliation', 'desc' => 'Bank statement entries matched against the books; unmatched items flagged.'],
            ],
            'Income & tax' => [
                ['slug' => 'sales-revenue', 'title' => 'Sales and revenue', 'desc' => 'Revenue by source and a month-by-month trend for the year.'],
                ['slug' => 'expense-report', 'title' => 'Expense report', 'desc' => 'Actual expenses by category and in detail.'],
                ['slug' => 'tax-report', 'title' => 'Tax report (income tax)', 'desc' => 'Estimated income tax for the month and year to date.'],
            ],
        ];
    }

    public static function slugs(): array
    {
        $out = [];
        foreach (self::catalog() as $items) {
            foreach ($items as $i) {
                $out[$i['slug']] = $i['title'];
            }
        }

        return $out;
    }

    public static function range(string $month): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = date('Y-m');
        }
        $from = $month . '-01';

        return [$month, $from, date('Y-m-t', strtotime($from))];
    }

    protected $db;
    protected LoanService $svc;

    public function __construct()
    {
        helper('loaning');
        $this->db  = db_connect();
        $this->svc = new LoanService();
    }

    public function build(string $slug, string $month): ?array
    {
        [$month, $from, $to] = self::range($month);
        $title = self::slugs()[$slug] ?? null;
        if ($title === null) {
            return null;
        }
        $method = 'r' . str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
        $r      = $this->$method($month, $from, $to);

        $r['title']  = $title;
        $r['slug']   = $slug;
        $r['month']  = $month;
        $r['alerts'] = $r['alerts'] ?? [];
        $r['notes']  = $r['notes'] ?? [];
        $r['tiles']  = $r['tiles'] ?? [];

        return $r;
    }

    // ---------------------------------------------------------------------
    //  shared queries
    // ---------------------------------------------------------------------

    protected function sumTxn(string $field, $cats, string $from, string $to): float
    {
        $cats = (array) $cats;
        $in   = implode(',', array_fill(0, count($cats), '?'));
        $r    = $this->db->query(
            "SELECT COALESCE(SUM($field), 0) AS s FROM transactions WHERE status = 'posted' AND category IN ($in) AND txn_date BETWEEN ? AND ?",
            array_merge($cats, [$from, $to])
        )->getRowArray();

        return round((float) $r['s'], 2);
    }

    /** @return array<string,float> expense totals by category (expense date in range) */
    protected function expensesByCategory(string $from, string $to): array
    {
        $out  = [];
        $rows = $this->db->query('SELECT category, SUM(amount) AS s FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY category ORDER BY category', [$from, $to])->getResultArray();
        foreach ($rows as $r) {
            $out[$r['category']] = round((float) $r['s'], 2);
        }

        return $out;
    }

    /** Cash on hand / in bank at the end of $date. */
    /** Fee income = service / membership / release fees + late-payment penalties collected. */
    protected function feeIncome(string $from, string $to): float
    {
        return round($this->sumTxn('amount', 'service_fee', $from, $to) + $this->sumTxn('penalty_part', 'loan_payment', $from, $to), 2);
    }

    protected function cashAsOf(string $date): array
    {
        $hand = (float) $this->svc->setting('opening_cash_on_hand');
        $bank = (float) $this->svc->setting('opening_cash_on_bank');

        $rows = $this->db->query(
            "SELECT method, SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END) AS net
               FROM transactions WHERE status = 'posted' AND direction IN ('in','out') AND txn_date <= ? GROUP BY method",
            [$date]
        )->getResultArray();
        foreach ($rows as $r) {
            if (in_array($r['method'], ['check', 'online'], true)) { // online payments settle in the bank / e-wallet
                $bank += (float) $r['net'];
            } else {
                $hand += (float) $r['net'];
            }
        }
        $rows = $this->db->query("SELECT paid_via, SUM(amount) AS s FROM expenses WHERE payment_status = 'paid' AND paid_date <= ? GROUP BY paid_via", [$date])->getResultArray();
        foreach ($rows as $r) {
            if ($r['paid_via'] === 'bank') {
                $bank -= (float) $r['s'];
            } else {
                $hand -= (float) $r['s'];
            }
        }

        return ['hand' => round($hand, 2), 'bank' => round($bank, 2)];
    }

    protected function ledgerTotal(string $type, string $asOf): float
    {
        $r = $this->db->query(
            "SELECT COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) AS s
               FROM transactions WHERE status = 'posted' AND ledger_type = ? AND direction IN ('in','out') AND txn_date <= ?",
            [$type, $asOf]
        )->getRowArray();

        return round((float) $r['s'], 2);
    }

    protected function loansReceivable(string $asOf): float
    {
        $r = $this->db->query(
            "SELECT COALESCE(SUM(CASE WHEN category = 'loan_release' THEN amount ELSE 0 END), 0) - COALESCE(SUM(principal_part), 0) AS s
               FROM transactions WHERE status = 'posted' AND txn_date <= ?",
            [$asOf]
        )->getRowArray();

        return round((float) $r['s'], 2);
    }

    protected function payables(string $asOf): float
    {
        $r = $this->db->query("SELECT COALESCE(SUM(amount), 0) AS s FROM expenses WHERE expense_date <= ? AND (payment_status = 'unpaid' OR paid_date > ?)", [$asOf, $asOf])->getRowArray();

        return round((float) $r['s'], 2);
    }

    protected function retainedEarnings(string $asOf): float
    {
        $inc = $this->sumTxn('interest_part', 'loan_payment', '2000-01-01', $asOf) + $this->feeIncome('2000-01-01', $asOf);
        $r   = $this->db->query('SELECT COALESCE(SUM(amount), 0) AS s FROM expenses WHERE expense_date <= ?', [$asOf])->getRowArray();

        return round($inc - (float) $r['s'], 2);
    }

    protected function budgetRows(string $month): array
    {
        $from    = $month . '-01';
        $to      = date('Y-m-t', strtotime($from));
        $budgets = [];
        foreach ($this->db->table('budgets')->where('month', $month)->get()->getResultArray() as $b) {
            $budgets[$b['category']] = (float) $b['amount'];
        }
        $actual = $this->expensesByCategory($from, $to);
        $cats   = array_unique(array_merge(array_keys($budgets), array_keys($actual)));
        sort($cats);

        $rows = [];
        foreach ($cats as $c) {
            $b = $budgets[$c] ?? null;
            $a = $actual[$c] ?? 0.0;
            $rows[] = [
                'category' => $c,
                'budget'   => $b,
                'actual'   => $a,
                'variance' => $b === null ? null : round($b - $a, 2),
                'state'    => $b === null ? ($a > 0 ? 'nobudget' : 'ok') : ($a > $b + 0.004 ? 'over' : 'under'),
            ];
        }

        return $rows;
    }

    // ---------------------------------------------------------------------
    //  reports
    // ---------------------------------------------------------------------

    protected function rArAging(string $month, string $from, string $to): array
    {
        $today = date('Y-m-d');
        $last  = [];
        foreach ($this->db->query("SELECT loan_id, kind FROM notifications WHERE is_auto = 1 ORDER BY sent_at, id")->getResultArray() as $n) {
            $last[$n['loan_id']] = ReminderEngine::KINDS[$n['kind']] ?? $n['kind'];
        }

        $loans = array_values(array_filter($this->svc->allLoans(null, $today), static fn ($l) => in_array($l['status'], ['pastdue', 'restructured', 'current'], true)));
        usort($loans, static fn ($a, $b) => [$b['days_overdue'], $a['client_name']] <=> [$a['days_overdue'], $b['client_name']]);

        $rows = [];
        $cls  = [];
        $tot  = [0, 0, 0, 0, 0, 0];
        foreach ($loans as $i => $l) {
            $d = $l['days_overdue'];
            $b = [0, 0, 0, 0, 0];
            $idx = $d <= 0 ? 0 : ($d <= 30 ? 1 : ($d <= 60 ? 2 : ($d <= 90 ? 3 : 4)));
            $b[$idx] = $l['outstanding'];
            foreach ($b as $k => $v) {
                $tot[$k] += $v;
            }
            $tot[5] += $l['outstanding'];
            $rows[] = array_merge(
                [$l['client_name'], $l['acct_no'] . ' · ' . $l['loan_type']],
                array_map(static fn ($v) => $v > 0 ? peso($v) : '—', $b),
                [peso($l['outstanding']), $d > 0 ? ($last[$l['id']] ?? 'Pending') : '—']
            );
            $cls[$i] = $d > 0 ? 'row-pastdue' : ($l['status'] === 'restructured' ? 'row-restructured' : 'row-current');
        }

        $overdue = $tot[1] + $tot[2] + $tot[3] + $tot[4];

        return [
            'subtitle' => 'Overdue loan payments by age, with the automated reminder already sent.',
            'period'   => 'As of ' . fdate($today),
            'tiles'    => [
                ['Total receivable', peso($tot[5])],
                ['Overdue', peso($overdue), 'warn'],
                ['Loans overdue', (string) count(array_filter($loans, static fn ($l) => $l['days_overdue'] > 0)), 'warn'],
            ],
            'sections' => [[
                'columns'   => ['Client', 'Account · loan', 'Not yet due', '1–30 days', '31–60 days', '61–90 days', 'Over 90 days', 'Total', 'Latest automated notice'],
                'rows'      => $rows,
                'row_class' => $cls,
                'totals'    => ['Totals', '', peso($tot[0]), peso($tot[1]), peso($tot[2]), peso($tot[3]), peso($tot[4]), peso($tot[5]), ''],
            ]],
            'notes' => ['Each loan\'s whole outstanding balance is aged by its oldest unpaid installment. Reminders and collection letters are generated automatically (1st reminder at 15 days, 2nd at 30, collection letters at 45 and 60 days overdue).'],
        ];
    }

    protected function rApAging(string $month, string $from, string $to): array
    {
        $today = date('Y-m-d');
        $bills = $this->db->table('expenses')->where('payment_status', 'unpaid')->orderBy('due_date')->get()->getResultArray();
        $rows  = [];
        $cls   = [];
        $tot   = [0, 0, 0, 0, 0];
        $over  = 0;
        foreach ($bills as $i => $b) {
            $due  = $b['due_date'] ?: add_days($b['expense_date'], 30);
            $days = max(0, days_between($due, $today));
            $idx  = $days <= 0 ? 0 : ($days <= 30 ? 1 : ($days <= 60 ? 2 : 3));
            $cell = ['—', '—', '—', '—'];
            $cell[$idx] = peso($b['amount']);
            $tot[$idx] += (float) $b['amount'];
            $tot[4]    += (float) $b['amount'];
            if ($days > 0) {
                $over++;
                $cls[$i] = 'row-pastdue';
            }
            $rows[] = array_merge([$b['supplier'] ?: '—', $b['invoice_no'] ?: '—', fdate($due), $days > 0 ? (string) $days : '—'], $cell, [peso($b['amount'])]);
        }

        return [
            'subtitle' => 'Unpaid supplier bills and how overdue they are.',
            'period'   => 'As of ' . fdate($today),
            'tiles'    => [['Total payable', peso($tot[4])], ['Overdue bills', (string) $over, 'warn'], ['Unpaid bills', (string) count($bills)]],
            'sections' => [[
                'columns'   => ['Supplier', 'Invoice no.', 'Due date', 'Days overdue', 'Not yet due', '1–30 days', '31–60 days', '61+ days', 'Amount'],
                'rows'      => $rows,
                'row_class' => $cls,
                'totals'    => ['Totals', '', '', '', peso($tot[0]), peso($tot[1]), peso($tot[2]), peso($tot[3]), peso($tot[4])],
            ]],
            'notes' => ['Add or settle bills under Accounting → Expenses & payables.'],
        ];
    }

    protected function rPnl(string $month, string $from, string $to): array
    {
        $interest = $this->sumTxn('interest_part', 'loan_payment', $from, $to);
        $fees     = $this->feeIncome($from, $to);
        $revenue  = $interest + $fees;
        $exp      = $this->expensesByCategory($from, $to);
        $expTotal = array_sum($exp);
        $net      = $revenue - $expTotal;

        $expRows = [];
        foreach ($exp as $c => $v) {
            $expRows[] = [$c, peso($v)];
        }
        if (! $expRows) {
            $expRows[] = ['No expenses recorded', '—'];
        }

        return [
            'subtitle' => 'Interest and fee income less expenses for the month.',
            'period'   => date('F Y', strtotime($from)),
            'tiles'    => [['Total revenue', peso($revenue)], ['Total expenses', peso($expTotal)], ['Net income', peso($net), $net < 0 ? 'warn' : '']],
            'sections' => [
                ['heading' => 'Revenue', 'columns' => ['Line item', 'Amount'], 'rows' => [['Interest income (collected)', peso($interest)], ['Service fees and penalties', peso($fees)]], 'totals' => ['Total revenue', peso($revenue)]],
                ['heading' => 'Expenses', 'columns' => ['Line item', 'Amount'], 'rows' => $expRows, 'totals' => ['Total expenses', peso($expTotal)]],
                ['heading' => 'Result', 'columns' => ['Line item', 'Amount'], 'rows' => [], 'totals' => [$net >= 0 ? 'Net income' : 'Net loss', peso($net)]],
            ],
            'notes' => ['Interest is recognized when it is collected (cash basis); expenses are recognized on the expense date, paid or not.'],
        ];
    }

    protected function rBalanceSheet(string $month, string $from, string $to): array
    {
        $cash    = $this->cashAsOf($to);
        $loans   = $this->loansReceivable($to);
        $assets  = $cash['hand'] + $cash['bank'] + $loans;
        $ap      = $this->payables($to);
        $savings = $this->ledgerTotal('savings', $to);
        $mort    = $this->ledgerTotal('mortuary', $to);
        $ins     = $this->ledgerTotal('insurance', $to);
        $liab    = $ap + $savings + $mort + $ins;
        $cbu     = $this->ledgerTotal('cbu', $to);
        $opening = (float) $this->svc->setting('opening_cash_on_hand') + (float) $this->svc->setting('opening_cash_on_bank');
        $re      = $this->retainedEarnings($to);
        $equity  = $cbu + $opening + $re;
        $diff    = round($assets - $liab - $equity, 2);

        return [
            'subtitle' => 'Reconciles cash on hand, cash in bank, loans receivable, payables and members\' equity to total assets.',
            'period'   => 'As of ' . fdate($to),
            'tiles'    => [['Total assets', peso($assets)], ['Total liabilities', peso($liab)], ['Members\' equity', peso($equity)]],
            'alerts'   => abs($diff) < 0.01
                ? [['success', 'Reconciled: total assets equal liabilities plus members\' equity.']]
                : [['danger', 'Out of balance by ' . peso($diff) . '. Check opening balances under Accounting → Settings.']],
            'sections' => [
                ['heading' => 'Assets', 'columns' => ['Line item', 'Amount'], 'rows' => [['Cash on hand', peso($cash['hand'])], ['Cash in bank', peso($cash['bank'])], ['Loans receivable (principal outstanding)', peso($loans)]], 'totals' => ['Total assets', peso($assets)]],
                ['heading' => 'Liabilities', 'columns' => ['Line item', 'Amount'], 'rows' => [['Accounts payable', peso($ap)], ['Members\' savings deposits', peso($savings)], ['Mortuary fund', peso($mort)], ['Insurance fund', peso($ins)]], 'totals' => ['Total liabilities', peso($liab)]],
                ['heading' => 'Members\' equity', 'columns' => ['Line item', 'Amount'], 'rows' => [['Capital Build Up (CBU)', peso($cbu)], ['Opening capital', peso($opening)], ['Retained earnings (net surplus)', peso($re)]], 'totals' => ['Total members\' equity', peso($equity)]],
                ['heading' => 'Reconciliation', 'columns' => ['Check', 'Amount'], 'rows' => [['Total assets', peso($assets)], ['Liabilities + members\' equity', peso($liab + $equity)]], 'totals' => ['Difference', peso($diff)]],
            ],
        ];
    }

    protected function rCashFlow(string $month, string $from, string $to): array
    {
        $begin = $this->cashAsOf(add_days($from, -1));
        $end   = $this->cashAsOf($to);

        $items = [
            ['Loan principal collected', $this->sumTxn('principal_part', 'loan_payment', $from, $to)],
            ['Interest collected', $this->sumTxn('interest_part', 'loan_payment', $from, $to)],
            ['Service fees (incl. loan release deductions)', $this->sumTxn('amount', 'service_fee', $from, $to)],
            ['Late-payment penalties collected', $this->sumTxn('penalty_part', 'loan_payment', $from, $to)],
            ['Savings deposits', $this->sumTxn('amount', 'savings_deposit', $from, $to)],
            ['CBU contributions', $this->sumTxn('amount', 'cbu_deposit', $from, $to)],
            ['Mortuary payments', $this->sumTxn('amount', 'mortuary_payment', $from, $to)],
            ['Insurance payments', $this->sumTxn('amount', 'insurance_payment', $from, $to)],
        ];
        $in = array_sum(array_column($items, 1));
        $r  = $this->db->query("SELECT COALESCE(SUM(amount),0) AS s FROM expenses WHERE payment_status = 'paid' AND paid_date BETWEEN ? AND ?", [$from, $to])->getRowArray();
        $outItems = [
            ['Loan releases', $this->sumTxn('amount', 'loan_release', $from, $to)],
            ['Savings withdrawals', $this->sumTxn('amount', 'savings_withdrawal', $from, $to)],
            ['CBU withdrawals', $this->sumTxn('amount', 'cbu_withdrawal', $from, $to)],
            ['Expenses paid', round((float) $r['s'], 2)],
        ];
        $out = array_sum(array_column($outItems, 1));
        $net = $in - $out;

        $begT = $begin['hand'] + $begin['bank'];
        $endT = $end['hand'] + $end['bank'];

        // day-by-day movement
        $days = [];
        $rows = $this->db->query(
            "SELECT txn_date AS d, SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END) AS i, SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END) AS o
               FROM transactions WHERE status = 'posted' AND direction IN ('in','out') AND txn_date BETWEEN ? AND ? GROUP BY txn_date",
            [$from, $to]
        )->getResultArray();
        foreach ($rows as $x) {
            $days[$x['d']] = ['i' => (float) $x['i'], 'o' => (float) $x['o']];
        }
        foreach ($this->db->query("SELECT paid_date AS d, SUM(amount) AS o FROM expenses WHERE payment_status = 'paid' AND paid_date BETWEEN ? AND ? GROUP BY paid_date", [$from, $to])->getResultArray() as $x) {
            $days[$x['d']] = ['i' => $days[$x['d']]['i'] ?? 0.0, 'o' => ($days[$x['d']]['o'] ?? 0.0) + (float) $x['o']];
        }
        ksort($days);
        $run      = $begT;
        $dayRows  = [['Opening balance', '—', '—', peso($begT)]];
        foreach ($days as $d => $v) {
            $run      += $v['i'] - $v['o'];
            $dayRows[] = [fdate($d), $v['i'] > 0 ? peso($v['i']) : '—', $v['o'] > 0 ? peso($v['o']) : '—', peso($run)];
        }

        return [
            'subtitle' => 'Cash received, cash paid and the resulting balance.',
            'period'   => date('F Y', strtotime($from)),
            'tiles'    => [['Cash in', peso($in)], ['Cash out', peso($out)], ['Ending cash', peso($endT)]],
            'sections' => [
                ['heading' => 'Cash received', 'columns' => ['Source', 'Amount'], 'rows' => array_map(static fn ($i) => [$i[0], peso($i[1])], $items), 'totals' => ['Total cash received', peso($in)]],
                ['heading' => 'Cash paid', 'columns' => ['Use', 'Amount'], 'rows' => array_map(static fn ($i) => [$i[0], peso($i[1])], $outItems), 'totals' => ['Total cash paid', peso($out)]],
                ['heading' => 'Cash position', 'columns' => ['Line', 'Amount'], 'rows' => [['Beginning cash (hand + bank)', peso($begT)], ['Net change', peso($net)], ['Ending cash on hand', peso($end['hand'])], ['Ending cash in bank', peso($end['bank'])]], 'totals' => ['Ending cash', peso($endT)]],
                ['heading' => 'Daily movement', 'columns' => ['Date', 'Cash in', 'Cash out', 'Running balance'], 'rows' => $dayRows],
            ],
        ];
    }

    protected function rBudgetActual(string $month, string $from, string $to): array
    {
        $rows   = [];
        $cls    = [];
        $alerts = [];
        $tb = 0.0;
        $ta = 0.0;
        foreach ($this->budgetRows($month) as $i => $r) {
            $tb += (float) $r['budget'];
            $ta += $r['actual'];
            if ($r['state'] === 'over') {
                $alerts[] = ['danger', 'OVER budget: ' . $r['category'] . ' is ' . peso($r['actual'] - $r['budget']) . ' above its ' . peso($r['budget']) . ' budget.'];
                $cls[$i]  = 'row-pastdue';
                $flag     = 'OVER-SPENDING';
            } elseif ($r['state'] === 'nobudget') {
                $alerts[] = ['warning', 'No budget set for ' . $r['category'] . ' but ' . peso($r['actual']) . ' was spent.'];
                $cls[$i]  = 'row-restructured';
                $flag     = 'No budget';
            } else {
                $cls[$i] = 'row-current';
                $flag    = 'Under budget';
            }
            $rows[] = [$r['category'], $r['budget'] === null ? '—' : peso($r['budget']), peso($r['actual']), $r['variance'] === null ? '—' : peso($r['variance']), $flag];
        }
        $var = $tb - $ta;

        return [
            'subtitle' => 'Budget against actual spending, with automatic over/under-spend alerts.',
            'period'   => date('F Y', strtotime($from)),
            'tiles'    => [['Budgeted', peso($tb)], ['Actual', peso($ta)], [$var >= 0 ? 'Under budget by' : 'Over budget by', peso(abs($var)), $var < 0 ? 'warn' : '']],
            'alerts'   => $alerts,
            'sections' => [[
                'columns'   => ['Category', 'Budget', 'Actual', 'Variance (budget − actual)', 'Alert'],
                'rows'      => $rows,
                'row_class' => $cls,
                'totals'    => ['Totals', peso($tb), peso($ta), peso($var), $var >= 0 ? 'Under' : 'Over'],
            ]],
            'notes' => ['Set monthly budgets under Accounting → Budgets.'],
        ];
    }

    protected function rSalesRevenue(string $month, string $from, string $to): array
    {
        $interest = $this->sumTxn('interest_part', 'loan_payment', $from, $to);
        $fees     = $this->feeIncome($from, $to);
        $year     = substr($month, 0, 4);

        $trend = [];
        $rows  = $this->db->query(
            "SELECT DATE_FORMAT(txn_date, '%Y-%m') AS m,
                    SUM(CASE WHEN category = 'loan_payment' THEN interest_part ELSE 0 END) AS i,
                    SUM(CASE WHEN category = 'service_fee' THEN amount WHEN category = 'loan_payment' THEN penalty_part ELSE 0 END) AS f
               FROM transactions WHERE status = 'posted' AND YEAR(txn_date) = ? GROUP BY m",
            [(int) $year]
        )->getResultArray();
        foreach ($rows as $r) {
            $trend[$r['m']] = [(float) $r['i'], (float) $r['f']];
        }
        $tr = [];
        $ti = 0.0;
        $tf = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $key = sprintf('%s-%02d', $year, $m);
            [$i, $f] = $trend[$key] ?? [0.0, 0.0];
            $ti += $i;
            $tf += $f;
            $tr[] = [date('F', strtotime($key . '-01')), peso($i), peso($f), peso($i + $f)];
        }

        return [
            'subtitle' => 'Revenue by source and a month-by-month trend for the year.',
            'period'   => date('F Y', strtotime($from)),
            'tiles'    => [['Revenue this month', peso($interest + $fees)], ['Interest income', peso($interest)], ['Fees', peso($fees)]],
            'sections' => [
                ['heading' => 'Revenue by source — ' . date('F Y', strtotime($from)), 'columns' => ['Source', 'Amount'], 'rows' => [['Interest income', peso($interest)], ['Service fees and penalties', peso($fees)]], 'totals' => ['Total revenue', peso($interest + $fees)]],
                ['heading' => 'Monthly trend — ' . $year, 'columns' => ['Month', 'Interest income', 'Fees', 'Total'], 'rows' => $tr, 'totals' => ['Year to date', peso($ti), peso($tf), peso($ti + $tf)]],
            ],
        ];
    }

    protected function rExpenseReport(string $month, string $from, string $to): array
    {
        $list = $this->db->table('expenses')->where('expense_date >=', $from)->where('expense_date <=', $to)->orderBy('expense_date')->orderBy('id')->get()->getResultArray();
        $cat  = [];
        $rows = [];
        $paid = 0.0;
        $unpd = 0.0;
        foreach ($list as $e) {
            $cat[$e['category']] = ($cat[$e['category']] ?? 0) + (float) $e['amount'];
            if ($e['payment_status'] === 'paid') {
                $paid += (float) $e['amount'];
            } else {
                $unpd += (float) $e['amount'];
            }
            $rows[] = [fdate($e['expense_date']), $e['category'], $e['description'] . ($e['supplier'] ? ' · ' . $e['supplier'] : ''), $e['payment_status'] === 'paid' ? 'Paid' : 'Unpaid', peso($e['amount'])];
        }
        ksort($cat);
        $catRows = [];
        foreach ($cat as $c => $v) {
            $catRows[] = [$c, peso($v)];
        }

        return [
            'subtitle' => 'Actual expenses by category and in detail.',
            'period'   => date('F Y', strtotime($from)),
            'tiles'    => [['Total expenses', peso($paid + $unpd)], ['Paid', peso($paid)], ['Unpaid', peso($unpd), $unpd > 0 ? 'warn' : '']],
            'sections' => [
                ['heading' => 'By category', 'columns' => ['Category', 'Amount'], 'rows' => $catRows, 'totals' => ['Total', peso($paid + $unpd)]],
                ['heading' => 'Detail', 'columns' => ['Date', 'Category', 'Description', 'Status', 'Amount'], 'rows' => $rows],
            ],
        ];
    }

    /** Book-side bank movements (checks and bank-paid expenses) up to $to, flagged matched/unmatched. */
    public function bankBook(string $to): array
    {
        $matched = [];
        foreach ($this->db->table('bank_entries')->where('match_id IS NOT', null)->get()->getResultArray() as $b) {
            $matched[$b['match_type'] . ':' . $b['match_id']] = $b['id'];
        }
        $book = [];
        $txns = $this->db->table('transactions')->where('status', 'posted')->whereIn('method', ['check', 'online'])->whereIn('direction', ['in', 'out'])->where('txn_date <=', $to)->notLike('reference', 'DED-', 'after')->orderBy('txn_date')->get()->getResultArray();
        $netOf = [];
        foreach ($this->db->table('loans')->select('id, principal, net_proceeds')->where('net_proceeds >', 0)->get()->getResultArray() as $ln) {
            $netOf[$ln['id']] = (float) $ln['net_proceeds'];
        }
        foreach ($txns as $t) {
            if ($t['category'] === 'loan_release' && isset($netOf[$t['loan_id']])) {
                $t['amount'] = $netOf[$t['loan_id']]; // the check/transfer actually issued is the net proceeds
            }
            $book[] = ['type' => 'txn', 'id' => (int) $t['id'], 'date' => $t['txn_date'], 'ref' => $t['reference'] ?: $t['txn_no'], 'desc' => txn_label($t['category']) . ' · ' . $t['txn_no'], 'amount' => $t['direction'] === 'in' ? (float) $t['amount'] : -(float) $t['amount'], 'matched' => isset($matched['txn:' . $t['id']])];
        }
        $exps = $this->db->table('expenses')->where('payment_status', 'paid')->where('paid_via', 'bank')->where('paid_date <=', $to)->orderBy('paid_date')->get()->getResultArray();
        foreach ($exps as $e) {
            $book[] = ['type' => 'expense', 'id' => (int) $e['id'], 'date' => $e['paid_date'], 'ref' => $e['invoice_no'] ?: ('EXP-' . $e['id']), 'desc' => $e['category'] . ' · ' . ($e['supplier'] ?: $e['description']), 'amount' => -(float) $e['amount'], 'matched' => isset($matched['expense:' . $e['id']])];
        }
        usort($book, static fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $book;
    }

    protected function rBankRecon(string $month, string $from, string $to): array
    {
        $bank = $this->db->table('bank_entries')->where('entry_date <=', $to)->orderBy('entry_date')->get()->getResultArray();
        $book = $this->bankBook($to);

        $ub = [];
        $mt = [];
        $sumUb = 0.0;
        foreach ($bank as $b) {
            if ($b['match_id'] === null) {
                $ub[]   = [fdate($b['entry_date']), $b['reference'] ?: '—', $b['description'] ?: '—', peso($b['amount'])];
                $sumUb += (float) $b['amount'];
            } else {
                $mt[] = [fdate($b['entry_date']), $b['reference'] ?: '—', $b['description'] ?: '—', peso($b['amount']), 'Matched to ' . $b['match_type'] . ' #' . $b['match_id']];
            }
        }
        $um    = [];
        $sumUm = 0.0;
        foreach ($book as $x) {
            if (! $x['matched']) {
                $um[]   = [fdate($x['date']), $x['ref'], $x['desc'], peso($x['amount'])];
                $sumUm += $x['amount'];
            }
        }
        $alerts = [];
        if ($ub || $um) {
            $alerts[] = ['warning', count($ub) . ' bank entr' . (count($ub) === 1 ? 'y' : 'ies') . ' and ' . count($um) . ' book entr' . (count($um) === 1 ? 'y' : 'ies') . ' are unreconciled.'];
        } else {
            $alerts[] = ['success', 'Everything on the bank statement is matched to the books.'];
        }

        return [
            'subtitle' => 'Bank statement entries matched against the books; unmatched items flagged.',
            'period'   => 'Through ' . fdate($to),
            'tiles'    => [['Unmatched bank entries', (string) count($ub), $ub ? 'warn' : ''], ['Unmatched book entries', (string) count($um), $um ? 'warn' : ''], ['Unreconciled difference', peso($sumUb - $sumUm)]],
            'alerts'   => $alerts,
            'sections' => [
                ['heading' => 'On the bank statement but not in the books', 'columns' => ['Date', 'Reference', 'Description', 'Amount'], 'rows' => $ub, 'row_class' => array_fill(0, count($ub), 'row-pastdue')],
                ['heading' => 'In the books (checks / bank payments) but not on the bank statement', 'columns' => ['Date', 'Reference', 'Description', 'Amount'], 'rows' => $um, 'row_class' => array_fill(0, count($um), 'row-restructured')],
                ['heading' => 'Reconciled', 'columns' => ['Date', 'Reference', 'Description', 'Amount', 'Matched to'], 'rows' => $mt, 'row_class' => array_fill(0, count($mt), 'row-current')],
            ],
            'notes' => ['Enter bank statement lines and run auto-match under Accounting → Bank reconciliation.'],
        ];
    }

    protected function rTaxReport(string $month, string $from, string $to): array
    {
        $rate  = (float) config('Loaning')->incomeTaxRate;
        $year0 = substr($month, 0, 4) . '-01-01';

        $calc = function (string $a, string $b) use ($rate) {
            $inc = $this->sumTxn('interest_part', 'loan_payment', $a, $b) + $this->feeIncome($a, $b);
            $exp = array_sum($this->expensesByCategory($a, $b));
            $tax = max(0.0, $inc - $exp);

            return [$inc, $exp, $tax, round($tax * $rate / 100, 2)];
        };
        [$mi, $me, $mt, $mx] = $calc($from, $to);
        [$yi, $ye, $yt, $yx] = $calc($year0, $to);

        return [
            'subtitle' => 'Estimated income tax for the month and year to date.',
            'period'   => date('F Y', strtotime($from)),
            'tiles'    => [['Taxable income (month)', peso($mt)], ['Estimated tax (month)', peso($mx)], ['Estimated tax (YTD)', peso($yx)]],
            'sections' => [[
                'columns' => ['Line', 'This month', 'Year to date'],
                'rows'    => [
                    ['Gross income (interest + fees)', peso($mi), peso($yi)],
                    ['Less: deductible expenses', peso($me), peso($ye)],
                    ['Taxable income', peso($mt), peso($yt)],
                    ['Tax rate', number_format($rate, 2) . '%', number_format($rate, 2) . '%'],
                ],
                'totals' => ['Estimated income tax due', peso($mx), peso($yx)],
            ]],
            'notes' => ['This is an estimate only. The rate is set in Config\\Loaning (incomeTaxRate). Cooperatives may enjoy exemptions on member transactions — confirm the actual computation with your accountant or CDA/BIR guidance before filing.'],
        ];
    }

    protected function rMonthlyFinancial(string $month, string $from, string $to): array
    {
        $loans = $this->svc->allLoans();
        $act   = array_filter($loans, static fn ($l) => in_array($l['status'], ['pastdue', 'restructured', 'current'], true));
        $by    = ['pastdue' => [0, 0.0], 'restructured' => [0, 0.0], 'current' => [0, 0.0]];
        foreach ($act as $l) {
            $by[$l['status']][0]++;
            $by[$l['status']][1] += $l['outstanding'];
        }
        $rel = $this->db->query("SELECT COUNT(*) AS n, COALESCE(SUM(amount),0) AS s FROM transactions WHERE status = 'posted' AND category = 'loan_release' AND txn_date BETWEEN ? AND ?", [$from, $to])->getRowArray();

        $interest = $this->sumTxn('interest_part', 'loan_payment', $from, $to);
        $fees     = $this->feeIncome($from, $to);
        $princ    = $this->sumTxn('principal_part', 'loan_payment', $from, $to);
        $exp      = array_sum($this->expensesByCategory($from, $to));
        $net      = $interest + $fees - $exp;
        $cash     = $this->cashAsOf($to);
        $alerts   = [];
        foreach ($this->budgetRows($month) as $b) {
            if ($b['state'] === 'over') {
                $alerts[] = ['danger', 'Over budget: ' . $b['category'] . ' (' . peso($b['actual'] - $b['budget']) . ' over).'];
            }
        }
        $pend = (int) $this->db->table('approvals')->where('status', 'pending')->countAllResults();
        if ($pend) {
            $alerts[] = ['warning', $pend . ' approval request(s) still waiting for sign-off.'];
        }

        return [
            'subtitle' => 'One-page summary of portfolio, income, cash and member funds.',
            'period'   => date('F Y', strtotime($from)),
            'tiles'    => [['Net income', peso($net), $net < 0 ? 'warn' : ''], ['Loans outstanding', peso($by['pastdue'][1] + $by['restructured'][1] + $by['current'][1])], ['Cash (hand + bank)', peso($cash['hand'] + $cash['bank'])]],
            'alerts'   => $alerts,
            'sections' => [
                ['heading' => 'Loan portfolio', 'columns' => ['Measure', 'Accounts', 'Amount'], 'rows' => [
                    ['Loans released this month', (string) $rel['n'], peso($rel['s'])],
                    ['Collections — principal', '', peso($princ)],
                    ['Collections — interest', '', peso($interest)],
                    ['Past due', (string) $by['pastdue'][0], peso($by['pastdue'][1])],
                    ['Restructured', (string) $by['restructured'][0], peso($by['restructured'][1])],
                    ['Current', (string) $by['current'][0], peso($by['current'][1])],
                ], 'row_class' => [3 => 'row-pastdue', 4 => 'row-restructured', 5 => 'row-current']],
                ['heading' => 'Income', 'columns' => ['Line', 'Amount'], 'rows' => [['Interest income', peso($interest)], ['Fees', peso($fees)], ['Expenses', peso(-$exp)]], 'totals' => ['Net income', peso($net)]],
                ['heading' => 'Cash and member funds (end of month)', 'columns' => ['Line', 'Amount'], 'rows' => [
                    ['Cash on hand', peso($cash['hand'])], ['Cash in bank', peso($cash['bank'])],
                    ['Savings deposits', peso($this->ledgerTotal('savings', $to))],
                    ['Capital Build Up (CBU)', peso($this->ledgerTotal('cbu', $to))],
                    ['Mortuary fund', peso($this->ledgerTotal('mortuary', $to))],
                    ['Insurance fund', peso($this->ledgerTotal('insurance', $to))],
                ]],
            ],
        ];
    }
}
