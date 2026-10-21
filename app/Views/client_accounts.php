<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
  $counts = ['all' => count($accounts), 'pastdue' => 0, 'restructured' => 0, 'current' => 0];
  foreach ($accounts as $a) { $counts[$a['status']]++; }
  $initial = (string) service('request')->getGet('status');
?>
<div class="tln-topbar">
  <div>
    <div class="tln-page-title">Client accounts</div>
    <div class="tln-page-sub">Sorted past due → restructured → current · <?= $counts['all'] ?> clients</div>
  </div>
  <?php if (can('clients.add')): ?>
  <div class="tln-topbar-right"><button class="btn btn-tln-primary" data-bs-toggle="modal" data-bs-target="#addClient"><i class="bi bi-plus-lg"></i> Add client account</button></div>
  <?php endif; ?>
</div>

<div class="tln-content">
  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <button class="tln-filter-chip" data-filter="all">All (<?= $counts['all'] ?>)</button>
    <button class="tln-filter-chip" data-filter="pastdue">Past due (<?= $counts['pastdue'] ?>)</button>
    <button class="tln-filter-chip" data-filter="restructured">Restructured (<?= $counts['restructured'] ?>)</button>
    <button class="tln-filter-chip" data-filter="current">Current (<?= $counts['current'] ?>)</button>
    <input type="search" id="acctSearch" class="tln-search ms-auto" placeholder="Search name or account no.">
  </div>

  <table class="table-ledger w-100">
    <thead><tr><th>Client</th><th>Account no.</th><th>Status</th><th>Classification</th><th class="num">Outstanding</th><th>Next due</th><th>Last payment</th><th>Loans</th></tr></thead>
    <tbody>
    <?php foreach ($accounts as $a): ?>
      <tr class="row-<?= esc($a['status']) ?> acct-row" data-status="<?= esc($a['status']) ?>" data-text="<?= esc(strtolower($a['name'] . ' ' . $a['acct_no'])) ?>">
        <td><a class="tln-row-link" href="<?= site_url('accounts/detail/' . $a['acct_no']) ?>"><?= esc($a['name']) ?></a></td>
        <td class="font-mono"><?= esc($a['acct_no']) ?></td>
        <td><?= status_badge($a['status'], $a['days_overdue']) ?></td>
        <td><?= class_badge($a['classification']) ?></td>
        <td class="num"><?= peso($a['outstanding']) ?></td>
        <td><?= fdate($a['next_due']) ?></td>
        <td><?= fdate($a['last_payment']) ?></td>
        <td><?= (int) $a['active_loans'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (! $accounts): ?><div class="tln-empty">No client accounts yet.</div><?php endif; ?>
  <div class="tln-small mt-3">Status and classification are computed automatically from each client's loans: <strong>Past due = Silver</strong>, <strong>Restructured = Bronze</strong>, <strong>Current = Gold</strong>. A client with several loans takes the worst status.</div>
</div>

<?php if (can('clients.add')): ?>
<div class="modal fade" id="addClient" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= site_url('accounts/add') ?>">
  <?= csrf_field() ?>
  <div class="modal-header"><h5 class="modal-title">Add client account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="tln-form-label">Full name (Last name, First name)</label><input class="form-control tln-form-control" name="name" required value="<?= old('name') ?>"></div>
    <div class="row g-3 mb-3">
      <div class="col-6"><label class="tln-form-label">Contact no.</label><input class="form-control tln-form-control" name="contact"></div>
      <div class="col-6"><label class="tln-form-label">E-mail (optional)</label><input class="form-control tln-form-control" type="email" name="email"></div>
    </div>
    <div class="mb-3"><label class="tln-form-label">Address</label><input class="form-control tln-form-control" name="address"></div>
    <div class="row g-3 mb-3">
      <div class="col-6"><label class="tln-form-label">Occupation / source of income</label><input class="form-control tln-form-control" name="occupation"></div>
      <div class="col-6"><label class="tln-form-label">Monthly income (₱)</label><input class="form-control tln-form-control font-mono" type="number" step="0.01" min="0" name="monthly_income"></div>
    </div>
    <div class="mb-1"><label class="tln-form-label">Co-maker (optional)</label><input class="form-control tln-form-control" name="co_maker"></div>
    <div class="tln-small mt-2">The account number is assigned automatically.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-tln-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn btn-tln-primary" type="submit">Create account</button></div>
</form></div></div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
  var rows = document.querySelectorAll('.acct-row'), chips = document.querySelectorAll('[data-filter]'), q = document.getElementById('acctSearch');
  var filter = <?= json_encode(in_array($initial, ['pastdue', 'restructured', 'current'], true) ? $initial : 'all') ?>;
  function apply() {
    var t = q.value.trim().toLowerCase();
    rows.forEach(function (r) { r.style.display = ((filter === 'all' || r.dataset.status === filter) && r.dataset.text.indexOf(t) !== -1) ? '' : 'none'; });
    chips.forEach(function (c) { c.classList.toggle('active', c.dataset.filter === filter); });
  }
  chips.forEach(function (c) { c.addEventListener('click', function () { filter = c.dataset.filter; apply(); }); });
  q.addEventListener('input', apply); apply();
  <?php if (session()->getFlashdata('error') && old('name')): ?>new bootstrap.Modal(document.getElementById('addClient')).show();<?php endif; ?>
})();
</script>
<?= $this->endSection() ?>
