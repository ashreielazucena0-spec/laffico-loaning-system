<?php

namespace App\Controllers;

use App\Libraries\Audit;

/** Staff sign-in (Administrator / Manager / Employee). Clients use /portal/login instead. */
class Auth extends BaseController
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    public function login()
    {
        if (session()->get('user_id')) {
            return redirect()->to(site_url('/'));
        }

        return view('login');
    }

    public function attempt()
    {
        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');
        $db       = db_connect();
        $back     = static fn (string $msg) => redirect()->to(site_url('login'))->with('old_user', $username)->with('error', $msg);

        $u = $username === '' ? null : $db->table('users')->where('username', $username)->get()->getRowArray();

        if ($u && $u['locked_until'] && strtotime($u['locked_until']) > time()) {
            $mins = max(1, (int) ceil((strtotime($u['locked_until']) - time()) / 60));

            return $back('Too many wrong passwords. Try again in ' . $mins . ' minute' . ($mins === 1 ? '' : 's') . '.');
        }

        if (! $u || ! password_verify($password, $u['password_hash'])) {
            if ($u) {
                $n   = (int) $u['failed_attempts'] + 1;
                $upd = ['failed_attempts' => $n];
                if ($n >= self::MAX_ATTEMPTS) {
                    $upd = ['failed_attempts' => 0, 'locked_until' => date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60)];
                    Audit::log('Account locked after failed sign-ins', $u['username'], $u['full_name']);
                }
                $db->table('users')->where('id', $u['id'])->update($upd);
            }

            return $back('Incorrect username or password.');
        }

        if (! (int) $u['is_active']) {
            return $back('This account is deactivated. Please contact the Administrator.');
        }

        $db->table('users')->where('id', $u['id'])->update(['failed_attempts' => 0, 'locked_until' => null, 'last_login' => date('Y-m-d H:i:s')]);

        $session = session();
        $session->regenerate();
        $session->remove(['client_id', 'client_name', 'client_must_change']); // never a staff + client session at once
        $session->set([
            'user_id'     => (int) $u['id'],
            'username'    => $u['username'],
            'full_name'   => $u['full_name'],
            'role'        => $u['role'],
            'must_change' => (int) $u['must_change_password'],
        ]);
        Audit::log('Signed in', $u['username'], $u['full_name']);

        return redirect()->to(site_url('/'));
    }

    public function logout()
    {
        if (session()->get('user_id')) {
            Audit::log('Signed out', (string) session()->get('username'));
        }
        session()->remove(['user_id', 'username', 'full_name', 'role', 'must_change']);

        return redirect()->to(site_url('login'))->with('info', 'You have been signed out.');
    }
}
