<?php
/**
 * phpGSB - PHP Google Safe Browsing Implementation
 * Released under New BSD License (see LICENSE)
 * Copyright (c) 2010-2012, Sam Cleaver (Beaver6813, Beaver6813.com)
 * All rights reserved.

 * INITIAL INSTALLER - RUN ONCE (or more than once if you're adding a new list!)
 */
require_once("../../config.php");
require_once("phpgsb.class.php");

$phpgsb = new phpGSB(DB_NAME, DB_USER, DB_PASS, DB_HOST, true);
$phpgsb->usinglists = array('googpub-phish-shavar','goog-malware-shavar', 'goog-unwanted-shavar');

$phpgsb->install();

?>
