<?php
require_once('include/load.php');

if (empty($_GET['alias']))
	header('Location: '.SITE_URL);

$code = escape(trim($_GET['alias']));
if (preg_match("/^[a-zA-Z0-9]+$/", $code) && ($url = get_url($code)) != null) {
	$clicks = get_clicks($code);
	echo "Code: " .$code."<br/>";
	echo "Clicks: " .$clicks."<br/>";
	echo "URL: " .$url."<br/>";
} else
	header('Location: '.SITE_URL);

?>
