<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $deduct = (float) $loan['service_fee'] + (float) $loan['insurance_fee'] + (float) $loan['cbu_retention']; $net = $loan['net_proceeds'] > 0 ? $loan['net_proceeds'] : $loan['principal'] - $deduct; ?>
<div class="tln-topbar"><div><div class="tln-page-title">Disbursement voucher</div><div class="tln-page-sub font-mono">DV-<?= esc($loan['loan_no']) ?></div></div>
  <div class="tln-topbar-right no-print d-flex gap-2"><a class="btn btn-tln-ghost btn-sm" href="<?= site_url('accounts/detail/' . $client['acct_no']) ?>"><i class="bi bi-arrow-left"></i> Back</a><button class="btn btn-tln-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button></div></div>
<div class="tln-content" style="max-width:760px;">
  <div class="text-center mb-3"><div class="font-display" style="font-size:20px;font-weight:600;">LAFFICO-MPC</div><div class="tln-small">Loan disbursement voucher</div></div>
  <div class="tln-panel p-3 mb-3" style="font-size:13px;">
    <div class="row g-2">
      <div class="col-6">Payee: <strong><?= esc($client['name']) ?></strong> <span class="font-mono"><?= esc($client['acct_no']) ?></span></div>
      <div class="col-6 text-end">Date: <strong><?= fdate($loan['release_date']) ?></strong></div>
      <div class="col-6">Loan: <?= esc($loan['loan_type']) ?> · <?= esc($loan['loan_no']) ?></div>
      <div class="col-6 text-end">Paid by: <strong><?= esc(ucfirst($loan['release_method'])) ?></strong></div>
    </div>
  </div>
  <table class="table-ledger w-100 mb-3">
    <thead><tr><th>Particulars</th><th class="num">Amount</th></tr></thead>
    <tbody>
      <tr><td>Loan principal</td><td class="num"><?= peso($loan['principal']) ?></td></tr>
      <tr><td>Less: service fee</td><td class="num"><?= peso(-$loan['service_fee']) ?></td></tr>
      <tr><td>Less: insurance</td><td class="num"><?= peso(-$loan['insurance_fee']) ?></td></tr>
      <tr><td>Less: Capital Build Up retention</td><td class="num"><?= peso(-$loan['cbu_retention']) ?></td></tr>
      <tr style="font-weight:600;"><td>NET PROCEEDS RELEASED</td><td class="num"><?= peso($net) ?></td></tr>
    </tbody>
  </table>
  <div class="row text-center mt-5" style="font-size:12px;"><div class="col-4"><div style="border-top:1px solid #333;padding-top:4px;">Prepared by<br><?= esc((string) $loan['created_by']) ?></div></div><div class="col-4"><div style="border-top:1px solid #333;padding-top:4px;">Approved by<br><?= esc((string) $loan['approved_by']) ?></div></div><div class="col-4"><div style="border-top:1px solid #333;padding-top:4px;">Received by (signature over printed name)</div></div></div>
</div>
<?= $this->endSection() ?>
