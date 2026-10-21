<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    protected $helpers = ['url', 'form', 'loaning'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
    }

    /** Absolute URL for an app path such as "accounts/detail/CL-0001?tab=ledger". */
    protected function to(string $path): string
    {
        return site_url($path);
    }

    /** Redirect with a green success message. */
    protected function done(string $to, string $message): RedirectResponse
    {
        return redirect()->to($this->to($to))->with('success', $message);
    }

    /** Redirect with a red error message (keeps what was typed unless $withInput is false). */
    protected function fail(string $to, string $message, bool $withInput = true): RedirectResponse
    {
        $r = redirect()->to($this->to($to))->with('error', $message);

        return $withInput ? $r->withInput() : $r;
    }
}
