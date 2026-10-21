<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $cfg = config('Loaning'); ?>
<div class="tln-topbar"><div><div class="tln-page-title">Accounting</div><div class="tln-page-sub">Expenses, payables, budgets and the bank statement — the inputs behind the financial reports</div></div></div>
<div class="tln-content">
  <ul class="nav tln-tabs mb-3">
    <?php foreach (['expenses' => 'Expenses & payables', 'budgets' => 'Budgets', 'bank' => 'Bank reconciliation', 'settings' => 'Opening balances'] as $k => $label): ?>
      <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="<?= site_url('accounting?tab=' . $k) ?>"><?= $label ?></a></li>
    <?php endforeach; ?>
  </ul>

<?php if ($tab === 'expenses'): ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="tln-section-head"><h2>Record expense / bill</h2></div>
      <form class="tln-panel p-3" method="post" action="<?= site_url('accounting/expense') ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-6"><label class="tln-form-label">Date</label><input class="form-control tln-form-control" type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required></div>
          <div class="col-6"><label class="tln-form-label">Amount (₱)</label><input class="form-control tln-form-control font-mono" type="number" step="0.01" min="0.01" name="amount" required></div>
          <div class="col-12"><label class="tln-form-label">Category</label><select class="form-select tln-form-control" name="category"><?php foreach ($cfg->expenseCategories as $c): ?><option><?= esc($c) ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="tln-form-label">Description</label><input class="form-control tln-form-control" name="description" required></div>
          <div class="col-6"><label class="tln-form-label">Supplier</label><input class="form-control tln-form-control" name="supplier"></div>
          <div class="col-6"><label class="tln-form-label">Invoice no.</label><input class="form-control tln-form-control" name="invoice_no"></div>
          <div class="col-6"><label class="tln-form-label">Status</label><select class="form-select tln-form-control" name="payment_status" id="exStatus"><option value="paid">Paid</option><option value="unpaid">Unpaid (payable)</option></select></div>
          <div class="col-6" id="exVia"><label class="tln-form-label">Paid from</label><select class="form-select tln-form-control" name="paid_via"><option value="cash">Cash on hand</option><option value="bank">Bank (check)</option></select></div>
          <div class="col-6 d-none" id="exDue"><label class="tln-form-label">Due date</label><input class="form-control tln-form-control" type="date" name="due_date"></div>
        </div>
        <button class="btn btn-tln-primary w-100 mt-3" type="submit">Save</button>
      </form>
    </div>
    <div class="col-lg-8">
      <div class="tln-section-head"><h2>Expenses &amp; payables</h2></div>
      <table class="table-ledger w-100">
        <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Status</th><th class="num">Amount</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($expenses as $e): $late = $e['payment_status'] === 'unpaid' && $e['due_date'] && $e['due_date'] < date('Y-m-d'); ?>
          <tr class="<?= $late ? 'row-pastdue' : '' ?>">
            <td><?= fdate($e['expense_date'], 'M d') ?></td><td><?= esc($e['category']) ?></td>
            <td><?= esc($e['description']) ?><div class="tln-small"><?= esc(trim($e['supplier'] . ' ' . $e['invoice_no'])) ?></div></td>
            <td><?= $e['payment_status'] === 'paid' ? 'Paid ' . fdate($e['paid_date'], 'M d') . ' · ' . esc($e['paid_via']) : 'Unpaid · due ' . fdate($e['due_date'], 'M d') ?></td>
            <td class="num"><?= peso($e['amount']) ?></td>
            <td class="text-end"><?php if ($e['payment_status'] === 'unpaid'): ?>
              <form class="d-flex gap-1 justify-content-end" method="post" action="<?= site_url('accounting/expense/pay/' . $e['id']) ?>"><?= csrf_field() ?><select class="form-select form-select-sm" name="paid_via" style="width:90px;"><option value="cash">Cash</option><option value="bank">Bank</option></select><button class="btn btn-tln-primary btn-sm">Pay</button></form>
            <?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (! $expenses): ?><div class="tln-empty">No expenses yet.</div><?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($tab === 'budgets'): ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="tln-section-head"><h2>Set a budget</h2></div>
      <form class="tln-panel p-3" method="post" action="<?= site_url('accounting/budget') ?>">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="tln-form-label">Month</label><input class="form-control tln-form-control" type="month" name="month" value="<?= esc($month) ?>" required></div>
        <div class="mb-3"><label class="tln-form-label">Category</label><select class="form-select tln-form-control" name="category"><?php foreach ($cfg->expenseCategories as $c): ?><option><?= esc($c) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="tln-form-label">Budget (₱)</label><input class="form-control tln-form-control font-mono" type="number" step="0.01" min="0" name="amount" required></div>
        <button class="btn btn-tln-primary w-100">Save budget</button>
      </form>
    </div>
    <div class="col-lg-8">
      <div class="tln-section-head"><h2>Budgets — <?= date('F Y', strtotime($month . '-01')) ?></h2><form method="get" class="tln-month-form"><input type="hidden" name="tab" value="budgets"><input class="form-control tln-form-control" type="month" name="month" value="<?= esc($month) ?>" onchange="this.form.submit()"></form></div>
      <table class="table-ledger w-100"><thead><tr><th>Category</th><th class="num">Budget</th></tr></thead><tbody>
        <?php foreach ($budgets as $b): ?><tr><td><?= esc($b['category']) ?></td><td class="num"><?= peso($b['amount']) ?></td></tr><?php endforeach; ?>
      </tbody></table>
      <?php if (! $budgets): ?><div class="tln-empty">No budgets for this month.</div><?php endif; ?>
      <div class="tln-small mt-3">Compare against spending in <a href="<?= site_url('reports/view/budget-actual?month=' . $month) ?>">Budget vs. actual</a>, which raises over-spending alerts automatically.</div>
    </div>
  </div>
<?php endif; ?>

<?php if ($tab === 'bank'): ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="tln-section-head"><h2>Add statement line</h2></div>
      <form class="tln-panel p-3" method="post" action="<?= site_url('accounting/bank') ?>">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="tln-form-label">Statement date</label><input class="form-control tln-form-control" type="date" name="entry_date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="mb-3"><label class="tln-form-label">Reference / check no.</label><input class="form-control tln-form-control" name="reference"></div>
        <div class="mb-3"><label class="tln-form-label">Description</label><input class="form-control tln-form-control" name="description"></div>
        <div class="mb-3"><label class="tln-form-label">Amount (₱) — deposits +, withdrawals −</label><input class="form-control tln-form-control font-mono" type="number" step="0.01" name="amount" required></div>
        <button class="btn btn-tln-primary w-100">Add</button>
      </form>
      <form class="mt-3" method="post" action="<?= site_url('accounting/bank/match') ?>"><?= csrf_field() ?><button class="btn btn-tln-ghost w-100"><i class="bi bi-link-45deg"></i> Auto-match to the books</button></form>
      <div class="tln-small mt-2">Matches by amount within 7 days, preferring the same reference. See the <a href="<?= site_url('reports/view/bank-recon') ?>">Bank reconciliation report</a>.</div>
    </div>
    <div class="col-lg-8">
      <div class="tln-section-head"><h2>Bank statement</h2></div>
      <table class="table-ledger w-100"><thead><tr><th>Date</th><th>Reference</th><th>Description</th><th class="num">Amount</th><th>Match</th></tr></thead><tbody>
        <?php foreach ($bank as $b): ?>
          <tr class="<?= $b['match_id'] ? 'row-current' : 'row-pastdue' ?>"><td><?= fdate($b['entry_date'], 'M d') ?></td><td><?= esc($b['reference'] ?: '—') ?></td><td><?= esc($b['description'] ?: '—') ?></td><td class="num"><?= peso($b['amount']) ?></td>
            <td><?php if ($b['match_id']): ?>Matched (<?= esc($b['match_type']) ?> #<?= (int) $b['match_id'] ?>)
              <form class="d-inline" method="post" action="<?= site_url('accounting/bank/unmatch/' . $b['id']) ?>"><?= csrf_field() ?><button class="btn btn-link btn-sm p-0 ms-1">undo</button></form>
              <?php else: ?>Unmatched<?php endif; ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
      <?php if (! $bank): ?><div class="tln-empty">No statement lines yet.</div><?php endif; ?>
      <div class="tln-section-head mt-4"><h2>Book entries still waiting for a bank line</h2></div>
      <table class="table-ledger w-100"><thead><tr><th>Date</th><th>Reference</th><th>Description</th><th class="num">Amount</th></tr></thead><tbody>
        <?php foreach ($bookOpen as $x): ?><tr class="row-restructured"><td><?= fdate($x['date'], 'M d') ?></td><td><?= esc($x['ref']) ?></td><td><?= esc($x['desc']) ?></td><td class="num"><?= peso($x['amount']) ?></td></tr><?php endforeach; ?>
      </tbody></table>
      <?php if (! $bookOpen): ?><div class="tln-empty">Every check / bank payment is matched.</div><?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($tab === 'settings'): ?>
  <form class="tln-panel p-3" style="max-width:460px;" method="post" action="<?= site_url('accounting/settings') ?>">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="tln-form-label">Opening cash on hand (₱)</label><input class="form-control tln-form-control font-mono" type="number" step="0.01" min="0" name="opening_cash_on_hand" value="<?= esc($openHand) ?>"></div>
    <div class="mb-3"><label class="tln-form-label">Opening cash in bank (₱)</label><input class="form-control tln-form-control font-mono" type="number" step="0.01" min="0" name="opening_cash_on_bank" value="<?= esc($openBank) ?>"></div>
    <div class="tln-small mb-3">The cooperative's capital at the start of record-keeping. The balance sheet is reconciled against these figures — change them only when setting up.</div>
    <button class="btn btn-tln-primary">Save</button>
  </form>
<?php endif; ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
  var s = document.getElementById('exStatus'); if (!s) return;
  function sync() { var u = s.value === 'unpaid'; document.getElementById('exVia').classList.toggle('d-none', u); document.getElementById('exDue').classList.toggle('d-none', !u); }
  s.addEventListener('change', sync); sync();
})();
</script>
<?= $this->endSection() ?>
