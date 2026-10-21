<?php

namespace App\Libraries;

/**
 * Online loan payments for the client portal.
 *
 *  start()      – creates an online_payments row and a hosted checkout page
 *  settle()     – posts the loan payment ONCE when a payment is confirmed
 *  verify()     – asks the provider whether a pending payment was paid
 *  webhook()    – handles the provider's server-to-server "paid" call
 *
 * Drivers:  paymongo (GCash / Maya / cards, https://paymongo.com)
 *           sandbox  (a fake checkout page for demos; refuses to run in production)
 */
class PaymentGateway
{
    protected $db;
    protected \Config\Payments $cfg;

    public function __construct()
    {
        helper('loaning');
        $this->db  = db_connect();
        $this->cfg = config('Payments');
    }

    public function driver(): string
    {
        $d = $this->cfg->driver;
        if ($d === 'sandbox' && ENVIRONMENT === 'production') {
            return 'off';
        }

        return in_array($d, ['paymongo', 'sandbox'], true) ? $d : 'off';
    }

    public function enabled(): bool
    {
        if ($this->driver() === 'paymongo' && $this->cfg->paymongoSecretKey === '') {
            return false;
        }

        return $this->driver() !== 'off';
    }

    public function minAmount(): float
    {
        return $this->cfg->minAmount;
    }

    // ------------------------------------------------------------------ start

    /** @return array{ok:bool, error?:string, url?:string} */
    public function start(int $clientId, int $loanId, float $amount): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'error' => 'Online payment is not available right now. Please pay at the cooperative office.'];
        }
        $amount = round($amount, 2);
        $loan   = $this->db->table('loans')->where('id', $loanId)->where('client_id', $clientId)->get()->getRowArray();
        if (! $loan || $loan['approval_status'] !== 'approved') {
            return ['ok' => false, 'error' => 'Choose one of your active loans.'];
        }
        $m = LoanService::metrics($loan);
        (new LoanService())->assessPenalties($loanId);
        $loan = $this->db->table('loans')->where('id', $loanId)->get()->getRowArray();
        $m    = LoanService::metrics($loan);
        if ($m['payoff'] <= 0.005) {
            return ['ok' => false, 'error' => 'That loan is already fully paid.'];
        }
        if ($amount < min($this->cfg->minAmount, $m['payoff'])) {
            return ['ok' => false, 'error' => 'The minimum online payment is ' . peso($this->cfg->minAmount) . '.'];
        }
        if ($amount > $m['payoff'] + 0.005) {
            return ['ok' => false, 'error' => 'That is more than the ' . peso($m['payoff']) . ' needed to settle this loan.'];
        }

        $ref = 'PAY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $this->db->table('online_payments')->insert([
            'pay_ref' => $ref, 'client_id' => $clientId, 'loan_id' => $loanId, 'amount' => $amount,
            'driver' => $this->driver(), 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $this->db->insertID();

        if ($this->driver() === 'sandbox') {
            $url = site_url('portal/pay/sandbox/' . $ref);
            $this->db->table('online_payments')->where('id', $id)->update(['provider_id' => 'sandbox-' . $ref, 'checkout_url' => $url]);

            return ['ok' => true, 'url' => $url];
        }

        // ---- PayMongo hosted checkout ----
        try {
            $res = $this->paymongo('POST', '/checkout_sessions', ['data' => ['attributes' => [
                'line_items' => [[
                    'currency' => 'PHP', 'amount' => (int) round($amount * 100), 'name' => 'Loan payment ' . $loan['loan_no'], 'quantity' => 1,
                ]],
                'payment_method_types' => $this->cfg->paymongoMethods,
                'description'          => 'LAFFICO-MPC loan payment ' . $loan['loan_no'],
                'reference_number'     => $ref,
                'send_email_receipt'   => false,
                'show_description'     => true,
                'show_line_items'      => true,
                'success_url'          => site_url('portal/pay/return/' . $ref),
                'cancel_url'           => site_url('portal/pay/cancel/' . $ref),
                'metadata'             => ['pay_ref' => $ref],
            ]]]);
            $sid = $res['data']['id'] ?? null;
            $url = $res['data']['attributes']['checkout_url'] ?? null;
            if (! $sid || ! $url) {
                throw new \RuntimeException('No checkout URL returned.');
            }
            $this->db->table('online_payments')->where('id', $id)->update(['provider_id' => $sid, 'checkout_url' => $url]);

            return ['ok' => true, 'url' => $url];
        } catch (\Throwable $e) {
            log_message('error', 'PayMongo checkout failed: ' . $e->getMessage());
            $this->db->table('online_payments')->where('id', $id)->update(['status' => 'failed', 'fail_reason' => mb_substr($e->getMessage(), 0, 250)]);

            return ['ok' => false, 'error' => 'We could not open the payment page. Please try again in a moment.'];
        }
    }

    // ------------------------------------------------------------------ settle

    public function find(string $ref, ?int $clientId = null): ?array
    {
        $b = $this->db->table('online_payments')->where('pay_ref', $ref);
        if ($clientId !== null) {
            $b->where('client_id', $clientId);
        }

        return $b->get()->getRowArray();
    }

    /**
     * Posts the confirmed payment as a normal loan-payment transaction.
     * Safe to call many times (return page + webhook): only the call that wins
     * the pending → paid switch posts the money.
     */
    public function settle(string $ref, ?string $providerPaymentId = null): array
    {
        $p = $this->find($ref);
        if (! $p) {
            return ['ok' => false, 'error' => 'Payment not found.'];
        }
        if ($p['status'] === 'paid') {
            return ['ok' => true, 'already' => true];
        }
        if ($p['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'This payment was ' . $p['status'] . '.'];
        }

        $this->db->table('online_payments')->where('id', $p['id'])->where('status', 'pending')->update(['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')]);
        if ($this->db->affectedRows() !== 1) {
            return ['ok' => true, 'already' => true]; // another request settled it first
        }

        $client = $this->db->table('clients')->where('id', $p['client_id'])->get()->getRowArray();
        $who    = 'Online · ' . ($client['name'] ?? 'client');
        $res    = (new LoanService())->recordTransaction([
            'client_id' => (int) $p['client_id'],
            'loan_id'   => (int) $p['loan_id'],
            'category'  => 'loan_payment',
            'amount'    => $p['amount'],
            'method'    => 'online',
            'reference' => $providerPaymentId ?: $p['pay_ref'],
            'remarks'   => 'Online payment via client portal (' . $p['driver'] . ') · ' . $p['pay_ref'],
        ], $who);

        if (! $res['ok']) {
            // The money was taken but the ledger refused it (e.g. staff already recorded the payment).
            // Flag it for staff instead of losing track of it.
            $this->db->table('online_payments')->where('id', $p['id'])->update(['fail_reason' => 'PAID but not posted: ' . mb_substr($res['error'], 0, 200)]);
            Audit::log('Online payment needs review', $p['pay_ref'] . ' · ' . peso($p['amount']) . ' · ' . $res['error'], $who);

            return ['ok' => true, 'needs_review' => true];
        }
        $this->db->table('online_payments')->where('id', $p['id'])->update(['transaction_id' => $res['id']]);
        Audit::log('Online loan payment received', $res['txn_no'] . ' · ' . ($client['name'] ?? '') . ' · ' . peso($p['amount']), $who);

        return ['ok' => true, 'txn_no' => $res['txn_no']];
    }

    public function cancel(string $ref, int $clientId): void
    {
        $this->db->table('online_payments')->where('pay_ref', $ref)->where('client_id', $clientId)->where('status', 'pending')->update(['status' => 'cancelled']);
    }

    /** Asks PayMongo whether a pending checkout was paid; settles it if so. */
    public function verify(string $ref, int $clientId): array
    {
        $p = $this->find($ref, $clientId);
        if (! $p) {
            return ['ok' => false, 'error' => 'Payment not found.'];
        }
        if ($p['status'] !== 'pending' || $p['driver'] !== 'paymongo') {
            return ['ok' => true, 'status' => $p['status']];
        }
        try {
            $res  = $this->paymongo('GET', '/checkout_sessions/' . rawurlencode((string) $p['provider_id']));
            $pays = $res['data']['attributes']['payments'] ?? [];
            foreach ($pays as $pay) {
                if (($pay['attributes']['status'] ?? '') === 'paid') {
                    // never trust the amount blindly: it must match what we asked for
                    $paid = ((int) ($pay['attributes']['amount'] ?? 0)) / 100;
                    if (abs($paid - (float) $p['amount']) > 0.005) {
                        $this->db->table('online_payments')->where('id', $p['id'])->update(['fail_reason' => 'Amount mismatch: provider says ' . $paid]);

                        return ['ok' => false, 'error' => 'The paid amount did not match. Please contact the cooperative.'];
                    }
                    $this->settle($ref, $pay['id'] ?? null);

                    return ['ok' => true, 'status' => 'paid'];
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'PayMongo verify failed: ' . $e->getMessage());
        }

        return ['ok' => true, 'status' => 'pending'];
    }

    // ------------------------------------------------------------------ webhook

    /** PayMongo → us. Verifies the signature, then settles. */
    public function webhook(string $rawBody, string $signatureHeader): bool
    {
        $secret = $this->cfg->paymongoWebhookSecret;
        if ($secret === '' || $signatureHeader === '') {
            return false;
        }
        $parts = [];
        foreach (explode(',', $signatureHeader) as $kv) {
            [$k, $v] = array_pad(explode('=', trim($kv), 2), 2, '');
            $parts[$k] = $v;
        }
        $t = $parts['t'] ?? '';
        if ($t === '' || abs(time() - (int) $t) > 600) {
            return false; // missing or replayed (older than 10 minutes)
        }
        $expected = hash_hmac('sha256', $t . '.' . $rawBody, $secret);
        $given    = ($parts['li'] ?? '') !== '' ? $parts['li'] : ($parts['te'] ?? '');
        if (! hash_equals($expected, $given)) {
            return false;
        }

        $evt  = json_decode($rawBody, true);
        $type = $evt['data']['attributes']['type'] ?? '';
        if ($type === 'checkout_session.payment.paid') {
            $data = $evt['data']['attributes']['data'] ?? [];
            $ref  = $data['attributes']['reference_number'] ?? ($data['attributes']['metadata']['pay_ref'] ?? '');
            $pay  = $data['attributes']['payments'][0] ?? [];
            $p    = $ref ? $this->find((string) $ref) : null;
            if ($p) {
                $paid = ((int) ($pay['attributes']['amount'] ?? 0)) / 100;
                if (abs($paid - (float) $p['amount']) <= 0.005) {
                    $this->settle($p['pay_ref'], $pay['id'] ?? null);
                }
            }
        }

        return true;
    }

    // ------------------------------------------------------------------ http

    protected function paymongo(string $method, string $path, ?array $body = null): array
    {
        $client = \Config\Services::curlrequest(['baseURI' => 'https://api.paymongo.com/v1/', 'timeout' => 20, 'http_errors' => false]);
        $opts   = [
            'auth'    => [$this->cfg->paymongoSecretKey, ''],
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ];
        if ($body !== null) {
            $opts['body'] = json_encode($body);
        }
        $resp = $client->request($method, ltrim($path, '/'), $opts);
        $json = json_decode((string) $resp->getBody(), true);
        if ($resp->getStatusCode() >= 400 || ! is_array($json)) {
            $msg = $json['errors'][0]['detail'] ?? ('HTTP ' . $resp->getStatusCode());
            throw new \RuntimeException($msg);
        }

        return $json;
    }
}
