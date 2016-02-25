<?php
require_once('include/load.php');

if (!empty($_GET['alias'])) {
    $code = escape($_GET['alias']);
    if (preg_match("/^[a-zA-Z0-9]+$/", $code)) {// Allows creation/show of QR only if it's sane
        if (!file_exists('qrcache/'.$code.'.png')) {                            // Creates QR only if it doesn't already exist in cache
            require_once('include/qr/qrlib.php');
            QRcode::png(SITE_URL.'/'.$code, 'qrcache/'.$code.'.png', 'L', 8, 2);    // Write QR to cache file
        }
        header('Content-Type: image/png');                                      // Override mime type header
        echo file_get_contents('qrcache/'.$code.'.png');                        // Print out the QR from file
        exit;
    }
}
header('Location: '.SITE_URL);
?>
