<?= $this->extend('portal/layout') ?>
<?= $this->section('content') ?>
<div style="max-width:460px;margin:0 auto;">
  <div class="alert alert-warning" style="font-size:13px;"><strong>TEST MODE.</strong> This is a fake checkout page for demos. No real money moves. Set <code>payments.driver = paymongo</code> in <code>.env</code> to use real GCash / Maya / card payments.</div>
  <div class="tln-panel">
    <div class="tln-panel-head"><h3>Confirm payment</h3></div>
    <div style="padding:16px;">
      <div class="tln-fig-label">Loan</div>
      <div class="mb-2"><?= esc($loan['loan_type'] ?? '') ?> <span class="tln-small font-mono"><?= esc($loan['loan_no'] ?? '') ?></span></div>
      <div class="tln-fig-label">Amount</div>
      <div class="tln-fig-value mb-3"><?= peso($p['amount']) ?></div>
      <div class="tln-small mb-3">Reference <span class="font-mono"><?= esc($p['pay_ref']) ?></span></div>
      <form method="post" action="<?= site_url('portal/pay/sandbox/' . $p['pay_ref']) ?>" class="d-flex gap-2">
        <?= csrf_field() ?>
        <button class="btn btn-tln-primary" name="result" value="pay">Pay <?= peso($p['amount']) ?> (test)</button>
        <button class="btn btn-tln-ghost" name="result" value="cancel">Cancel</button>
      </form>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
