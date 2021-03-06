<?php

namespace crazedsanity\core;

use \Exception;
use \InvalidArgumentException;
use \LogicException;

class ToolBox {
	
	static public $debugRemoveHr = 0;
	static public $debugPrintOpt = 0;
	
	//================================================================================================================
	/**
	 * Automatically selects either the header() function, or printing meta-refresh data for redirecting a browser.
	 */
	static public function conditional_header($url, $permRedir=FALSE) {
		if(count(func_get_args())==3) {
			self::debug_print(func_get_args(),1);
			throw new InvalidArgumentException("the 'exitAfter' argument is no longer valid");
		}
		
		if(isset($_SESSION) && is_array($_SESSION)) {
			//do some things to help protect against recursive redirects.
			if(isset($_SESSION['__conditional_header__'])) {
				$number = $_SESSION['__conditional_header__']['number'];
				$lastTime = $_SESSION['__conditional_header__']['last_time'];
				$timeSinceLast = microtime(true) - $lastTime;
				if(($timeSinceLast) <= 0.005 && $number > 5) {
					unset($_SESSION['__conditional_header__']);
					throw new Exception(__METHOD__ .": too many redirects (". $number .") in a short time (". $timeSinceLast ."), last url: (". $url .")");
				}
				else {
					$_SESSION['__conditional_header__']['number']++;
					$_SESSION['__conditional_header__']['last_time'] = microtime(true);
				}
			}
			else {
				$_SESSION['__conditional_header__'] = array(
					'last_time'	=> microtime(true),
					'number'		=> 0
				);
			}
		}
		
		if(!strlen($url)) {
			throw new InvalidArgumentException(__METHOD__ .": failed to specify URL (". $url .")");
		}
		else {
			if(headers_sent()) {
				//headers sent.  Use the meta redirect.
				print "
				<HTML>
				<HEAD>
				<TITLE>Redirect Page</TITLE>
				<META HTTP-EQUIV='refresh' content='0; URL=$url'>
				</HEAD>
				<a href=\"$url\"></a>
				</HTML>
				";
			}
			else {
				if($permRedir) {
					//NOTE: can't do much for permanent redirects if headers have already been sent.
					header("HTTP/1.1 301 Moved Permanently");
				}
				header("location:$url");
			}
		}
	}//end conditional_header()
	//================================================================================================================
	
	
	
	//================================================================================================================
	/**
	 * Basically, just a wrapper for create_list(), which returns a list or 
	 * an array of lists, depending upon what was requested.
	 * 
	 * @param $array		<array> list for the array...
	 * @param $style		<str,optional> what "style" it should be returned 
	 *						 as (select, update, etc).
	 * @param $separator	<str,optional> what separattes key from value: see each
	 * 							style for more information.
	 * @param $cleanString	<mixed,optional> clean the values in $array by sending it
	 * 							to cleanString(), with this as the second argument.
	 * @param $removeEmptyVals	<bool,optional> If $cleanString is an ARRAY and this
	 * 							evaluates as TRUE, indexes of $array whose values have
	 *							a length of 0 will be removed.
	 */
	static public function string_from_array($array,$style=NULL,$separator=NULL, $cleanString=NULL, $removeEmptyVals=FALSE) {
		
		$retval = NULL;
		//precheck... if it's not an array, kill it.
		if(!is_array($array)) {
			return(NULL);
		}
		
		//make sure $style is valid.
		$style = strtolower($style);
		
		if(is_array($array)) {
		
			//if $cleanString is an array, assume it's arrayIndex => cleanStringArg
			if(is_array($cleanString) && (!is_null($style) && (strlen($style)))) {
				$cleanStringArr = array_intersect_key($cleanString, $array);
				if(count($cleanStringArr) > 0 && is_array($cleanStringArr)) {
					foreach($cleanStringArr as $myIndex=>$myCleanStringArg) {
						if(($removeEmptyVals) && (strlen($array[$myIndex]) == 0)) {
							//remove the index.
							unset($array[$myIndex]);
						}
						else {
							//now format it properly.
							$myUseSqlQuotes = null;
							if(in_array($myCleanStringArg, array('int', 'integer', 'numeric', 'number', 'decimal', 'float'))) {
								$myUseSqlQuotes = false;
							}
							$array[$myIndex] = ToolBox::cleanString($array[$myIndex], $myCleanStringArg, $myUseSqlQuotes);
							unset($myUseSqlQuotes);
						}
					}
				}
			}
			switch($style) {
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				case "insert":
				if(!$separator) {
					$separator = " VALUES ";
				}
				//build temporary data...
				$tmp = array();
				foreach($array as $key=>$value) {
					@$tmp[0] = ToolBox::create_list($tmp[0], $key);
					//clean the string, if required.
					if(is_null($value)) {
						$value = "NULL";
					}
					elseif($cleanString) {
						//make sure it's not full of poo...
						$value = ToolBox::cleanString($value, "sql");
						#$value = "'". $value ."'";
					}
					@$tmp[1] = ToolBox::create_list($tmp[1], $value, ",", 1);
				}
				
				//make the final product.
				$retval = "(". $tmp[0] .")" . $separator . "(". $tmp[1] .")";
				
				break;
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				case "update":
				if(!$separator) {
					$separator = "=";
				}
				//build final product.
				foreach($array as $field=>$value) {
					$sqlQuotes = 1;
					if(($value === "NULL" || $value === NULL)) {
						$sqlQuotes = 0;
					}
					if($cleanString && !(preg_match('/^\'/',$value) && preg_match('/\'$/', $value))) {
						//make sure it doesn't have crap in it...
						$value = ToolBox::cleanString($value, "sql",$sqlQuotes);
					}
					if($value == "'") {
						//Fix possible SQL-injection.
						$value = "'\''";
					}
					elseif(!strlen($value)) {
						$value = "''";
					}
					$retval = ToolBox::create_list($retval, $field . $separator . $value);
				}
				break;
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				case "order":
				case "limit":
				//for creating the "limit 50 offset 35" part of a query... or at least using that "style".
				$separator = " ";
				//build final product.
				foreach($array as $field=>$value) {
					if($cleanString) {
						//make sure it doesn't have crap in it...
						$value = ToolBox::cleanString($value, "sql");
						$value = "'". $value ."'";
					}
					$retval = ToolBox::create_list($retval, $value, ", ");
				}
				if($style == "order" && !preg_match('/order by/', strtolower($retval))) {
					$retval = "ORDER BY ". $retval;
				}
				break;
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				case "select":
				//build final product.
				$separator = "=";
				foreach($array as $field=>$value) {
					
					//allow for tricksie things...
					/*
					 * Example: 
					 * string_from_array(array("y"=>3, "x" => array(1,2,3))); 
					 * 
					 * would yield: "y=3 AND (x=1 OR x=2 OR x=3)"
					 */
					$delimiter = "AND";
					if(is_array($value)) {
						//doing tricksie things!!!
						$retval = ToolBox::create_list($retval, $field ." IN (". ToolBox::string_from_array($value) .")",
								" $delimiter ");
					}
					else {
						//if there's already an operator ($separator), don't specify one.
						if(preg_match('/^[\(<=>]/', $value)) {
							$separator = NULL;
						}
						if($cleanString) {
							//make sure it doesn't have crap in it...
							$value = ToolBox::cleanString($value, "sql");	
						}
						if(isset($separator)) {
							$value = "'". $value ."'";	
						}
						$retval = ToolBox::create_list($retval, $field . $separator . $value, " $delimiter ");
					}
				}
				break;
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				
				
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				case "url":{
					//an array like "array('module'='todo','action'='view','ID'=164)" to "module=todo&action=view&ID=164"
					if(!$separator) {
						$separator = "&";
					}
					foreach($array as $field=>$value) {
						if($cleanString && !is_array($cleanString)) {
							$value = ToolBox::cleanString($value, $cleanString);
						}
						$retval = ToolBox::create_list($retval, "$field=$value", $separator);
					}
				}
				break;
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				
				
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				case "text_list":{
					if(is_null($separator)) {
						$separator = '=';
					}
					foreach($array as $field=>$value) {
						$retval = ToolBox::create_list($retval, $field . $separator . $value, "\n");
					}
				}
				break;
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				
				
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				case "html_list":{
					if(is_null($separator)) {
						$separator = '=';
					}
					foreach($array as $field=>$value) {
						$retval = ToolBox::create_list($retval, $field . $separator . $value, "<BR>\n");
					}
				}
				break;
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				
				
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				DEFAULT:
				if(!$separator) {
					$separator = ", ";
				}
				foreach($array as $field=>$value) {
					if($cleanString) {
						$value = ToolBox::cleanString($value, $cleanString);
					}
					$retval = ToolBox::create_list($retval, $value, $separator);
				}
				//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			}
		}
		else {
			//not an array.
			$retval = NULL;
		}
		
		return($retval);
	}//end string_from_array()
	//================================================================================================================
	
	
	
	//================================================================================================================
	/**
	 * Easy way of cleaning data using types/styles of cleaning, with optional quoting.
	 * 
	 * @param $cleanThis		(str) data to be cleaned
	 * @param $cleanType		(str,optional) how to clean the data.
	 * @param $sqlQuotes		(bool,optional) quote the string for SQL
	 * 
	 * @return (string)			Cleaned data.
	 */
	static public function cleanString($cleanThis=NULL, $cleanType="all",$sqlQuotes=0) {
		$cleanType = strtolower($cleanType);
		switch ($cleanType) {
			case "none":
				//nothing to see here (no cleaning wanted/needed).  Move along.
				$sqlQuotes = 0;
			break;
			
			case "query":
				/*
					replace \' with '
					gets rid of evil characters that might lead to SQL injection attacks.
					replace line-break characters
				*/
				$evilChars = array("\$", "%", "~", "*",">", "<", "-", "{", "}", "[", "]", ")", "(", "&", "#", "?", ".", "\,","\/","\\","\"","\|","!","^","+","`","\n","\r");
				$cleanThis = preg_replace("/\|/","",$cleanThis);
				$cleanThis = preg_replace("/\'/", "", $cleanThis);
				$cleanThis = str_replace($evilChars,"", $cleanThis);
				$cleanThis = stripslashes(addslashes($cleanThis));
			break;
			
			case "sql":
				$cleanThis = addslashes(stripslashes($cleanThis));
			break;
			
			
			case "varchar":
			case "text":
			case "sql_insert":
				/*
				 * This is for descriptive fields, where double quotes don't need to be escaped: in these 
				 * cases, escaping the double-quotes might lead to inserting something that looks different 
				 * than the original, but in fact is identical. 
				 */
				$cleanThis = addslashes(stripslashes($cleanThis));
				$cleanThis = preg_replace('/\\\\"/', '"', $cleanThis);
				$cleanThis = preg_replace("/'/", "\\\'", $cleanThis);
				
			break;
			
			
			case "sql92_insert":
				/*
				 * Just like 'sql_insert', except that single quotes are "delimited" by
				 * adding another single quote, which works *at least* with postgres & sqlite.
				 */
				$cleanThis = preg_replace("/'/", "''", $cleanThis);
				$cleanThis = preg_replace('/\\\\"/', '"', $cleanThis);
				$cleanThis = stripslashes($cleanThis);
				$sqlQuotes = 0;
			break;
			
			case "double_quote":
				//This will remove all double quotes from a string.
				$cleanThis = str_replace('"',"",$cleanThis);
			break;
	
			case "htmlspecial":
				/*
				This function is useful in preventing user-supplied text from containing HTML markup, such as in a message board or guest book application. 
					The translations performed are:
					  '&' (ampersand) becomes '&amp;'
					  '"' (double quote) becomes '&quot;'.
					  '<' (less than) becomes '&lt;'
					  '>' (greater than) becomes '&gt;' 
				*/
	
				$cleanThis = htmlspecialchars($cleanThis);
			break;
	
			case "htmlspecial_q":
			/*
				'&' (ampersand) becomes '&amp;'
				'"' (double quote) becomes '&quot;'.
				''' (single quote) becomes '&#039;'.
				'<' (less than) becomes '&lt;'
				'>' (greater than) becomes '&gt;
			*/
				$cleanThis = htmlspecialchars($cleanThis,ENT_QUOTES);
			break;
	
			case "htmlspecial_nq":
			/*
				'&' (ampersand) becomes '&amp;'
				'<' (less than) becomes '&lt;'
				'>' (greater than) becomes '&gt;
			*/
				$cleanThis = htmlspecialchars($cleanThis,ENT_NOQUOTES);
			break;
	
			case "htmlentity":
				/*	
					Convert all applicable text to its html entity
					Will convert double-quotes and leave single-quotes alone
				*/
				$cleanThis = htmlentities(html_entity_decode($cleanThis));
			break;
	
			case "htmlentity_plus_brackets":
				/*	
					Just like htmlentity, but also converts "{" and "}" (prevents template 
					from being incorrectly parse).
					Also converts "{" and "}" to their html entity.
				*/
				$cleanThis = htmlentities(html_entity_decode($cleanThis));
				$cleanThis = str_replace('$', '&#36;', $cleanThis);
				$cleanThis = str_replace('{', '&#123;', $cleanThis);
				$cleanThis = str_replace('}', '&#125;', $cleanThis);
			break;
	
			case "double_entity":
				//Removed double quotes, then calls html_entities on it.
				$cleanThis = str_replace('"',"",$cleanThis);
				$cleanThis = htmlentities(html_entity_decode($cleanThis));
			break;
		
			case "meta":
				// Returns a version of str with a backslash character (\) before every character that is among these:
				// . \\ + * ? [ ^ ] ( $ )
				$cleanThis = quotemeta($cleanThis);
			break;
	
			case "email":
				//Remove all characters that aren't allowed in an email address.
				$cleanThis = preg_replace("/[^A-Za-z0-9\._@-]/","",$cleanThis);
			break;
	
			case "email_plus":
			case "email_plus_spaces":
				//Remove all characters that aren't allowed in an email address.
				$cleanThis = preg_replace("/[^A-Za-z0-9\ \._@:-]/","",$cleanThis);
			break;
	
			case "phone_fax":
				//Remove everything that's not numeric or +()-   example: +1 (555)-555-2020 is valid
				$cleanThis = preg_replace("/[^0-9-+() ]/","",$cleanThis);
			break;
			
			case "int":
			case "integer":
			case "numeric":
			case "number":
				//Remove everything that's not numeric.
				if(is_null($cleanThis)) {
					$cleanThis = "NULL";
					$sqlQuotes = 0;
				}
				else {
					$cleanThis = preg_replace("/[^0-9\-]/","",$cleanThis);
				}
			break;
			
			case "decimal":
			case "float":
				//same as integer only the decimal point is allowed
				$cleanThis = preg_replace("/[^0-9\.]/","",$cleanThis);
			break;
			
			case "name":
			case "names":
				//allows only things in the "alpha" case and single quotes.
				$cleanThis = preg_replace("/[^a-zA-Z']/", "", $cleanThis);
			break;
	
			case "alpha":
				//Removes anything that's not English a-zA-Z
				$cleanThis = preg_replace("/[^a-zA-Z]/","",$cleanThis);
			break;
			
			case "bool":
			case "boolean":
				//makes it either T or F (gotta lower the string & only check the first char to ensure accurate results).
				$cleanThis = ToolBox::interpret_bool($cleanThis, array('f', 't'));
			break;
			
			case "date":
				$cleanThis = preg_replace("/[^0-9\-]/","",$cleanThis);
				break;
				
			case "datetime":
				$cleanThis=preg_replace("/[^A-Za-z0-9\/: \-\'\.]/","",$cleanThis);
			break;
				
			case "all":
			default:
				// 1. Remove all naughty characters we can think of except alphanumeric.
				$cleanThis = preg_replace("/[^A-Za-z0-9]/","",$cleanThis);
			break;
	
		}
		if($sqlQuotes) {
			$cleanThis = "'". $cleanThis ."'";
		}
		return $cleanThis;
	}//end cleanString()
	//================================================================================================================
	
	
	
	
	//================================================================================================================
	/**
	 * Returns a list delimited by the given delimiter.  Does the work of checking if the given variable has data
	 * in it already, that needs to be added to, vs. setting the variable with the new content.
	 */
	static public function create_list($string=NULL, $addThis=NULL, $delimiter=", ") {
		if(count(func_get_args()) > 3) {
			trigger_error(__METHOD__ .": argument 'useSqlQuotes' is deprecated and no longer utilized");
		}
		if(strlen($string)) {
			$retVal = $string . $delimiter . $addThis;
		}
		else {
			$retVal = $addThis;
		}
	
		return($retVal);
	} //end create_list()
	//================================================================================================================
	
	
	
	//================================================================================================================
	/**
	 * A way of printing out human-readable information, especially arrays & objects, either to a web browser or via
	 * the command line.
	 * 
	 * @param $input		(mixed,optional) data to print/return
	 * @param $printItForMe	(bool,optional) whether it should be printed or just returned.
	 * 
	 * @return (string)		printed data.
	 * @codeCoverageIgnore
	 */
	static public function debug_print($input=NULL, $printItForMe=NULL, $removeHR=NULL, $usePreTags=true) {
		if(!is_numeric($removeHR)) {
			$removeHR = ToolBox::$debugRemoveHr;
		}

		if(!is_numeric($printItForMe)) {
			$printItForMe = ToolBox::$debugPrintOpt;
		}
		
		ob_start();
		print_r($input);
		$output = ob_get_contents();
		ob_end_clean();
		
		if($usePreTags === true) {
			$output = "<pre class='cs debug'>$output</pre>";
		}
		
		if(!isset($_SERVER['SERVER_PROTOCOL']) || !$_SERVER['SERVER_PROTOCOL']) {
			$output = strip_tags($output);
			$hrString = "\n***************************************************************\n";
		}
		else {
			$hrString = "<hr>";
		}
		if($removeHR) {
			$hrString = NULL;;
		}
		
		if($printItForMe) {
			print "$output". $hrString ."\n";
		}
	
		return($output);
	} //end debug_print()
	//================================================================================================================
	
	
	
	//================================================================================================================
	static function swapValue(&$value, $c1, $c2) {
		if(!$value) {
			$value = $c1;
		}
	
		/* choose the next color */
		if($value == "$c1") {
			$value = "$c2";
		}
		else {
			$value = "$c1";
		}
	
		return($value);
	}//end swapValue()
	//================================================================================================================



	//---------------------------------------------------------------------------------------------
	/**
	 * Using the given template, it will replace each index (in $repArr) with it's value: each
	 * var to be replaced must begin the given begin & end delimiters.
	 * 
	 * @param $template		(str) Data to perform the replacements on.
	 * @param $repArr		(array) Array of name=>value pairs, where name is to be replaced with value.
	 * @param $b			(str,optional) beginning delimiter.
	 * @param $e			(str,optional) ending delimiter.
	 */
	static public function mini_parser($template, $repArr, $b='%%', $e='%%') {
		if(!isset($b) OR !isset($e)){
			$b="{";
			$e="}";
		}
		
		if(is_array($repArr)) {
			foreach($repArr as $key=>$value) {
				//run the replacements.
				$key = "$b" . $key . "$e";
				$template = str_replace("$key", $value, $template);
			}
		}
		else {
			throw new exception(__METHOD__ .": no replacement array passed, or array was empty");
		}

		return($template);
	}//end mini_parser()
	//---------------------------------------------------------------------------------------------
	
	
	//---------------------------------------------------------------------------------------------
	/**
	 * Takes the given string & truncates it so the final string is the given 
	 * maximum length.  Optionally adds a chunk of text to the end, and also 
	 * optionally STRICTLY truncates (non-strict means the endString will be 
	 * added blindly, while strict means the length of the endString will be 
	 * subtracted from the total length, so the final string is EXACTLY the 
	 * given length or shorter).
	 * 
	 * @param string		(str) the string to truncate
	 * @param $maxLength	(int) maximum length for the result.
	 * @param $endString	(str,optional) this is added to the end of the 
	 * 							truncated string, if it exceeds $maxLength
	 * @param $strict		(bool,optional) if non-strict, the length of 
	 * 							the return would be $maxLength + length($endString)
	 */
	static function truncate_string($string,$maxLength,$endString="...",$strict=FALSE) {
	
		//determine if it's even worth truncating.
		if(is_string($string) && is_numeric($maxLength) && $maxLength > 0) {
			$strLength = strlen($string);
			if($strLength <= $maxLength) {
				//no need to truncate.
				$retval = $string;
			}
			else {
				//actually needs to be truncated...
				if($strict) {
					$trueMaxLength = $maxLength - strlen($endString);
				}
				else {
					$trueMaxLength = $maxLength;
				}
				
				//rip the first ($trueMaxLength) characters from string, append $endString, and go.
				$tmp = substr($string,0,$trueMaxLength);
				$retval = $tmp . $endString;
			}
		}
		else {
			$retval = $string;
		}
		
		return($retval);
		
	}//end truncate_string()
	//---------------------------------------------------------------------------------------------
	
	
	
	//##########################################################################
	static public function array_as_option_list(array $data, $checkedValue=NULL, $type="select", $useTemplateString=NULL, array $repArr=NULL) {
		$typeArr = array (
			"select"	=> "selected",
			"radio"		=> "checked",
			"checkbox"	=> "checked"
		);
		
		$myType = $typeArr[$type];
		if(is_null($useTemplateString)) {
			//
			$useTemplateString = "\t\t<option value='%%value%%'%%selectedString%%>%%display%%</option>";
		}
		
		$retval = "";
		foreach($data as $value=>$display) {
			//see if it's the value that's been selected.
			$selectedString = "";
			if($value == $checkedValue || $display == $checkedValue) {
				//yep, it's selected.
				$selectedString = " ". $myType;
			}
			
			//create the string.
			$myRepArr = array(
				'value'				=> $value,
				'display'			=> $display,
				'selectedString'	=> $selectedString
			);
			if(is_array($repArr) && is_array($repArr[$value])) {
				//merge the arrays.
				$myRepArr = array_merge($repArr[$value], $myRepArr);
			}
			$addThis = ToolBox::mini_parser($useTemplateString, $myRepArr, "%%", "%%");
			$retval = ToolBox::create_list($retval, $addThis, "\n");
		}
		
		return($retval);
	}//end array_as_option_list()
	//##########################################################################
	
	
	
	//##########################################################################
	static public function interpret_bool($interpretThis, array $trueFalseMapper=null) {
		$interpretThis = preg_replace('/ /', '', $interpretThis);
		if(is_array($trueFalseMapper)) {
			if(count($trueFalseMapper) == 2 && isset($trueFalseMapper[0]) && isset($trueFalseMapper[1])) {
				$realVals = $trueFalseMapper;
			}
			else {
				throw new \InvalidArgumentException(__METHOD__ .": invalid true/false map");
			}
		}
		else {
			//set an array that defines what "0" and "1" return.
			$realVals = array(
				0 => false,
				1 => true
			);
		}
		
		//now figure out the value to return.
		if(is_numeric($interpretThis)) {
			if(preg_match('/\.[0-9]{1,}/', $interpretThis)) {
				//if it is a decimal number, remove the dot (i.e. "0.000001" -> "0000001" -> 1)
				$interpretThis = str_replace('.', '', $interpretThis);
			}
			settype($interpretThis, 'integer');
			if($interpretThis == 0) {
				$index=0;
			}
			else {
				$index=1;
			}
		}
		elseif(is_bool($interpretThis)) {
			if($interpretThis == true) {
				$index=1;
			}
			else {
				$index=0;
			}
		}
		elseif(preg_match('/^true$/i', $interpretThis) || preg_match('/^false$/', $interpretThis) || preg_match("/^[tf]$/", $interpretThis)) {
			if(preg_match('/^true$/i', $interpretThis) || preg_match('/^t$/', $interpretThis)) {
				$index=1;
			}
			else {
				$index=0;
			}
		}
		else {
			//straight-up PHP if/else evaluation.
			if($interpretThis) {
				$index=1;
			}
			else {
				$index=0;
			}
		}
		
		return($realVals[$index]);
	}//end interpret_bool()
	//##########################################################################
	
	
	//##########################################################################
	/** @codeCoverageIgnore
	 */
	static public function debug_var_dump($data, $printItForMe=null, $removeHr=null) {
		
		ob_start();
		var_dump($data);
		$printThis = ob_get_contents();
		ob_end_clean();
		
		return(ToolBox::debug_print($printThis, $printItForMe, $removeHr));
	}//end debug_var_dump()
	//##########################################################################
	
	
	
	//------------------------------------------------------------------------
	/**
	 * Removes all the crap from the url, so we can figure out what section we
	 * 	need to load templates & includes for.
	 */
	static public function clean_url($url=NULL) {
		//make sure we've still got something valid to work with.
		if(strlen($url)) {
			//if there's an "APPURL" constant, drop that from the url.
			if(defined('APPURL') && strlen(constant('APPURL'))) {
				$dropThis = preg_replace('/^\//', '', constant('APPURL'));
				$dropThis = preg_replace('/\//', '\\/', $dropThis);
				$url = preg_replace('/^'. $dropThis .'/', '', $url);
			}
			
			//check the string to make sure it doesn't begin with a "/"
			if($url[0] == '/') {
				$url = substr($url, 1, strlen($url));
			}
	
			//check the last char for a "/"...
			if($url[strlen($url) -1] == '/') {
				//last char is a '/'... kill it.
				$url = substr($url, 0, strlen($url) -1);
			}
	
			//if we've been sent a query, kill it off the string...
			if(preg_match('/\?/', $url)) {
				$url = preg_split('/\?/', $url);
				$url = $url[0];
			}
	
			if(preg_match("/\./", $url)) {
				//disregard file extensions, but keep everything else...
				//	i.e. "index.php/yermom.html" becomes "index/yermom"
				$tArr = explode('/', $url);
				$tUrl = null;
				foreach($tArr as $tUrlPart) {
					$temp = explode(".", $tUrlPart);
					if(strlen($temp[0])) {
						$tUrlPart = $temp[0];
					}
					$tUrl = ToolBox::create_list($tUrl, $tUrlPart, '/');
				}
				$url = $tUrl;
			}
		}
		else {
			$url = null;
		}

		return($url);
	}//end clean_url()
	//------------------------------------------------------------------------



	//========================================================================================
	/**
	 * Fix a path that contains "../".
	 *
	 * EXAMPLE: changes "/home/user/blah/blah/../../test" into "/home/user/test"
	 */
	public static function resolve_path_with_dots($path) {

		if(!is_null($path) && is_string($path) && strlen($path)) {
			while(preg_match('/\/\//', $path)) {
				$path = preg_replace('/\/\//', '/', $path);
			}
			$retval = $path;
			if(preg_match('/\./', $path)) {

				$isAbsolute = FALSE;
				if(preg_match('/^\//', $path)) {
					$isAbsolute = TRUE;
					$path = preg_replace('/^\//', '', $path);
				}
				$pieces = explode('/', $path);

				$finalPieces = array();
				for($i=0; $i < count($pieces); $i++) {
					$dirName = $pieces[$i];
					if($dirName == '.') {
						//do nothing; don't bother appending.
					}
					elseif($dirName == '..') {
						array_pop($finalPieces);
					}
					else {
						$finalPieces[] = $dirName;
					}
				}

				//$retval = cs_global::string_from_array($finalPieces, NULL, '/');
				$retval = implode('/', $finalPieces);
				if($isAbsolute) {
					$retval = '/'. $retval;
				}
			}
		}
		else {
			throw new \InvalidArgumentException(__METHOD__ .": invalid path (". $path .")");
		}

		if(is_null($retval)) {
			throw new \LogicException(__METHOD__ .": returning NULL for path (". $path .")");
		}
		return($retval);
	}//end resolve_path_with_dots()
	//========================================================================================


}//end cs_globalFunctions{}
