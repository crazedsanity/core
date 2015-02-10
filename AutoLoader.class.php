<?php
//Original script (now modified) from: http://jes.st/2011/phpunit-bootstrap-and-autoloading-classes/
//TODO: make this non-static if possible.
/**
 * @codeCoverageIgnore
 */
class AutoLoader {
	 
	static private $classNames = array();
	 
	/**
	* Store the filename (sans extension) & full path of all ".php" files found
	*/
	public static function registerDirectory($dirName) {
		$di = new DirectoryIterator($dirName);
		foreach ($di as $file) {
//crazedsanity\cs_global::debug_print("\t". __METHOD__ .": processing $dirName (". $file->getFilename() .")... ",1);
			 
			if ($file->isDir() && !$file->isLink() && !$file->isDot()) {
				// recurse into directories other than a few special ones
				self::registerDirectory($file->getPathname());
			} 
			elseif (preg_match('~[Aa]bstract\.class~', $file->getFilename())) {
				//cs_version.abstract.class.php becomes "cs_versionAbstract"
				$className = preg_replace('~.abstract.class.php~i', '', $file->getFilename()) . "Abstract";
				AutoLoader::registerClass($className, $file->getPathname());
			}
			elseif (preg_match('~\.abstract\.php~i', $file->getFilename())) {
				//cs_version.abstract.class.php becomes "cs_versionAbstract"
				$className = preg_replace('~.abstract\.php~', '', $file->getFilename()) . "Abstract";
				AutoLoader::registerClass($className, $file->getPathname());
			}
			elseif((bool)preg_match('~\.interface\.php~', $file->getFilename())) {
				// Stores "iTemplate.interface.php"
				$className = preg_replace('~\.interface\.php~i', '', $file->getFilename());
				AutoLoader::registerClass($className, $file->getPathname());
			}
			elseif (substr($file->getFilename(), -10) === '.class.php') {
				$className = preg_replace('~.class.php~', '', $file->getFilename());#substr($file->getFilename(), 0, -10);
				AutoLoader::registerClass($className, $file->getPathname());
			}
			elseif (substr($file->getFilename(), -4) === '.php') {
				// save the class name / path of a .php file found
				$className = substr($file->getFilename(), 0, -4);
				AutoLoader::registerClass($className, $file->getPathname());
			}
		}
	}
	 
	public static function registerClass($className, $fileName) {
		AutoLoader::$classNames[$className] = $fileName;
	}
	 
	public static function loadClass($className) {
		// remove namespace from the className, if present.
		$bits = explode('\\', $className);
		if(count($bits) > 1) {
			$className = $bits[1];
		}
		if (isset(AutoLoader::$classNames[$className])) {
			require_once(AutoLoader::$classNames[$className]);
		}
	}
	 
}
 
spl_autoload_register(array('AutoLoader', 'loadClass'));
