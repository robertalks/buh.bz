<?php
/**
 * phpGSB - PHP Google Safe Browsing Implementation
 * Released under New BSD License (see LICENSE)
 * Copyright (c) 2010-2012, Sam Cleaver (Beaver6813, Beaver6813.com)
 * All rights reserved.

 * INITIAL INSTALLER - RUN ONCE (or more than once if you're adding a new list!)
 */
include('config.php');
require("include/phpgsb/phpgsb.class.php");

$phpgsb = new phpGSB(DB_NAME, DB_USER, DB_PASS, DB_HOST, true);
$phpgsb->usinglists = array('googpub-phish-shavar','goog-malware-shavar', 'goog-unwanted-shavar');

$phpgsb->install();

/*
// Check timeout files writable
if (file_put_contents("testfile.dat", "TEST PRE-USE PHPGSB " . time())) {
    unlink("testfile.dat");
} else {
    echo "DIRECTORY IS NOT WRITABLE, CHMOD to 775 or 777";
}
*/
?>
