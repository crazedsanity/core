<?php

namespace crazedsanity\core;
use crazedsanity\version\Version;

/**
 * @codeCoverageIgnore
 */
abstract class baseAbstract {
	
	static public $version;
	
	
	//-------------------------------------------------------------------------
	public function __construct() {
		self::GetVersionObject();
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public static function GetVersionObject() {
		if(!is_object(self::$version)) {
			self::$version = new Version(dirname(__FILE__) .'/../VERSION');
		}
		return(self::$version);
	}//end GetVersionObject()
	//-------------------------------------------------------------------------
}
