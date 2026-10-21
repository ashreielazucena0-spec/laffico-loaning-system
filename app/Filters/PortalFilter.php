<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Guards every client-portal page (except portal login & the payment webhook).
 * Re-checks the account on each request so a disabled client is signed out at once.
 * Changing the password is optional; the must_change_password flag is no longer enforced.
 */
class PortalFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('url');
        $session = session();
        $id      = (int) $session->get('client_id');

        if (! $id) {
            return redirect()->to(site_url('portal/login'))->with('error', 'Please sign in to view your account.');
        }
        $acc = db_connect()->table('client_users cu')->select('cu.is_active, cu.must_change_password, c.name')
            ->join('clients c', 'c.id = cu.client_id')->where('cu.client_id', $id)->get()->getRowArray();

        if (! $acc || (int) $acc['is_active'] !== 1) {
            $session->remove(['client_id', 'client_name', 'client_must_change']);

            return redirect()->to(site_url('portal/login'))->with('error', 'This account is not active. Please contact the cooperative.');
        }
        $session->set(['client_name' => $acc['name'], 'client_must_change' => (int) $acc['must_change_password']]);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
