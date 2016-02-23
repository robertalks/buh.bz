<?php

define('CONFIGFILE', str_replace( '\\', '/', $_SERVER['DOCUMENT_ROOT']) . '/config.php');
if (!file_exists(CONFIGFILE))
	die('Config file not found');

require_once(CONFIGFILE);
date_default_timezone_set('UTC');

global $mydb;
global $http_status = 200;

require_once('errors.php');
require_once('utils.php');
require_once('db.php');
require_once('spam.php');
require_once('core.php');

load_mysql();

