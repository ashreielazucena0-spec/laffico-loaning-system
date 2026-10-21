<?php
/**
 * @var array<string,mixed>      $cert
 * @var array<string,mixed>|null $resident
 */
$residentName = $resident ? trim($resident['first'] . ' ' . ($resident['middle'] ? $resident['middle'] . ' ' : '') . $resident['last']) : ($cert['resident'] ?? '');
$purok        = $resident['purok'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate — <?= esc($cert['id']) ?></title>
    <link href="<?= base_url('css/bootstrap.min.css') ?>" rel="stylesheet">
    <style>
        body {
            font-family: Georgia, 'Times New Roman', serif;
            color: #222;
            padding: 40px;
        }
        .print-toolbar {
            max-width: 800px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .certificate {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #16325c;
            padding: 50px 60px;
        }
        .cert-header {
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 2px solid #16325c;
            padding-bottom: 16px;
            margin-bottom: 30px;
        }
        .cert-header img { width: 70px; height: 70px; object-fit: contain; }
        .cert-header h4 { margin: 0; color: #16325c; }
        .cert-header small { color: #555; }
        .cert-title {
            text-align: center;
            font-size: 26px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin: 30px 0;
            color: #16325c;
        }
        .cert-body { font-size: 16px; line-height: 2; text-align: justify; }
        .cert-body strong { text-transform: uppercase; }
        .cert-signature {
            margin-top: 70px;
            text-align: right;
        }
        .cert-signature .line {
            display: inline-block;
            border-top: 1px solid #222;
            padding-top: 6px;
            min-width: 260px;
            text-align: center;
        }
        .cert-meta {
            margin-top: 40px;
            font-size: 13px;
            color: #666;
            display: flex;
            justify-content: space-between;
        }
        @media print {
            .print-toolbar { display: none; }
            body { padding: 0; }
            .certificate { border: none; max-width: 100%; }
        }
    </style>
</head>
<body>

    <div class="print-toolbar">
        <button class="btn btn-navy" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        <button class="btn btn-outline-secondary" onclick="window.close()">Close</button>
    </div>

    <div class="certificate">

        <div class="cert-header">
            <img src="<?= base_url('images/logo.jpg') ?>" alt="Barangay Sumadat logo">
            <div>
                <h4>Barangay Sumadat</h4>
                <small>Dumalinao, Zamboanga del Sur</small><br>
                <small>Office of the Punong Barangay</small>
            </div>
        </div>

        <div class="cert-title"><?= esc($cert['type'] ?? 'Certificate') ?></div>

        <div class="cert-body">
            <p>TO WHOM IT MAY CONCERN:</p>

            <p>
                This is to certify that <strong><?= esc($residentName) ?></strong>,
                of legal age, <?= $purok !== '' ? 'a resident of ' . esc($purok) . ', ' : '' ?>
                Barangay Sumadat, Dumalinao, Zamboanga del Sur, is personally known to me
                to be a bona fide resident of this barangay.
            </p>

            <p>
                This certification is being issued upon the request of the above-named person
                for the purpose of <strong><?= esc($cert['purpose'] ?: 'whatever legal purpose it may serve') ?></strong>.
            </p>

            <p>
                Issued this <?= esc(date('jS \d\a\y \o\f F Y', strtotime($cert['date'] ?? 'now'))) ?>
                at Barangay Sumadat, Dumalinao, Zamboanga del Sur.
            </p>
        </div>

        <div class="cert-signature">
            <div class="line">
                Punong Barangay
            </div>
        </div>

        <div class="cert-meta">
            <span>Certificate No.: <?= esc($cert['id']) ?></span>
            <span>Amount Paid: ₱<?= esc(number_format((float) ($cert['amount'] ?? 0), 2)) ?></span>
        </div>

    </div>

    <script>
        window.onload = function () {
            window.print();
        };
    </script>

</body>
</html>