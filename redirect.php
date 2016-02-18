<?php
require_once('include/load.php');

if (!empty($_GET['alias'])) {
	$code = escape(trim($_GET['alias']));
	if (preg_match("/^[a-zA-Z0-9]+$/", $code) && (code_exists($code))) {
		$url = get_url($code);
		if (empty($url))
			goto out;

		if (lookup_url_is_spam($url))
			goto out;

		if (check4spam($url)) {
			add_bad_url_to_db($url);
			goto out;
		}

		increase_clicks($code);
		header('Location: '.$url, true, 301);
		exit;
	}
}

out:
	header('Location: '.SITE_URL);
?>
