<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
  $cfg   = config('Loaning');
  $tab   = in_array($tab, ['ledger', 'monitor', 'payments', 'docs', 'reminders'], true) ? $tab : 'loans';
  $base  = site_url('accounts/detail/' . $client['acct_no']);
  $open  = array_values(array_filter($loans, static fn ($l) => in_array($l['status'], ['pastdue', 'restructured', 'current'], true)));
  $hasApp = [];
  foreach ($docs as $d) { if ($d['doc_type'] === 'application_for_loan' && $d['loan_id']) { $hasApp[$d['loan_id']] = true; } }
  $stateLabel = ['paid' => 'Paid', 'pastdue' => 'Past due', 'restructured' => 'Restructured', 'current' => 'Current'];
?>
<div class="tln-topbar">
  <div>
    <div class="tln-page-title"><?= esc($client['name']) ?></div>
    <div class="tln-page-sub font-mono"><?= esc($client['acct_no']) ?> · opened <?= fdate($client['opened_at']) ?></div>
  </div>
  <div class="tln-topbar-right d-flex gap-2 align-items-center">
    <?= status_badge($sum['status'], $sum['days_overdue']) ?> <?= class_badge($sum['classification']) ?>
    <?php if (can('clients.delete')): ?>
      <form class="m-0" method="post" action="<?= site_url('accounts/delete') ?>" onsubmit="return confirm('Delete <?= esc($client['name'], 'js') ?>? This cannot be undone. Only clients with no loans or transactions can be deleted.');"><?= csrf_field() ?><input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>"><button class="btn btn-tln-ghost btn-sm text-danger"><i class="bi bi-trash"></i> Delete client</button></form>
    <?php endif; ?>
    <a class="btn btn-tln-ghost btn-sm" href="<?= site_url('accounts') ?>"><i class="bi bi-arrow-left"></i> All clients</a>
  </div>
</div>

<div class="tln-content">
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="tln-tile"><div class="tln-fig-label">Total outstanding</div><div class="tln-fig-value"><?= peso($sum['outstanding']) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="tln-tile"><div class="tln-fig-label">Active loans</div><div class="tln-fig-value"><?= (int) $sum['active_loans'] ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="tln-tile"><div class="tln-fig-label">Next due</div><div class="tln-fig-value" style="font-size:16px;"><?= fdate($sum['next_due']) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="tln-tile"><div class="tln-fig-label">Contact</div><div style="font-size:13px;"><?= esc($client['contact'] ?: '—') ?><br><span class="tln-small"><?= esc($client['address'] ?: '') ?></span><?php if ($client['occupation'] || $client['monthly_income']): ?><br><span class="tln-small"><?= esc((string) $client['occupation']) ?><?= $client['monthly_income'] ? ' · income ' . peso($client['monthly_income']) . '/mo' : '' ?></span><?php endif; ?></div></div></div>
  </div>

  <?php $creds = session()->getFlashdata('portal_credentials'); ?>
  <?php if ($creds): ?>
  <div class="tln-panel mb-3" id="credBox" style="border-color:var(--gold);">
    <div class="tln-panel-head"><h3><i class="bi bi-key"></i> Client portal login — give this to the client</h3></div>
    <div style="padding:14px 16px;">
      <div class="row g-3 align-items-end">
        <div class="col-sm-4"><div class="tln-fig-label">Website</div><div class="font-mono" style="font-size:13px;"><?= esc(site_url('portal/login')) ?></div></div>
        <div class="col-sm-3"><div class="tln-fig-label">Username (client name)</div><div class="tln-fig-value" style="font-size:16px;"><?= esc($creds['username']) ?></div></div>
        <div class="col-sm-3"><div class="tln-fig-label">Temporary password</div><div class="tln-fig-value"><?= esc($creds['password']) ?></div></div>
        <div class="col-sm-2 text-sm-end no-print"><button type="button" class="btn btn-tln-ghost btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button></div>
      </div>
      <div class="tln-small mt-2">This password is shown <strong>only once</strong>. The client can change it any time from the portal (optional). Lost it? Use “Reset password” below.</div>
    </div>
  </div>
  <?php endif; ?>

  <div class="tln-panel mb-4 no-print">
    <div class="tln-panel-head d-flex justify-content-between align-items-center"><h3><i class="bi bi-person-lock"></i> Client portal access</h3>
      <?php if ($portal): ?><span class="tln-status <?= $portal['is_active'] ? 'current' : 'pastdue' ?>"><span class="dot"></span><?= $portal['is_active'] ? 'Active' : 'Disabled' ?></span><?php endif; ?>
    </div>
    <div style="padding:12px 16px;" class="d-flex flex-wrap gap-3 align-items-center">
    <?php if (! $portal): ?>
      <span class="tln-small">This client has no online account yet.</span>
      <?php if (can('clients.add')): ?>
      <form method="post" action="<?= site_url('accounts/portal/create') ?>" class="m-0"><?= csrf_field() ?><input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>"><button class="btn btn-tln-primary btn-sm">Create portal account</button></form>
      <?php endif; ?>
    <?php else: ?>
      <span class="tln-small">Username <strong class="font-mono"><?= esc($portal['username']) ?></strong> · last sign-in <?= $portal['last_login'] ? esc(fdate($portal['last_login'], 'M d, Y g:i A')) : 'never' ?><?= $portal['must_change_password'] ? ' · <em>has not set own password yet</em>' : '' ?></span>
      <?php if (can('clients.add')): ?>
      <form method="post" action="<?= site_url('accounts/portal/reset') ?>" class="m-0 ms-auto" onsubmit="return confirm('Generate a new temporary password? The old one stops working.');"><?= csrf_field() ?><input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>"><button class="btn btn-tln-ghost btn-sm"><i class="bi bi-arrow-repeat"></i> Reset password</button></form>
      <form method="post" action="<?= site_url('accounts/portal/toggle') ?>" class="m-0"><?= csrf_field() ?><input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>"><button class="btn btn-tln-ghost btn-sm"><?= $portal['is_active'] ? 'Disable access' : 'Enable access' ?></button></form>
      <?php endif; ?>
    <?php endif; ?>
    </div>
    <?php foreach ($portalPay as $op): if ($op['fail_reason'] && strpos($op['fail_reason'], 'PAID but not posted') === 0): ?>
      <div class="tln-doc-warn" style="margin:0 16px 12px;"><i class="bi bi-exclamation-triangle"></i> Online payment <?= esc($op['pay_ref']) ?> (<?= peso($op['amount']) ?>) was paid but could not be posted: <?= esc(substr($op['fail_reason'], 20)) ?>. Please check and record it manually.</div>
    <?php endif; endforeach; ?>
  </div>

  <ul class="nav tln-tabs mb-3">
    <?php foreach (['loans' => 'Loans', 'ledger' => 'Ledger accounts', 'monitor' => 'Transaction monitor', 'payments' => 'Payment history', 'docs' => 'Supporting documents', 'reminders' => 'Reminders'] as $k => $label): ?>
      <li class="nav-item"><a class="nav-link <?= $k === $tab ? 'active' : '' ?>" href="<?= $base ?>?tab=<?= $k ?>"><?= $label ?></a></li>
    <?php endforeach; ?>
  </ul>

<?php if ($tab === 'loans'): ?>
  <div class="tln-section-head"><h2>Loan history</h2>
    <?php if (can('loans.create')): ?><button class="btn btn-tln-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newLoan"><i class="bi bi-plus-lg"></i> New loan application</button><?php endif; ?>
  </div>
  <table class="table-ledger w-100 mb-4">
    <thead><tr><th>Loan</th><th>Status</th><th class="num">Principal</th><th class="num">Monthly due</th><th class="num">Interest</th><th class="num">Outstanding</th><th>Due date</th><th>1st pay</th><th>2nd pay</th><th>3rd pay</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($loans as $l): ?>
      <tr class="row-<?= esc($l['status']) ?>">
        <td><strong><?= esc($l['loan_type']) ?></strong><div class="tln-small font-mono"><?= esc($l['loan_no']) ?> · <?= (int) $l['term_months'] ?> mo @ <?= rtrim(rtrim(number_format((float) $l['interest_rate'], 2), '0'), '.') ?>%</div>
          <?php if (! empty($l['purpose'])): ?><div class="tln-small">Purpose: <?= esc($l['purpose']) ?></div><?php endif; ?>
          <?php if (! empty($l['collateral'])): ?><div class="tln-small">Collateral: <?= esc($l['collateral']) ?></div><?php endif; ?></td>
        <td><?= status_badge($l['status'], $l['days_overdue']) ?>
          <?php if ($l['status'] === 'pending'): ?><div class="tln-small">Needs Manager approval</div>
            <?php $ev = json_decode((string) ($l['evaluation'] ?? ''), true) ?: []; foreach (($ev['flags'] ?? []) as $fl): ?><div class="tln-small" style="color:var(--rust);"><i class="bi bi-flag"></i> <?= esc($fl) ?></div><?php endforeach; ?>
            <?php if (empty($hasApp[$l['id']])): ?><div class="tln-doc-warn"><i class="bi bi-exclamation-triangle"></i> No Application for Loan attached</div><?php endif; ?>
          <?php endif; ?></td>
        <td class="num"><?= peso($l['principal']) ?></td>
        <td class="num"><?= peso($l['monthly_due']) ?></td>
        <td class="num"><?= peso($l['interest_amount']) ?></td>
        <td class="num"><?= $l['approval_status'] === 'approved' ? peso($l['outstanding']) : '—' ?>
          <?php if ($l['approval_status'] === 'approved' && $l['penalty_due'] > 0): ?><div class="tln-small" style="color:var(--rust);">+ <?= peso($l['penalty_due']) ?> penalty</div><div class="tln-small">Settle: <strong><?= peso($l['payoff']) ?></strong></div><?php endif; ?>
          <?php if ($l['status'] === 'paid' && ! empty($l['closed_at'])): ?><div class="tln-small">Closed <?= fdate($l['closed_at']) ?></div><?php endif; ?></td>
        <td><?= fdate($l['next_due']) ?></td>
        <?php for ($k = 1; $k <= 3; $k++): ?>
          <td><?php if (isset($l['installments'][$k])): $st = $l['installments'][$k]['state']; ?>
            <span class="tln-status <?= esc($st) ?>"><span class="dot"></span><?= $stateLabel[$st] ?></span><div class="tln-small"><?= fdate($l['installments'][$k]['due'], 'M d') ?></div>
          <?php else: ?>—<?php endif; ?></td>
        <?php endfor; ?>
        <td class="text-end text-nowrap">
          <?php if ($l['approval_status'] === 'approved'): ?>
            <a class="btn btn-tln-ghost btn-sm" href="<?= site_url('loans/schedule/' . $l['id']) ?>" title="Amortization schedule"><i class="bi bi-table"></i></a>
            <a class="btn btn-tln-ghost btn-sm" href="<?= site_url('loans/voucher/' . $l['id']) ?>" title="Disbursement voucher"><i class="bi bi-receipt"></i></a>
          <?php endif; ?>
          <?php if (can('approve.manager') && $l['approval_status'] === 'approved' && $l['penalty_due'] > 0): ?>
            <button class="btn btn-tln-ghost btn-sm js-waive" data-bs-toggle="modal" data-bs-target="#waive" data-loan="<?= (int) $l['id'] ?>" data-due="<?= esc(number_format($l['penalty_due'], 2, '.', '')) ?>" data-label="<?= esc($l['loan_no'] . ' · penalty owed ' . peso($l['penalty_due'])) ?>">Waive penalty</button>
          <?php endif; ?>
          <?php if (can('loans.restructure') && in_array($l['status'], ['pastdue', 'current', 'restructured'], true)): ?>
            <button class="btn btn-tln-ghost btn-sm js-restructure" data-bs-toggle="modal" data-bs-target="#restructure" data-loan="<?= (int) $l['id'] ?>" data-label="<?= esc($l['loan_no'] . ' · ' . $l['loan_type'] . ' · balance ' . peso($l['outstanding'])) ?>">Restructure</button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (! $loans): ?><div class="tln-empty">No loans yet for this client.</div><?php endif; ?>

  <div class="tln-section-head"><h2>Collection &amp; reminders</h2><span class="hint">Generated automatically — no one has to press send</span></div>
  <table class="table-ledger w-100">
    <thead><tr><th>Loan</th><th>1st reminder (<?= $cfg->reminder1Days ?> days)</th><th>2nd reminder (<?= $cfg->reminder2Days ?> days)</th><th>Notification for unpaid loan</th><th>Payment status</th><th>Collection status</th><th>Classification</th></tr></thead>
    <tbody>
    <?php foreach ($open as $l): $d = $l['days_overdue']; ?>
      <tr>
        <td><?= esc($l['loan_type']) ?></td>
        <td><?= $d >= $cfg->reminder1Days ? '<span class="tln-status pastdue"><span class="dot"></span>Sent ' . fdate($l['r1_date'], 'M d') . '</span>' : 'Auto on ' . fdate($l['r1_date'], 'M d') ?></td>
        <td><?= $d >= $cfg->reminder2Days ? '<span class="tln-status pastdue"><span class="dot"></span>Sent ' . fdate($l['r2_date'], 'M d') . '</span>' : 'Auto on ' . fdate($l['r2_date'], 'M d') ?></td>
        <td><?= $d >= $cfg->collection2Days ? '2nd collection letter' : ($d >= $cfg->collection1Days ? '1st collection letter' : 'Auto on ' . fdate($l['c1_date'], 'M d')) ?></td>
        <td><?= esc($l['payment_status']) ?></td>
        <td><?= esc($l['collection_status']) ?></td>
        <td><?= class_badge(classification($l['status'])) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (! $open): ?><tr><td colspan="7" class="tln-small">No open loans.</td></tr><?php endif; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if ($tab === 'ledger'): ?>
  <div class="tln-section-head"><h2>Ledger accounts</h2>
    <?php if (can('ledger.add') && $missingLedgers): ?><button class="btn btn-tln-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addLedger"><i class="bi bi-plus-lg"></i> Add ledger account</button><?php endif; ?>
  </div>
  <table class="table-ledger w-100">
    <thead><tr><th>Ledger</th><th>Account no.</th><th>Opened</th><th class="num">Balance</th></tr></thead>
    <tbody>
    <?php foreach ($ledgers as $g): ?>
      <tr><td><?= esc($cfg->ledgerTypes[$g['ledger_type']]['label']) ?></td><td class="font-mono"><?= esc($g['account_no']) ?></td><td><?= fdate($g['opened_at']) ?></td><td class="num"><?= peso($g['balance']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (! $ledgers): ?><div class="tln-empty">No ledger accounts yet. Add Savings, Capital Build Up, Mortuary or Insurance.</div><?php endif; ?>
  <div class="tln-small mt-3">Deposits, withdrawals (cash or check) and payments are recorded under <a href="<?= site_url('transactions') ?>">Daily transactions</a>; balances update automatically.</div>
<?php endif; ?>

<?php if ($tab === 'monitor'): ?>
  <div class="tln-section-head"><h2>Transaction monitor</h2>
    <div class="d-flex gap-2">
      <?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $k => $label): ?>
        <a class="tln-filter-chip <?= $period === $k ? 'active' : '' ?>" href="<?= $base ?>?tab=monitor&period=<?= $k ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="row g-3">
    <div class="col-lg-5">
      <table class="table-ledger w-100">
        <thead><tr><th><?= $period === 'weekly' ? 'Week starting' : ($period === 'monthly' ? 'Month' : 'Date') ?></th><th class="num">Entries</th><th class="num">In</th><th class="num">Out</th></tr></thead>
        <tbody>
        <?php foreach ($buckets as $b): $label = $period === 'monthly' ? date('F Y', strtotime($b['k'] . '-01')) : fdate($b['k']); ?>
          <tr class="tln-bucket <?= $selected === $b['k'] ? 'active' : '' ?>" onclick="location.href='<?= $base ?>?tab=monitor&period=<?= $period ?>&bucket=<?= esc($b['k']) ?>'">
            <td><a class="tln-row-link" href="<?= $base ?>?tab=monitor&period=<?= $period ?>&bucket=<?= esc($b['k']) ?>"><?= esc($label) ?></a></td>
            <td class="num"><?= (int) $b['n'] ?></td><td class="num"><?= peso($b['money_in']) ?></td><td class="num"><?= peso($b['money_out']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (! $buckets): ?><div class="tln-empty">No transactions yet.</div><?php endif; ?>
    </div>
    <div class="col-lg-7">
      <?php if ($selected): ?>
        <table class="table-ledger w-100">
          <thead><tr><th>Date</th><th>Txn no.</th><th>Type</th><th>Method</th><th class="num">Amount</th></tr></thead>
          <tbody>
          <?php foreach ($bucketTx as $t): ?>
            <tr><td><?= fdate($t['txn_date'], 'M d') ?></td><td class="font-mono"><?= esc($t['txn_no']) ?></td><td><?= esc(txn_label($t['category'])) ?><?= $t['loan_no'] ? '<div class="tln-small">' . esc($t['loan_no']) . '</div>' : '' ?></td><td><?= esc(ucfirst((string) $t['method'])) ?></td><td class="num"><?= $t['direction'] === 'out' ? '−' : '' ?><?= peso($t['amount']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="tln-empty">Click a row to see the transactions inside it.</div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($tab === 'payments'): ?>
  <div class="tln-section-head"><h2>Payment history</h2><span class="hint">Every entry for this client, newest first</span></div>
  <table class="table-ledger w-100">
    <thead><tr><th>Date</th><th>Txn no.</th><th>Type</th><th>Loan</th><th>Reference</th><th>Method</th><th class="num">Amount</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($history as $t): ?>
      <tr style="<?= $t['status'] === 'cancelled' ? 'opacity:.55;text-decoration:line-through;' : '' ?>">
        <td><?= fdate($t['txn_date']) ?></td><td class="font-mono"><?= esc($t['txn_no']) ?></td><td><?= esc(txn_label($t['category'])) ?></td>
        <td><?= esc($t['loan_no'] ?: '—') ?></td><td><?= esc($t['reference'] ?: '—') ?></td><td><?= esc(ucfirst((string) $t['method']) ?: '—') ?></td>
        <td class="num"><?= $t['direction'] === 'out' ? '−' : '' ?><?= peso($t['amount']) ?></td>
        <td style="text-decoration:none;"><?= $t['status'] === 'cancelled' ? 'Cancelled' : ($t['cancel_requested'] ? 'Cancel pending' : 'Posted') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (! $history): ?><div class="tln-empty">No transactions yet.</div><?php endif; ?>
<?php endif; ?>

<?php if ($tab === 'docs'): ?>
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="tln-section-head"><h2>Supporting documents</h2></div>
      <div class="tln-panel px-3">
        <?php foreach ($docs as $d): ?>
          <div class="tln-doc-row">
            <div class="tln-doc-ic"><i class="bi bi-file-earmark-text"></i></div>
            <div><a class="tln-row-link" href="<?= site_url('documents/file/' . $d['id']) ?>"><?= esc($d['title'] ?: $d['original_name']) ?></a>
              <div class="tln-small"><?= esc($cfg->docTypes[$d['doc_type']] ?? $d['doc_type']) ?><?= $d['loan_no'] ? ' · ' . esc($d['loan_no']) : '' ?></div></div>
            <div class="tln-doc-meta"><?= fdate($d['uploaded_at']) ?> · <?= esc($d['uploaded_by']) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (! $docs): ?><div class="tln-empty">No documents uploaded.</div><?php endif; ?>
      </div>
    </div>
    <?php if (can('documents.upload')): ?>
    <div class="col-lg-5">
      <div class="tln-section-head"><h2>Upload a document</h2></div>
      <form class="tln-panel p-3" method="post" action="<?= site_url('documents/upload') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
        <div class="mb-3"><label class="tln-form-label">Document type</label><select class="form-select tln-form-control" name="doc_type"><?php foreach ($cfg->docTypes as $k => $v): ?><option value="<?= $k ?>"><?= esc($v) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="tln-form-label">Attach to loan (optional)</label><select class="form-select tln-form-control" name="loan_id"><option value="">— none —</option><?php foreach ($loans as $l): ?><option value="<?= (int) $l['id'] ?>"><?= esc($l['loan_no'] . ' · ' . $l['loan_type']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="tln-form-label">Title (optional)</label><input class="form-control tln-form-control" name="title"></div>
        <div class="mb-3"><label class="tln-form-label">File (<?= esc(implode(', ', $cfg->allowedUploadExt)) ?> · max <?= $cfg->maxUploadKb ?> KB)</label><input class="form-control tln-form-control" type="file" name="document" required></div>
        <button class="btn btn-tln-primary" type="submit">Upload</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($tab === 'reminders'): ?>
  <div class="tln-section-head"><h2>Reminders for this client</h2><span class="hint">Automated</span></div>
  <div class="tln-panel">
    <?php foreach ($notes as $n): ?>
      <div class="tln-reminder-row"><div class="tln-reminder-flag <?= $n['kind'] === 'due_soon' ? 'soon' : 'over' ?>"></div>
        <div><div class="tln-reminder-text"><?= esc($n['message']) ?></div><div class="tln-reminder-meta"><?= fdate($n['sent_at'], 'M d, Y g:i A') ?><?= $n['is_auto'] ? ' · automatic' : ' · added by ' . esc($n['created_by']) ?></div></div></div>
    <?php endforeach; ?>
    <?php if (! $notes): ?><div class="tln-empty">No reminders have been triggered for this client.</div><?php endif; ?>
  </div>
<?php endif; ?>
</div>

<?php if (can('loans.create')): ?>
<div class="modal fade" id="newLoan" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" method="post" action="<?= site_url('loans/apply') ?>" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
  <div class="modal-header"><h5 class="modal-title">New loan application — <?= esc($client['name']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-md-6"><label class="tln-form-label">Type of loan</label><select class="form-select tln-form-control" id="lnType" name="loan_type" required>
      <?php foreach ($cfg->loanProducts as $name => $pr): ?><option value="<?= esc($name) ?>" data-rate="<?= $pr['rate'] ?>" data-min="<?= $pr['min'] ?>" data-max="<?= $pr['max'] ?>" data-term="<?= $pr['max_term'] ?>" data-fee="<?= $pr['service_fee'] ?>" data-ins="<?= $pr['insurance'] ?>" data-cbu="<?= $pr['cbu'] ?>" data-col="<?= $pr['collateral'] ? 1 : 0 ?>"><?= esc($name) ?></option><?php endforeach; ?>
    </select><div class="tln-small mt-1" id="lnLimits"></div></div>
    <div class="col-md-6"><label class="tln-form-label">Principal (₱)</label><input class="form-control tln-form-control font-mono" id="lnPrincipal" name="principal" type="number" min="1" step="0.01" required></div>
    <div class="col-md-4"><label class="tln-form-label">Interest (% flat for the term)</label><input class="form-control tln-form-control font-mono" id="lnRate" type="number" value="<?= $cfg->defaultInterestRate ?>" readonly title="Fixed by the loan product"></div>
    <div class="col-md-4"><label class="tln-form-label">Term (months)</label><input class="form-control tln-form-control font-mono" id="lnTerm" name="term_months" type="number" min="1" max="120" value="12" required></div>
    <div class="col-md-4"><label class="tln-form-label">Release by</label><select class="form-select tln-form-control" name="release_method"><option value="cash">Cash</option><option value="check">Check</option></select></div>
    <div class="col-md-6"><label class="tln-form-label">Purpose of the loan</label><input class="form-control tln-form-control" name="purpose" maxlength="255" placeholder="e.g. Palay planting inputs, tuition, medical" required></div>
    <div class="col-md-6" id="lnColWrap"><label class="tln-form-label">Collateral <span class="tln-small" id="lnColReq"></span></label><input class="form-control tln-form-control" name="collateral" maxlength="255" placeholder="Describe the collateral, if any"></div>
    <div class="col-md-6"><label class="tln-form-label">Release date</label><input class="form-control tln-form-control" type="date" id="lnRelease" name="release_date" value="<?= date('Y-m-d') ?>" required></div>
    <div class="col-md-6"><label class="tln-form-label">1st due date</label><input class="form-control tln-form-control" type="date" id="lnFirst" name="first_due_date" value="<?= add_months(date('Y-m-d'), 1) ?>" required></div>
    <div class="col-12"><div class="tln-panel p-3"><div class="row text-center g-2">
      <div class="col-6 col-md-3"><div class="tln-fig-label">Interest</div><div class="tln-fig-value" id="pvInterest">₱0.00</div></div>
      <div class="col-6 col-md-3"><div class="tln-fig-label">Total payable</div><div class="tln-fig-value" id="pvTotal">₱0.00</div></div>
      <div class="col-6 col-md-3"><div class="tln-fig-label">Monthly due</div><div class="tln-fig-value" id="pvMonthly">₱0.00</div></div>
      <div class="col-6 col-md-3"><div class="tln-fig-label">Net proceeds</div><div class="tln-fig-value" id="pvNet">₱0.00</div></div>
    </div><div class="tln-small text-center mt-2" id="pvDed">Service fee, insurance and Capital Build Up are deducted at release.</div>
    <div class="tln-small text-center mt-1">The application is credit-checked automatically and every loan needs a <strong>Manager</strong>'s approval.</div></div></div>
    <div class="col-12"><label class="tln-form-label">Application for Loan (signed form)</label><input class="form-control tln-form-control" type="file" name="application_file"></div>
    <div class="col-12"><label class="tln-form-label">Remarks</label><input class="form-control tln-form-control" name="remarks"></div>
  </div></div>
  <div class="modal-footer"><button type="button" class="btn btn-tln-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn btn-tln-primary" type="submit">File application</button></div>
</form></div></div>
<?php endif; ?>

<?php if (can('ledger.add') && $missingLedgers): ?>
<div class="modal fade" id="addLedger" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= site_url('accounts/ledger') ?>">
  <?= csrf_field() ?><input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
  <div class="modal-header"><h5 class="modal-title">Add ledger account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="tln-form-label">Ledger</label><select class="form-select tln-form-control" name="ledger_type"><?php foreach ($missingLedgers as $k): ?><option value="<?= $k ?>"><?= esc($cfg->ledgerTypes[$k]['label']) ?></option><?php endforeach; ?></select></div>
    <div class="mb-1"><label class="tln-form-label">Opening balance (₱, optional — recorded as a cash deposit)</label><input class="form-control tln-form-control font-mono" type="number" step="0.01" min="0" name="opening" value="0"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-tln-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn btn-tln-primary" type="submit">Add ledger</button></div>
</form></div></div>
<?php endif; ?>

<?php if (can('approve.manager')): ?>
<div class="modal fade" id="waive" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= site_url('loans/waive') ?>">
  <?= csrf_field() ?><input type="hidden" name="loan_id" id="wvLoan">
  <div class="modal-header"><h5 class="modal-title">Waive late-payment penalty</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="tln-small mb-3" id="wvLabel"></div>
    <div class="row g-3">
      <div class="col-6"><label class="tln-form-label">Amount to waive (₱)</label><input class="form-control tln-form-control font-mono" type="number" step="0.01" min="0.01" name="amount" id="wvAmount" required></div>
      <div class="col-12"><label class="tln-form-label">Reason</label><textarea class="form-control tln-form-control" name="reason" rows="2" required></textarea></div>
    </div>
    <div class="tln-small mt-2">Recorded in the audit trail with your name. The newest penalty charges are waived first.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-tln-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn btn-tln-primary" type="submit">Waive penalty</button></div>
</form></div></div>
<?php endif; ?>

<?php if (can('loans.restructure')): ?>
<div class="modal fade" id="restructure" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= site_url('loans/restructure') ?>">
  <?= csrf_field() ?><input type="hidden" name="loan_id" id="rsLoan">
  <div class="modal-header"><h5 class="modal-title">Restructure loan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="tln-small mb-3" id="rsLabel"></div>
    <div class="row g-3">
      <div class="col-6"><label class="tln-form-label">New term (months)</label><input class="form-control tln-form-control" type="number" name="new_term" min="1" max="120" value="12" required></div>
      <div class="col-6"><label class="tln-form-label">1st due date of new schedule</label><input class="form-control tln-form-control" type="date" name="first_due" value="<?= add_months(date('Y-m-d'), 1) ?>" required></div>
      <div class="col-12"><label class="tln-form-label">Reason</label><textarea class="form-control tln-form-control" name="reason" rows="2" required></textarea></div>
    </div>
    <div class="tln-small mt-2">The remaining balance is re-spread over the new term once a Manager approves.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-tln-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn btn-tln-primary" type="submit">Send for approval</button></div>
</form></div></div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
  var peso = function (n) { return '₱' + n.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };
  var p = document.getElementById('lnPrincipal'), r = document.getElementById('lnRate'), t = document.getElementById('lnTerm'), ty = document.getElementById('lnType');
  function calc() {
    if (!p || !ty) return;
    var o = ty.options[ty.selectedIndex].dataset;
    var pr = parseFloat(p.value) || 0, rate = parseFloat(o.rate) || 0, term = parseInt(t.value, 10) || 1;
    var interest = Math.round(pr * rate) / 100, total = pr + interest;
    var fee = Math.round(pr * parseFloat(o.fee)) / 100, ins = Math.round(pr * parseFloat(o.ins)) / 100, cbu = Math.round(pr * parseFloat(o.cbu)) / 100;
    r.value = rate;
    document.getElementById('pvInterest').textContent = peso(interest);
    document.getElementById('pvTotal').textContent = peso(total);
    document.getElementById('pvMonthly').textContent = peso(Math.round(total / term * 100) / 100);
    document.getElementById('pvNet').textContent = peso(pr - fee - ins - cbu);
    document.getElementById('pvDed').textContent = 'Deducted at release: service fee ' + peso(fee) + ' · insurance ' + peso(ins) + ' · Capital Build Up ' + peso(cbu);
    document.getElementById('lnLimits').textContent = 'Allowed: ' + peso(parseFloat(o.min)) + ' – ' + peso(parseFloat(o.max)) + ', up to ' + o.term + ' months.';
    document.getElementById('lnColReq').textContent = o.col === '1' ? '(required for this loan)' : '(optional)';
    p.min = o.min; p.max = o.max; t.max = o.term;
  }
  [p, t, ty].forEach(function (el) { if (el) el.addEventListener('input', calc); });
  calc();
  var rel = document.getElementById('lnRelease'), first = document.getElementById('lnFirst');
  if (rel) rel.addEventListener('change', function () {
    var d = new Date(rel.value + 'T00:00:00'); if (isNaN(d)) return; d.setMonth(d.getMonth() + 1);
    first.value = d.toISOString().slice(0, 10);
  });
  document.querySelectorAll('.js-waive').forEach(function (b) {
    b.addEventListener('click', function () {
      document.getElementById('wvLoan').value = b.dataset.loan; document.getElementById('wvLabel').textContent = b.dataset.label;
      var a = document.getElementById('wvAmount'); a.value = b.dataset.due; a.max = b.dataset.due;
    });
  });
  document.querySelectorAll('.js-restructure').forEach(function (b) {
    b.addEventListener('click', function () { document.getElementById('rsLoan').value = b.dataset.loan; document.getElementById('rsLabel').textContent = b.dataset.label; });
  });
})();
</script>
<?= $this->endSection() ?>
