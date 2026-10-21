<?= $this->extend('resident/layout') ?>
<?= $this->section('content') ?>
<?php /** @var array $requests */
$notes = [
    'Pending'    => 'Waiting for the secretary to review it.',
    'Processing' => 'The secretary is preparing your certificate.',
    'Ready'      => 'Your certificate is printed and ready to be released — please claim it at the Barangay Hall.',
    'Released'   => 'Released to you. It is listed under Released Certificates.',
];
?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h2 class="fw-bold mb-1">My Requests</h2>
        <p class="text-muted mb-0"><?= count($requests) ?> request<?= count($requests) === 1 ? '' : 's' ?></p>
    </div>
    <a href="<?= base_url('resident/request') ?>" class="btn btn-gold"><i class="bi bi-plus-lg me-1"></i> New Request</a>
</div>

<div class="content-card">
    <?php if (empty($requests)) : ?>
        <div class="empty-state"><i class="bi bi-card-checklist"></i><p>No requests yet.</p></div>
    <?php else : ?>
        <?php foreach ($requests as $r) : ?>
            <div class="r-item">
                <div>
                    <div class="r-title"><?= esc($r['type']) ?> <span class="text-muted fw-normal small">· <?= esc($r['id']) ?></span></div>
                    <div class="r-meta">Purpose: <?= esc($r['purpose']) ?> · Requested <?= esc(date('M d, Y', strtotime($r['date'] ?? 'now'))) ?></div>
                    <div class="r-meta <?= $r['status'] === 'Ready' ? 'text-success fw-semibold' : '' ?>"><?= esc($notes[$r['status']] ?? '') ?></div>
                </div>
                <div class="r-side">
                    <span class="badge-status status-<?= esc(strtolower($r['status'])) ?>"><?= esc($r['status'] === 'Ready' ? 'Ready to release' : $r['status']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
