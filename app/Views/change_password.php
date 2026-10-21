<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="tln-topbar"><div><div class="tln-page-title">Change password</div><div class="tln-page-sub">At least 8 characters with a letter and a number</div></div></div>
<div class="tln-content" style="max-width:480px;">
  <form method="post" action="<?= site_url('account/password') ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="tln-form-label">Current password</label><input class="form-control tln-form-control" type="password" name="current_password" required autocomplete="current-password"></div>
    <div class="mb-3"><label class="tln-form-label">New password</label><input class="form-control tln-form-control" type="password" name="new_password" required minlength="8" autocomplete="new-password"></div>
    <div class="mb-4"><label class="tln-form-label">Confirm new password</label><input class="form-control tln-form-control" type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></div>
    <button class="btn btn-tln-primary" type="submit">Update password</button>
  </form>
</div>
<?= $this->endSection() ?>
