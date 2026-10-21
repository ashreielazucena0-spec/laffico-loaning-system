<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $cfg = config('Loaning'); ?>
<div class="tln-topbar"><div><div class="tln-page-title">Users</div><div class="tln-page-sub">Who can sign in, and what each role may do</div></div></div>
<div class="tln-content"><div class="row g-4">
  <div class="col-lg-8">
    <table class="table-ledger w-100">
      <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr><td><?= esc($u['full_name']) ?></td><td class="font-mono"><?= esc($u['username']) ?></td><td><?= esc(role_label($u['role'])) ?></td>
          <td><?= $u['last_login'] ? fdate($u['last_login'], 'M d, Y g:i A') : 'Never' ?></td>
          <td><?= $u['is_active'] ? 'Active' : 'Deactivated' ?><?= $u['locked_until'] && strtotime($u['locked_until']) > time() ? ' · locked' : '' ?></td>
          <td class="text-end">
            <?php if ((int) $u['id'] !== (int) session()->get('user_id')): ?>
            <form class="d-inline-flex gap-1" method="post" action="<?= site_url('users/role/' . $u['id']) ?>"><?= csrf_field() ?>
              <select class="form-select form-select-sm" style="width:120px;" name="role"><?php foreach ($cfg->roles as $k => $v): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= esc($v) ?></option><?php endforeach; ?></select>
              <button class="btn btn-tln-ghost btn-sm">Change role</button></form>
            <form class="d-inline" method="post" action="<?= site_url('users/toggle/' . $u['id']) ?>"><?= csrf_field() ?><button class="btn btn-tln-ghost btn-sm"><?= $u['is_active'] ? 'Deactivate' : 'Reactivate' ?></button></form>
            <form class="d-inline" method="post" action="<?= site_url('users/delete/' . $u['id']) ?>" onsubmit="return confirm('Delete <?= esc($u['full_name'], 'js') ?>? This cannot be undone. To keep the account but block sign-in, use Deactivate instead.');"><?= csrf_field() ?><button class="btn btn-tln-ghost btn-sm text-danger">Delete</button></form>
            <?php else: ?><span class="tln-small">This is you</span><?php endif; ?>
            <form class="d-inline-flex gap-1" method="post" action="<?= site_url('users/reset/' . $u['id']) ?>"><?= csrf_field() ?><input class="form-control form-control-sm" style="width:130px;" type="text" name="password" placeholder="New password" autocomplete="off"><button class="btn btn-tln-ghost btn-sm">Reset</button></form>
          </td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="col-lg-4">
    <div class="tln-section-head"><h2>Add user</h2></div>
    <form class="tln-panel p-3" method="post" action="<?= site_url('users/add') ?>" autocomplete="off">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="tln-form-label">Full name</label><input class="form-control tln-form-control" name="full_name" value="<?= old('full_name') ?>" required></div>
      <div class="mb-3"><label class="tln-form-label">Username</label><input class="form-control tln-form-control" name="username" value="<?= old('username') ?>" required></div>
      <div class="mb-3"><label class="tln-form-label">Role</label><select class="form-select tln-form-control" name="role"><?php foreach ($cfg->roles as $k => $v): ?><option value="<?= $k ?>"><?= esc($v) ?></option><?php endforeach; ?></select></div>
      <div class="mb-3"><label class="tln-form-label">Password</label><input class="form-control tln-form-control" type="text" name="password" required autocomplete="off"></div>
      <button class="btn btn-tln-primary w-100">Create user</button>
    </form>
    <div class="tln-small mt-3"><strong>Employee</strong> adds clients, files loans and sends due-date reminders. <strong>Manager</strong> does the same and also approves loans. <strong>Administrator</strong> manages user accounts (add, change role, deactivate, delete).</div>
  </div>
</div></div>
<?= $this->endSection() ?>
