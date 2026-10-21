<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="tln-topbar">
  <div>
    <?php $h = (int) date('G'); $first = trim(explode(' ', (string) session()->get('full_name'))[0]); ?>
    <div class="tln-page-title"><?= $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening') ?><?= $first !== '' ? ', ' . esc($first) : '' ?></div>
    <div class="tln-page-sub">Where every client account stands, right now</div>
  </div>
  <div class="tln-topbar-right">
    <div class="tln-date-chip"><i class="bi bi-calendar3"></i> <?= date('D, M d Y') ?> · <?= date('g:i A') ?></div>
  </div>
</div>

<div class="tln-content">
  <!-- Tiles: past due → restructured → current -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?= site_url('accounts?status=pastdue') ?>"><div class="tln-tile tile-pastdue"><i class="bi bi-exclamation-triangle tln-tile-ic"></i><div class="tln-tile-label">Past due accounts</div><div class="tln-tile-value"><?= count($groups['pastdue']) ?></div><div class="tln-tile-delta">Classified Silver</div></div></a></div>
    <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?= site_url('accounts?status=restructured') ?>"><div class="tln-tile tile-restructured"><i class="bi bi-arrow-repeat tln-tile-ic"></i><div class="tln-tile-label">Restructured accounts</div><div class="tln-tile-value"><?= count($groups['restructured']) ?></div><div class="tln-tile-delta">Classified Bronze</div></div></a></div>
    <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?= site_url('accounts?status=current') ?>"><div class="tln-tile tile-current"><i class="bi bi-check2-circle tln-tile-ic"></i><div class="tln-tile-label">Current accounts</div><div class="tln-tile-value"><?= count($groups['current']) ?></div><div class="tln-tile-delta">Classified Gold</div></div></a></div>
    <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?= site_url('transactions') ?>"><div class="tln-tile"><i class="bi bi-cash-coin tln-tile-ic"></i><div class="tln-tile-label">Recorded today</div><div class="tln-tile-value"><?= peso($recorded['total']) ?></div><div class="tln-tile-delta"><?= (int) $recorded['n'] ?> entr<?= (int) $recorded['n'] === 1 ? 'y' : 'ies' ?></div></div></a></div>
  </div>

  <?php if ($pending && (can('approve.manager'))): ?>
    <div class="alert alert-warning py-2"><i class="bi bi-hourglass-split"></i> <?= $pending ?> request(s) waiting for approval. <a href="<?= site_url('audit-trail?tab=approvals') ?>">Review</a></div>
  <?php endif; ?>

  <!-- Names under each status, in order: past due, restructured, current -->
  <div class="row g-3 mb-4">
    <?php foreach (['pastdue' => 'Past due', 'restructured' => 'Restructured', 'current' => 'Current'] as $key => $label): ?>
    <div class="col-lg-4">
      <div class="tln-status-panel <?= $key ?> h-100">
        <h3><span><?= $label ?> accounts</span><span class="font-mono"><?= count($groups[$key]) ?></span></h3>
        <ul>
          <?php foreach ($groups[$key] as $c): ?>
            <li>
              <a class="tln-row-link" href="<?= site_url('accounts/detail/' . $c['acct_no']) ?>"><?= esc($c['name']) ?></a>
              <span class="tln-small font-mono"><?= $key === 'pastdue' ? $c['days_overdue'] . ' d' : peso($c['outstanding']) ?></span>
            </li>
          <?php endforeach; ?>
          <?php if (! $groups[$key]): ?><li class="tln-small">No accounts</li><?php endif; ?>
        </ul>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="tln-panel">
        <div class="tln-panel-head"><h3>Latest automated reminders</h3><a class="tln-small" href="<?= site_url('reminders') ?>">View all</a></div>
        <?php foreach ($reminders as $r): ?>
          <div class="tln-reminder-row">
            <div class="tln-reminder-flag <?= $r['kind'] === 'due_soon' ? 'soon' : 'over' ?>"></div>
            <div>
              <div class="tln-reminder-text"><?= esc($r['message']) ?></div>
              <div class="tln-reminder-meta"><?= fdate($r['sent_at'], 'M d, Y g:i A') ?> · sent automatically</div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (! $reminders): ?><div class="tln-empty">No reminders have fired yet.</div><?php endif; ?>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="tln-panel">
        <div class="tln-panel-head"><h3>Recent activity</h3><a class="tln-small" href="<?= site_url('audit-trail?tab=activity') ?>">Audit trail</a></div>
        <?php foreach ($activity as $a): ?>
          <div class="tln-activity-row">
            <div><span class="who"><?= esc($a['user_name']) ?></span> — <?= esc($a['action']) ?></div>
            <div class="when"><?= fdate($a['created_at'], 'M d g:i A') ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
