<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Business rules for the LAFFICO-MPC loaning system. Everything the
 * cooperative may want to tune lives here (or in .env, e.g.
 * loaning.incomeTaxRate = 25) instead of being buried in controllers.
 */
class Loaning extends BaseConfig
{
    /**
     * Loan products. Each product fixes the rules staff must follow, so nobody can
     * quote a client a rate, term or fee the cooperative did not approve.
     *   rate         flat % of the principal for the whole term
     *   min / max    principal range (PHP)
     *   max_term     longest term in months
     *   service_fee  % of principal deducted at release (income)
     *   insurance    % of principal deducted at release (goes to the client's Insurance ledger)
     *   cbu          % of principal retained at release (goes to the client's Capital Build Up)
     *   collateral   true = a collateral description is required
     */
    public array $loanProducts = [
        'Lending'               => ['rate' => 5.0, 'min' => 1000,  'max' => 50000,  'max_term' => 12, 'service_fee' => 2.0, 'insurance' => 0.5, 'cbu' => 1.0, 'collateral' => false],
        'Palay Production Loan' => ['rate' => 5.0, 'min' => 5000,  'max' => 100000, 'max_term' => 12, 'service_fee' => 2.0, 'insurance' => 1.0, 'cbu' => 2.0, 'collateral' => false],
        'Financial Loan'        => ['rate' => 5.0, 'min' => 5000,  'max' => 50000,  'max_term' => 24, 'service_fee' => 2.0, 'insurance' => 1.0, 'cbu' => 2.0, 'collateral' => false],
        'Salary Loan'           => ['rate' => 5.0, 'min' => 1000,  'max' => 30000,  'max_term' => 12, 'service_fee' => 1.5, 'insurance' => 0.5, 'cbu' => 1.0, 'collateral' => false],
        'Vehicle Loan'          => ['rate' => 5.0, 'min' => 20000, 'max' => 150000, 'max_term' => 36, 'service_fee' => 2.0, 'insurance' => 1.5, 'cbu' => 2.0, 'collateral' => true],
        'Emergency Loan'        => ['rate' => 5.0, 'min' => 500,   'max' => 10000,  'max_term' => 6,  'service_fee' => 1.0, 'insurance' => 0.0, 'cbu' => 0.0, 'collateral' => false],
    ];

    /** Names of the products above (filled in by the constructor). */
    public array $loanTypes = [];

    /** Default interest: flat % of the principal for the whole term. */
    public float $defaultInterestRate = 5.0;

    // ---- credit evaluation (done automatically when a loan is applied for) ----
    /** Hard stop: a client may not hold more open loans than this. */
    public int $maxActiveLoans = 3;
    /** Flag for the Manager: no co-maker on file for a principal at or above this amount. */
    public float $coMakerThreshold = 30000.0;
    /** Flag for the Manager: all monthly dues (old + new) above this % of the client's monthly income. */
    public float $maxDebtServicePct = 40.0;
    /** Flag for the Manager: principal above this many times the client's Capital Build Up (0 = don't check). */
    public float $loanToCbuMultiple = 0.0;

    // ---- late-payment penalty ----
    /** % of the unpaid installment charged for each month it stays late (0 = no penalties). */
    public float $penaltyRatePct = 3.0;
    /** Days after the due date before the first penalty applies. */
    public int $penaltyGraceDays = 3;
    /** Penalty months stop piling up on one installment after this many months. */
    public int $penaltyMaxMonths = 12;

    public function __construct()
    {
        parent::__construct();
        $this->loanTypes = array_keys($this->loanProducts);
    }

    // ---- automated reminders (days relative to the unpaid installment's due date) ----
    public int $reminderDueSoonDays = 3;   // heads-up before the due date
    public int $reminder1Days       = 15;  // 1st reminder: 15 days overdue
    public int $reminder2Days       = 30;  // 2nd reminder: 30 days overdue
    public int $collection1Days     = 45;  // 1st collection letter
    public int $collection2Days     = 60;  // 2nd collection letter
    public int $remedialDays        = 75;  // remedial action / small claims
    public int $restructuredGraceDays = 30; // a restructured loan turns past-due after this many days late

    /** Try to e-mail reminders too (needs Config\Email set up and a client e-mail address). */
    public bool $emailReminders = true;

    /** Estimated income tax rate (%) used by the Tax Report. Confirm with your tax adviser. */
    public float $incomeTaxRate = 25.0;

    // ---- uploads ----
    public int $maxUploadKb = 5120;
    public array $allowedUploadExt = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];

    public array $docTypes = [
        'application_for_loan' => 'Application for Loan',
        'official_receipt'     => 'Official receipt',
        'payment_record'       => 'Payment record',
        'loan_document'        => 'Loan document',
        'voucher'              => 'Voucher',
        'deposit_slip'         => 'Deposit slip',
        'invoice'              => 'Invoice',
        'cancellation_support' => 'Cancellation support',
        'other'                => 'Other supporting form',
    ];

    // ---- people ----
    /** The three roles of the system. */
    public array $roles = [
        'admin'    => 'Administrator',
        'manager'  => 'Manager',
        'employee' => 'Employee',
    ];

    /** What every role can do. Employee = day-to-day work; Manager = employee + approvals; Admin = user accounts only. */
    public array $permissions = [
        'employee' => ['clients.add', 'loans.create', 'loans.restructure', 'ledger.add', 'txn.record', 'txn.cancel',
                       'documents.upload', 'reports.view', 'accounting.manage', 'reminders.manage'],
        'manager'  => ['clients.add', 'loans.create', 'loans.restructure', 'ledger.add', 'txn.record', 'txn.cancel',
                       'documents.upload', 'reports.view', 'accounting.manage', 'reminders.manage',
                       'approve.manager', 'clients.delete'],
        'admin'    => ['users.manage', 'backups.manage', 'reports.view'],
    ];

    // ---- ledger accounts a client can hold ----
    public array $ledgerTypes = [
        'savings'   => ['label' => 'Savings',            'prefix' => 'SV', 'deposit' => 'savings_deposit',   'withdrawal' => 'savings_withdrawal'],
        'cbu'       => ['label' => 'Capital Build Up',   'prefix' => 'CB', 'deposit' => 'cbu_deposit',       'withdrawal' => 'cbu_withdrawal'],
        'mortuary'  => ['label' => 'Mortuary',           'prefix' => 'MF', 'deposit' => 'mortuary_payment',  'withdrawal' => null],
        'insurance' => ['label' => 'Insurance',          'prefix' => 'IN', 'deposit' => 'insurance_payment', 'withdrawal' => null],
    ];

    /** dir: in = money received, out = money paid, none = balance adjustment only (no cash moves). */
    public array $txnCategories = [
        'loan_payment'       => ['label' => 'Loan payment',                 'dir' => 'in',   'ledger' => null],
        'loan_release'       => ['label' => 'Loan release',                 'dir' => 'out',  'ledger' => null],
        'adjustment'         => ['label' => 'Loan adjustment',              'dir' => 'none', 'ledger' => null],
        'mortuary_payment'   => ['label' => 'Mortuary payment',             'dir' => 'in',   'ledger' => 'mortuary'],
        'cbu_deposit'        => ['label' => 'Capital Build Up (CBU)',       'dir' => 'in',   'ledger' => 'cbu'],
        'savings_deposit'    => ['label' => 'Savings deposit',              'dir' => 'in',   'ledger' => 'savings'],
        'insurance_payment'  => ['label' => 'Insurance payment',            'dir' => 'in',   'ledger' => 'insurance'],
        'service_fee'        => ['label' => 'Service / membership fee',     'dir' => 'in',   'ledger' => null],
        'savings_withdrawal' => ['label' => 'Savings withdrawal',           'dir' => 'out',  'ledger' => 'savings'],
        'cbu_withdrawal'     => ['label' => 'CBU withdrawal',               'dir' => 'out',  'ledger' => 'cbu'],
    ];

    public array $expenseCategories = [
        'Salaries and wages',
        'Office and admin',
        'Printing and supplies',
        'Utilities',
        'Transportation',
        'Professional fees',
        'Provision for loan losses',
        'Other expenses',
    ];
}
