<?= $this->extend('portal/layout') ?>
<?= $this->section('content') ?>
<div style="max-width:480px;">
  <h1 class="font-display" style="font-size:24px;font-weight:600;">Change password</h1>
  <p class="tln-small mb-3"><?= $forced ? 'For your security, choose your own password before you continue. ' : '' ?>At least 8 characters with a letter and a number.</p>
  <form method="post" action="<?= site_url('portal/password') ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="tln-form-label"><?= $forced ? 'Temporary password (from the cooperative)' : 'Current password' ?></label><input class="form-control tln-form-control" type="password" name="current_password" required autocomplete="current-password"></div>
    <div class="mb-3"><label class="tln-form-label">New password</label><input class="form-control tln-form-control" type="password" name="new_password" required minlength="8" autocomplete="new-password"></div>
    <div class="mb-4"><label class="tln-form-label">Confirm new password</label><input class="form-control tln-form-control" type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></div>
    <button class="btn btn-tln-primary" type="submit">Update password</button>
    <?php if (! $forced): ?><a class="btn btn-tln-ghost ms-2" href="<?= site_url('portal') ?>">Back</a><?php endif; ?>
  </form>
</div>
<?= $this->endSection() ?>
