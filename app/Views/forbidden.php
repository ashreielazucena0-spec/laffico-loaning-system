<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="tln-topbar"><div><div class="tln-page-title">Access denied</div><div class="tln-page-sub">Your role does not include this action</div></div></div>
<div class="tln-content"><div class="tln-empty">You are signed in as <strong><?= esc(role_label(session()->get('role'))) ?></strong>. Ask an Administrator if you need access to this page.<br><a class="btn btn-tln-ghost mt-3" href="<?= site_url('dashboard') ?>">Back to dashboard</a></div></div>
<?= $this->endSection() ?>
