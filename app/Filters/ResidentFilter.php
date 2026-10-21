<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restricts the resident portal (/resident/*) to accounts with the
 * "Resident" role. Staff who open a resident URL go to their dashboard.
 */
class ResidentFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('isLoggedIn')) {
            return redirect()->to(site_url('login'))->with('error', 'Please log in to continue.');
        }

        if (strcasecmp((string) $session->get('role'), 'Resident') !== 0) {
            return redirect()->to(site_url('barangay/dashboard'));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nothing to do
    }
}
