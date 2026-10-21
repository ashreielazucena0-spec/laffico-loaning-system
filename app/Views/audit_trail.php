<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $cfg = config('Loaning'); $stat = ['pending' => 'pending', 'approved' => 'current', 'rejected' => 'rejected']; ?>
<div class="tln-topbar"><div><div class="tln-page-title">Audit trail</div><div class="tln-page-sub">What was recorded, who approved it, and which documents support it</div></div></div>
<div class="tln-content">
  <ul class="nav tln-tabs mb-3">
    <?php foreach (['collections' => 'Loan collections', 'approvals' => 'Approvals', 'activity' => 'Activity log'] as $k => $label): ?>
      <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="<?= site_url('audit-trail?tab=' . $k) ?>"><?= $label ?></a></li>
    <?php endforeach; ?>
  </ul>

<?php if ($tab === 'collections'): ?>
  <table class="table-ledger w-100">
    <thead><tr><th>Client</th><th>Loan</th><th>Status</th><th class="num">Outstanding</th><th>Due date</th><th>Payment status</th><th>Detail</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($loans as $l): ?>
      <tr class="row-<?= esc($l['status']) ?>">
        <td><a class="tln-row-link" href="<?= site_url('accounts/detail/' . $l['acct_no']) ?>"><?= esc($l['client_name']) ?></a></td>
        <td><?= esc($l['loan_type']) ?><div class="tln-small font-mono"><?= esc($l['loan_no']) ?></div></td>
        <td><?= status_badge($l['status'], $l['days_overdue']) ?></td>
        <td class="num"><?= peso($l['outstanding']) ?></td>
        <td><?= fdate($l['next_due']) ?></td>
        <td><?= esc($l['payment_status']) ?></td><td><?= esc($l['stage_detail'] ?: '—') ?></td><td><?= esc($l['stage_action'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="tln-small mt-3">Detail / action follows the automated collection ladder: reminders → collection letters → <strong>Remedial / Small Claims</strong> at <?= $cfg->remedialDays ?> days overdue. Paid-off loans show <strong>Settled</strong>.</div>
<?php endif; ?>

<?php if ($tab === 'approvals'): ?>
  <table class="table-ledger w-100">
    <thead><tr><th>Request</th><th>Needs</th><th>Requested by</th><th>Supporting documents</th><th>Decision</th></tr></thead>
    <tbody>
    <?php foreach ($approvals as $a): ?>
      <tr>
        <td><?= esc($a['summary']) ?><div class="tln-small"><?= esc(ucwords(str_replace('_', ' ', $a['entity_type']))) ?> · <?= fdate($a['requested_at'], 'M d, Y g:i A') ?></div>
          <?php if ($a['entity_type'] === 'loan_release'): $pl = json_decode((string) ($a['payload'] ?? ''), true) ?: []; ?>
            <?php if (! empty($pl['purpose'])): ?><div class="tln-small">Purpose: <?= esc($pl['purpose']) ?></div><?php endif; ?>
            <?php if (! empty($pl['collateral'])): ?><div class="tln-small">Collateral: <?= esc($pl['collateral']) ?></div><?php endif; ?>
            <?php if (isset($pl['net_proceeds'])): ?><div class="tln-small">Net proceeds after deductions: <strong><?= peso($pl['net_proceeds']) ?></strong><?= isset($pl['dsr']) && $pl['dsr'] !== null ? ' · dues = ' . esc((string) $pl['dsr']) . '% of income' : '' ?></div><?php endif; ?>
            <?php foreach (($pl['flags'] ?? []) as $fl): ?><div class="tln-small" style="color:var(--rust);"><i class="bi bi-flag"></i> <?= esc($fl) ?></div><?php endforeach; ?>
            <?php if ($a['status'] === 'pending' && empty($pl['flags'])): ?><div class="tln-small" style="color:var(--ok-ink);"><i class="bi bi-check-circle"></i> Credit checks passed with no flags.</div><?php endif; ?>
          <?php endif; ?></td>
        <td>Manager</td>
        <td><?= esc($a['requested_by']) ?><div class="tln-small"><?= esc(role_label($a['requested_role'])) ?></div></td>
        <td>
          <?php foreach ($a['docs'] as $d): ?><div class="tln-small"><i class="bi bi-paperclip"></i> <a class="tln-row-link" href="<?= site_url('documents/file/' . $d['id']) ?>"><?= esc($cfg->docTypes[$d['doc_type']] ?? $d['doc_type']) ?></a></div><?php endforeach; ?>
          <?php if (! $a['docs']): ?><span class="tln-doc-warn"><?= $a['entity_type'] === 'loan_release' ? 'No Application for Loan attached' : 'None' ?></span><?php endif; ?>
        </td>
        <td>
          <?php if ($a['status'] === 'pending'): ?>
            <span class="tln-status pending"><span class="dot"></span>Pending</span>
            <?php if (! $a['blocker']): ?>
              <form method="post" action="<?= site_url('approvals/decide/' . $a['id']) ?>" class="mt-2">
                <?= csrf_field() ?>
                <input class="form-control tln-form-control mb-2" name="note" placeholder="Note (required to reject)">
                <button class="btn btn-tln-primary btn-sm" name="decision" value="approve">Approve</button>
                <button class="btn btn-tln-ghost btn-sm" name="decision" value="reject">Reject</button>
              </form>
            <?php else: ?><div class="tln-small mt-1"><?= esc($a['blocker']) ?></div><?php endif; ?>
          <?php else: ?>
            <span class="tln-status <?= $stat[$a['status']] ?>"><span class="dot"></span><?= ucfirst($a['status']) ?></span>
            <div class="tln-small">by <?= esc($a['decided_by']) ?> (<?= esc(role_label($a['decided_role'])) ?>) · <?= fdate($a['decided_at'], 'M d, Y') ?><?= $a['decision_note'] ? '<br>' . esc($a['decision_note']) : '' ?></div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (! $approvals): ?><div class="tln-empty">No approval requests yet.</div><?php endif; ?>
<?php endif; ?>

<?php if ($tab === 'activity'): ?>
  <form class="mb-3 d-flex gap-2" method="get"><input type="hidden" name="tab" value="activity"><input class="tln-search" name="q" value="<?= esc($q) ?>" placeholder="Search user, action or detail"><button class="btn btn-tln-ghost btn-sm">Search</button></form>
  <table class="table-ledger w-100">
    <thead><tr><th>When</th><th>User</th><th>Action</th><th>Detail</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $g): ?>
      <tr><td class="font-mono" style="white-space:nowrap;"><?= fdate($g['created_at'], 'M d, Y g:i A') ?></td><td><?= esc($g['user_name']) ?><div class="tln-small"><?= esc(role_label($g['role'])) ?></div></td><td><?= esc($g['action']) ?></td><td class="tln-small"><?= esc($g['detail']) ?></td><td class="tln-small font-mono"><?= esc($g['ip']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?= $this->endSection() ?>
