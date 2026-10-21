<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $cfg = config('Loaning'); $isToday = $date === date('Y-m-d'); ?>
<div class="tln-topbar">
  <div>
    <div class="tln-page-title">Daily transactions</div>
    <div class="tln-page-sub">Every payment, release and adjustment — balances update the moment you save</div>
  </div>
  <form class="tln-topbar-right tln-month-form" method="get"><input class="form-control tln-form-control" type="date" name="date" value="<?= esc($date) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()"></form>
</div>

<div class="tln-content">
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="tln-tile tile-current"><div class="tln-tile-label">Money in — <?= $isToday ? 'today' : fdate($date) ?></div><div class="tln-tile-value"><?= peso($in) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="tln-tile tile-pastdue"><div class="tln-tile-label">Money out</div><div class="tln-tile-value"><?= peso($out) ?></div></div></div>
    <div class="col-12 col-lg-6"><div class="tln-tile"><div class="tln-tile-label">By type</div>
      <?php foreach ($byCat as $c => $v): ?><span class="tln-small me-3"><?= esc(txn_label($c)) ?>: <strong><?= peso($v) ?></strong></span><?php endforeach; ?>
      <?php if (! $byCat): ?><span class="tln-small">Nothing recorded for this date.</span><?php endif; ?></div></div>
  </div>

  <div class="row g-4">
    <?php if (can('txn.record')): ?>
    <div class="col-lg-4">
      <div class="tln-section-head"><h2>Record a transaction</h2></div>
      <form class="tln-panel p-3" method="post" action="<?= site_url('transactions/record') ?>" enctype="multipart/form-data" id="txnForm">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="tln-form-label">Client</label>
          <select class="form-select tln-form-control" name="client_id" id="txClient" required><option value="">Select client…</option>
            <?php foreach ($clients as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (string) old('client_id') === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name'] . ' — ' . $c['acct_no']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="tln-form-label">Transaction type</label>
          <select class="form-select tln-form-control" name="category" id="txCat" required>
            <?php foreach ($cfg->txnCategories as $k => $v): if ($k === 'loan_release') continue; ?><option value="<?= $k ?>" <?= old('category') === $k ? 'selected' : '' ?>><?= esc($v['label']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3" id="rowLoan"><label class="tln-form-label">Loan</label><select class="form-select tln-form-control" name="loan_id" id="txLoan"></select></div>
        <div class="mb-3 d-none" id="rowAdj"><label class="tln-form-label">Adjustment</label><select class="form-select tln-form-control" name="adj_type"><option value="debit">Debit — adds to the balance</option><option value="credit">Credit — reduces the balance</option></select></div>
        <div class="row g-3 mb-3">
          <div class="col-6"><label class="tln-form-label">Amount (₱)</label><input class="form-control tln-form-control font-mono" type="number" name="amount" id="txAmount" step="0.01" min="0.01" required value="<?= old('amount') ?>"></div>
          <div class="col-6" id="rowMethod"><label class="tln-form-label">Cash or check</label><select class="form-select tln-form-control" name="method"><option value="cash">Cash</option><option value="check">Check</option></select></div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-6"><label class="tln-form-label">Date</label><input class="form-control tln-form-control" type="date" name="txn_date" value="<?= esc($date) ?>" max="<?= date('Y-m-d') ?>" required></div>
          <div class="col-6"><label class="tln-form-label">OR / check no.</label><input class="form-control tln-form-control" name="reference" placeholder="auto if blank"></div>
        </div>
        <div class="mb-3"><label class="tln-form-label">Remarks</label><input class="form-control tln-form-control" name="remarks" value="<?= old('remarks') ?>"></div>
        <div class="mb-3"><label class="tln-form-label">Supporting document (optional)</label>
          <div class="d-flex gap-2"><select class="form-select tln-form-control" name="doc_type" style="max-width:150px;"><option value="official_receipt">Receipt</option><option value="deposit_slip">Deposit slip</option><option value="voucher">Voucher</option><option value="payment_record">Payment record</option><option value="other">Other</option></select><input class="form-control tln-form-control" type="file" name="document"></div></div>
        <button class="btn btn-tln-primary w-100" type="submit">Save transaction</button>
      </form>
    </div>
    <?php endif; ?>

    <div class="<?= can('txn.record') ? 'col-lg-8' : 'col-12' ?>">
      <div class="tln-section-head"><h2>Entries for <?= fdate($date) ?></h2><span class="hint"><?= count($rows) ?> entr<?= count($rows) === 1 ? 'y' : 'ies' ?></span></div>
      <table class="table-ledger w-100">
        <thead><tr><th>Txn no.</th><th>Client</th><th>Type</th><th>Ref / method</th><th class="num">Amount</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $cancelled = $r['status'] === 'cancelled'; ?>
          <tr style="<?= $cancelled ? 'opacity:.55;' : '' ?>">
            <td class="font-mono"><?= esc($r['txn_no']) ?></td>
            <td><?= $r['client_name'] ? '<a class="tln-row-link" href="' . site_url('accounts/detail/' . $r['acct_no']) . '">' . esc($r['client_name']) . '</a>' : '—' ?></td>
            <td><?= esc(txn_label($r['category'])) ?><?= $r['loan_no'] ? '<div class="tln-small">' . esc($r['loan_no']) . '</div>' : '' ?><?= $r['category'] === 'adjustment' ? '<div class="tln-small">' . ((int) $r['adj_sign'] > 0 ? 'Debit' : 'Credit') . ($r['remarks'] ? ' — ' . esc($r['remarks']) : '') . '</div>' : '' ?></td>
            <td><?= esc($r['reference'] ?: '—') ?><div class="tln-small"><?= esc(ucfirst((string) $r['method'])) ?><?php if (! $cancelled && $r['direction'] !== 'none'): ?> · <a class="tln-row-link" href="<?= site_url('transactions/receipt/' . $r['id']) ?>" target="_blank">Receipt</a><?php endif; ?></div></td>
            <td class="num"><?= $r['direction'] === 'out' ? '−' : '' ?><?= peso($r['amount']) ?></td>
            <td class="text-end">
              <?php if ($cancelled): ?><span class="tln-status rejected"><span class="dot"></span>Cancelled</span><div class="tln-small"><?= esc($r['cancel_reason']) ?></div>
              <?php elseif ($r['cancel_requested']): ?><span class="tln-status pending"><span class="dot"></span>Cancel pending</span>
              <?php elseif (can('txn.cancel') && $r['category'] !== 'loan_release'): ?>
                <button class="btn btn-tln-ghost btn-sm js-cancel" data-bs-toggle="modal" data-bs-target="#cancelTxn" data-id="<?= (int) $r['id'] ?>" data-label="<?= esc($r['txn_no'] . ' · ' . txn_label($r['category']) . ' · ' . peso($r['amount'])) ?>">Cancel</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (! $rows): ?><div class="tln-empty">No transactions on this date.</div><?php endif; ?>
    </div>
  </div>
</div>

<?php if (can('txn.cancel')): ?>
<div class="modal fade" id="cancelTxn" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= site_url('transactions/cancel') ?>" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="txn_id" id="cxId">
  <div class="modal-header"><h5 class="modal-title">Cancel transaction</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="tln-small mb-3" id="cxLabel"></div>
    <div class="mb-3"><label class="tln-form-label">Valid reason for cancelling</label><textarea class="form-control tln-form-control" name="reason" rows="3" minlength="10" required></textarea></div>
    <div class="mb-1"><label class="tln-form-label">Supporting document (required)</label><input class="form-control tln-form-control" type="file" name="document" required></div>
    <div class="tln-small mt-2"><?= can('approve.manager') ? 'As an approver, the cancellation takes effect immediately and is logged.' : 'A Manager must approve before the cancellation takes effect.' ?></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-tln-ghost" data-bs-dismiss="modal">Keep transaction</button><button class="btn btn-tln-primary" type="submit">Submit cancellation</button></div>
</form></div></div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
  var loansBy = <?= json_encode($loansBy, JSON_UNESCAPED_UNICODE) ?>;
  var cl = document.getElementById('txClient'), cat = document.getElementById('txCat'), loan = document.getElementById('txLoan');
  if (cl) {
    function sync() {
      var needsLoan = cat.value === 'loan_payment' || cat.value === 'adjustment';
      document.getElementById('rowLoan').classList.toggle('d-none', !needsLoan);
      document.getElementById('rowAdj').classList.toggle('d-none', cat.value !== 'adjustment');
      document.getElementById('rowMethod').classList.toggle('d-none', cat.value === 'adjustment');
      loan.innerHTML = '';
      (loansBy[cl.value] || []).forEach(function (l) { var o = document.createElement('option'); o.value = l.id; o.textContent = l.label; o.dataset.monthly = l.monthly; loan.appendChild(o); });
      if (needsLoan && !loan.options.length) { var o = document.createElement('option'); o.value = ''; o.textContent = 'No open loan for this client'; loan.appendChild(o); }
    }
    cl.addEventListener('change', sync); cat.addEventListener('change', sync);
    loan.addEventListener('change', function () { var o = loan.options[loan.selectedIndex]; if (cat.value === 'loan_payment' && o && o.dataset.monthly && !document.getElementById('txAmount').value) document.getElementById('txAmount').value = o.dataset.monthly; });
    sync();
  }
  document.querySelectorAll('.js-cancel').forEach(function (b) { b.addEventListener('click', function () { document.getElementById('cxId').value = b.dataset.id; document.getElementById('cxLabel').textContent = b.dataset.label; }); });
})();
</script>
<?= $this->endSection() ?>
