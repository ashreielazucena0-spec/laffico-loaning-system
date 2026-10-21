<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="tln-topbar"><div><div class="tln-page-title">Official receipt</div><div class="tln-page-sub font-mono"><?= esc($t['reference'] ?: $t['txn_no']) ?></div></div>
  <div class="tln-topbar-right no-print d-flex gap-2"><a class="btn btn-tln-ghost btn-sm" href="<?= site_url('transactions?date=' . $t['txn_date']) ?>"><i class="bi bi-arrow-left"></i> Back</a><button class="btn btn-tln-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button></div></div>
<div class="tln-content" style="max-width:640px;">
  <div class="text-center mb-3"><div class="font-display" style="font-size:20px;font-weight:600;">LAFFICO-MPC</div><div class="tln-small"><?= $t['direction'] === 'out' ? 'Cash / check voucher' : 'Official receipt' ?></div></div>
  <?php if ($t['status'] === 'cancelled'): ?><div class="alert alert-danger py-2">CANCELLED — <?= esc((string) $t['cancel_reason']) ?></div><?php endif; ?>
  <div class="tln-panel p-3 mb-3" style="font-size:13px;">
    <div class="d-flex justify-content-between"><span>No. <strong class="font-mono"><?= esc($t['reference'] ?: $t['txn_no']) ?></strong></span><span>Date <strong><?= fdate($t['txn_date']) ?></strong></span></div>
    <div class="mt-2"><?= $t['direction'] === 'out' ? 'Paid to' : 'Received from' ?>: <strong><?= esc((string) $t['client_name']) ?></strong> <span class="font-mono"><?= esc((string) $t['acct_no']) ?></span></div>
    <div>For: <?= esc(txn_label($t['category'])) ?><?= $t['loan_no'] ? ' — ' . esc($t['loan_type']) . ' ' . esc($t['loan_no']) : '' ?></div>
    <div>Mode: <?= esc(ucfirst((string) $t['method'])) ?></div>
  </div>
  <table class="table-ledger w-100 mb-3">
    <tbody>
    <?php if ($t['category'] === 'loan_payment'): ?>
      <tr><td>Applied to penalty</td><td class="num"><?= peso($t['penalty_part']) ?></td></tr>
      <tr><td>Applied to interest</td><td class="num"><?= peso($t['interest_part']) ?></td></tr>
      <tr><td>Applied to principal</td><td class="num"><?= peso($t['principal_part']) ?></td></tr>
    <?php endif; ?>
      <tr style="font-weight:600;"><td>TOTAL</td><td class="num"><?= peso($t['amount']) ?></td></tr>
    </tbody>
  </table>
  <?php if ($m && $t['category'] === 'loan_payment'): ?><div class="tln-small mb-3">Loan balance now: <strong><?= peso($m['outstanding']) ?></strong><?= $m['penalty_due'] > 0 ? ' + penalty ' . peso($m['penalty_due']) : '' ?><?= $m['status'] === 'paid' ? ' — loan fully paid' : '' ?></div><?php endif; ?>
  <?php if ($t['remarks']): ?><div class="tln-small mb-3">Remarks: <?= esc($t['remarks']) ?></div><?php endif; ?>
  <div class="row text-center mt-5" style="font-size:12px;"><div class="col-6"><div style="border-top:1px solid #333;padding-top:4px;"><?= esc((string) $t['recorded_by']) ?><br>Received / issued by</div></div><div class="col-6"><div style="border-top:1px solid #333;padding-top:4px;">Client's signature</div></div></div>
</div>
<?= $this->endSection() ?>
