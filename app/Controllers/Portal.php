<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\ClientPortalService;
use App\Libraries\PaymentGateway;

/**
 * The client-facing side: sign in, see balance + due date, change password, pay online.
 * Staff pages and portal pages use separate session keys and separate filters.
 */
class Portal extends BaseController
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    private function cid(): int
    {
        return (int) session()->get('client_id');
    }

    private function client(): array
    {
        return db_connect()->table('clients')->where('id', $this->cid())->get()->getRowArray() ?: [];
    }

    // ---------------------------------------------------------------- sign in / out

    public function login()
    {
        if ($this->cid()) {
            return redirect()->to(site_url('portal'));
        }

        return view('portal/login');
    }

    public function attempt()
    {
        $username = trim((string) preg_replace('/\s+/', ' ', (string) $this->request->getPost('username'))); // name, not case-sensitive
        $password = (string) $this->request->getPost('password');
        $db       = db_connect();
        $acc      = $username !== '' ? $db->table('client_users')->where('username', $username)->get()->getRowArray() : null;
        $back     = static fn (string $msg) => redirect()->to(site_url('portal/login'))->with('old_user', $username)->with('error', $msg);

        if ($acc && $acc['locked_until'] && strtotime($acc['locked_until']) > time()) {
            $mins = max(1, (int) ceil((strtotime($acc['locked_until']) - time()) / 60));

            return $back('Too many failed attempts. Try again in ' . $mins . ' minute(s).');
        }

        $valid = $acc
            ? password_verify($password, $acc['password_hash'])
            : password_verify($password, '$2y$10$abcdefghijklmnopqrstuuYw1nXQ1c0nQ7qv0e5h8c5oXx3V6l9aK'); // constant-time dummy

        if (! $acc || ! $valid || ! (int) $acc['is_active']) {
            if ($acc) {
                $n   = (int) $acc['failed_attempts'] + 1;
                $upd = ['failed_attempts' => $n];
                if ($n >= self::MAX_ATTEMPTS) {
                    $upd = ['failed_attempts' => 0, 'locked_until' => date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60)];
                }
                $db->table('client_users')->where('id', $acc['id'])->update($upd);
            }
            Audit::log('Failed client sign-in', 'Account: ' . mb_substr($username, 0, 40), $username ?: 'Unknown');

            return $back('Incorrect name or password.');
        }

        $client = $db->table('clients')->where('id', $acc['client_id'])->get()->getRowArray();
        $db->table('client_users')->where('id', $acc['id'])->update(['failed_attempts' => 0, 'locked_until' => null, 'last_login' => date('Y-m-d H:i:s')]);

        $session = session();
        $session->regenerate(); // prevents session fixation
        $session->remove(['user_id', 'username', 'full_name', 'role', 'must_change']); // never a staff + client session at once
        $session->set([
            'client_id'          => (int) $acc['client_id'],
            'client_name'        => $client['name'] ?? $username,
            'client_must_change' => (int) $acc['must_change_password'],
        ]);
        Audit::log('Client signed in', $username, $client['name'] ?? $username);

        return redirect()->to(site_url('portal'));
    }

    public function logout()
    {
        session()->remove(['client_id', 'client_name', 'client_must_change']);

        return redirect()->to(site_url('portal/login'))->with('info', 'You have been signed out.');
    }

    // ---------------------------------------------------------------- dashboard

    public function index(): string
    {
        $svc  = new ClientPortalService();
        $gw   = new PaymentGateway();
        $data = $svc->overview($this->cid());

        return view('portal/dashboard', $data + [
            'page_title' => 'My account',
            'client'     => $this->client(),
            'payments'   => $svc->recentPayments($this->cid()),
            'schedule'   => array_combine(array_column($data['loans'], 'id'), array_map(fn ($l) => $svc->upcomingSchedule($l), $data['loans'])) ?: [],
            'payOnline'  => $gw->enabled(),
            'minAmount'  => $gw->minAmount(),
            'driver'     => $gw->driver(),
            'pending'    => db_connect()->table('online_payments')->where('client_id', $this->cid())->where('status', 'pending')->where('created_at >', date('Y-m-d H:i:s', time() - 3600))->countAllResults(),
        ]);
    }

    /** The client's own copy of the amortization schedule. */
    public function schedule(int $id)
    {
        $data = (new Loans())->loanDoc($id, $this->cid());
        if ($data === null) {
            return redirect()->to(site_url('portal'))->with('error', 'That loan is not available.');
        }

        return view('loan_schedule', $data + ['layout' => 'portal/layout', 'portal' => true]);
    }

    // ---------------------------------------------------------------- password

    public function password(): string
    {
        return view('portal/password', ['page_title' => 'Change password', 'client' => $this->client(), 'forced' => false]);
    }

    public function updatePassword()
    {
        $db   = db_connect();
        $acc  = $db->table('client_users')->where('client_id', $this->cid())->get()->getRowArray();
        $cur  = (string) $this->request->getPost('current_password');
        $new  = (string) $this->request->getPost('new_password');
        $conf = (string) $this->request->getPost('confirm_password');
        $to   = 'portal/password';

        if (! $acc || ! password_verify($cur, $acc['password_hash'])) {
            return $this->fail($to, 'Your current password is not correct.', false);
        }
        if (! ClientPortalService::validPassword($new)) {
            return $this->fail($to, 'The new password needs at least 8 characters with a letter and a number.', false);
        }
        if ($new !== $conf) {
            return $this->fail($to, 'The two new passwords do not match.', false);
        }
        if (password_verify($new, $acc['password_hash'])) {
            return $this->fail($to, 'Choose a password different from the current one.', false);
        }
        $db->table('client_users')->where('id', $acc['id'])->update(['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'must_change_password' => 0]);
        session()->set('client_must_change', 0);
        Audit::log('Client changed own password', $acc['username'], (string) session()->get('client_name'));

        return $this->done('portal', 'Password updated.');
    }

    // ---------------------------------------------------------------- online payment

    /** Step 1: client picks a loan + amount → we open the hosted checkout. */
    public function pay()
    {
        $loanId = (int) $this->request->getPost('loan_id');
        $amount = round((float) str_replace(',', '', (string) $this->request->getPost('amount')), 2);

        $res = (new PaymentGateway())->start($this->cid(), $loanId, $amount);
        if (! $res['ok']) {
            return $this->fail('portal', $res['error'], false);
        }

        return redirect()->to($res['url']);
    }

    /** Step 3: the provider sends the client back here. We confirm with the provider before saying "paid". */
    public function payReturn(string $ref)
    {
        $gw = new PaymentGateway();
        $r  = $gw->verify($ref, $this->cid());
        if (! ($r['ok'] ?? false)) {
            return $this->fail('portal', $r['error'] ?? 'Could not check your payment.', false);
        }
        if (($r['status'] ?? '') === 'paid') {
            $p = $gw->find($ref, $this->cid());

            return $this->done('portal', 'Payment received — thank you! ' . peso($p['amount']) . ' was applied to your loan.');
        }

        return redirect()->to(site_url('portal'))->with('info', 'We are still waiting for confirmation of your payment. Refresh this page in a minute — your balance updates as soon as it is confirmed.');
    }

    public function payCancel(string $ref)
    {
        (new PaymentGateway())->cancel($ref, $this->cid());

        return redirect()->to(site_url('portal'))->with('info', 'Payment cancelled. You were not charged.');
    }

    // ---------------------------------------------------------------- sandbox checkout (demo only)

    public function sandbox(string $ref)
    {
        $gw = new PaymentGateway();
        $p  = $gw->find($ref, $this->cid());
        if ($gw->driver() !== 'sandbox' || ! $p || $p['driver'] !== 'sandbox' || $p['status'] !== 'pending') {
            return redirect()->to(site_url('portal'))->with('error', 'That test checkout is not available.');
        }
        $loan = db_connect()->table('loans')->select('loan_no, loan_type')->where('id', $p['loan_id'])->get()->getRowArray();

        return view('portal/sandbox', ['page_title' => 'Test checkout', 'p' => $p, 'loan' => $loan]);
    }

    public function sandboxDecide(string $ref)
    {
        $gw = new PaymentGateway();
        $p  = $gw->find($ref, $this->cid());
        if ($gw->driver() !== 'sandbox' || ! $p || $p['driver'] !== 'sandbox') {
            return redirect()->to(site_url('portal'))->with('error', 'That test checkout is not available.');
        }
        if ($this->request->getPost('result') === 'pay') {
            $gw->settle($ref, 'SANDBOX-' . $ref); // the fake provider "confirms" immediately

            return redirect()->to(site_url('portal/pay/return/' . $ref));
        }

        return redirect()->to(site_url('portal/pay/cancel/' . $ref));
    }

    // ---------------------------------------------------------------- webhook (PayMongo → us)

    public function webhook()
    {
        $ok = (new PaymentGateway())->webhook((string) $this->request->getBody(), (string) $this->request->getHeaderLine('Paymongo-Signature'));

        return $this->response->setStatusCode($ok ? 200 : 400)->setBody($ok ? 'ok' : 'invalid');
    }
}
