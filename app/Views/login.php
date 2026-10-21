<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in — LAFFICO-MPC Loaning System</title>
<link rel="shortcut icon" href="<?= base_url('favicon.ico') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= base_url('css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('css/talaan.css') ?>">
<script>try{var t=localStorage.getItem('tlnTheme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
</head>
<body>
<div class="tln-auth">
  <section class="tln-auth-hero">
    <div class="d-flex align-items-center gap-3">
      <div class="brand-logo"><img src="<?= base_url('images/logo.jpg') ?>" alt="LAFFICO-MPC logo"></div>
      <div><div style="font-weight:700;font-size:16px;letter-spacing:-.01em;">LAFFICO-MPC</div><div style="font-size:12px;color:#7F92A6;">Loaning system</div></div>
    </div>
    <div>
      <h1>Every loan, every client, always up to date.</h1>
      <p>The cooperative’s loaning system — accounts, collections, reminders and reports in one place.</p>
      <ul class="tln-auth-points"><li><i class="bi bi-people"></i>Client accounts and loan ledgers</li><li><i class="bi bi-bell"></i>Automatic due-date reminders</li><li><i class="bi bi-shield-check"></i>Full audit trail on every change</li></ul>
    </div>
    <div class="tln-auth-hero-foot">&copy; <?= date('Y') ?> LAFFICO-MPC. All rights reserved.</div>
  </section>

  <section class="tln-auth-panel">
    <div class="tln-auth-card">
      <div class="tln-auth-mobile-brand">
        <div class="brand-logo"><img src="<?= base_url('images/logo.jpg') ?>" alt="LAFFICO-MPC logo"></div>
        <div style="font-weight:700;font-size:16px;">LAFFICO-MPC</div>
      </div>
      <h2>Welcome back</h2>
      <p class="lead-sm">Sign in to continue to the staff workspace.</p>
      <?php foreach (['error' => 'danger', 'info' => 'info', 'success' => 'success'] as $k => $c): if ($m = session()->getFlashdata($k)): ?>
        <div class="alert alert-<?= $c ?> py-2" role="alert"><?= esc($m) ?></div>
      <?php endif; endforeach; ?>
      <form method="post" action="<?= site_url('login') ?>" autocomplete="off" id="authForm">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="tln-form-label" for="username">Username</label>
          <input class="form-control tln-form-control" id="username" name="username" value="<?= esc((string) session()->getFlashdata('old_user')) ?>" required autofocus>
        </div>
        <div class="mb-4">
          <label class="tln-form-label" for="password">Password</label>
          <div class="tln-pw-wrap">
            <input class="form-control tln-form-control" type="password" id="password" name="password" required autocomplete="current-password">
            <button class="tln-pw-toggle" type="button" id="pwToggle" aria-label="Show password"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <button class="btn btn-tln-primary w-100" type="submit" id="authBtn">Sign in</button>
      </form>
      <div class="tln-small text-center mt-4">Authorized personnel only. Five wrong attempts lock the account for 15 minutes.</div>
    </div>
  </section>
</div>
<script>
(function () {
  var p = document.getElementById('password'), b = document.getElementById('pwToggle');
  b.addEventListener('click', function () {
    var show = p.type === 'password'; p.type = show ? 'text' : 'password';
    b.firstElementChild.className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
    b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
  document.getElementById('authForm').addEventListener('submit', function () {
    var btn = document.getElementById('authBtn'); btn.disabled = true; btn.textContent = 'Signing in…';
  });
})();
</script>
</body>
</html>
