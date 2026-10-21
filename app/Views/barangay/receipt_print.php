<?php
/**
 * @var array<string,mixed>      $payment
 * @var array<string,mixed>|null $resident
 * @var array<string,mixed>|null $cert
 * @var string                   $receivedBy
 */
$name = $resident
    ? trim($resident['first'] . ' ' . ($resident['middle'] ? $resident['middle'] . ' ' : '') . $resident['last'])
    : ($payment['resident'] ?? '');
$amount = (float) ($payment['amount'] ?? 0);

$words = function (int $n) use (&$words): string {
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
        'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    if ($n === 0) return 'Zero';
    if ($n < 20) return $ones[$n];
    if ($n < 100) return $tens[intdiv($n, 10)] . ($n % 10 ? '-' . $ones[$n % 10] : '');
    if ($n < 1000) return $ones[intdiv($n, 100)] . ' Hundred' . ($n % 100 ? ' ' . $words($n % 100) : '');
    if ($n < 1000000) return $words(intdiv($n, 1000)) . ' Thousand' . ($n % 1000 ? ' ' . $words($n % 1000) : '');
    return number_format($n);
};
$pesos    = (int) floor($amount);
$centavos = (int) round(($amount - $pesos) * 100);
$inWords  = $words($pesos) . ' Pesos' . ($centavos ? ' and ' . str_pad((string) $centavos, 2, '0', STR_PAD_LEFT) . '/100' : ' Only');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Receipt — <?= esc($payment['id']) ?></title>
    <link href="<?= base_url('css/bootstrap.min.css') ?>" rel="stylesheet">
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; color: #222; padding: 30px; }
        .print-toolbar { max-width: 720px; margin: 0 auto 16px; display: flex; justify-content: flex-end; gap: 8px; }
        .receipt { max-width: 720px; margin: 0 auto; border: 2px solid #16325c; padding: 28px 36px; }
        .r-head { display: flex; align-items: center; gap: 14px; border-bottom: 2px solid #16325c; padding-bottom: 12px; margin-bottom: 18px; }
        .r-head img { width: 60px; height: 60px; object-fit: contain; }
        .r-head h4 { margin: 0; color: #16325c; }
        .r-head small { color: #555; display: block; }
        .r-title { text-align: center; font-size: 22px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: #16325c; margin-bottom: 4px; }
        .r-no { text-align: center; margin-bottom: 18px; color: #555; }
        table.r-rows { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.r-rows td { padding: 7px 4px; border-bottom: 1px dotted #999; vertical-align: top; }
        table.r-rows td:first-child { width: 34%; color: #555; }
        .r-total { display: flex; justify-content: space-between; align-items: center; border: 2px solid #16325c; padding: 10px 14px; margin-bottom: 6px; font-size: 18px; font-weight: bold; }
        .r-words { font-style: italic; margin-bottom: 40px; font-size: 14px; }
        .r-sign { text-align: right; }
        .r-sign .line { display: inline-block; border-top: 1px solid #222; padding-top: 6px; min-width: 240px; text-align: center; }
        .r-foot { margin-top: 22px; font-size: 12px; color: #777; text-align: center; }
        @media print { .print-toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>

<div class="print-toolbar">
    <button class="btn btn-primary" onclick="window.print()">Print</button>
    <button class="btn btn-outline-secondary" onclick="window.close()">Close</button>
</div>

<div class="receipt">
    <div class="r-head">
        <img src="<?= base_url('images/logo.jpg') ?>" alt="Barangay Sumadat logo">
        <div>
            <h4>Barangay Sumadat</h4>
            <small>Dumalinao, Zamboanga del Sur</small>
            <small>Office of the Barangay Treasurer</small>
        </div>
    </div>

    <div class="r-title">Official Receipt</div>
    <div class="r-no">O.R. No. <strong><?= esc($payment['id']) ?></strong></div>

    <table class="r-rows">
        <tr><td>Date</td><td><?= esc(date('F j, Y', strtotime($payment['date'] ?? 'now'))) ?></td></tr>
        <tr><td>Received from</td><td><strong><?= esc($name) ?></strong></td></tr>
        <?php if ($resident && ! empty($resident['purok'])) : ?>
            <tr><td>Address</td><td><?= esc($resident['purok']) ?>, Barangay Sumadat</td></tr>
        <?php endif; ?>
        <tr><td>Payment for</td><td><?= esc($payment['type']) ?><?= $cert ? ' (Certificate No. ' . esc($cert['id']) . ')' : '' ?></td></tr>
        <?php if ($cert && ! empty($cert['purpose'])) : ?>
            <tr><td>Purpose</td><td><?= esc($cert['purpose']) ?></td></tr>
        <?php endif; ?>
    </table>

    <div class="r-total"><span>Amount Paid</span><span>₱<?= esc(number_format($amount, 2)) ?></span></div>
    <div class="r-words"><?= esc($inWords) ?></div>

    <div class="r-sign">
        <div class="line"><?= esc($receivedBy) ?><br><small>Received by</small></div>
    </div>

    <div class="r-foot">This is an official receipt of Barangay Sumadat. Please keep it for your records.</div>
</div>

</body>
</html>
