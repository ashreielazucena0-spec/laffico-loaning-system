<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Real-world loan lifecycle:
 *   - borrower profile for credit evaluation (occupation, monthly income)
 *   - loan purpose / collateral, the credit-evaluation result, release deductions and net proceeds
 *   - late-payment penalties (one row per late month per installment) and penalty-first allocation
 *   - date the loan was fully settled
 * Run with:  php spark migrate
 */
class LoanLifecycle extends Migration
{
    private function money(): array
    {
        return ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0];
    }

    public function up(): void
    {
        $this->forge->addColumn('clients', [
            'occupation'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'co_maker'],
            'monthly_income' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true, 'after' => 'occupation'],
        ]);

        $this->forge->addColumn('loans', [
            'purpose'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'loan_type'],
            'collateral'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'purpose'],
            'evaluation'    => ['type' => 'TEXT', 'null' => true],
            'service_fee'   => $this->money(),
            'insurance_fee' => $this->money(),
            'cbu_retention' => $this->money(),
            'net_proceeds'  => $this->money(),
            'penalty_total' => $this->money(),
            'penalty_paid'  => $this->money(),
            'closed_at'     => ['type' => 'DATE', 'null' => true],
        ]);

        $this->forge->addColumn('transactions', [
            'penalty_part' => array_merge($this->money(), ['after' => 'interest_part']),
        ]);

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'loan_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'due_date'      => ['type' => 'DATE'],
            'month_no'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            'base_amount'   => $this->money(),
            'amount'        => $this->money(),
            'waived_amount' => $this->money(),
            'assessed_on'   => ['type' => 'DATE'],
            'waive_reason'  => ['type' => 'TEXT', 'null' => true],
            'waived_by'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'waived_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('loan_id');
        $this->forge->addUniqueKey(['loan_id', 'due_date', 'month_no']);
        $this->forge->createTable('penalties', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('penalties', true);
        $this->forge->dropColumn('transactions', 'penalty_part');
        $this->forge->dropColumn('loans', ['purpose', 'collateral', 'evaluation', 'service_fee', 'insurance_fee', 'cbu_retention', 'net_proceeds', 'penalty_total', 'penalty_paid', 'closed_at']);
        $this->forge->dropColumn('clients', ['occupation', 'monthly_income']);
    }
}
