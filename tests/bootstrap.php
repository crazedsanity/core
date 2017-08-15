<?php

//echo "RUNNING (". __FILE__ .")!!!!\n";

// set the timezone to avoid spurious errors from PHP
date_default_timezone_set("America/Chicago");

require_once(__DIR__ .'/../src/core/debugFunctions.php');
if(file_exists(__DIR__ .'/../vendor/autoload.php')) {
	require_once(__DIR__ .'/../vendor/autoload.php');
}
else {
	trigger_error("vendor autoloader not found, unit tests will probably fail -- try running 'composer update'");
}

// set a constant for testing...
if(!defined('UNITTEST__LOCKFILE')) { // fixes issues with running in a separate process...
	define('UNITTEST__LOCKFILE', dirname(__FILE__) .'/files/rw/');
	define('cs_lockfile-RWDIR', constant('UNITTEST__LOCKFILE'));
	define('RWDIR', constant('UNITTEST__LOCKFILE'));
	define('LIBDIR', dirname(__FILE__) .'/..');
	define('UNITTEST_ACTIVE', true);
}

