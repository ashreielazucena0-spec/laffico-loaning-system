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
    <button class="btn btn-navy" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg me-1"></i> Add New
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
                                <?php elseif ($col === 'file') : ?>
                                    <?php if (! empty($row['file_path'])) : ?>
                                        <a href="<?= base_url('files/document/' . $row['id']) ?>" class="small text-decoration-none"><i class="bi bi-download"></i> <?= esc($row['file_name']) ?></a>
                                    <?php else : ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
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
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delModal<?= esc(md5($row['id'])) ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data" action="<?= base_url('barangay/' . $module . '/store') ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Add <?= esc(rtrim($schema['title'], 's')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php foreach ($schema['fields'] as $key => $field) : ?>
                        <div class="mb-3">
                            <label class="form-label"><?= esc($field['label']) ?></label>
                            <?php if ($field['type'] === 'select') : ?>
                                <select class="form-select" name="<?= esc($key) ?>" <?= ! empty($field['required']) ? 'required' : '' ?>>
                                    <option value="">Select...</option>
                                    <?php foreach ($field['options'] as $optVal => $optLabel) :
                                        $ov = is_int($optVal) ? $optLabel : $optVal; ?>
                                        <option value="<?= esc($ov) ?>"><?= esc($optLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($field['type'] === 'file') : ?>
                                <input class="form-control" type="file" name="<?= esc($key) ?>" accept=".doc,.docx,.xls,.xlsx,.pdf">
                                <div class="form-text">Optional. Word, Excel or PDF, up to 10 MB.</div>
                            <?php elseif ($field['type'] === 'textarea') : ?>
                                <textarea class="form-control" name="<?= esc($key) ?>" rows="3" <?= ! empty($field['required']) ? 'required' : '' ?>></textarea>
                            <?php else : ?>
                                <input class="form-control" type="<?= esc($field['type']) ?>" name="<?= esc($key) ?>" <?= ! empty($field['required']) ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy">Save</button>
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
                <form method="post" enctype="multipart/form-data" action="<?= base_url('barangay/' . $module . '/update/' . $row['id']) ?>">
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
                                <?php elseif ($field['type'] === 'file') : ?>
                                    <?php if (! empty($row['file_name'])) : ?>
                                        <div class="small mb-1"><i class="bi bi-paperclip"></i> Current file: <?= esc($row['file_name']) ?></div>
                                    <?php endif; ?>
                                    <input class="form-control" type="file" name="<?= esc($key) ?>" accept=".doc,.docx,.xls,.xlsx,.pdf">
                                    <div class="form-text">Choose a file only if you want to replace the current one.</div>
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

    <div class="modal fade" id="delModal<?= esc(md5($row['id'])) ?>" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form method="post" action="<?= base_url('barangay/' . $module . '/delete/' . $row['id']) ?>">
                    <div class="modal-body text-center py-4">
                        <i class="bi bi-exclamation-triangle text-danger fs-1 mb-2 d-block"></i>
                        <p class="mb-0">Delete record <strong><?= esc($row['id']) ?></strong>? This can't be undone.</p>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php endforeach; ?>

<?= $this->endSection() ?>