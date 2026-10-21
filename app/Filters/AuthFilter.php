<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Gate for every staff page. Re-reads the user on each request so a deactivated
 * account is signed out at once and a role change takes effect on the next click.
 * Changing the password is optional; the must_change_password flag is no longer enforced.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('url');
        $session = session();
        $id      = (int) $session->get('user_id');

        if (! $id) {
            return redirect()->to(site_url('login'))->with('error', 'Please sign in to continue.');
        }
        $u = db_connect()->table('users')->select('username, full_name, role, is_active, must_change_password')->where('id', $id)->get()->getRowArray();
        if (! $u || ! (int) $u['is_active']) {
            $session->remove(['user_id', 'username', 'full_name', 'role', 'must_change']);

            return redirect()->to(site_url('login'))->with('error', 'This account is not active. Please contact the Administrator.');
        }
        $session->set(['username' => $u['username'], 'full_name' => $u['full_name'], 'role' => $u['role'], 'must_change' => (int) $u['must_change_password']]);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
