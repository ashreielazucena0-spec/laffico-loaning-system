<?php
/**
 * @var array<int,array<string,string>> $logs
 */
?>
<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-clock-history me-2"></i>Activity Logs</h2>
        <p class="text-muted mb-0"><?= count($logs) ?> entr<?= count($logs) === 1 ? 'y' : 'ies' ?></p>
    </div>
</div>

<div class="content-card">
    <?php if (empty($logs)) : ?>
        <div class="empty-state">
            <i class="bi bi-clock-history"></i>
            <p>No activity recorded yet.</p>
        </div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>User</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td class="text-muted small"><?= esc($log['time'] ?? '') ?></td>
                        <td><strong><?= esc($log['user'] ?? '') ?></strong></td>
                        <td><?= esc($log['action'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
