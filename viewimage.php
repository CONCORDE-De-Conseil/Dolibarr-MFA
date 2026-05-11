<?php

require_once DOL_DOCUMENT_ROOT . '/includes/tcpdf/tcpdf_barcodes_2d.php';

$barcodeobj = new TCPDF2DBarcode($uri, 'QRCODE,H');
$imageData = $barcodeobj->getBarcodePngData(4, 4);
$base64Image = 'data:image/png;base64,' . base64_encode($imageData);

// In your HTML/Template:
echo '<img src="' . $base64Image . '" />';
