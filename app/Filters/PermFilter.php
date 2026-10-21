<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Route-level permission check:  ['filter' => 'perm:txn.record']
 * (several permissions separated by commas = any one is enough).
 */
class PermFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('loaning');

        foreach ((array) $arguments as $permission) {
            if (can(trim($permission))) {
                return null;
            }
        }

        return service('response')->setStatusCode(403)->setBody(view('forbidden', [
            'page_title' => 'Access denied',
            'active'     => '',
        ]));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
