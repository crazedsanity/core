<?php
/*
 * Created on Aug 19, 2009
 */

namespace crazedsanity;

/**
 * @codeCoverageIgnore
 */
abstract class baseAbstract {
	
	protected $gfObj;
	static public $version;
	public $isTest = FALSE;
	protected $versionFileLocation=null;
	private $fullVersionString;
	private $suffixList = array(
		'ALPHA', 	//very unstable
		'BETA', 	//kinda unstable, but probably useable
		'RC'		//all known bugs fixed, searching for unknown ones
	);
	
	
	//-------------------------------------------------------------------------
	public function __construct() {
		//$this->set_version_file_location(dirname(__FILE__) .'/../VERSION');
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
	
	
	
	//-------------------------------------------------------------------------
	public function load_schema($dbType, Database $db) {
		$file = dirname(__FILE__) .'/../setup/schema.'. $dbType .'.sql';
		try {
			$result = $db->run_sql_file($file);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .": failed to load schema file (". $file ."), DETAILS::: ". $e->getMessage());
		}
		return($result);
	}//end load_schema()
	//-------------------------------------------------------------------------
}
