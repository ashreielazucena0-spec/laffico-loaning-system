<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?php
  $last  = $rows ? end($rows)['due'] : null; reset($rows);
  $tot   = ['amount' => 0, 'principal' => 0, 'interest' => 0, 'paid' => 0, 'penalty' => 0];
  foreach ($rows as $r) { foreach ($tot as $k => $_) { $tot[$k] += $r[$k]; } }
  $stateLabel = ['paid' => 'Paid', 'pastdue' => 'Past due', 'restructured' => 'Restructured', 'current' => 'Upcoming'];
  $deduct = (float) $loan['service_fee'] + (float) $loan['insurance_fee'] + (float) $loan['cbu_retention'];
  $wrapStart = $portal ? '<div>' : '<div class="tln-content">';
?>
<?php if (! $portal): ?>
<div class="tln-topbar"><div><div class="tln-page-title">Loan schedule &amp; disclosure</div><div class="tln-page-sub font-mono"><?= esc($loan['loan_no']) ?></div></div>
  <div class="tln-topbar-right no-print d-flex gap-2"><a class="btn btn-tln-ghost btn-sm" href="<?= site_url('accounts/detail/' . $client['acct_no']) ?>"><i class="bi bi-arrow-left"></i> Back</a><button class="btn btn-tln-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button></div></div>
<?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-3 no-print"><h4 class="font-display m-0">Payment schedule · <?= esc($loan['loan_no']) ?></h4><div class="d-flex gap-2"><a class="btn btn-tln-ghost btn-sm" href="<?= site_url('portal') ?>">Back</a><button class="btn btn-tln-primary btn-sm" onclick="window.print()">Print</button></div></div>
<?php endif; ?>
<?= $wrapStart ?>
  <div class="text-center mb-3"><div class="font-display" style="font-size:20px;font-weight:600;">LAFFICO-MPC</div><div class="tln-small">Loan disclosure statement &amp; amortization schedule</div></div>
  <div class="row g-3 mb-3">
    <div class="col-md-6"><div class="tln-panel p-3" style="height:100%;">
      <div class="tln-fig-label">Borrower</div><div><strong><?= esc($client['name']) ?></strong> <span class="font-mono tln-small"><?= esc($client['acct_no']) ?></span></div>
      <div class="tln-small"><?= esc((string) $client['address']) ?></div>
      <?php if ($client['co_maker']): ?><div class="tln-small">Co-maker: <?= esc($client['co_maker']) ?></div><?php endif; ?>
      <div class="tln-fig-label mt-3">Loan</div><div><?= esc($loan['loan_type']) ?></div>
      <?php if ($loan['purpose']): ?><div class="tln-small">Purpose: <?= esc($loan['purpose']) ?></div><?php endif; ?>
      <?php if ($loan['collateral']): ?><div class="tln-small">Collateral: <?= esc($loan['collateral']) ?></div><?php endif; ?>
      <div class="tln-small">Released <?= fdate($loan['release_date']) ?> by <?= esc($loan['release_method']) ?> · approved by <?= esc((string) $loan['approved_by']) ?></div>
    </div></div>
    <div class="col-md-6"><div class="tln-panel p-3" style="height:100%;">
      <table class="w-100" style="font-size:13px;">
        <tr><td>Principal</td><td class="text-end font-mono"><?= peso($loan['principal']) ?></td></tr>
        <tr><td>Interest (<?= rtrim(rtrim(number_format((float) $loan['interest_rate'], 2), '0'), '.') ?>% flat × <?= (int) $loan['term_months'] ?> mo)</td><td class="text-end font-mono"><?= peso($loan['interest_amount']) ?></td></tr>
        <tr><td><strong>Total payable</strong></td><td class="text-end font-mono"><strong><?= peso($loan['total_payable']) ?></strong></td></tr>
        <tr><td>Monthly installment</td><td class="text-end font-mono"><?= peso($loan['monthly_due']) ?></td></tr>
        <tr><td>First / last due date</td><td class="text-end"><?= fdate($loan['first_due_date']) ?> / <?= fdate($last) ?></td></tr>
        <tr><td colspan="2"><hr class="my-2"></td></tr>
        <tr><td>Less: service fee</td><td class="text-end font-mono"><?= peso($loan['service_fee']) ?></td></tr>
        <tr><td>Less: insurance</td><td class="text-end font-mono"><?= peso($loan['insurance_fee']) ?></td></tr>
        <tr><td>Less: Capital Build Up retention</td><td class="text-end font-mono"><?= peso($loan['cbu_retention']) ?></td></tr>
        <tr><td><strong>Net proceeds received</strong></td><td class="text-end font-mono"><strong><?= peso($loan['net_proceeds'] > 0 ? $loan['net_proceeds'] : $loan['principal'] - $deduct) ?></strong></td></tr>
      </table>
    </div></div>
  </div>

  <table class="table-ledger w-100 mb-3">
    <thead><tr><th>#</th><th>Due date</th><th class="num">Installment</th><th class="num">Principal</th><th class="num">Interest</th><th class="num">Paid</th><th class="num">Penalty</th><th class="num">Balance after</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= $r['no'] ?></td><td><?= fdate($r['due']) ?></td><td class="num"><?= peso($r['amount']) ?></td><td class="num"><?= peso($r['principal']) ?></td><td class="num"><?= peso($r['interest']) ?></td><td class="num"><?= peso($r['paid']) ?></td><td class="num"><?= $r['penalty'] > 0 ? peso($r['penalty']) : '—' ?></td><td class="num"><?= peso($r['balance']) ?></td>
        <td><span class="tln-status <?= esc($r['state']) ?>"><span class="dot"></span><?= $stateLabel[$r['state']] ?? esc($r['state']) ?></span></td></tr>
    <?php endforeach; ?>
      <tr style="font-weight:600;"><td colspan="2">Total</td><td class="num"><?= peso($tot['amount']) ?></td><td class="num"><?= peso($tot['principal']) ?></td><td class="num"><?= peso($tot['interest']) ?></td><td class="num"><?= peso($tot['paid']) ?></td><td class="num"><?= peso($tot['penalty']) ?></td><td></td><td></td></tr>
    </tbody>
  </table>

  <div class="row g-3 mb-3">
    <div class="col-md-6"><div class="tln-panel p-3" style="font-size:13px;">
      <div>Outstanding balance: <strong><?= peso($m['outstanding']) ?></strong></div>
      <div>Late-payment penalty owed: <strong><?= peso($m['penalty_due']) ?></strong></div>
      <div>Amount to settle the loan today: <strong><?= peso($m['payoff']) ?></strong></div>
      <?php if ($loan['closed_at']): ?><div class="mt-1" style="color:var(--ok-ink);"><i class="bi bi-check-circle"></i> Fully paid on <?= fdate($loan['closed_at']) ?></div><?php endif; ?>
    </div></div>
    <div class="col-md-6 tln-small">An installment that stays unpaid more than <?= (int) $cfg->penaltyGraceDays ?> days after its due date is charged a penalty of <?= rtrim(rtrim(number_format($cfg->penaltyRatePct, 2), '0'), '.') ?>% of the unpaid amount, and again for every further 30 days. Each payment settles the penalty first, then interest and principal.</div>
  </div>

  <?php if (! $portal): ?>
  <div class="row text-center mt-5" style="font-size:12px;"><div class="col-4"><div style="border-top:1px solid #333;padding-top:4px;">Borrower's signature</div></div><div class="col-4"><div style="border-top:1px solid #333;padding-top:4px;">Co-maker's signature</div></div><div class="col-4"><div style="border-top:1px solid #333;padding-top:4px;">Approved by (Manager)</div></div></div>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
