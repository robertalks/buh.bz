<?php
require_once('include/load.php');

$url = '';
$code = '';
$format = '';
$request_type = '';
$error = 0;
$redirect = SITE_URL.'/index.php';

$urlArray = array();
if (isset($_POST['url'])) {
	$urlArray = $_POST;
	$request_type = 'ajax';
} elseif (isset($_GET['url'])) {
	$urlArray = $_GET;
	$request_type = isset($urlArray['out']) ? $urlArray['out'] : 'api';
}

if (isset($urlArray['url']) && !empty($urlArray['url']))
	$url = $urlArray['url'];

if (isset($urlArray['alias']) && !empty($urlArray['alias']))
	$code = escape($urlArray['alias']);

if (isset($urlArray['format']) && !empty($urlArray['format']))
	$format = escape($urlArray['format']);

if (!empty($urlArray)) {
	foreach ($urlArray as $index => $value) {
		if ($index == 'url' || $index == 'alias' || $index == 'format')
			continue;

		if ($value)
			$url.= "&".$index."=".$value;
	}
}

empty($url) == true ? $entered_url = '' : $entered_url = escape($url);
empty($code) == true ? $entered_alias = '' : $entered_alias = escape($code);

if (empty($url)) {
        $error = ERR_INVALID_REQUEST;
        goto out;
}

$format == 'json' ? $request_type = 'api' : $format = 'text'; 

$url = polish_url($url);
$protocol = @parse_url($url, PHP_URL_SCHEME);
if ($protocol == null)
	$url = 'http://'.$url;

$error = process_url($url);
if ($error > 0)
	goto out;

$url = trim($url, '/');
$codes = get_codes_for_url($url);
$ip = get_IP();
$uuid = DEFAULT_UUID;

if (strlen($code) == 0) {
	if (count($codes) == 0) {
		$id = get_next_id();
		$code = int2code($id, false);
		insert_url($code, $url, $ip, $uuid);
	} else
		$code = $codes{0}->code;
} else {
	if (!empty($codes)) {
		if (in_array($code, $codes))
			goto out;
	}

	if (code_exists($code)) {
		$error = ERR_ALIAS_ALREADY_EXISTS;
		goto out;
	}

	insert_url($code, $url, $ip, $uuid);
}

out:
switch ($request_type) {
	case 'ajax':
		header('Content-type: text/html; charset=utf-8');
		echo $error;
		if ($error == 0)
			echo SITE_URL.'/'.$code;
		else
			echo $errors[$error];
		break;
	case 'api':
		if ($format == 'json') {
			header('Content-Type: application/json');
			$json_output = array('status' => $error, 'code' => $code, 'url' => $url);
			echo json_encode($json_output, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
		} elseif ($error == 0) {
			header('Content-type: text/html; charset=utf-8');
			echo SITE_URL.'/'.$code;
		}
		break;
	case 'mobile':
	case 'noscript':
		$redirect = $redirect.'?rc='.$error.'&url='.$entered_url.'&alias='.$entered_alias;
		if ($error == 0)
			header('Location: '.$redirect.'&code='.$code);
		else
			header('Location: '.$redirect);
		break;
	break;
}

?>
