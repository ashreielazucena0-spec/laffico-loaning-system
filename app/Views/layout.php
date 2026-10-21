<?php
helper('loaning');
$active = $active ?? '';
$nav    = nav_counts();
$name   = (string) session()->get('full_name');
$init   = strtoupper(mb_substr($name !== '' ? $name : 'U', 0, 1));
$items  = [
    ['dashboard',    'dashboard',     'Dashboard',          'bi-grid',            null,                 null],
    ['accounts',     'accounts',      'Client accounts',    'bi-people',          null,                 'clients'],
    ['transactions', 'transactions',  'Daily transactions', 'bi-cash-coin',       'txn.record',         null],
    ['reminders',    'reminders',     'Reminders',          'bi-bell',            'reminders.manage',   'reminders'],
    ['accounting',   'accounting',    'Accounting',         'bi-journal-text',    'accounting.manage',  null],
    ['reports',      'reports',       'Reports',            'bi-file-earmark-bar-graph', 'reports.view', null],
    ['audit',        'audit-trail',   'Audit trail',        'bi-shield-check',    null,                 'approvals'],
    ['users',        'users',         'Users',              'bi-person-gear',     'users.manage',       null],
    ['backups',      'backups',       'Backups',            'bi-database',        'backups.manage',     null],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? esc($page_title) . ' — ' : '' ?>LAFFICO-MPC Loaning System</title>
<link rel="shortcut icon" href="<?= base_url('favicon.ico') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= base_url('css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('css/talaan.css') ?>">
<script>try{var t=localStorage.getItem('tlnTheme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
</head>
<body>
<div class="tln-mobile-bar">
  <button class="tln-hamburger" type="button" id="tlnMenu" aria-label="Open menu"><i class="bi bi-list"></i></button>
  <span class="tln-brand-name-mobile font-display">LAFFICO-MPC</span>
</div>
<div class="tln-sidebar-overlay" id="tlnOverlay"></div>
<div class="tln-shell">
  <aside class="tln-sidebar" id="tlnSidebar">
    <div class="tln-brand">
      <div class="d-flex align-items-center gap-2">
        <div class="brand-logo"><img src="<?= base_url('images/logo.jpg') ?>" alt="LAFFICO-MPC logo"></div>
        <div class="tln-brand-name">LAFFICO-MPC</div>
      </div>
      <div class="tln-brand-sub">Loaning system</div>
    </div>
    <div class="tln-nav-label">Menu</div>
    <?php foreach ($items as [$key, $path, $label, $icon, $perm, $count]):
        if ($perm !== null && ! can($perm)) { continue; }
        $n = $count ? (int) ($nav[$count] ?? 0) : 0;
        if ($count === 'approvals' && ! can('approve.manager')) { $n = 0; } ?>
      <a class="tln-nav-link <?= $active === $key || $active === $path ? 'active' : '' ?>" href="<?= site_url($path) ?>">
        <i class="bi <?= $icon ?>"></i><span><?= esc($label) ?></span>
        <?php if ($n > 0 && $count !== 'clients'): ?><span class="tln-badge"><?= $n ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
    <div class="tln-sidebar-foot">
      <div class="d-flex align-items-center gap-2 mb-3">
        <div class="tln-avatar"><?= esc($init) ?></div>
        <div style="min-width:0"><div class="tln-user-name text-truncate"><?= esc($name) ?></div><div class="tln-user-role"><?= esc(role_label(session()->get('role'))) ?></div></div>
        <button type="button" class="tln-theme-btn" id="tlnTheme" aria-label="Toggle dark mode" title="Toggle dark mode"><i class="bi bi-moon-stars"></i></button>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-tln-side flex-fill" href="<?= site_url('account/password') ?>"><i class="bi bi-key"></i> Password</a>
        <form method="post" action="<?= site_url('logout') ?>" class="m-0 flex-fill"><?= csrf_field() ?><button class="btn btn-sm btn-tln-side w-100" type="submit"><i class="bi bi-box-arrow-right"></i> Sign out</button></form>
      </div>
    </div>
  </aside>
  <main class="tln-main">
    <?php foreach (['success' => 'success', 'error' => 'danger', 'info' => 'info'] as $k => $cls): $m = session()->getFlashdata($k); if ($m): ?>
      <div class="alert alert-<?= $cls ?> alert-dismissible fade show tln-flash" role="alert"><?= esc($m) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    <?php endif; endforeach; ?>
    <?= $this->renderSection('content') ?>
  </main>
</div>
<script src="<?= base_url('js/bootstrap.bundle.min.js') ?>"></script>
<script>
(function () {
  var s = document.getElementById('tlnSidebar'), o = document.getElementById('tlnOverlay'), b = document.getElementById('tlnMenu');
  function t(open) { s.classList.toggle('is-open', open); o.classList.toggle('is-open', open); }
  if (b) { b.addEventListener('click', function () { t(!s.classList.contains('is-open')); }); }
  if (o) { o.addEventListener('click', function () { t(false); }); }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { t(false); } });

  // light / dark toggle (remembered per browser)
  var tb = document.getElementById('tlnTheme'), root = document.documentElement;
  function paint() { if (tb) { tb.firstElementChild.className = 'bi ' + (root.getAttribute('data-theme') === 'dark' ? 'bi-sun' : 'bi-moon-stars'); } }
  if (tb) { tb.addEventListener('click', function () {
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('tlnTheme', next); } catch (e) {}
    paint();
  }); paint(); }

  // wide tables scroll sideways on small screens instead of breaking the layout
  document.querySelectorAll('.table-ledger').forEach(function (t) {
    if (!t.parentElement.classList.contains('tln-table-scroll')) {
      var w = document.createElement('div'); w.className = 'tln-table-scroll';
      t.parentNode.insertBefore(w, t); w.appendChild(t);
    }
  });
  // flash messages fade out on their own
  document.querySelectorAll('.tln-flash.alert-success, .tln-flash.alert-info').forEach(function (a) {
    setTimeout(function () { if (window.bootstrap) { bootstrap.Alert.getOrCreateInstance(a).close(); } }, 6000);
  });
})();
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
