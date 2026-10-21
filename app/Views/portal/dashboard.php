<?= $this->extend('portal/layout') ?>
<?= $this->section('content') ?>
<?php
  $due      = array_sum(array_column($loans, 'due_now'));
  $over     = $summary['days_overdue'] > 0;
  $stateLbl = ['pastdue' => 'Past due', 'restructured' => 'Restructured', 'current' => 'Current'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
  <div>
    <h1 class="font-display mb-0" style="font-size:26px;font-weight:600;">Hello, <?= esc(trim(explode(',', $client['name'])[1] ?? $client['name'])) ?></h1>
    <div class="tln-small font-mono">Account <?= esc($client['acct_no']) ?></div>
  </div>
  <div><?= $summary['active_loans'] ? status_badge($summary['status'], $summary['days_overdue']) : '' ?></div>
</div>

<?php $soonDays = (int) config('Loaning')->reminderDueSoonDays; $untilDue = $summary['next_due'] ? days_between(date('Y-m-d'), $summary['next_due']) : null; ?>
<?php if ($summary['active_loans'] && $untilDue !== null && $untilDue >= 0 && $untilDue <= $soonDays): ?>
  <div class="alert alert-warning py-2" style="font-size:13px;"><i class="bi bi-bell"></i> Your next payment is due <strong><?= $untilDue === 0 ? 'today' : ($untilDue === 1 ? 'tomorrow' : 'in ' . $untilDue . ' days') ?></strong> (<?= fdate($summary['next_due']) ?>). Please settle it on time to keep your account in good standing.</div>
<?php endif; ?>

<?php if ($pending): ?>
  <div class="alert alert-info py-2" style="font-size:13px;"><i class="bi bi-hourglass-split"></i> You have a payment that is still waiting for confirmation. Refresh in a minute — your balance updates automatically once it is confirmed.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="tln-tile"><div class="tln-fig-label">Total balance</div><div class="tln-fig-value"><?= peso($summary['outstanding']) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="tln-tile <?= $over ? 'warn' : '' ?>"><div class="tln-fig-label">Amount due now</div><div class="tln-fig-value"><?= peso($due) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="tln-tile"><div class="tln-fig-label">Next due date</div><div class="tln-fig-value" style="font-size:17px;"><?= fdate($summary['next_due']) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="tln-tile"><div class="tln-fig-label">Active loans</div><div class="tln-fig-value"><?= (int) $summary['active_loans'] ?></div></div></div>
</div>

<div class="tln-section-head"><h2>My loans</h2></div>
<?php if (! $loans): ?><div class="tln-empty mb-4">You have no active loans right now.</div><?php endif; ?>

<?php foreach ($loans as $l): $lo = $l['days_overdue'] > 0; $def = $l['due_now'] > 0 ? $l['due_now'] : min((float) $l['monthly_due'], $l['outstanding']); ?>
  <div class="pt-loan">
    <div class="pt-loan-head">
      <div><strong style="font-size:15px;"><?= esc($l['loan_type']) ?></strong>
        <div class="tln-small font-mono"><?= esc($l['loan_no']) ?> · <?= (int) $l['sched_terms'] ?> monthly payments · <a href="<?= site_url('portal/schedule/' . $l['id']) ?>">View payment schedule</a></div></div>
      <div><?= status_badge($l['status'], $l['days_overdue']) ?></div>
    </div>
    <div class="pt-loan-body">
      <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="tln-fig-label">Outstanding balance</div><div class="tln-fig-value"><?= peso($l['outstanding']) ?></div><?php if ($l['penalty_due'] > 0): ?><div class="tln-small" style="color:var(--rust);">+ <?= peso($l['penalty_due']) ?> late penalty</div><?php endif; ?></div>
        <div class="col-6 col-md-3"><div class="tln-fig-label">Monthly payment</div><div class="tln-fig-value"><?= peso($l['monthly_due']) ?></div></div>
        <div class="col-6 col-md-3"><div class="tln-fig-label">Next due date</div><div class="tln-fig-value" style="font-size:17px;"><?= fdate($l['next_due']) ?></div></div>
        <div class="col-6 col-md-3"><div class="tln-fig-label">Paid so far</div><div class="tln-fig-value"><?= peso($l['paid_total']) ?></div></div>
      </div>

      <?php if ($lo): ?>
        <div class="pt-due over mb-3"><strong><?= (int) $l['days_overdue'] ?> day<?= $l['days_overdue'] === 1 ? '' : 's' ?> overdue.</strong> Amount to bring your loan up to date<?= $l['penalty_due'] > 0 ? ' (including the late penalty)' : '' ?>: <strong><?= peso($l['due_now']) ?></strong>. Please pay as soon as you can.</div>
      <?php elseif ($l['due_now'] > 0): ?>
        <div class="pt-due mb-3">Your next payment of <strong><?= peso($l['due_now']) ?></strong> is due on <strong><?= fdate($l['next_due']) ?></strong>.</div>
      <?php endif; ?>

      <?php if (! empty($schedule[$l['id']])): ?>
        <div class="tln-fig-label">Upcoming payment dates</div>
        <div class="pt-sched mb-3">
          <?php foreach ($schedule[$l['id']] as $s): ?><span class="<?= $s['due'] < date('Y-m-d') ? 'pastdue' : '' ?>">#<?= (int) $s['no'] ?> · <?= fdate($s['due'], 'M d, Y') ?></span><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($payOnline && $l['payable']): ?>
        <form method="post" action="<?= site_url('portal/pay') ?>" class="no-print js-payform">
          <?= csrf_field() ?>
          <input type="hidden" name="loan_id" value="<?= (int) $l['id'] ?>">
          <div class="row g-2 align-items-end">
            <div class="col-sm-5 col-md-4">
              <label class="tln-form-label">Amount to pay (₱)</label>
              <input class="form-control tln-form-control font-mono" name="amount" type="number" step="0.01"  min="<?= esc((string) min($minAmount, $l['payoff'])) ?>" max="<?= esc((string) $l['payoff']) ?>" value="<?= esc(number_format($def, 2, '.', '')) ?>" required>
            </div>
            <div class="col-auto"><button class="btn btn-tln-primary" type="submit"><i class="bi bi-credit-card"></i> Pay online</button></div>
            <div class="col-12 col-md d-flex gap-2 flex-wrap">
              <?php if ($l['due_now'] > 0): ?><button type="button" class="btn btn-tln-ghost btn-sm js-fill" data-v="<?= esc(number_format($l['due_now'], 2, '.', '')) ?>">Amount due <?= peso($l['due_now']) ?></button><?php endif; ?>
              <button type="button" class="btn btn-tln-ghost btn-sm js-fill" data-v="<?= esc(number_format($l['payoff'], 2, '.', '')) ?>">Pay in full <?= peso($l['payoff']) ?></button>
            </div>
          </div>
          <div class="tln-small mt-2">You will be taken to a secure payment page<?= $driver === 'paymongo' ? ' (GCash, Maya or card)' : '' ?>. Your balance updates as soon as the payment is confirmed.</div>
        </form>
      <?php elseif (! $payOnline && $l['payable']): ?>
        <div class="tln-small">Online payment is not available right now — please pay at the cooperative office.</div>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if ($ledgers): ?>
  <div class="tln-section-head mt-4"><h2>Savings &amp; contributions</h2></div>
  <div class="row g-3 mb-4">
    <?php foreach ($ledgers as $a): ?>
      <div class="col-6 col-md-3"><div class="tln-tile"><div class="tln-fig-label"><?= esc(config('Loaning')->ledgerTypes[$a['ledger_type']]['label'] ?? $a['ledger_type']) ?></div><div class="tln-fig-value"><?= peso($a['balance']) ?></div><div class="tln-small font-mono"><?= esc($a['account_no']) ?></div></div></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="tln-section-head mt-4"><h2>My loan payments</h2></div>
<?php if ($payments): ?>
<table class="table-ledger w-100">
  <thead><tr><th>Date</th><th>Receipt</th><th>Loan</th><th>Method</th><th class="num">Amount</th></tr></thead>
  <tbody>
  <?php foreach ($payments as $t): ?>
    <tr><td><?= fdate($t['txn_date']) ?></td><td class="font-mono"><?= esc($t['reference'] ?: $t['txn_no']) ?></td><td><?= esc($t['loan_type'] ?? '') ?><div class="tln-small"><?= esc($t['loan_no'] ?? '') ?></div></td><td><?= esc(ucfirst((string) $t['method'])) ?></td><td class="num"><?= peso($t['amount']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?><div class="tln-empty">No payments recorded yet.</div><?php endif; ?>

<div class="tln-small mt-4">Questions about your account? Please contact the cooperative office. Balances shown are as of today, <?= fdate(date('Y-m-d')) ?>.</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('.js-fill').forEach(function (b) {
  b.addEventListener('click', function () { b.closest('form').querySelector('[name=amount]').value = b.dataset.v; });
});
document.querySelectorAll('.js-payform').forEach(function (f) {
  f.addEventListener('submit', function () { var btn = f.querySelector('button[type=submit]'); btn.disabled = true; btn.innerHTML = 'Opening payment page…'; });
});
</script>
<?= $this->endSection() ?>
