<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="mb-4">
    <h2 class="fw-bold mb-1">Backup &amp; Restore</h2>
    <p class="text-muted">Export the whole system as a JSON file, or restore from a previous export.</p>
</div>

<div class="row g-3">

    <div class="col-lg-4">
        <div class="content-card h-100">
            <div class="card-header-custom"><h5><i class="bi bi-download me-1"></i> Export Backup</h5></div>
            <p class="text-muted">Downloads every record — residents, households, certificates, and everything else — as a single JSON file.</p>
            <a href="<?= base_url('barangay/backup/export') ?>" class="btn btn-navy w-100">Download Backup</a>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="content-card h-100">
            <div class="card-header-custom"><h5><i class="bi bi-upload me-1"></i> Restore Backup</h5></div>
            <p class="text-muted">Choose a previously exported JSON file to overwrite the current data.</p>
            <form method="post" action="<?= base_url('barangay/backup/import') ?>" enctype="multipart/form-data">
                <input type="file" name="backupFile" accept="application/json" class="form-control mb-3" required>
                <button type="submit" class="btn btn-outline-navy w-100" onclick="return confirm('This will replace all current data. Continue?')">Restore</button>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="content-card h-100">
            <div class="card-header-custom"><h5><i class="bi bi-arrow-counterclockwise me-1"></i> Reset to Demo Data</h5></div>
            <p class="text-muted">Wipes everything and reloads the original sample dataset.</p>
            <form method="post" action="<?= base_url('barangay/backup/reset') ?>">
                <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('This will erase all current data and reload the demo dataset. Continue?')">Reset Data</button>
            </form>
        </div>
    </div>

</div>

<?= $this->endSection() ?>
