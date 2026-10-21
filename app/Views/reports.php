<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="tln-topbar"><div><div class="tln-page-title">Reports</div><div class="tln-page-sub">Generated live from the database — pick a month inside each report</div></div></div>
<div class="tln-content">
  <?php foreach ($groups as $group => $items): ?>
    <div class="tln-section-head"><h2><?= esc($group) ?></h2></div>
    <div class="row g-3 mb-4">
      <?php foreach ($items as $i): ?>
        <div class="col-md-6 col-xl-4"><a class="text-decoration-none text-reset" href="<?= site_url('reports/view/' . $i['slug']) ?>"><div class="tln-report-card h-100"><h4><?= esc($i['title']) ?></h4><p><?= esc($i['desc']) ?></p></div></a></div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
<?= $this->endSection() ?>
