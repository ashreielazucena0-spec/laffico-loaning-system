<?= $this->extend('resident/layout') ?>
<?= $this->section('content') ?>
<?php /** @var array $documents */ ?>

<div class="mb-3">
    <h2 class="fw-bold mb-1">Ordinances &amp; Resolutions</h2>
    <p class="text-muted mb-0">Approved barangay ordinances and resolutions.</p>
</div>

<div class="content-card">
    <?php if (empty($documents)) : ?>
        <div class="empty-state"><i class="bi bi-journal-text"></i><p>No documents have been posted yet.</p></div>
    <?php else : ?>
        <?php foreach ($documents as $d) : ?>
            <div class="r-item">
                <div>
                    <div class="r-title"><?= esc($d['title']) ?></div>
                    <div class="r-meta"><?= esc($d['type']) ?> · No. <?= esc($d['number'] ?: '—') ?><?= ! empty($d['date']) ? ' · ' . esc(date('M d, Y', strtotime($d['date']))) : '' ?></div>
                </div>
                <div class="r-side">
                    <?php if (! empty($d['file_path'])) : ?>
                        <a href="<?= base_url('files/document/' . $d['id']) ?>" class="btn btn-sm btn-outline-navy"><i class="bi bi-download me-1"></i> Download</a>
                    <?php else : ?>
                        <span class="text-muted small">No file</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
