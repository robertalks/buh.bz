<?php
require_once('include/load.php');

if (empty($_GET['alias']))
	header('Location: '.SITE_URL);

$code = escape(trim($_GET['alias']));
if (preg_match("/^[a-zA-Z0-9]+$/", $code) && (code_exists($code))) {
	$url = get_url($code);
	$clicks = get_clicks($code);
	echo "Code: " .$code."<br/>";
	echo "Clicks: " .$clicks."<br/>";
	echo "URL: " .$url."<br/>";

        if (lookup_url_is_spam($url))
                echo "SPAM: yes<br/>";
        else
                echo "SPAM: no<br/>";
} else
	header('Location: '.SITE_URL);

?>
