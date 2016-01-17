<?php
function verify_url($url) {
	if (empty($url))
		return null;

	$response = null;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)");
	curl_setopt($ch, CURLOPT_NOBODY, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
	curl_setopt($ch, CURLOPT_TIMEOUT, 40);
	curl_exec($ch);
	$response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if (!empty($response))
		return $response;
	
	return null;
}

function process_url($url) {
	$response = null;

	if (empty($url))
		return ERR_INVALID_REQUEST;

	$url_protocol = @parse_url($url, PHP_URL_SCHEME);
	if (!preg_match('/^('.ALLOWED_PROTOCOLS.')$/i', $url_protocol))
		return ERR_PROTOCOL_NOT_ALLOWED;

	$url_host = @parse_url($url, PHP_URL_HOST);
	if (preg_match('/^('.NOT_ALLOWED_HOSTS.')$/i', $url_host))
		return ERR_DOMAIN_NOT_ALLOWED;

	if (preg_match('/^([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/', $url_host)) {
		if (!is_allowed_ipv4($url_host))
			return ERR_IP_NOT_PUBLIC;

		if (gethostbyaddr($url_host) == $url_host)
			return ERR_IP_NOT_RESOLVED;
	}

	if (!checkdnsrr($url_host.'.', 'ANY'))
		return ERR_HOST_NOT_RESOLVED;

	$response = verify_url($url);
	if ($response != null) {
		if ($response == 404)
			return ERR_HOST_NOT_RESOLVED;
	} else
		return ERR_INVALID_REQUEST;
	
	if (lookup_url_in_db($url))
		return ERR_URL_IS_SPAM;

	if (check4spam($url)) {
		add_bad_url_to_db($url);
		return ERR_URL_IS_SPAM;
	}

	return 0;
}

function process_code($code) {
	$url = null;

	if (empty($code))
		return -1;

	$url = get_url($code);
	if (empty($url))
		return -1;

	$response = verify_url($url);
	if ($response != null) {
		if ($response == 404)
			return 2;
	} else
                return -1;

	if (lookup_url_in_db($url))
		return 1;

        if (check4spam($url)) {
		add_bad_url_to_db($url);
                return 1;
	}
	
	return 0;
}

?>
