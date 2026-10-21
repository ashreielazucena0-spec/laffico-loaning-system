<?php
/**
 * @var string $pageTitle
 * @var string $active
 * @var array  $me
 * @var int    $readyCount
 */
$nav = [
    'dashboard'    => ['resident/dashboard',    'bi-grid',                 'Dashboard'],
    'request'      => ['resident/request',      'bi-file-earmark-plus',    'Request Certificate'],
    'requests'     => ['resident/requests',     'bi-card-checklist',       'My Requests'],
    'certificates' => ['resident/certificates', 'bi-file-earmark-check',   'Released Certificates'],
    'household'    => ['resident/household',    'bi-house',                'My Household'],
    'documents'    => ['resident/documents',    'bi-journal-text',         'Ordinances & Resolutions'],
    'account'      => ['resident/account',      'bi-person-gear',          'My Account'],
];
$bottom = ['dashboard', 'request', 'requests', 'certificates'];
$firstName = $me['first'] ?? 'Resident';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($pageTitle ?? 'Resident') ?> — Barangay Sumadat</title>
    <link href="<?= base_url('css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('css/barangay.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/resident.css') ?>">
</head>
<body class="r-body">

<div class="r-shell">

    <aside class="r-sidebar" id="rSidebar">
        <div class="brand">
            <div class="brand-logo"><img src="<?= base_url('images/logo.jpg') ?>" alt="Barangay Sumadat logo"></div>
            <div>
                <h6 class="mb-0">Barangay Sumadat</h6>
                <small>Resident Portal</small>
            </div>
        </div>

        <nav class="r-nav">
            <?php foreach ($nav as $key => [$url, $icon, $label]) : ?>
                <a href="<?= base_url($url) ?>" class="menu-item <?= ($active ?? '') === $key ? 'active' : '' ?>">
                    <i class="bi <?= $icon ?>"></i><span><?= esc($label) ?></span>
                    <?php if ($key === 'requests' && ($readyCount ?? 0) > 0) : ?>
                        <span class="badge rounded-pill bg-warning text-dark"><?= (int) $readyCount ?> ready</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
            <a href="<?= base_url('logout') ?>" class="menu-item mt-3">
                <i class="bi bi-box-arrow-right"></i><span>Log Out</span>
            </a>
        </nav>
    </aside>
    <div class="r-backdrop" id="rBackdrop"></div>

    <div class="r-main">

        <header class="r-topbar">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-light r-menu-btn" type="button" data-drawer aria-label="Menu"><i class="bi bi-list fs-5"></i></button>
                <h6 class="mb-0"><?= esc($pageTitle ?? '') ?></h6>
            </div>
            <div class="user-profile">
                <div class="avatar"><?= esc(mb_strtoupper(mb_substr($firstName, 0, 1))) ?></div>
                <div class="d-none d-md-block">
                    <strong><?= esc(trim(($me['first'] ?? '') . ' ' . ($me['last'] ?? ''))) ?></strong>
                    <small>Resident</small>
                </div>
            </div>
        </header>

        <div class="r-content">

            <?php if (session()->getFlashdata('success')) : ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= esc(session()->getFlashdata('success')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('error')) : ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= esc(session()->getFlashdata('error')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->get('tempPassword') && ($active ?? '') !== 'account') : ?>
                <div class="alert alert-warning alert-persist" role="alert">
                    <i class="bi bi-shield-lock me-1"></i>
                    You are using the temporary password from the barangay office.
                    <a href="<?= base_url('resident/account') ?>" class="alert-link">Change it now</a> to keep your account safe.
                </div>
            <?php endif; ?>

            <?= $this->renderSection('content') ?>

        </div>
    </div>

    <nav class="r-bottomnav">
        <?php foreach ($bottom as $key) : [$url, $icon, $label] = $nav[$key]; ?>
            <a href="<?= base_url($url) ?>" class="<?= ($active ?? '') === $key ? 'active' : '' ?>">
                <i class="bi <?= $icon ?>"></i>
                <span><?= esc(['dashboard' => 'Home', 'request' => 'Request', 'requests' => 'Requests', 'certificates' => 'Released'][$key]) ?></span>
                <?php if ($key === 'requests' && ($readyCount ?? 0) > 0) : ?><span class="dot"><?= (int) $readyCount ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <button type="button" data-drawer><i class="bi bi-three-dots"></i><span>More</span></button>
    </nav>

</div>

<script src="<?= base_url('js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('js/resident.js') ?>"></script>
</body>
</html>
