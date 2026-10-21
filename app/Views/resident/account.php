<?= $this->extend('resident/layout') ?>
<?= $this->section('content') ?>
<?php /** @var array $me @var array|null $user */ ?>

<div class="mb-3">
    <h2 class="fw-bold mb-1">My Account</h2>
    <p class="text-muted mb-0">Your profile and password.</p>
</div>

<?php if (session()->get('tempPassword')) : ?>
    <div class="alert alert-warning"><i class="bi bi-shield-lock me-1"></i> You are still using the temporary password. Please choose your own below.</div>
<?php endif; ?>

<div class="r-grid-even">

    <div class="content-card">
        <div class="card-header-custom"><h5>Profile</h5></div>
        <dl class="r-dl mb-0">
            <dt>Name</dt>
            <dd><?= esc(trim($me['first'] . ' ' . ($me['middle'] ? $me['middle'] . ' ' : '') . $me['last'])) ?></dd>
            <dt>Username</dt>
            <dd><?= esc($user['username'] ?? '') ?></dd>
            <dt>Purok</dt>
            <dd><?= esc($me['purok'] ?: '—') ?></dd>
            <dt>Contact No.</dt>
            <dd><?= esc($me['contact'] ?: '—') ?></dd>
        </dl>
        <small class="text-muted">To correct any of these details, please visit the barangay office.</small>
    </div>

    <div class="content-card">
        <div class="card-header-custom"><h5>Change Password</h5></div>
        <form method="post" action="<?= base_url('resident/account') ?>" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Current password</label>
                <input class="form-control" type="password" name="current" required autocomplete="current-password">
            </div>
            <div class="mb-3">
                <label class="form-label">New password</label>
                <input class="form-control" type="password" name="new" required minlength="6" autocomplete="new-password">
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm new password</label>
                <input class="form-control" type="password" name="confirm" required minlength="6" autocomplete="new-password">
            </div>
            <button class="btn btn-navy" type="submit">Save Password</button>
        </form>
    </div>

</div>

<?= $this->endSection() ?>
