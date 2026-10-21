<?= $this->extend('resident/layout') ?>
<?= $this->section('content') ?>
<?php /** @var array $types */ ?>

<div class="mb-3">
    <h2 class="fw-bold mb-1">Request a Certificate</h2>
    <p class="text-muted mb-0">Your request goes straight to the barangay secretary. You can follow it under My Requests.</p>
</div>

<div class="r-grid">

    <div class="content-card">
        <?php if (empty($types)) : ?>
            <div class="empty-state"><i class="bi bi-file-earmark-x"></i><p>No certificates are available to request right now.</p></div>
        <?php else : ?>
            <form method="post" action="<?= base_url('resident/request') ?>">
                <div class="mb-3">
                    <label class="form-label" for="certType">Certificate</label>
                    <select class="form-select" name="type" id="certType" required>
                        <option value="">Choose a certificate…</option>
                        <?php foreach ($types as $t) : ?>
                            <option value="<?= esc($t['name']) ?>"
                                    data-fee="<?= esc($t['fee']) ?>"
                                    data-desc="<?= esc($t['description'] ?? '') ?>"
                                    <?= old('type') === $t['name'] ? 'selected' : '' ?>><?= esc($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text" id="certInfo"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="purpose">Purpose</label>
                    <input class="form-control" name="purpose" id="purpose" maxlength="255" required
                           placeholder="e.g. Employment, School enrollment, Hospital" value="<?= esc(old('purpose') ?? '') ?>">
                </div>

                <button class="btn btn-navy" type="submit"><i class="bi bi-send me-1"></i> Send Request</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="content-card">
        <div class="card-header-custom"><h5>Available Certificates</h5></div>
        <?php foreach ($types as $t) : ?>
            <div class="r-type-card">
                <div class="d-flex justify-content-between gap-2">
                    <strong><?= esc($t['name']) ?></strong>
                    <span>₱<?= esc(number_format((float) $t['fee'], 2)) ?></span>
                </div>
                <?php if (! empty($t['description'])) : ?><small class="text-muted"><?= esc($t['description']) ?></small><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <small class="text-muted">The fee is paid at the Barangay Hall when you claim your certificate.</small>
    </div>

</div>

<?= $this->endSection() ?>
