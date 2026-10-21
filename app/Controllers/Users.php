<?php

namespace App\Controllers;

use App\Libraries\Audit;

class Users extends BaseController
{
    public function index(): string
    {
        return view('users', [
            'page_title' => 'Users',
            'active'     => 'users',
            'users'      => db_connect()->table('users')->orderBy('full_name')->get()->getResultArray(),
        ]);
    }

    public function add()
    {
        $p    = $this->request->getPost();
        $db   = db_connect();
        $user = trim((string) ($p['username'] ?? ''));
        $name = trim((string) ($p['full_name'] ?? ''));
        $role = (string) ($p['role'] ?? '');
        $pw   = (string) ($p['password'] ?? '');

        if (! preg_match('/^[A-Za-z0-9._-]{3,40}$/', $user) || $name === '' || ! isset(config('Loaning')->roles[$role])) {
            return $this->fail('users', 'Enter a username (3–40 letters/numbers), the full name and a role.', false);
        }
        if (strlen($pw) < 8 || ! preg_match('/[A-Za-z]/', $pw) || ! preg_match('/\d/', $pw)) {
            return $this->fail('users', 'The password needs 8+ characters with a letter and a number.', false);
        }
        if ($db->table('users')->where('username', $user)->countAllResults()) {
            return $this->fail('users', 'That username is taken.', false);
        }
        $db->table('users')->insert([
            'username' => $user, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'full_name' => $name,
            'role' => $role, 'is_active' => 1, 'must_change_password' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        Audit::log('Created user', $user . ' (' . $role . ')');

        return $this->done('users', 'User created. They can sign in with this password and change it any time from the Password button.');
    }

    public function toggle(int $id)
    {
        $db = db_connect();
        $u  = $db->table('users')->where('id', $id)->get()->getRowArray();
        if (! $u) {
            return $this->fail('users', 'User not found.');
        }
        if ($id === (int) session()->get('user_id')) {
            return $this->fail('users', 'You cannot deactivate your own account.');
        }
        if ($u['is_active'] && $this->isLastAdmin($u)) {
            return $this->fail('users', 'There must always be at least one active administrator.');
        }
        $db->table('users')->where('id', $id)->update(['is_active' => $u['is_active'] ? 0 : 1, 'failed_attempts' => 0, 'locked_until' => null]);
        Audit::log($u['is_active'] ? 'Deactivated user' : 'Reactivated user', $u['username']);

        return $this->done('users', 'User updated.');
    }

    public function reset(int $id)
    {
        $db = db_connect();
        $u  = $db->table('users')->where('id', $id)->get()->getRowArray();
        $pw = (string) $this->request->getPost('password');
        if (! $u) {
            return $this->fail('users', 'User not found.');
        }
        if (strlen($pw) < 8 || ! preg_match('/[A-Za-z]/', $pw) || ! preg_match('/\d/', $pw)) {
            return $this->fail('users', 'The password needs 8+ characters with a letter and a number.', false);
        }
        $db->table('users')->where('id', $id)->update([
            'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'must_change_password' => 0, 'failed_attempts' => 0, 'locked_until' => null,
        ]);
        Audit::log('Reset user password', $u['username']);

        return $this->done('users', 'Password reset. They can sign in with the new password and change it any time from the Password button.');
    }

    /** True when $id is the only active administrator left. */
    protected function isLastAdmin(array $u): bool
    {
        if ($u['role'] !== 'admin' || ! (int) $u['is_active']) {
            return false;
        }

        return db_connect()->table('users')->where('role', 'admin')->where('is_active', 1)->where('id !=', (int) $u['id'])->countAllResults() === 0;
    }

    public function role(int $id)
    {
        $db   = db_connect();
        $u    = $db->table('users')->where('id', $id)->get()->getRowArray();
        $role = (string) $this->request->getPost('role');
        if (! $u) {
            return $this->fail('users', 'User not found.');
        }
        if (! isset(config('Loaning')->roles[$role])) {
            return $this->fail('users', 'Pick a valid role.');
        }
        if ($id === (int) session()->get('user_id')) {
            return $this->fail('users', 'You cannot change your own role.');
        }
        if ($u['role'] === $role) {
            return $this->done('users', 'Role unchanged.');
        }
        if ($role !== 'admin' && $this->isLastAdmin($u)) {
            return $this->fail('users', 'There must always be at least one active administrator.');
        }
        $db->table('users')->where('id', $id)->update(['role' => $role]);
        Audit::log('Changed user role', $u['username'] . ': ' . $u['role'] . ' → ' . $role);

        return $this->done('users', 'Role changed. It takes effect on their next page load.');
    }

    public function delete(int $id)
    {
        $db = db_connect();
        $u  = $db->table('users')->where('id', $id)->get()->getRowArray();
        if (! $u) {
            return $this->fail('users', 'User not found.');
        }
        if ($id === (int) session()->get('user_id')) {
            return $this->fail('users', 'You cannot delete your own account.');
        }
        if ($this->isLastAdmin($u)) {
            return $this->fail('users', 'There must always be at least one active administrator.');
        }
        $db->table('users')->where('id', $id)->delete();
        Audit::log('Deleted user', $u['username'] . ' (' . $u['role'] . ')');

        return $this->done('users', 'User deleted. Their past activity stays in the audit trail.');
    }
}
