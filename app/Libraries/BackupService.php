<?php

namespace App\Libraries;

/**
 * Database backups: a JSON snapshot of every table, stored in writable/backups.
 * JSON (instead of a MySQL dump) keeps restore independent of the DB driver
 * and of command-line tools such as mysqldump.
 *
 * Uploaded documents live in writable/uploads — copy that folder too for a
 * full backup (see README).
 */
class BackupService
{
    /** Tables in restore order. */
    public const TABLES = [
        'users', 'clients', 'ledger_accounts', 'loans', 'transactions', 'documents',
        'approvals', 'notifications', 'audit_logs', 'expenses', 'budgets', 'bank_entries', 'settings',
        'client_users', 'online_payments',
    ];

    protected $db;
    protected string $dir;

    public function __construct()
    {
        $this->db  = db_connect();
        $this->dir = WRITEPATH . 'backups';
        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
    }

    public function create(string $by, string $note = ''): array
    {
        $data = ['meta' => [
            'app'        => 'LAFFICO-MPC Loaning System',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $by,
            'note'       => $note,
        ], 'tables' => []];
        $counts = [];
        foreach (self::TABLES as $t) {
            $rows              = $this->db->table($t)->get()->getResultArray();
            $data['tables'][$t] = $rows;
            $counts[$t]        = count($rows);
        }
        $data['meta']['row_counts'] = $counts;

        $name = 'backup_' . date('Ymd_His') . '.json';
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($this->dir . DIRECTORY_SEPARATOR . $name, $json) === false) {
            return ['ok' => false, 'error' => 'Could not write the backup file. Check that writable/backups is writable.'];
        }
        Audit::log('Created database backup', $name . ' (' . array_sum($counts) . ' rows)', $by);

        return ['ok' => true, 'name' => $name];
    }

    public function list(): array
    {
        $out = [];
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . 'backup_*.json') ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
        }
        usort($out, static fn ($a, $b) => $b['time'] <=> $a['time']);

        return $out;
    }

    public function path(string $name): ?string
    {
        if (! preg_match('/^backup_\d{8}_\d{6}\.json$/', $name)) {
            return null;
        }
        $p = $this->dir . DIRECTORY_SEPARATOR . $name;

        return is_file($p) ? $p : null;
    }

    public function delete(string $name, string $by): bool
    {
        $p = $this->path($name);
        if ($p === null) {
            return false;
        }
        unlink($p);
        Audit::log('Deleted database backup', $name, $by);

        return true;
    }

    /** Replaces ALL data with the snapshot. A safety backup is taken first. */
    public function restore(string $name, string $by): array
    {
        $p = $this->path($name);
        if ($p === null) {
            return ['ok' => false, 'error' => 'Backup file not found.'];
        }
        $data = json_decode((string) file_get_contents($p), true);
        if (! is_array($data) || ! isset($data['tables'])) {
            return ['ok' => false, 'error' => 'That file is not a valid backup.'];
        }

        $safety = $this->create($by, 'Automatic safety copy before restoring ' . $name);
        if (! $safety['ok']) {
            return $safety;
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->transStart();
        foreach (array_reverse(self::TABLES) as $t) {
            $this->db->table($t)->emptyTable();
        }
        foreach (self::TABLES as $t) {
            $rows = $data['tables'][$t] ?? [];
            foreach (array_chunk($rows, 200) as $chunk) {
                if ($chunk) {
                    $this->db->table($t)->insertBatch($chunk);
                }
            }
        }
        $this->db->transComplete();
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        if (! $this->db->transStatus()) {
            return ['ok' => false, 'error' => 'Restore failed and was rolled back. Your data is unchanged (a safety backup was also saved).'];
        }
        Audit::log('Restored database backup', $name . ' (safety copy: ' . $safety['name'] . ')', $by);

        return ['ok' => true, 'safety' => $safety['name']];
    }
}
