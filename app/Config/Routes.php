<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', 'Home::index');

// ---------------------------------------------------------------- staff sign-in
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attempt');
$routes->match(['get', 'post'], 'logout', 'Auth::logout');

// ---------------------------------------------------------------- client portal
$routes->get('portal/login', 'Portal::login');
$routes->post('portal/login', 'Portal::attempt');
$routes->post('portal/webhook', 'Portal::webhook'); // PayMongo -> us (CSRF-exempt, signature checked)
$routes->group('portal', ['filter' => 'portal'], static function ($routes) {
    $routes->get('/', 'Portal::index');
    $routes->get('password', 'Portal::password');
    $routes->post('password', 'Portal::updatePassword');
    $routes->match(['get', 'post'], 'logout', 'Portal::logout');
    $routes->get('schedule/(:num)', 'Portal::schedule/$1');
    $routes->post('pay', 'Portal::pay');
    $routes->get('pay/return/(:segment)', 'Portal::payReturn/$1');
    $routes->get('pay/cancel/(:segment)', 'Portal::payCancel/$1');
    $routes->get('pay/sandbox/(:segment)', 'Portal::sandbox/$1');
    $routes->post('pay/sandbox/(:segment)', 'Portal::sandboxDecide/$1');
});

// ---------------------------------------------------------------- staff area
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('dashboard', 'Dashboard::index');

    $routes->get('account/password', 'Account::password');
    $routes->post('account/password', 'Account::updatePassword');

    // clients & their accounts
    $routes->get('accounts', 'ClientAccounts::index');
    $routes->get('accounts/detail/(:segment)', 'ClientAccounts::detail/$1');
    $routes->post('accounts/add', 'ClientAccounts::add', ['filter' => 'perm:clients.add']);
    $routes->post('accounts/delete', 'ClientAccounts::delete', ['filter' => 'perm:clients.delete']);
    $routes->post('accounts/ledger', 'ClientAccounts::addLedger', ['filter' => 'perm:ledger.add']);
    $routes->post('accounts/portal/create', 'ClientAccounts::portalCreate', ['filter' => 'perm:clients.add']);
    $routes->post('accounts/portal/reset', 'ClientAccounts::portalReset', ['filter' => 'perm:clients.add']);
    $routes->post('accounts/portal/toggle', 'ClientAccounts::portalToggle', ['filter' => 'perm:clients.add']);

    // loans
    $routes->post('loans/apply', 'Loans::apply', ['filter' => 'perm:loans.create']);
    $routes->post('loans/restructure', 'Loans::restructure', ['filter' => 'perm:loans.restructure']);
    $routes->post('loans/waive', 'Loans::waive', ['filter' => 'perm:approve.manager']);
    $routes->get('loans/schedule/(:num)', 'Loans::schedule/$1');
    $routes->get('loans/voucher/(:num)', 'Loans::voucher/$1');

    // daily transactions
    $routes->get('transactions', 'Transactions::index', ['filter' => 'perm:txn.record']);
    $routes->post('transactions/record', 'Transactions::record', ['filter' => 'perm:txn.record']);
    $routes->post('transactions/cancel', 'Transactions::cancel', ['filter' => 'perm:txn.cancel']);
    $routes->get('transactions/receipt/(:num)', 'Transactions::receipt/$1', ['filter' => 'perm:txn.record']);

    // supporting documents
    $routes->post('documents/upload', 'Documents::upload', ['filter' => 'perm:documents.upload']);
    $routes->get('documents/file/(:num)', 'Documents::file/$1');

    // reminders
    $routes->group('reminders', ['filter' => 'perm:reminders.manage'], static function ($routes) {
        $routes->get('/', 'Reminders::index');
        $routes->post('add', 'Reminders::add');
        $routes->post('ack-all', 'Reminders::ack');
        $routes->post('ack/(:num)', 'Reminders::ack/$1');
    });

    // bookkeeping
    $routes->group('accounting', ['filter' => 'perm:accounting.manage'], static function ($routes) {
        $routes->get('/', 'Accounting::index');
        $routes->post('expense', 'Accounting::addExpense');
        $routes->post('expense/pay/(:num)', 'Accounting::payExpense/$1');
        $routes->post('budget', 'Accounting::saveBudget');
        $routes->post('bank', 'Accounting::addBank');
        $routes->post('bank/match', 'Accounting::autoMatch');
        $routes->post('bank/unmatch/(:num)', 'Accounting::unmatch/$1');
        $routes->post('settings', 'Accounting::saveSettings');
    });

    // reports
    $routes->get('reports', 'Reports::index', ['filter' => 'perm:reports.view']);
    $routes->get('reports/view/(:segment)', 'Reports::view/$1', ['filter' => 'perm:reports.view']);

    // audit trail + approvals (everyone can read; only a Manager can decide)
    $routes->get('audit-trail', 'AuditTrail::index');
    $routes->post('approvals/decide/(:num)', 'AuditTrail::decide/$1', ['filter' => 'perm:approve.manager']);

    // administrator
    $routes->group('users', ['filter' => 'perm:users.manage'], static function ($routes) {
        $routes->get('/', 'Users::index');
        $routes->post('add', 'Users::add');
        $routes->post('toggle/(:num)', 'Users::toggle/$1');
        $routes->post('reset/(:num)', 'Users::reset/$1');
        $routes->post('role/(:num)', 'Users::role/$1');
        $routes->post('delete/(:num)', 'Users::delete/$1');
    });
    $routes->group('backups', ['filter' => 'perm:backups.manage'], static function ($routes) {
        $routes->get('/', 'Backups::index');
        $routes->post('create', 'Backups::create');
        $routes->get('download/(:segment)', 'Backups::download/$1');
        $routes->post('restore', 'Backups::restore');
        $routes->post('delete', 'Backups::delete');
    });
});
