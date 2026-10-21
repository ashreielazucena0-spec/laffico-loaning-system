<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        if (session()->get('user_id')) {
            return redirect()->to(site_url(session()->get('role') === 'admin' ? 'users' : 'dashboard'));
        }
        if (session()->get('client_id')) {
            return redirect()->to(site_url('portal'));
        }

        return redirect()->to(site_url('login'));
    }
}
