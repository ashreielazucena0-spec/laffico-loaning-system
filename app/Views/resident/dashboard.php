<?= $this->extend('resident/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * @var array $me
 * @var array $requests
 * @var array $ready
 * @var int   $pending
 * @var int   $released
 * @var array $meetings
 */
?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h2 class="fw-bold mb-1">Hello, <?= esc($me['first']) ?>!</h2>
        <p class="text-muted mb-0">Request barangay certificates and follow their progress here.</p>
    </div>
    <a href="<?= base_url('resident/request') ?>" class="btn btn-gold"><i class="bi bi-plus-lg me-1"></i> Request a Certificate</a>
</div>

<?php foreach ($ready as $r) : ?>
    <div class="r-ready-note">
        <i class="bi bi-check-circle-fill"></i>
        <div>
            <strong>Your <?= esc($r['type']) ?> is ready to be released.</strong><br>
            <span>Please claim it at the Barangay Hall (request <?= esc($r['id']) ?>).</span>
        </div>
    </div>
<?php endforeach; ?>

<div class="r-stats">
    <a href="<?= base_url('resident/requests') ?>" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-hourglass-split"></i></div>
            <div><small>Waiting for the secretary</small><h3><?= (int) $pending ?></h3></div>
        </div>
    </a>
    <a href="<?= base_url('resident/requests') ?>" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check2-circle"></i></div>
            <div><small>Ready to be released</small><h3><?= count($ready) ?></h3></div>
        </div>
    </a>
    <a href="<?= base_url('resident/certificates') ?>" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-file-earmark-check"></i></div>
            <div><small>Released certificates</small><h3><?= (int) $released ?></h3></div>
        </div>
    </a>
</div>

<div class="r-grid">

    <div class="content-card">
        <div class="card-header-custom">
            <h5>Recent Requests</h5>
            <a href="<?= base_url('resident/requests') ?>" class="btn btn-sm btn-outline-secondary">View all</a>
        </div>
        <?php if (empty($requests)) : ?>
            <div class="empty-state"><i class="bi bi-card-checklist"></i><p>You haven't requested a certificate yet.</p></div>
        <?php else : ?>
            <?php foreach ($requests as $r) : ?>
                <div class="r-item">
                    <div>
                        <div class="r-title"><?= esc($r['type']) ?></div>
                        <div class="r-meta"><?= esc($r['purpose']) ?> · <?= esc(date('M d, Y', strtotime($r['date'] ?? 'now'))) ?></div>
                    </div>
                    <div class="r-side">
                        <span class="badge-status status-<?= esc(strtolower($r['status'])) ?>"><?= esc($r['status'] === 'Ready' ? 'Ready to release' : $r['status']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="content-card">
        <div class="card-header-custom"><h5>Upcoming Meetings</h5></div>
        <?php if (empty($meetings)) : ?>
            <p class="text-muted mb-0">No meetings scheduled.</p>
        <?php else : ?>
            <?php foreach ($meetings as $m) : $ts = strtotime($m['date'] ?? 'now'); ?>
                <div class="event-card mb-2">
                    <div class="event-date">
                        <strong><?= esc(date('d', $ts)) ?></strong>
                        <small><?= esc(strtoupper(date('M', $ts))) ?></small>
                    </div>
                    <div>
                        <strong><?= esc($m['title']) ?></strong>
                        <small><?= esc(substr((string) $m['time'], 0, 5)) ?> · <?= esc($m['venue']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?= $this->endSection() ?>
