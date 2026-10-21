<?php

namespace App\Libraries;

/** Writes one row to the audit trail. Safe to call from the web, the CLI and the seeder. */
class Audit
{
    public static function log(string $action, string $detail = '', ?string $who = null): void
    {
        try {
            $userId = null;
            $role   = null;
            $name   = $who;
            $ip     = null;

            try {
                $session = session();
                $userId  = $session->get('user_id') ?: null;
                $role    = $session->get('role') ?: null;
                $name    = $name ?? ($session->get('full_name') ?: null);
                $ip      = service('request')->getIPAddress();
            } catch (\Throwable $e) {
                // CLI / seeder: no session available
            }

            db_connect()->table('audit_logs')->insert([
                'user_id'    => $userId,
                'user_name'  => $name ?: 'System',
                'role'       => $role,
                'action'     => mb_substr($action, 0, 120),
                'detail'     => $detail,
                'ip'         => $ip,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Audit log failed: ' . $e->getMessage());
        }
    }
}
