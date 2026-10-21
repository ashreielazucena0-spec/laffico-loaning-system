<?php

namespace App\Controllers;

use App\Libraries\Audit;

class Account extends BaseController
{
    public function password(): string
    {
        return view('change_password', ['page_title' => 'Change password', 'active' => '']);
    }

    public function updatePassword()
    {
        $db   = db_connect();
        $user = $db->table('users')->where('id', (int) session()->get('user_id'))->get()->getRowArray();
        $cur  = (string) $this->request->getPost('current_password');
        $new  = (string) $this->request->getPost('new_password');
        $conf = (string) $this->request->getPost('confirm_password');

        if (! $user || ! password_verify($cur, $user['password_hash'])) {
            return $this->fail('account/password', 'Your current password is not correct.', false);
        }
        if (strlen($new) < 8 || ! preg_match('/[A-Za-z]/', $new) || ! preg_match('/\d/', $new)) {
            return $this->fail('account/password', 'The new password needs at least 8 characters with a letter and a number.', false);
        }
        if ($new !== $conf) {
            return $this->fail('account/password', 'The two new passwords do not match.', false);
        }
        if (password_verify($new, $user['password_hash'])) {
            return $this->fail('account/password', 'Choose a password different from the current one.', false);
        }

        $db->table('users')->where('id', $user['id'])->update([
            'password_hash'        => password_hash($new, PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ]);
        session()->set('must_change', 0);
        Audit::log('Changed own password', $user['username']);

        return $this->done('dashboard', 'Password updated.');
    }
}
