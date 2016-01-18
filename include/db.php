<?php

function load_mysql_driver() {
	$driver = 'none';
	$class = '';

	if (extension_loaded('mysqli')) {
		$driver = 'mysqli';
	} elseif (extension_loaded('mysql')) {
		$driver = 'mysql';
	} elseif (extension_loaded('pdo_mysql')) {
		$driver = 'pdo';
	}

	if (in_array($driver, array('mysql', 'mysqli', 'pdo'))) {
		$class = 'ezSQL_' . $driver;
	}

	require_once('sql/shared/ez_sql_core.php');
	require_once('sql/ez_sql_' . $driver . '.php');

	global $mydb;

	if (!class_exists($class, false)) {
		die('buh.bz requires the mysql, mysqli or pdo_mysql PHP extension. No extension found.');
	}

	$mydb = new $class(DB_USER, DB_PASS, DB_NAME, DB_HOST);
	$mydb->driver = $driver;
	$mydb->host = DB_HOST;
	$mydb->database = DB_NAME;
}

function load_mysql() {
	global $mydb;

	load_mysql_driver();

	return $mydb;
}

function insert_url($code, $url, $ip , $uuid) {
	global $mydb;
	$table = DB_URLS_TABLE;
	$result = false;

	if ($uuid == '')
		$uuid = DEFAULT_UUID;

	$url = mysql_real_escape_string($url);
	$result = $mydb->query("INSERT INTO `$table` (code, url, clicks, ip, date, uuid) VALUES ('$code', '$url', 0, '$ip', NOW(), '$uuid')");

	return $result;
}

function get_url($code) {
	global $mydb;
	$table = DB_URLS_TABLE;
	$result = null;

	$result = $mydb->get_var("SELECT `url` FROM `$table` WHERE `code` = '" . $code . "' LIMIT 1;");

	return $result;
}

function get_clicks($code) {
	global $mydb;
	$table = DB_URLS_TABLE;
	$result = null;

	$result = $mydb->get_var("SELECT `clicks` FROM `$table` WHERE `code` = '" . $code . "' LIMIT 1;");

	return $result;
}

function code_exists($code) {
	global $mydb;
	$table = DB_URLS_TABLE;
	$result = 0;

	$result = intval($mydb->get_var("SELECT COUNT(clicks) FROM `$table` WHERE `code` = '" . $code . "' LIMIT 1;"));
	if ($result > 0)
		return true;

	return false;
}

function delete_code($code) {
	global $mydb;
	$table = DB_URLS_TABLE;
	$delete = false;

	if (empty($code))
		return false;

	$code = escape($code);
	$delete = $mydb->query("DELETE FROM `$table` WHERE `code` = '" . $code . "';");

	return $delete;
}

function get_codes_for_url($url) {
	global $mydb;
	$result = array();
	$table = DB_URLS_TABLE;

	if (empty($url))
		return null;

	$result = $mydb->get_results("SELECT `code` from `$table` WHERE `url` = '" . $url . "' ORDER BY `code` DESC;");
	if (!empty($result))
		return $result;

	return null;
}

function code_count() {
	global $mydb;
	$table = DB_URLS_TABLE;
	$result = 0;

	$result = $mydb->get_var("SELECT COUNT(code) FROM `$table` LIMIT 1;");

	return $result;
}

function clicks_count() {
	global $mydb;
	$table = DB_URLS_TABLE;
	$result = 0;

	$result = $mydb->get_var("SELECT SUM(clicks) FROM `$table` LIMIT 1;");

	return $result;
}

function top_clicks() {
	global $mydb;
	$table = DB_URLS_TABLE;
	$result = array();

	$result = $mydb->get_results("SELECT `code`, `clicks` FROM `$table` ORDER BY `clicks` DESC LIMIT 5;");
	if (!empty($result))
		return $result;

	return null;
}

function increase_clicks($code) {
	global $mydb;
	$table = DB_URLS_TABLE;
	$update = false;

	$update = $mydb->query("UPDATE `$table` SET `clicks` = `clicks` + 1 WHERE `code` = '" . $code . "';");

	return $update;
}

function get_next_id() {
	global $mydb;
	$table = DB_URLS_TABLE;
	$result = 0;
	$id = 1;

	$result = intval($mydb->get_var("SELECT `_id` FROM `$table` ORDER BY `_id` DESC LIMIT 1;"));
	$id += $result;

	return $id;
}

function block_url($code, $url, $reason, $ip) {
	global $mydb;
	$table = DB_SPAM_TABLE;
	$result = false;

	$url = mysql_real_escape_string($url);
	$result = $mydb->query("INSERT INTO `$table` (code, url, reason, ip, date) VALUES ('$code', '$url', '$reason', '$ip', NOW())");

	return false;	
}

function unblock_url($url) {
	global $mydb;
	$table = DB_SPAM_TABLE;
	$delete = false;

	$delete = $mydb->query("DELETE FROM `$table` WHERE `url` = '" . $url . "';");

	return $delete;
}

function lookup_url_in_db($url) {
	global $mydb;
	$table = DB_SPAM_TABLE;
	$result = null;

	$result = $mydb->get_var("SELECT `reason` FROM `$table` WHERE `url` = '" . $url . "' LIMIT 1;");

	return $result;
}

?>
