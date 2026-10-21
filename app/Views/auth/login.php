<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barangay Sumadat — Log In</title>
    <link href="<?= base_url('css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('css/barangay.css') ?>">
</head>
<body class="auth-body">

<div class="auth-wrap">
    <div class="auth-card">

        <div class="auth-head">
            <div class="auth-logo"><img src="<?= base_url('images/logo.jpg') ?>" alt="Barangay Sumadat logo"></div>
            <h1>Barangay Sumadat</h1>
            <p>Dumalinao, Zamboanga del Sur</p>
        </div>

        <?php if (session()->getFlashdata('loginError')) : ?>
            <div class="alert alert-danger py-2"><?= esc(session()->getFlashdata('loginError')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')) : ?>
            <div class="alert alert-warning py-2"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('login') ?>" class="auth-form">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input class="form-control" name="username" required autocomplete="username" autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input class="form-control" name="password" type="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-gold w-100" type="submit">Log In</button>
        </form>

    </div>
</div>

<script src="<?= base_url('js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
