<?php

if (!class_exists('phpGSB', false))
        require_once('phpgsb/phpgsb.class.php');

function check_with_phishtank($url) {
	$API = "http://checkurl.phishtank.com/checkurl/";
	$KEY = "6f9c4309365547230cea0bf4e70198cfac591aea4e98cdab915feb6aca441433";
	$URL = urlencode(escape($url));

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_USERAGENT, "x90");
	curl_setopt($ch, CURLOPT_POSTFIELDS, "format=xml&app_key=$KEY&url=$URL");
	curl_setopt($ch, CURLOPT_URL, "$API");
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	$result = curl_exec($ch);
	curl_close($ch);

	if (!empty($result)) {
		if (preg_match("/phish_detail_page/", $result))
			return true;
	}

	return false;
}

function check_with_GSB_phishing($url) {
	$rc = false;

	$phpgsb = new phpGSB(DB_NAME, DB_USER, DB_PASS, DB_HOST, false);
	$phpgsb->apikey = GSB_API_KEY;

	$phpgsb->usinglists = array('googpub-phish-shavar');
	if ($phpgsb->doLookup($url))
		$rc = true;

	return $rc;
}

function check_with_GSB_malware($url) {
	$rc = false;

	$phpgsb = new phpGSB(DB_NAME, DB_USER, DB_PASS, DB_HOST, false);
	$phpgsb->apikey = GSB_API_KEY;

	$phpgsb->usinglists = array('goog-malware-shavar');
	if ($phpgsb->doLookup($url))
		$rc = true;

	return $rc;
}

function check_with_GSB_unwanted($url) {
	$rc = false;

	$phpgsb = new phpGSB(DB_NAME, DB_USER, DB_PASS, DB_HOST, false);
	$phpgsb->apikey = GSB_API_KEY;

	$phpgsb->usinglists = array('goog-unwanted-shavar');
	if ($phpgsb->doLookup($url))
		$rc = true;

	return $rc;
}

function check_with_curl($url) {
	$data = get_url_data($url);
	if (empty($data))
		return false;

	$results = strpos($data, 'var host =');
	if ($results) {
		$results = strpos($data, '/?cid=');
		if ($results) {
			$results = strpos($data, '/report/spam/');
			if ($results)
				return true;
		}
	}

	return false;
}

function check4spam($url) {

	if (check_with_phishtank($url))
		return true;

	if (check_with_GSB_phishing($url))
		return true;

	if (check_with_GSB_malware($url))
		return true;

	if (check_with_GSB_unwanted($url))
		return true;

	if (check_with_curl($url))
		return true;

	return false;	
}

function check_url_badness($url) {
	$reason = '';

	if (check_with_phishtank($url))
		$reason = 'phishing';
        elseif (check_with_GSB_phishing($url))
                $reason = 'phishing';
        elseif (check_with_GSB_malware($url))
                $reason = 'malware';
	elseif (check_with_curl($url))
		$reason = 'malware';
        elseif (check_with_GSB_unwanted($url))
                $reason = 'unwanted';

	return $reason;
}

function add_bad_url_to_db($url) {
	$reason = '';
	$ip = '';
	$codes = '';
	$code = 'none';

	if (empty($url))
		return false;

	if (lookup_url_is_spam($url))
		return true;

	$reason = check_url_badness($url);
	if (empty($reason))
		return false;

	$codes = get_codes_for_url($url);
	if (!empty($codes))
		$code = $codes{0}->code;

	$ip = get_IP();
	if (block_url($code, $url, $reason, $ip))
		return true;

	return false;
}

function add_bad_code_to_db($code) {
	$reason = '';
	$ip = '';
	$url = '';

	if (empty($code))
		return false;

	$url = get_url($code);
	if (empty($url))
		return false;

	if (lookup_url_is_spam($url))
		return true;

	$reason = check_url_badness($url);
	if (empty($reason))
		return false;

	$ip = get_IP();
	if (block_url($code, $url, $reason, $ip))
		return true;

	return false;
}

function blacklist_code($code, $reason = 'unwanted') {
	if (empty($code))
		return false;

	$url = get_url($code);
	if (empty($url))
		return false;

	if (lookup_url_is_spam($url))
		return true;

	$ip = get_IP();
	if (block_url($code, $url, $reason, $ip))
		return true;

	return false;
}

?>
