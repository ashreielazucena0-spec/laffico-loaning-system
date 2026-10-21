<?php
/**
 * @var array{
 *     title: string,
 *     icon: string,
 *     idPrefix: string,
 *     fields: array<string,array{label:string,type:string,required?:bool,options?:array<int|string,string>}>,
 *     columns: array<int,string>
 * } $schema
 * @var array<int,array<string,mixed>> $rows
 * @var string                         $module
 * @var string                         $search
 * @var array<string,string>           $residentLookup
 */
?>
<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<?php
$residentFields = ['resident', 'head', 'complainant', 'respondent'];
$fmtVal = function ($key, $val) use ($residentFields, $residentLookup) {
    if (in_array($key, $residentFields, true) && isset($residentLookup[$val])) {
        return $residentLookup[$val];
    }
    return $val;
};
$statusFields = ['status'];
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi <?= esc($schema['icon']) ?> me-2"></i><?= esc($schema['title']) ?></h2>
        <p class="text-muted mb-0"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?></p>
    </div>
    <button class="btn btn-navy" data-bs-toggle="modal" data-bs-target="#termModal">
        <i class="bi bi-arrow-repeat me-1"></i> Start New Term
    </button>
</div>

<div class="content-card">

    <form method="get" class="mb-3">
        <div class="input-group search-box">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" name="q" value="<?= esc($search) ?>" placeholder="Search <?= esc(strtolower($schema['title'])) ?>...">
            <?php if ($search !== '') : ?>
                <a href="<?= base_url('barangay/' . $module) ?>" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($rows)) : ?>
        <div class="empty-state">
            <i class="bi <?= esc($schema['icon']) ?>"></i>
            <p>No records found.</p>
        </div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <?php foreach ($schema['columns'] as $col) : ?>
                            <th><?= esc($schema['fields'][$col]['label'] ?? ucfirst($col)) ?></th>
                        <?php endforeach; ?>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><span class="text-muted small"><?= esc($row['id']) ?></span></td>
                        <?php foreach ($schema['columns'] as $col) :
                            $val = $fmtVal($col, $row[$col] ?? ''); ?>
                            <td>
                                <?php if (in_array($col, $statusFields, true) && $val !== '') : ?>
                                    <span class="badge-status status-<?= esc(strtolower((string) $val)) ?>"><?= esc($val) ?></span>
                                <?php elseif ($col === 'name' && trim((string) $val) === '') : ?>
                                    <span class="text-muted fst-italic">Vacant — enter name</span>
                                <?php elseif ($col === 'amount' && $val !== '') : ?>
                                    ₱<?= esc(number_format((float) $val, 2)) ?>
                                <?php else : ?>
                                    <?= esc((string) $val) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?= esc(md5($row['id'])) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<!-- START NEW TERM -->
<div class="modal fade" id="termModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= base_url('barangay/officials/new-term') ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Start a New Term</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">All positions are already listed. This sets the new term on every position at once.
                    Afterwards, use the pencil on each row to enter the new official's name and contact number.</p>
                    <label class="form-label">New Term</label>
                    <input class="form-control" name="term" placeholder="e.g. 2026-2029" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy">Apply Term</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT + DELETE MODALS (one pair per row) -->
<?php foreach ($rows as $row) : ?>

    <div class="modal fade" id="editModal<?= esc(md5($row['id'])) ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="<?= base_url('barangay/' . $module . '/update/' . $row['id']) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit <?= esc(rtrim($schema['title'], 's')) ?> — <?= esc($row['id']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php foreach ($schema['fields'] as $key => $field) : ?>
                            <div class="mb-3">
                                <label class="form-label"><?= esc($field['label']) ?></label>
                                <?php if ($field['type'] === 'select') : ?>
                                    <select class="form-select" name="<?= esc($key) ?>">
                                        <option value="">Select...</option>
                                        <?php foreach ($field['options'] as $optVal => $optLabel) :
                                            $ov = is_int($optVal) ? $optLabel : $optVal; ?>
                                            <option value="<?= esc($ov) ?>" <?= (string) ($row[$key] ?? '') === (string) $ov ? 'selected' : '' ?>><?= esc($optLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($field['type'] === 'textarea') : ?>
                                    <textarea class="form-control" name="<?= esc($key) ?>" rows="3"><?= esc($row[$key] ?? '') ?></textarea>
                                <?php elseif ($field['type'] === 'password') : ?>
                                    <input class="form-control" type="password" name="<?= esc($key) ?>" placeholder="Leave blank to keep current password">
                                <?php else : ?>
                                    <input class="form-control" type="<?= esc($field['type']) ?>" name="<?= esc($key) ?>" value="<?= esc($row[$key] ?? '') ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-navy">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php endforeach; ?>

<?= $this->endSection() ?>