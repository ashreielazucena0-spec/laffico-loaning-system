<?= $this->extend('resident/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * @var array|null $household
 * @var array|null $head
 * @var array      $members
 */
?>

<div class="mb-3">
    <h2 class="fw-bold mb-1">My Household</h2>
    <p class="text-muted mb-0">The household you are registered in.</p>
</div>

<?php if (! $household) : ?>
    <div class="content-card">
        <div class="empty-state"><i class="bi bi-house"></i><p>You are not assigned to a household yet. Please visit the barangay office.</p></div>
    </div>
<?php else : ?>
    <div class="r-grid-even">

        <div class="content-card">
            <div class="card-header-custom"><h5>Household <?= esc($household['id']) ?></h5></div>
            <dl class="r-dl mb-0">
                <dt>Household Head</dt>
                <dd><?= esc($head ? trim($head['first'] . ' ' . $head['last']) : $household['head']) ?></dd>
                <dt>Purok</dt>
                <dd><?= esc($household['purok'] ?: '—') ?></dd>
                <dt>Street</dt>
                <dd><?= esc($household['street'] ?: '—') ?></dd>
                <?php if (($household['in_building'] ?? 'No') === 'Yes') : ?>
                    <dt>Building</dt>
                    <dd><?= esc($household['building'] ?: '—') ?></dd>
                    <dt>Floor</dt>
                    <dd><?= esc($household['floor'] ?: '—') ?></dd>
                <?php endif; ?>
                <dt>Number of Members</dt>
                <dd><?= (int) $household['members'] ?></dd>
            </dl>
        </div>

        <div class="content-card">
            <div class="card-header-custom"><h5>Registered Members</h5></div>
            <?php foreach ($members as $m) : ?>
                <div class="r-item">
                    <div>
                        <div class="r-title"><?= esc(trim($m['first'] . ' ' . $m['last'])) ?></div>
                        <div class="r-meta"><?= esc($m['purok'] ?: '') ?></div>
                    </div>
                    <?php if ($m['id'] === $household['head']) : ?><span class="badge-status status-ready">Head</span><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
<?php endif; ?>

<?= $this->endSection() ?>
