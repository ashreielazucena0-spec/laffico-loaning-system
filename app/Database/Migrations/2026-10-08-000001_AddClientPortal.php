<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Client portal: one login per client + the online payments they start.
 * Run with:  php spark migrate
 */
class AddClientPortal extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'client_id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'username'             => ['type' => 'VARCHAR', 'constraint' => 40],
            'password_hash'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'is_active'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'must_change_password' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'failed_attempts'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'locked_until'         => ['type' => 'DATETIME', 'null' => true],
            'last_login'           => ['type' => 'DATETIME', 'null' => true],
            'created_by'           => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->addUniqueKey('client_id');
        $this->forge->createTable('client_users', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'pay_ref'       => ['type' => 'VARCHAR', 'constraint' => 40],
            'client_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'loan_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'amount'        => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'driver'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'provider_id'   => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'checkout_url'  => ['type' => 'TEXT', 'null' => true],
            'status'        => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'pending'], // pending | paid | failed | cancelled
            'transaction_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'fail_reason'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'paid_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('client_id');
        $this->forge->addKey('provider_id');
        $this->forge->addUniqueKey('pay_ref');
        $this->forge->createTable('online_payments', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('online_payments', true);
        $this->forge->dropTable('client_users', true);
    }
}
