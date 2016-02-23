<?php

$noredirect = 2;
$q = 'redirect';
$code = null;

function display_status($code) {
	$url = get_url($code);
	$clicks = get_clicks($code);
	$spam = lookup_url_is_spam($url);
	$spam == false ? $spam_text = 'no' : $spam_text = 'yes';

	echo "Code: <a href=".SITE_URL."/".$code." target=_blank>".$code."</a><br/>";
	echo "Clicks: " .$clicks."<br/>";
	echo "URL: <a href=".$url." target=_blank>".$url."</a><br/>";
	echo "SPAM: ".$spam_text."<br/>";
}

function do_redirect($code, $noredirect) {
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

if (isset($_POST['alias'])) {
	if (!empty($_POST['alias']))
		$code = escape(trim($_POST['alias']));
} elseif (isset($_GET['alias'])) {
	if (!empty($_GET['alias']))
		$code = escape(trim($_GET['alias']));
}
 
if (isset($_POST['q'])) {
	if (!empty($_POST['q']))
		$q = escape(trim($_POST['q']));
} elseif (isset($_GET['q'])) {
	if (!empty($_GET['q']))
		$q = escape(trim($_GET['q']));
}

if (preg_match("/^[a-zA-Z0-9]+$/", $code) && code_exists($code)) {
	if (strpos($q, 'status'))
		display_status($code);
	if (strpos($q, 'blacklist'))
		do_blacklist($code);
	if (strpos($q, 'redirect') || empty($q))
		$noredirect = 0;
} else
	$noredirect = 1;

out:
	do_redirect($code, $noredirect);

	if ($noredirect < 2)
		header('Location: '.SITE_URL);
