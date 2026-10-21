<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
/** @var array{residents: int, households: int, requests: int, cases: int} $stats */
/** @var array<int, array<string, mixed>> $recentRequests */
/** @var array<int, array<string, mixed>> $recentLogs */
/** @var array<int, array<string, mixed>> $upcomingMeetings */
/** @var callable(string): string $residentNameFn */
?>

<div class="mb-4">
    <h2 class="fw-bold mb-1">Barangay Dashboard</h2>
    <p class="text-muted">Overview of Barangay Sumadat records and activities.</p>
</div>

<div class="row g-3 mb-4">

    <div class="col-xl-3 col-md-6">
        <a href="<?= base_url('barangay/residents') ?>" class="stat-card-link">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-people"></i></div>
                <div>
                    <small>Total of Registered Residents</small>
                    <h3><?= number_format($stats['residents']) ?></h3>
                </div>
            </div>
        </a>
    </div>

    <div class="col-xl-3 col-md-6">
        <a href="<?= base_url('barangay/households') ?>" class="stat-card-link">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-house"></i></div>
                <div>
                    <small>Total of Registered Households</small>
                    <h3><?= number_format($stats['households']) ?></h3>
                </div>
            </div>
        </a>
    </div>

    <div class="col-xl-3 col-md-6">
        <a href="<?= base_url('barangay/requests') ?>" class="stat-card-link">
            <div class="stat-card">
                <div class="stat-icon gold"><i class="bi bi-card-checklist"></i></div>
                <div>
                    <small>Pending Requests</small>
                    <h3><?= number_format($stats['requests']) ?></h3>
                </div>
            </div>
        </a>
    </div>

    <div class="col-xl-3 col-md-6">
        <a href="<?= base_url('barangay/cases') ?>" class="stat-card-link">
            <div class="stat-card">
                <div class="stat-icon red"><i class="bi bi-shield-exclamation"></i></div>
                <div>
                    <small>Blotter Cases</small>
                    <h3><?= number_format($stats['cases']) ?></h3>
                </div>
            </div>
        </a>
    </div>

</div>

<div class="row g-3">

    <div class="col-lg-7">
        <div class="content-card mb-3">
            <div class="card-header-custom">
                <h5>Recent Requests</h5>
                <a href="<?= base_url('barangay/requests') ?>" class="btn btn-sm btn-outline-secondary">View all</a>
            </div>

            <?php if (empty($recentRequests)) : ?>
                <p class="text-muted mb-0">No requests yet.</p>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Resident</th><th>Type</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentRequests as $r) : ?>
                            <tr>
                                <td><?= esc(($residentNameFn)($r['resident'] ?? '')) ?></td>
                                <td><?= esc($r['type'] ?? '') ?></td>
                                <td><?= esc($r['date'] ?? '') ?></td>
                                <td><span class="badge-status status-<?= esc(strtolower($r['status'] ?? '')) ?>"><?= esc($r['status'] ?? '') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="content-card">
            <div class="card-header-custom">
                <h5>Recent Activity</h5>
                <a href="<?= base_url('barangay/backup') ?>" class="btn btn-sm btn-outline-secondary">System</a>
            </div>
            <?php if (empty($recentLogs)) : ?>
                <p class="text-muted mb-0">No activity yet.</p>
            <?php else : ?>
                <ul class="list-unstyled mb-0 activity-list">
                    <?php foreach ($recentLogs as $log) : ?>
                        <li>
                            <i class="bi bi-clock-history"></i>
                            <span><strong><?= esc($log['user'] ?? '') ?></strong> — <?= esc($log['action'] ?? '') ?></span>
                            <small class="d-block text-muted"><?= esc($log['time'] ?? '') ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="content-card mb-3">
            <div class="card-header-custom">
                <h5>Released Certificates</h5>
                <a href="<?= base_url('barangay/certificates') ?>" class="btn btn-sm btn-outline-secondary">View all</a>
            </div>

            <?php if (empty($recentCertificates)) : ?>
                <p class="text-muted mb-0">No certificates issued yet.</p>
            <?php else : ?>
                <?php foreach ($recentCertificates as $c) : ?>
                    <div class="event-card mb-2">
                        <div class="event-date">
                            <strong><?= esc(date('d', strtotime($c['date'] ?? 'now'))) ?></strong>
                            <small><?= esc(strtoupper(date('M', strtotime($c['date'] ?? 'now')))) ?></small>
                        </div>
                        <div>
                            <strong><?= esc($c['type'] ?? '') ?></strong>
                            <small><?= esc(($residentNameFn)($c['resident'] ?? '')) ?> · <?= esc($c['purpose'] ?? '') ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="content-card mb-3">
            <div class="card-header-custom">
                <h5>Upcoming Meetings</h5>
                <a href="<?= base_url('barangay/meetings') ?>" class="btn btn-sm btn-outline-secondary">View all</a>
            </div>

            <?php if (empty($upcomingMeetings)) : ?>
                <p class="text-muted mb-0">No meetings scheduled.</p>
            <?php else : ?>
                <?php foreach ($upcomingMeetings as $m) :
                    $ts = strtotime($m['date'] ?? 'now'); ?>
                    <div class="event-card mb-2">
                        <div class="event-date">
                            <strong><?= esc(date('d', $ts)) ?></strong>
                            <small><?= esc(strtoupper(date('M', $ts))) ?></small>
                        </div>
                        <div>
                            <strong><?= esc($m['title'] ?? '') ?></strong>
                            <small><?= esc($m['time'] ?? '') ?> · <?= esc($m['venue'] ?? '') ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

<?= $this->endSection() ?>
