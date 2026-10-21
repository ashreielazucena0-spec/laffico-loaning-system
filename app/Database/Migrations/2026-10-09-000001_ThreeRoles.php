<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The system has three roles: admin, manager, employee.
 * users.role becomes ENUM('admin','manager','employee'); any other existing value becomes 'employee'.
 */
class ThreeRoles extends Migration
{
    public function up(): void
    {
        $this->db->query("UPDATE users SET role = 'employee' WHERE role NOT IN ('admin', 'manager', 'employee')");
        $this->db->query("ALTER TABLE users MODIFY role ENUM('admin','manager','employee') NOT NULL DEFAULT 'employee'");
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE users MODIFY role VARCHAR(20) NOT NULL DEFAULT 'employee'");
    }
}
