<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Online payment settings for the client portal.
 * Override in .env, e.g.
 *     payments.driver = paymongo
 *     payments.paymongoSecretKey = sk_test_xxx
 *     payments.paymongoWebhookSecret = whsk_xxx
 */
class Payments extends BaseConfig
{
    /** 'sandbox' = built-in fake checkout for demos (disabled in production) | 'paymongo' = real GCash / Maya / card checkout | 'off' */
    public string $driver = 'sandbox';

    public string $paymongoSecretKey     = '';
    public string $paymongoWebhookSecret = '';

    /** Payment methods offered on the PayMongo checkout page. */
    public array $paymongoMethods = ['gcash', 'paymaya', 'card'];

    /** Smallest online payment accepted (PayMongo's own minimum is ₱20). */
    public float $minAmount = 100.00;
}
