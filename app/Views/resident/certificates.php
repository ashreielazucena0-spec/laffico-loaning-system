<?= $this->extend('resident/layout') ?>
<?= $this->section('content') ?>
<?php /** @var array $certificates */ ?>

<div class="mb-3">
    <h2 class="fw-bold mb-1">Released Certificates</h2>
    <p class="text-muted mb-0">Certificates the barangay has already released to you.</p>
</div>

<div class="content-card">
    <?php if (empty($certificates)) : ?>
        <div class="empty-state"><i class="bi bi-file-earmark-check"></i><p>No released certificates yet.</p></div>
    <?php else : ?>
        <?php foreach ($certificates as $c) : ?>
            <div class="r-item">
                <div>
                    <div class="r-title"><?= esc($c['type']) ?></div>
                    <div class="r-meta">Purpose: <?= esc($c['purpose'] ?? '') ?></div>
                    <div class="r-meta">Certificate No. <?= esc($c['id']) ?><?= ! empty($receipts[$c['request_id'] ?? '']) ? ' · Receipt No. ' . esc($receipts[$c['request_id']]) : '' ?></div>
                </div>
                <div class="r-side">
                    <div><?= esc(date('M d, Y', strtotime($c['date'] ?? 'now'))) ?></div>
                    <div class="r-meta">₱<?= esc(number_format((float) ($c['amount'] ?? 0), 2)) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
