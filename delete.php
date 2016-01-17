<?php
require_once('include/load.php');

if (empty($_GET['alias']))
	header('Location: '.SITE_URL);

$code = escape(trim($_GET['alias']));
if ((preg_match("/^[a-zA-Z0-9]+$/", $code)) && (get_url($code) != null)) {
	if (delete_code($code))
		echo "Code deleted.";
	else
		echo "Code not deleted.";
} else
	header('Location: '.SITE_URL);

?>
