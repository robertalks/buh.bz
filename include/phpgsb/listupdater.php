<?php
/**
 * phpGSB - PHP Google Safe Browsing Implementation
 * Released under New BSD License (see LICENSE)
 * Copyright (c) 2010-2012, Sam Cleaver (Beaver6813, Beaver6813.com)
 * All rights reserved.
 *
 */

if (!class_exists('phpGSB', false))
	require_once('phpgsb.class.php');

define('CONFIGFILE', str_replace( '\\', '/', $_SERVER['DOCUMENT_ROOT']) . '/config.php');
if (!file_exists(CONFIGFILE))
	die('Config file not found');

require_once(CONFIGFILE);

$phpgsb = new phpGSB(DB_NAME, DB_USER, DB_PASS, DB_HOST, true);

$phpgsb->apikey = GSB_API_KEY;
$phpgsb->usinglists = array('googpub-phish-shavar', 'goog-malware-shavar', 'goog-unwanted-shavar');
$phpgsb->runUpdate();

?>
