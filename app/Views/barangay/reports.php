<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports</h2>
    <p class="text-muted">A quick summary across every module.</p>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($counts as $key => $c) : ?>
        <div class="col-xl-3 col-md-6">
            <a href="<?= base_url('barangay/' . $key) ?>" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="bi <?= esc($c['icon']) ?>"></i></div>
                    <div>
                        <small><?= esc($c['title']) ?></small>
                        <h3><?= number_format($c['count']) ?></h3>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="content-card">
    <div class="card-header-custom"><h5>Registered Residents by Purok</h5></div>
    <?php if (empty($purokCounts)) : ?>
        <p class="text-muted mb-0">No resident records yet.</p>
    <?php else :
        $max = max($purokCounts) ?: 1; ?>
        <?php foreach ($purokCounts as $purok => $count) : ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                    <span><?= esc($purok) ?></span>
                    <strong><?= (int) $count ?></strong>
                </div>
                <div class="progress" style="height:8px">
                    <div class="progress-bar bg-navy" style="width: <?= round($count / $max * 100) ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
