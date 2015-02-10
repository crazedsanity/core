<?php

//echo "RUNNING (". __FILE__ .")!!!!\n";

// set the timezone to avoid spurious errors from PHP
date_default_timezone_set("America/Chicago");

require_once(dirname(__FILE__) .'/../AutoLoader.class.php');
require_once(dirname(__FILE__) .'/../debugFunctions.php');

// set a constant for testing...
if(!defined('UNITTEST__LOCKFILE')) { // fixes issues with running in a separate process...
	define('UNITTEST__LOCKFILE', dirname(__FILE__) .'/files/rw/');
	define('cs_lockfile-RWDIR', constant('UNITTEST__LOCKFILE'));
	define('RWDIR', constant('UNITTEST__LOCKFILE'));
	define('LIBDIR', dirname(__FILE__) .'/..');
	define('UNITTEST_ACTIVE', true);
}

AutoLoader::registerDirectory(dirname(__FILE__) .'/../');

require_once(__DIR__ .'/../base.abstract.php');
require_once(__DIR__ .'/../FileSystem.class.php');
