<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? esc($page_title) . ' — ' : '' ?>LAFFICO-MPC Client Portal</title>
<link rel="shortcut icon" href="<?= base_url('favicon.ico') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= base_url('css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('css/talaan.css') ?>">
<style>@media print{.pt-bar,.no-print{display:none!important}}</style>
<script>try{var t=localStorage.getItem('tlnTheme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
</head>
<body>
<div class="pt-bar">
  <div class="brand-logo"><img src="<?= base_url('images/logo.jpg') ?>" alt="LAFFICO-MPC logo"></div>
  <div><div class="name">LAFFICO-MPC</div><div class="sub">Client portal</div></div>
  <?php if (session()->get('client_id')): ?>
  <div class="right">
    <span class="d-none d-sm-inline"><?= esc((string) session()->get('client_name')) ?></span>
    <a class="btn btn-sm btn-tln-side" href="<?= site_url('portal/password') ?>"><i class="bi bi-key"></i> Password</a>
    <form method="post" action="<?= site_url('portal/logout') ?>" class="m-0"><?= csrf_field() ?><button class="btn btn-sm btn-tln-side" type="submit"><i class="bi bi-box-arrow-right"></i> Sign out</button></form>
  </div>
  <?php endif; ?>
</div>
<div class="pt-wrap">
  <?php foreach (['success' => 'success', 'error' => 'danger', 'info' => 'info'] as $k => $cls): $m = session()->getFlashdata($k); if ($m): ?>
    <div class="alert alert-<?= $cls ?> alert-dismissible fade show" role="alert"><?= esc($m) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
  <?php endif; endforeach; ?>
  <?= $this->renderSection('content') ?>
</div>
<script src="<?= base_url('js/bootstrap.bundle.min.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
