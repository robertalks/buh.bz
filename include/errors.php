<?php
define('ERR_INVALID_REQUEST', 1);
define('ERR_PROTOCOL_NOT_ALLOWED', 2);
define('ERR_DOMAIN_NOT_ALLOWED', 3);
define('ERR_INVALID_ALIAS', 4);
define('ERR_IP_NOT_PUBLIC', 5);
define('ERR_IP_NOT_RESOLVED', 6);
define('ERR_HOST_NOT_RESOLVED', 7);
define('ERR_ALIAS_ALREADY_EXISTS', 8);
define('ERR_URL_IS_SPAM', 9);

$errors = array(
0 => "OK",
1 => "Invalid request.",
2 => "Entered protocol is not allowed.",
3 => "Entered domain is not allowed.",
4 => "Custom alias can only contain letters, numbers, underscores and dashes.",
5 => "Entered IP address is not public.",
6 => "Entered IP address couldn't be resolved.",
7 => "Entered URL couldn't be resolved.",
8 => "The custom alias you entered already exists for another URL.",
9 => "Entered URL is blacklisted due spam/malware.");
?>
