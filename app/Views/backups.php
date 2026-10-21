<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="tln-topbar"><div><div class="tln-page-title">Backups</div><div class="tln-page-sub">Snapshots of the whole database, stored in writable/backups</div></div>
  <div class="tln-topbar-right"><form class="d-flex gap-2" method="post" action="<?= site_url('backups/create') ?>"><?= csrf_field() ?><input class="tln-search" name="note" placeholder="Note (optional)"><button class="btn btn-tln-primary"><i class="bi bi-database-down"></i> Back up now</button></form></div></div>
<div class="tln-content">
  <table class="table-ledger w-100">
    <thead><tr><th>Backup file</th><th>Created</th><th class="num">Size</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($files as $f): ?>
      <tr><td class="font-mono"><?= esc($f['name']) ?></td><td><?= date('M d, Y g:i A', $f['time']) ?></td><td class="num"><?= number_format($f['size'] / 1024, 1) ?> KB</td>
        <td class="text-end">
          <a class="btn btn-tln-ghost btn-sm" href="<?= site_url('backups/download/' . $f['name']) ?>"><i class="bi bi-download"></i> Download</a>
          <form class="d-inline" method="post" action="<?= site_url('backups/restore') ?>" onsubmit="return confirm('Replace ALL current data with this backup? A safety copy of the current data is saved first, and you will be signed out if your account is not in the backup.');"><?= csrf_field() ?><input type="hidden" name="name" value="<?= esc($f['name']) ?>"><button class="btn btn-tln-ghost btn-sm">Restore</button></form>
          <form class="d-inline" method="post" action="<?= site_url('backups/delete') ?>" onsubmit="return confirm('Delete this backup file?');"><?= csrf_field() ?><input type="hidden" name="name" value="<?= esc($f['name']) ?>"><button class="btn btn-tln-ghost btn-sm">Delete</button></form>
        </td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (! $files): ?><div class="tln-empty">No backups yet. Click “Back up now”.</div><?php endif; ?>
  <div class="tln-small mt-3">Uploaded documents are stored in <code>writable/uploads</code> — copy that folder together with a downloaded backup for a complete off-site copy.</div>
</div>
<?= $this->endSection() ?>
