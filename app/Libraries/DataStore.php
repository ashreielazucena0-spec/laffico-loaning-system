<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseBuilder;
use Config\Database as DatabaseConfig;

/**
 * DataStore
 *
 * Same static API the app was originally written against (all/find/insert/
 * update/delete/count/log/export/import/reset/nextId), but now backed by the
 * real `barangay_secretary` MySQL database instead of writable/data/db.json.
 *
 * Every Controller in the app keeps calling DataStore::all('residents') etc.
 * exactly as before — only this class changed.
 */
class DataStore
{
    /**
     * The app code refers to the activity log as table "logs"; the actual
     * MySQL table (see barangay_secretary_database.sql) is "activity_logs".
     */
    protected static array $tableMap = [
        'logs' => 'activity_logs',
    ];

    /**
     * How many recent rows to keep in activity_logs, mirroring the old
     * JSON store's `array_slice(..., 0, 300)` trimming.
     */
    protected static int $logLimit = 300;

    protected static function realTable(string $table): string
    {
        return self::$tableMap[$table] ?? $table;
    }

    protected static function db()
    {
        return db_connect();
    }

    protected static function builder(string $table): BaseBuilder
    {
        return self::db()->table(self::realTable($table));
    }

    /**
     * Demo dataset used by reset() — matches the original seed() that used
     * to ship with the JSON DataStore (kept separate from the user's real
     * imported data in barangay_secretary_database.sql).
     */
    protected static function seed(): array
    {
        $demoHash = password_hash('resident123', PASSWORD_DEFAULT);

        return [
            'certificate_types' => [
                ['id' => 'CT-001', 'name' => 'Barangay Clearance', 'fee' => 100, 'description' => null],
                ['id' => 'CT-002', 'name' => 'Certificate of Residency', 'fee' => 100, 'description' => null],
                ['id' => 'CT-003', 'name' => 'Certificate of Indigency', 'fee' => 100, 'description' => null],
                ['id' => 'CT-004', 'name' => 'Business Clearance', 'fee' => 100, 'description' => null],
                ['id' => 'CT-005', 'name' => 'Barangay Certificate', 'fee' => 100, 'description' => null],
            ],
            'residents' => [
                ['id' => 'R-001', 'first' => 'Juan', 'middle' => 'Santos', 'last' => 'Dela Cruz', 'birth' => '1990-04-12', 'sex' => 'Male', 'civil' => 'Married', 'contact' => '09171234567', 'purok' => 'Purok Falcata', 'household_id' => 'HH-001', 'occupation' => 'Farmer', 'voter' => 'Registered', 'senior' => 'No', 'pwd' => 'No'],
                ['id' => 'R-002', 'first' => 'Maria', 'middle' => 'Lopez', 'last' => 'Santos', 'birth' => '1995-08-21', 'sex' => 'Female', 'civil' => 'Single', 'contact' => '09181234567', 'purok' => 'Purok Acasia', 'household_id' => 'HH-002', 'occupation' => 'Teacher', 'voter' => 'Registered', 'senior' => 'No', 'pwd' => 'No'],
                ['id' => 'R-003', 'first' => 'Pedro', 'middle' => '', 'last' => 'Garcia', 'birth' => '1958-02-03', 'sex' => 'Male', 'civil' => 'Widowed', 'contact' => '09191234567', 'purok' => 'Purok Subis', 'household_id' => 'HH-002', 'occupation' => 'Retired', 'voter' => 'Registered', 'senior' => 'Yes', 'pwd' => 'No'],
            ],
            'households' => [
                ['id' => 'HH-001', 'head' => 'R-001', 'purok' => 'Purok Falcata', 'street' => 'Rizal Street', 'in_building' => 'No', 'building' => null, 'floor' => null, 'members' => 1],
                ['id' => 'HH-002', 'head' => 'R-002', 'purok' => 'Purok Acasia', 'street' => 'Mabini Street', 'in_building' => 'Yes', 'building' => 'Sumadat Apartments', 'floor' => '2nd Floor', 'members' => 2],
            ],
            'users' => [
                ['id' => 'U-001', 'username' => 'admin', 'name' => 'Administrator', 'role' => 'Admin', 'status' => 'Active', 'password' => 'admin123', 'resident_id' => null, 'temp_password' => 0],
                ['id' => 'U-002', 'username' => 'secretary', 'name' => 'Maria Santos', 'role' => 'Secretary', 'status' => 'Active', 'password' => 'secretary123', 'resident_id' => null, 'temp_password' => 0],
                ['id' => 'U-003', 'username' => 'juan.delacruz', 'name' => 'Juan Dela Cruz', 'role' => 'Resident', 'status' => 'Active', 'password' => $demoHash, 'resident_id' => 'R-001', 'temp_password' => 1],
                ['id' => 'U-004', 'username' => 'maria.santos', 'name' => 'Maria Santos', 'role' => 'Resident', 'status' => 'Active', 'password' => $demoHash, 'resident_id' => 'R-002', 'temp_password' => 1],
                ['id' => 'U-005', 'username' => 'pedro.garcia', 'name' => 'Pedro Garcia', 'role' => 'Resident', 'status' => 'Active', 'password' => $demoHash, 'resident_id' => 'R-003', 'temp_password' => 1],
            ],
            'officials' => [
                ['id' => 'O-001', 'name' => 'Juan Dela Cruz', 'position' => 'Punong Barangay', 'term' => '2023-2026', 'contact' => '09171234567'],
                ['id' => 'O-002', 'name' => 'Maria Santos', 'position' => 'Barangay Secretary', 'term' => '2023-2026', 'contact' => '09181234567'],
                ['id' => 'O-003', 'name' => '', 'position' => 'Barangay Treasurer', 'term' => '2023-2026', 'contact' => ''],
                ['id' => 'O-004', 'name' => '', 'position' => 'Kagawad', 'term' => '2023-2026', 'contact' => ''],
                ['id' => 'O-005', 'name' => '', 'position' => 'Kagawad', 'term' => '2023-2026', 'contact' => ''],
                ['id' => 'O-006', 'name' => '', 'position' => 'Kagawad', 'term' => '2023-2026', 'contact' => ''],
                ['id' => 'O-007', 'name' => '', 'position' => 'Kagawad', 'term' => '2023-2026', 'contact' => ''],
                ['id' => 'O-008', 'name' => '', 'position' => 'Kagawad', 'term' => '2023-2026', 'contact' => ''],
                ['id' => 'O-009', 'name' => '', 'position' => 'Kagawad', 'term' => '2023-2026', 'contact' => ''],
                ['id' => 'O-010', 'name' => '', 'position' => 'Kagawad', 'term' => '2023-2026', 'contact' => ''],
                ['id' => 'O-011', 'name' => '', 'position' => 'SK Chairperson', 'term' => '2023-2026', 'contact' => ''],
            ],
            'requests' => [
                ['id' => 'REQ-001', 'resident' => 'R-002', 'type' => 'Certificate of Residency', 'purpose' => 'School', 'date' => date('Y-m-d'), 'status' => 'Pending'],
            ],
            'certificates' => [
                ['id' => 'CERT-001', 'resident' => 'R-001', 'type' => 'Barangay Clearance', 'purpose' => 'Employment', 'date' => date('Y-m-d'), 'amount' => 50, 'request_id' => null],
            ],
            'cases' => [
                ['id' => 'B-' . date('Y') . '-001', 'complainant' => 'R-001', 'respondent' => 'R-002', 'nature' => 'Noise Complaint', 'date' => date('Y-m-d'), 'status' => 'Pending', 'details' => 'Excessive noise at night.', 'action' => 'Scheduled mediation.'],
            ],
            'meetings' => [
                ['id' => 'M-001', 'title' => 'Barangay Assembly', 'date' => date('Y-m-d', strtotime('+3 day')), 'time' => '09:00', 'venue' => 'Barangay Hall'],
            ],
            'documents' => [
                ['id' => 'RES-' . date('Y') . '-001', 'number' => 'RES-' . date('Y') . '-001', 'title' => 'Waste Management Resolution', 'type' => 'Resolution', 'date' => date('Y-m-d'), 'file_name' => null, 'file_path' => null],
            ],
            'payments' => [
                ['id' => 'OR-001', 'resident' => 'R-001', 'type' => 'Barangay Clearance', 'amount' => 50, 'date' => date('Y-m-d')],
            ],
        ];
    }

    /** Tables that have a created_at column we can order new-first/old-first by. */
    protected static function hasTimestamps(string $table): bool
    {
        return self::realTable($table) !== 'activity_logs';
    }

    public static function all(string $table): array
    {
        $builder = self::builder($table);

        if (self::realTable($table) === 'activity_logs') {
            $builder->orderBy('id', 'DESC');
        } elseif (self::hasTimestamps($table)) {
            $builder->orderBy('created_at', 'ASC')->orderBy('id', 'ASC');
        }

        return $builder->get()->getResultArray();
    }

    public static function find(string $table, string $id): ?array
    {
        $row = self::builder($table)->where('id', $id)->get()->getRowArray();
        return $row ?: null;
    }

    /** Rows matching simple column = value conditions. */
    public static function where(string $table, array $conditions): array
    {
        $builder = self::builder($table)->where($conditions);
        if (self::hasTimestamps($table)) {
            $builder->orderBy('created_at', 'ASC')->orderBy('id', 'ASC');
        }

        return $builder->get()->getResultArray();
    }

    /** Apply the same change to every row in a table (e.g. a new officials term). */
    public static function updateAll(string $table, array $changes): void
    {
        self::builder($table)->where('1 = 1', null, false)->update($changes);
    }

    public static function countWhere(string $table, array $conditions): int
    {
        return self::builder($table)->where($conditions)->countAllResults();
    }

    public static function nextId(string $table, string $prefix, int $pad = 3): string
    {
        $rows = self::db()
            ->table(self::realTable($table))
            ->select('id')
            ->like('id', $prefix, 'after')
            ->get()
            ->getResultArray();

        $max = 0;
        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');
            if (str_starts_with($id, $prefix)) {
                $num = (int) substr($id, strlen($prefix));
                $max = max($max, $num);
            }
        }

        return $prefix . str_pad((string) ($max + 1), $pad, '0', STR_PAD_LEFT);
    }

    public static function insert(string $table, array $row): array
    {
        self::builder($table)->insert($row);
        return $row;
    }

    public static function update(string $table, string $id, array $changes): bool
    {
        if (! self::find($table, $id)) {
            return false;
        }

        self::builder($table)->where('id', $id)->update($changes);
        return true;
    }

    /**
     * Set by delete() when a row couldn't be removed because other records
     * still reference it (a foreign key, e.g. a resident used on a
     * certificate/household/etc). Controllers can check this right after
     * a failed delete() to show a friendly message instead of a crash.
     */
    public static bool $lastDeleteBlockedByReference = false;

    public static function delete(string $table, string $id): bool
    {
        self::$lastDeleteBlockedByReference = false;

        if (! self::find($table, $id)) {
            return false;
        }

        try {
            self::builder($table)->where('id', $id)->delete();
            return true;
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            // MySQL error 1451: a foreign key elsewhere still points at this row.
            if (str_contains($e->getMessage(), '1451') || stripos($e->getMessage(), 'foreign key') !== false) {
                self::$lastDeleteBlockedByReference = true;
                return false;
            }
            throw $e;
        }
    }

    public static function count(string $table): int
    {
        return self::builder($table)->countAllResults();
    }

    public static function log(string $user, string $action): void
    {
        self::db()->table('activity_logs')->insert([
            'time'   => date('Y-m-d h:i A'),
            'user'   => $user,
            'action' => $action,
        ]);

        // Keep activity_logs from growing forever, same trimming behaviour
        // the old JSON store had (array_slice(..., 0, 300)).
        $total = self::db()->table('activity_logs')->countAllResults();
        if ($total > self::$logLimit) {
            $cutoff = self::db()
                ->table('activity_logs')
                ->select('id')
                ->orderBy('id', 'DESC')
                ->limit(1, self::$logLimit - 1)
                ->get()
                ->getRow();

            if ($cutoff) {
                self::db()->table('activity_logs')->where('id <', $cutoff->id)->delete();
            }
        }
    }

    /**
     * All table names, in an order that's safe to TRUNCATE / re-insert
     * against (parents before the tables that hold foreign keys to them).
     */
    protected static function tablesInOrder(): array
    {
        return [
            'certificate_types', 'residents', 'households', 'users', 'officials',
            'requests', 'certificates', 'cases', 'payments',
            'meetings', 'documents',
            'activity_logs',
        ];
    }

    public static function export(): string
    {
        $out = [];
        foreach (self::tablesInOrder() as $table) {
            $key = array_search($table, self::$tableMap) ?: $table;
            $out[$key] = self::db()->table($table)->orderBy('1', 'ASC', false)->get()->getResultArray();
        }
        return json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function import(string $json): bool
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return false;
        }

        $db = self::db();
        $db->transStart();
        $db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::tablesInOrder() as $table) {
            $key  = array_search($table, self::$tableMap) ?: $table;
            $rows = $data[$key] ?? null;
            if (! is_array($rows)) {
                continue;
            }

            $builder = $db->table($table);
            $builder->emptyTable();

            $rows = array_filter($rows, 'is_array');
            if ($rows) {
                // insertBatch needs consistent keys across all rows.
                foreach (array_chunk($rows, 200) as $chunk) {
                    $builder->insertBatch(array_values($chunk));
                }
            }
        }

        $db->query('SET FOREIGN_KEY_CHECKS = 1');
        $db->transComplete();

        return $db->transStatus() !== false;
    }

    public static function reset(): void
    {
        $db = self::db();
        $db->transStart();
        $db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::tablesInOrder() as $table) {
            $db->table($table)->emptyTable();
        }

        $seed = self::seed();
        foreach ($seed as $table => $rows) {
            if ($rows) {
                $db->table($table)->insertBatch($rows);
            }
        }

        $db->query('SET FOREIGN_KEY_CHECKS = 1');
        $db->transComplete();
    }
}
