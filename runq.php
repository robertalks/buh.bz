<?php

$noredirect = 2;
$query = 'redirect';
$output = 'none';
$code = null;

function do_status($code, $output = 'none') {
	$url = get_url($code);
	$clicks = get_clicks($code);
	$spam = lookup_url_is_spam($url);
	$spam == false ? $spam_text = 'no' : $spam_text = 'yes';

	if ($output == 'json') {
		header('Content-Type: application/json');
		$json_output = array('code' => $code, 'url' => $url, 'clicks' => $clicks, 'spam' => $spam_text);
		echo json_encode($json_output, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
	} else {
		header('Content-type: text/html; charset=utf-8');
		echo "Code: <a href=".SITE_URL."/".$code." target=_blank>".$code."</a><br/>";
		echo "Clicks: " .$clicks."<br/>";
		echo "URL: <a href=".$url." target=_blank>".$url."</a><br/>";
		echo "SPAM: ".$spam_text."<br/>";
	}
}

function do_redirect($code, $noredirect = 0) {
	if ($noredirect)
		return;

	$url = get_url($code);
	if (empty($url))
		return;

	if (lookup_url_is_spam($url))
		return;

	if (check4spam($url)) {
		add_bad_url_to_db($url);
		return;
	}

	increase_clicks($code);
	header('Location: '.$url, true, 301);
	exit(0);
}

function do_blacklist($code) {
	if (blacklist_code($code))
		echo "Code has been blacklisted.";
}

function do_delete($code) {
	if (delete_code($code))
		echo "Code has been deleted.";
}

require_once('include/load.php');

if (isset($_GET['alias'])) {
	if (!empty($_GET['alias']))
		$code = escape(trim($_GET['alias']));
}
 
if (isset($_GET['q'])) {
	if (!empty($_GET['q']))
		$query = escape(trim($_GET['q']));
}

if (isset($_GET['j'])) {
	if (!empty($_GET['j']))
		$output = escape(trim($_GET['j']));
}

if (preg_match("/^[a-zA-Z0-9]+$/", $code) && code_exists($code)) {
	if ($query == 'redirect' || empty($query))
		$noredirect = 0;
	if ($query == 'status')
		do_status($code, $output);
	if ($query == 'blacklist')
		do_blacklist($code);
} else
	$noredirect = 1;

out:
	do_redirect($code, $noredirect);

	if ($noredirect < 2)
		header('Location: '.SITE_URL);
