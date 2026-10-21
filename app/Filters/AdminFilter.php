<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restricts a route to logged-in accounts whose role is "Admin".
 * Runs after the 'auth' filter, but doesn't assume it — if the
 * session isn't logged in yet, it sends the user to the login page
 * instead of a bare access-denied message.
 */
class AdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('isLoggedIn')) {
            return redirect()->to(site_url('login'))->with('error', 'Please log in to continue.');
        }

        $role = (string) $session->get('role');

        if (strcasecmp($role, 'Admin') !== 0) {
            return redirect()->to(site_url('barangay/dashboard'))
                ->with('error', 'That area is restricted to administrator accounts.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nothing to do
    }
}
