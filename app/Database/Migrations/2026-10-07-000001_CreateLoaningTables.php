<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates every table the loaning system uses.
 * Run with:  php spark migrate
 */
class CreateLoaningTables extends Migration
{
    private function pk(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
    }

    private function int(bool $null = false, $default = null): array
    {
        $f = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => $null];
        if ($default !== null) {
            $f['default'] = $default;
        }

        return $f;
    }

    private function str(int $len, bool $null = false, $default = null): array
    {
        $f = ['type' => 'VARCHAR', 'constraint' => $len, 'null' => $null];
        if ($default !== null) {
            $f['default'] = $default;
        }

        return $f;
    }

    private function money(): array
    {
        return ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0];
    }

    private function flag(int $default = 0): array
    {
        return ['type' => 'TINYINT', 'constraint' => 1, 'default' => $default];
    }

    private function date(bool $null = true): array
    {
        return ['type' => 'DATE', 'null' => $null];
    }

    private function dt(bool $null = true): array
    {
        return ['type' => 'DATETIME', 'null' => $null];
    }

    private function text(): array
    {
        return ['type' => 'TEXT', 'null' => true];
    }

    private function make(string $table, array $fields, array $keys = [], array $uniques = []): void
    {
        $this->forge->addField($fields);
        $this->forge->addKey('id', true);
        foreach ($keys as $k) {
            $this->forge->addKey($k);
        }
        foreach ($uniques as $u) {
            $this->forge->addUniqueKey($u);
        }
        $this->forge->createTable($table, true, ['ENGINE' => 'InnoDB']);
    }

    public function up(): void
    {
        $this->make('users', [
            'id'                   => $this->pk(),
            'username'             => $this->str(60),
            'password_hash'        => $this->str(255),
            'full_name'            => $this->str(120),
            'role'                 => $this->str(20, false, 'staff'),
            'is_active'            => $this->flag(1),
            'must_change_password' => $this->flag(0),
            'failed_attempts'      => $this->int(false, 0),
            'locked_until'         => $this->dt(),
            'last_login'           => $this->dt(),
            'created_at'           => $this->dt(),
        ], [], ['username']);

        $this->make('clients', [
            'id'         => $this->pk(),
            'acct_no'    => $this->str(20),
            'name'       => $this->str(150),
            'contact'    => $this->str(60, true),
            'email'      => $this->str(120, true),
            'address'    => $this->str(255, true),
            'co_maker'   => $this->str(150, true),
            'opened_at'  => $this->date(),
            'created_by' => $this->str(120, true),
            'created_at' => $this->dt(),
        ], ['name'], ['acct_no']);

        $this->make('ledger_accounts', [
            'id'         => $this->pk(),
            'client_id'  => $this->int(),
            'ledger_type' => $this->str(20),
            'account_no' => $this->str(30),
            'opened_at'  => $this->date(),
            'created_by' => $this->str(120, true),
            'created_at' => $this->dt(),
        ], [], ['account_no', ['client_id', 'ledger_type']]);

        $this->make('loans', [
            'id'               => $this->pk(),
            'loan_no'          => $this->str(40),
            'client_id'        => $this->int(),
            'loan_type'        => $this->str(60),
            'principal'        => $this->money(),
            'interest_rate'    => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 0],
            'interest_amount'  => $this->money(),
            'term_months'      => $this->int(false, 1),
            'total_payable'    => $this->money(),
            'monthly_due'      => $this->money(),
            'release_date'     => $this->date(false),
            'first_due_date'   => $this->date(false),
            'release_method'   => $this->str(10, false, 'cash'),
            'sched_terms'      => $this->int(false, 1),
            'sched_total'      => $this->money(),
            'sched_offset'     => $this->money(),
            'paid_total'       => $this->money(),
            'adjustment_total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'is_restructured'  => $this->flag(0),
            'restructured_at'  => $this->date(),
            'approval_status'  => $this->str(12, false, 'pending'),
            'required_role'    => $this->str(12, false, 'manager'),
            'approved_by'      => $this->str(120, true),
            'approved_at'      => $this->dt(),
            'remarks'          => $this->text(),
            'created_by'       => $this->str(120, true),
            'created_at'       => $this->dt(),
        ], ['client_id', 'approval_status'], ['loan_no']);

        $this->make('transactions', [
            'id'               => $this->pk(),
            'txn_no'           => $this->str(40),
            'txn_date'         => $this->date(false),
            'client_id'        => $this->int(true),
            'loan_id'          => $this->int(true),
            'category'         => $this->str(30),
            'ledger_type'      => $this->str(20, true),
            'direction'        => $this->str(5, false, 'in'),
            'adj_sign'         => ['type' => 'TINYINT', 'constraint' => 2, 'default' => 1],
            'amount'           => $this->money(),
            'method'           => $this->str(10, true),
            'reference'        => $this->str(60, true),
            'remarks'          => $this->str(255, true),
            'principal_part'   => $this->money(),
            'interest_part'    => $this->money(),
            'status'           => $this->str(10, false, 'posted'),
            'cancel_requested' => $this->flag(0),
            'cancel_reason'    => $this->text(),
            'cancelled_by'     => $this->str(120, true),
            'cancelled_at'     => $this->dt(),
            'recorded_by'      => $this->str(120, true),
            'created_at'       => $this->dt(),
        ], ['txn_date', 'client_id', 'loan_id', 'category'], ['txn_no']);

        $this->make('documents', [
            'id'             => $this->pk(),
            'client_id'      => $this->int(true),
            'loan_id'        => $this->int(true),
            'transaction_id' => $this->int(true),
            'approval_id'    => $this->int(true),
            'doc_type'       => $this->str(40, false, 'other'),
            'title'          => $this->str(150, true),
            'original_name'  => $this->str(255),
            'stored_name'    => $this->str(255),
            'mime'           => $this->str(100, true),
            'size'           => $this->int(false, 0),
            'uploaded_by'    => $this->str(120, true),
            'uploaded_at'    => $this->dt(),
        ], ['client_id', 'loan_id', 'transaction_id', 'approval_id']);

        $this->make('approvals', [
            'id'             => $this->pk(),
            'entity_type'    => $this->str(20),
            'entity_id'      => $this->int(),
            'client_id'      => $this->int(true),
            'summary'        => $this->str(255),
            'amount'         => $this->money(),
            'payload'        => $this->text(),
            'required_role'  => $this->str(12, false, 'manager'),
            'status'         => $this->str(10, false, 'pending'),
            'requested_by'   => $this->str(120, true),
            'requested_by_id' => $this->int(true),
            'requested_role' => $this->str(20, true),
            'requested_at'   => $this->dt(),
            'decided_by'     => $this->str(120, true),
            'decided_by_id'  => $this->int(true),
            'decided_role'   => $this->str(20, true),
            'decided_at'     => $this->dt(),
            'decision_note'  => $this->text(),
        ], ['status', 'entity_type', 'entity_id']);

        $this->make('notifications', [
            'id'              => $this->pk(),
            'loan_id'         => $this->int(),
            'client_id'       => $this->int(),
            'kind'            => $this->str(20),
            'installment_due' => $this->date(),
            'days_overdue'    => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'message'         => $this->text(),
            'channel'         => $this->str(20, false, 'system'),
            'is_auto'         => $this->flag(1),
            'status'          => $this->str(15, false, 'sent'),
            'created_by'      => $this->str(120, true),
            'sent_at'         => $this->dt(),
        ], ['client_id', 'status'], [['loan_id', 'kind', 'installment_due']]);

        $this->make('audit_logs', [
            'id'         => $this->pk(),
            'user_id'    => $this->int(true),
            'user_name'  => $this->str(120),
            'role'       => $this->str(20, true),
            'action'     => $this->str(120),
            'detail'     => $this->text(),
            'ip'         => $this->str(45, true),
            'created_at' => $this->dt(),
        ], ['created_at']);

        $this->make('expenses', [
            'id'             => $this->pk(),
            'expense_date'   => $this->date(false),
            'category'       => $this->str(60),
            'description'    => $this->str(255),
            'supplier'       => $this->str(120, true),
            'invoice_no'     => $this->str(60, true),
            'amount'         => $this->money(),
            'payment_status' => $this->str(10, false, 'paid'),
            'due_date'       => $this->date(),
            'paid_date'      => $this->date(),
            'paid_via'       => $this->str(10, true),
            'created_by'     => $this->str(120, true),
            'created_at'     => $this->dt(),
        ], ['expense_date', 'payment_status']);

        $this->make('budgets', [
            'id'       => $this->pk(),
            'month'    => ['type' => 'CHAR', 'constraint' => 7],
            'category' => $this->str(60),
            'amount'   => $this->money(),
        ], [], [['month', 'category']]);

        $this->make('bank_entries', [
            'id'          => $this->pk(),
            'entry_date'  => $this->date(false),
            'reference'   => $this->str(60, true),
            'description' => $this->str(255, true),
            'amount'      => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'match_type'  => $this->str(12, true),
            'match_id'    => $this->int(true),
            'created_by'  => $this->str(120, true),
            'created_at'  => $this->dt(),
        ], ['entry_date']);

        $this->forge->addField([
            'name'  => ['type' => 'VARCHAR', 'constraint' => 60],
            'value' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->forge->addKey('name', true);
        $this->forge->createTable('settings', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        foreach (['settings', 'bank_entries', 'budgets', 'expenses', 'audit_logs', 'notifications', 'approvals',
                  'documents', 'transactions', 'loans', 'ledger_accounts', 'clients', 'users'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
