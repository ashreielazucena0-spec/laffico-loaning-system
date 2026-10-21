<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/** Creates the first administrator on an empty database:  php spark db:seed AdminSeeder */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->db->table('users')->where('username', 'admin')->countAllResults() > 0) {
            echo "An 'admin' user already exists.\n";

            return;
        }
        $this->db->table('users')->insert([
            'username' => 'admin', 'password_hash' => password_hash('ChangeMe#2026', PASSWORD_DEFAULT), 'full_name' => 'System Administrator',
            'role' => 'admin', 'is_active' => 1, 'must_change_password' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        echo "Created admin / ChangeMe#2026 (you must change it at first sign-in).\n";
    }
}
