<?php

/**
 * Small view/controller helpers shared across the app.
 */

if (! function_exists('peso')) {
    function peso($amount, bool $symbol = true): string
    {
        $n = (float) $amount;
        $s = ($symbol ? '₱' : '') . number_format(abs($n), 2);

        return $n < -0.004 ? '(' . $s . ')' : $s;
    }
}

if (! function_exists('fdate')) {
    function fdate(?string $date, string $format = 'M d, Y'): string
    {
        if (! $date || $date === '0000-00-00') {
            return '—';
        }
        $ts = strtotime($date);

        return $ts ? date($format, $ts) : '—';
    }
}

if (! function_exists('days_between')) {
    /** Whole days from $from to $to (positive when $to is later). */
    function days_between(string $from, string $to): int
    {
        return (int) round((strtotime($to) - strtotime($from)) / 86400);
    }
}

if (! function_exists('add_days')) {
    function add_days(string $date, int $days): string
    {
        return date('Y-m-d', strtotime($date . ' ' . sprintf('%+d', $days) . ' days'));
    }
}

if (! function_exists('add_months')) {
    /** Adds calendar months, clamping to month end (Jan 31 + 1 month = Feb 28/29). */
    function add_months(string $date, int $months): string
    {
        $d     = new DateTimeImmutable($date);
        $day   = (int) $d->format('j');
        $first = $d->modify('first day of this month')->modify(sprintf('%+d months', $months));
        $last  = (int) $first->format('t');

        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), min($day, $last))->format('Y-m-d');
    }
}

if (! function_exists('status_rank')) {
    function status_rank(string $status): int
    {
        $ranks = ['pastdue' => 0, 'restructured' => 1, 'current' => 2, 'paid' => 3, 'pending' => 4, 'rejected' => 5];

        return $ranks[$status] ?? 9;
    }
}

if (! function_exists('status_label')) {
    function status_label(string $status, int $days = 0): string
    {
        switch ($status) {
            case 'pastdue':
                return $days > 0 ? 'Past due · ' . $days . ' day' . ($days === 1 ? '' : 's') : 'Past due';
            case 'restructured':
                return 'Restructured';
            case 'current':
                return 'Current';
            case 'paid':
                return 'Paid';
            case 'pending':
                return 'Pending approval';
            case 'rejected':
                return 'Rejected';
        }

        return ucfirst($status);
    }
}

if (! function_exists('status_badge')) {
    function status_badge(string $status, int $days = 0): string
    {
        return '<span class="tln-status ' . esc($status) . '"><span class="dot"></span>' . esc(status_label($status, $days)) . '</span>';
    }
}

if (! function_exists('classification')) {
    /** HCI rule: Past due = Silver, Restructured = Bronze, Current = Gold. */
    function classification(string $status): string
    {
        $map = ['pastdue' => 'Silver', 'restructured' => 'Bronze', 'current' => 'Gold'];

        return $map[$status] ?? '—';
    }
}

if (! function_exists('class_badge')) {
    function class_badge(string $class): string
    {
        if ($class === '—' || $class === '') {
            return '—';
        }

        return '<span class="tln-class ' . esc(strtolower($class)) . '"><i class="bi bi-award"></i> ' . esc($class) . '</span>';
    }
}

if (! function_exists('can')) {
    function can(string $permission): bool
    {
        $role  = (string) session()->get('role');
        $perms = config('Loaning')->permissions[$role] ?? [];

        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }
}

if (! function_exists('role_label')) {
    function role_label(?string $role): string
    {
        return config('Loaning')->roles[$role] ?? ucfirst((string) $role);
    }
}

if (! function_exists('current_user_name')) {
    function current_user_name(): string
    {
        try {
            return (string) (session()->get('full_name') ?: 'System');
        } catch (\Throwable $e) {
            return 'System';
        }
    }
}

if (! function_exists('txn_label')) {
    function txn_label(string $category): string
    {
        return config('Loaning')->txnCategories[$category]['label'] ?? ucfirst(str_replace('_', ' ', $category));
    }
}

if (! function_exists('nav_counts')) {
    /** Counters shown in the sidebar. */
    function nav_counts(): array
    {
        static $counts = null;
        if ($counts !== null) {
            return $counts;
        }
        $db     = db_connect();
        $counts = ['clients' => 0, 'reminders' => 0, 'approvals' => 0];

        try {
            $counts['clients']   = (int) $db->table('clients')->countAllResults();
            $counts['reminders'] = (int) $db->table('notifications')->where('status', 'sent')->countAllResults();
            $counts['approvals'] = (int) $db->table('approvals')->where('status', 'pending')->countAllResults();
        } catch (\Throwable $e) {
            // database not migrated yet — leave the counters at zero
        }

        return $counts;
    }
}
