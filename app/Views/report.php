<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $r = $report; $csv = site_url('reports/view/' . $r['slug']) . '?month=' . $r['month'] . '&format=csv'; ?>
<div class="tln-topbar">
  <div><div class="tln-page-title"><?= esc($r['title']) ?></div><div class="tln-page-sub"><?= esc($r['subtitle'] ?? '') ?> · <?= esc($r['period']) ?></div></div>
  <div class="tln-topbar-right no-print d-flex gap-2 align-items-center">
    <form class="tln-month-form" method="get"><input class="form-control tln-form-control" type="month" name="month" value="<?= esc($r['month']) ?>" onchange="this.form.submit()"></form>
    <a class="btn btn-tln-ghost btn-sm" href="<?= $csv ?>"><i class="bi bi-filetype-csv"></i> CSV</a>
    <button class="btn btn-tln-ghost btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    <a class="btn btn-tln-ghost btn-sm" href="<?= site_url('reports') ?>">All reports</a>
  </div>
</div>
<div class="tln-content">
  <?php foreach ($r['alerts'] as [$lvl, $text]): ?><div class="alert alert-<?= esc($lvl) ?> py-2" style="font-size:13px;"><?= esc($text) ?></div><?php endforeach; ?>

  <?php if ($r['tiles']): ?>
  <div class="row g-3 mb-4">
    <?php foreach ($r['tiles'] as $t): ?><div class="col-6 col-lg-4"><div class="tln-tile <?= ($t[2] ?? '') === 'warn' ? 'warn' : '' ?>"><div class="tln-tile-label"><?= esc($t[0]) ?></div><div class="tln-tile-value"><?= esc($t[1]) ?></div></div></div><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php foreach ($r['sections'] as $s): $cols = count($s['columns']); ?>
    <?php if (! empty($s['heading'])): ?><div class="tln-section-head"><h2><?= esc($s['heading']) ?></h2></div><?php endif; ?>
    <table class="table-ledger tln-report-table w-100 mb-4">
      <thead><tr><?php foreach ($s['columns'] as $i => $c): ?><th class="<?= $i > 0 && $cols > 2 || ($cols === 2 && $i === 1) ? 'num' : '' ?>"><?= esc($c) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
        <?php foreach ($s['rows'] as $ri => $row): ?>
          <tr class="<?= esc($s['row_class'][$ri] ?? '') ?>"><?php foreach ($row as $i => $cell): ?><td class="<?= $i > 0 && preg_match('/^[\(−-]?₱|^\d+(\.\d+)?%?$/u', (string) $cell) ? 'num' : '' ?>"><?= esc($cell) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        <?php if (! $s['rows']): ?><tr><td colspan="<?= $cols ?>" class="tln-small">Nothing to show for this period.</td></tr><?php endif; ?>
      </tbody>
      <?php if (! empty($s['totals'])): ?><tfoot><tr><?php foreach ($s['totals'] as $i => $cell): ?><td class="<?= $i > 0 && $cell !== '' ? 'num' : '' ?>"><?= esc($cell) ?></td><?php endforeach; ?></tr></tfoot><?php endif; ?>
    </table>
  <?php endforeach; ?>
  <?php foreach ($r['notes'] as $n): ?><div class="tln-small mb-1"><?= esc($n) ?></div><?php endforeach; ?>
</div>
<?= $this->endSection() ?>
