<?php
require_once('include/load.php');

if (empty($_GET['alias']))
	header('Location: '.SITE_URL);

$code = escape(trim($_GET['alias']));
if (preg_match("/^[a-zA-Z0-9]+$/", $code) && (code_exists($code))) {
	if (blacklist_code($code))
		echo "Code has been blacklisted.";
} else
	header('Location: '.SITE_URL);

?>
