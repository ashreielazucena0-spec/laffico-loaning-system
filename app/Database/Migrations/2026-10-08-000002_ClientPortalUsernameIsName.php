<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Client portal usernames are now the client's name (e.g. "Reyes, Marilou")
 * instead of the account number. Widens the column and converts existing logins.
 */
class ClientPortalUsernameIsName extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('client_users', [
            'username' => ['name' => 'username', 'type' => 'VARCHAR', 'constraint' => 150],
        ]);
        $this->db->query('UPDATE client_users cu JOIN clients c ON c.id = cu.client_id SET cu.username = c.name');
    }

    public function down(): void
    {
        $this->db->query('UPDATE client_users cu JOIN clients c ON c.id = cu.client_id SET cu.username = c.acct_no');
        $this->forge->modifyColumn('client_users', [
            'username' => ['name' => 'username', 'type' => 'VARCHAR', 'constraint' => 40],
        ]);
    }
}
