<?php

function escape($str) {
	return str_replace('"', '\\x22', $str);
}

function str_encode($str) {
	return str_replace('&', '\\x26', $str);
}

function polish_url($url) {
	if (empty($url))
		return null;

	$URL = trim($url);
	$URL = rtrim($URL);
	$URL = str_replace('"', '\\x22', $URL);
	$URL = str_replace('&', '\\x26', $URL);
	$URL = str_replace("'", '\\x27', $URL);
	$URL = str_replace(" ", "+", $URL);
	$URL = addslashes($URL);

	return $URL;
}

function get_url_data($url) {
	if (empty($url))
		return false;

	$data = null;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
	curl_setopt($ch, CURLOPT_TIMEOUT, 40);
	$data = curl_exec($ch);
	curl_close($ch);

	return $data;
}

function prettify_numbers($n = 0) {
	$n = (0+str_replace(", ", "", $n));
       
	if (!is_numeric($n))
		return false;
       
	if ($n > 1000000000000)
		return round(($n/1000000000000),1).' trillion';
	elseif ($n > 1000000000)
		return round(($n/1000000000),1).' billion';
	elseif ($n > 1000000)
		return round(($n/1000000),1).' million';
	elseif ($n > 1000)
		return round(($n/1000),1).' thousand';
       
        return number_format($n);
}

function int2code($id, $make_rand = false) {
	$chars = "123456789bcdfghjkmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ";
	$chars = str_split($chars);
        $len = strlen($chars);
	$code = '';

	if ($make_rand)
		$num = intval(rand($id, $id + 100));
	else
		$num = intval($id);

        while ($num > $len - 1) {
		$code = $chars[fmod($num, $len)] . $code;
		$num = floor($num / $len);
        }

	return $chars[intval($num)] . $code;
}

function get_IP() {
	$ip = "0.0.0.0";

	if (isset($_SERVER)) {
		if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
			$ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
			goto out;
		} elseif (isset($_SERVER["HTTP_CLIENT_IP"])) {
			$ip = $_SERVER["HTTP_CLIENT_IP"];
			goto out;
		} elseif (isset($_SERVER["REMOTE_ADDR"])) {
			$ip = $_SERVER["REMOTE_ADDR"];
			goto out;
		}
	}

	if (getenv('HTTP_X_FORWARDED_FOR')) {
        	$ip = getenv('HTTP_X_FORWARDED_FOR');
		goto out; 
	} elseif (getenv('HTTP_CLIENT_IP')) {
		$ip = getenv('HTTP_CLIENT_IP');
		goto out;
	} elseif (getenv('REMOTE_ADDR')) {
		$ip = getenv('REMOTE_ADDR');
		goto out;
	}

out:
	return $ip;
}

function is_allowed_ipv4($ip) {
	if (empty($ip))
		return false;

	$ip = explode('.',$ip);
	switch ($ip[0]) {
		case 0:
		case 10:
		case 127:
			return false;
		case 169:
			if ($ip[1] < 254)
				return false;
			return true;
		case 172:
			if ($ip[1] < 16)
				return false;
			return true;
		case 192:
			switch (intval($ip[1])) {
				case 0:
					if ($ip[2] == 0 || $ip[2] == 2)
						return false;
					return true;
				case 88:
					if ($ip[2] == 99)
						return false;
					return true;
				case 168:
					return false;
				default:
					return true;
			}
		case 198:
			switch ($ip[1]) {
				case 18:
				case 19:
					return false;
				case 51:
					if ($ip[2] == 100)
						return false;
				default:
					return true;
			}
		case 203:
			if ($ip[1] == 0 && $ip[2] == 113)
				return false;
			return true;
		default:
			if ($ip[0] >= 224)
				return false;
			return true;
	}
}

?>
