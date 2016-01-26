<?php
require_once('include/load.php');

if (!empty($_GET['alias'])) {
    $code = escape(trim($_GET['alias']));
    if (preg_match("/^[a-zA-Z0-9]+$/", $code) && code_exists($code) !== false) {// Allows creation/show of QR only if it's sane
        if (!file_exists('qrcache/'.$code.'.png')) {                            // Creates QR only if it doesn't already exist in cache
            require_once('include/qr/qrlib.php');
            QRcode::png(SITE_URL.'/'.$code, 'qrcache/'.$code.'.png', 'L', 8, 2);    // Write QR to cache file
        }
	echo '<img src="qrcache/'.$code.'.png">';
        exit;
    } else echo '<div><strong>Oops, seems like a problem: code doesn`t exists.</strong></div>';
} else header('Location: '.SITE_URL);

?>
