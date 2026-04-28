<?php
$qrRef = isset($_GET['qrRef']) ? (string) $_GET['qrRef'] : '';
$qrData = rawurlencode($qrRef);
$qrImageSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&qzone=20&format=png&data=' . $qrData;
$safeQrImageSrc = htmlspecialchars($qrImageSrc, ENT_QUOTES, 'UTF-8');
?>

<img id="qrCode" class="qrCode" src="<?= $safeQrImageSrc ?>" alt="<?= htmlspecialchars(uiText('modals.qr.alt', 'QR code'), ENT_QUOTES, 'UTF-8') ?>">
<a id="copyQR" class="qrCode"><i class="material-icons" style="font-size: 14px">content_copy</i> <?= htmlspecialchars(uiText('modals.qr.copy', 'Copy QR code'), ENT_QUOTES, 'UTF-8') ?></a>