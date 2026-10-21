<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $cfg = config('Loaning'); $kinds = \App\Libraries\ReminderEngine::KINDS; ?>
<div class="tln-topbar">
  <div><div class="tln-page-title">Reminders</div><div class="tln-page-sub">Sent automatically: heads-up <?= $cfg->reminderDueSoonDays ?> days before due · 1st reminder <?= $cfg->reminder1Days ?> d · 2nd <?= $cfg->reminder2Days ?> d · collection letters <?= $cfg->collection1Days ?> / <?= $cfg->collection2Days ?> d overdue</div></div>
  <?php if (can('reminders.manage')): ?><div class="tln-topbar-right"><button class="btn btn-tln-ghost" data-bs-toggle="modal" data-bs-target="#addReminder"><i class="bi bi-plus-lg"></i> Add manual reminder</button></div><?php endif; ?>
</div>
<div class="tln-content">
  <div class="tln-section-head"><h2>Accounts with an open balance</h2><span class="hint">Past due first</span></div>
  <table class="table-ledger w-100 mb-4">
    <thead><tr><th>Client</th><th>Loan</th><th class="num">Principal</th><th class="num">Monthly due</th><th class="num">Interest</th><th class="num">Outstanding</th><th>Due</th><th>1st reminder</th><th>2nd reminder</th><th>Unpaid-loan notice</th></tr></thead>
    <tbody>
    <?php foreach ($loans as $l):
      $s = $sent[$l['id']] ?? []; $d = $l['next_due'];
      $cell = static function ($kind, $sched) use ($s, $d) { return isset($s[$kind][$d]) ? '<span class="tln-status pastdue"><span class="dot"></span>Sent ' . fdate($s[$kind][$d], 'M d') . '</span>' : '<span class="tln-small">Auto ' . fdate($sched, 'M d') . '</span>'; };
      $notice = isset($s['collection_2'][$d]) ? '2nd collection letter' : (isset($s['collection_1'][$d]) ? '1st collection letter' : (isset($s['due_soon'][$d]) ? 'Due-soon notice sent' : '—'));
    ?>
      <tr class="row-<?= esc($l['status']) ?>">
        <td><a class="tln-row-link" href="<?= site_url('accounts/detail/' . $l['acct_no'] . '?tab=reminders') ?>"><?= esc($l['client_name']) ?></a><div class="tln-small"><?= status_badge($l['status'], $l['days_overdue']) ?></div></td>
        <td><?= esc($l['loan_type']) ?></td>
        <td class="num"><?= peso($l['principal']) ?></td><td class="num"><?= peso($l['monthly_due']) ?></td><td class="num"><?= peso($l['interest_amount']) ?></td><td class="num"><?= peso($l['outstanding']) ?></td>
        <td><?= fdate($d) ?></td>
        <td><?= $cell('reminder_1', $l['r1_date']) ?></td><td><?= $cell('reminder_2', $l['r2_date']) ?></td><td><?= esc($notice) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="tln-section-head"><h2>Notification log</h2>
    <div class="d-flex gap-2 align-items-center">
      <a class="tln-filter-chip <?= $kind === '' ? 'active' : '' ?>" href="<?= site_url('reminders') ?>">All</a>
      <?php foreach ($kinds as $k => $label): ?><a class="tln-filter-chip <?= $kind === $k ? 'active' : '' ?>" href="<?= site_url('reminders?kind=' . $k) ?>"><?= esc($label) ?></a><?php endforeach; ?>
      <?php if (can('reminders.manage')): ?><form method="post" action="<?= site_url('reminders/ack-all') ?>" class="m-0"><?= csrf_field() ?><button class="btn btn-tln-ghost btn-sm">Mark all followed up</button></form><?php endif; ?>
    </div>
  </div>
  <div class="tln-panel">
    <?php foreach ($log as $n): ?>
      <div class="tln-reminder-item <?= $n['status'] === 'done' ? 'is-done' : '' ?>">
        <div class="tln-reminder-flag <?= $n['kind'] === 'due_soon' ? 'soon' : 'over' ?>"></div>
        <div class="tln-reminder-body"><div class="tln-reminder-text"><?= esc($n['message']) ?></div>
          <div class="tln-reminder-meta"><?= esc($kinds[$n['kind']] ?? $n['kind']) ?> · <?= fdate($n['sent_at'], 'M d, Y g:i A') ?> · <?= $n['is_auto'] ? 'sent automatically' : 'added by ' . esc($n['created_by']) ?></div></div>
        <?php if ($n['status'] !== 'done' && can('reminders.manage')): ?>
          <form class="tln-reminder-actions" method="post" action="<?= site_url('reminders/ack/' . $n['id']) ?>"><?= csrf_field() ?><button class="btn btn-tln-ghost btn-sm">Followed up</button></form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (! $log): ?><div class="tln-empty">No notifications.</div><?php endif; ?>
  </div>
</div>

<?php if (can('reminders.manage')): ?>
<div class="modal fade" id="addReminder" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= site_url('reminders/add') ?>">
  <?= csrf_field() ?>
  <div class="modal-header"><h5 class="modal-title">Add manual reminder</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="tln-form-label">Client &amp; loan</label><select class="form-select tln-form-control" name="loan_id" required><?php foreach ($loans as $l): ?><option value="<?= (int) $l['id'] ?>"><?= esc($l['client_name'] . ' — ' . $l['loan_type'] . ' (' . peso($l['outstanding']) . ')') ?></option><?php endforeach; ?></select></div>
    <div class="mb-1"><label class="tln-form-label">Message (optional)</label><input class="form-control tln-form-control" name="note" placeholder="e.g. Client promised to pay on Friday"></div>
    <div class="tln-small mt-2">Extra to the automatic schedule — the standard reminders need no action.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-tln-ghost" data-bs-dismiss="modal">Cancel</button><button class="btn btn-tln-primary" type="submit">Add</button></div>
</form></div></div>
<?php endif; ?>
<?= $this->endSection() ?>
