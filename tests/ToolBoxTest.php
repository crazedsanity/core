<?php
/*
 * Created on Jan 13, 2009
 */

use crazedsanity\baseAbstract;
use crazedsanity\Lockfile;
use crazedsanity\ToolBox;


//=============================================================================
class TestOfToolBox extends PHPUnit_Framework_TestCase {
	
	//-------------------------------------------------------------------------
	public function setUp() {
	}//end setUp()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function tearDown() {
	}//end tearDown()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function test_swapValue() {
		$original = 'two';
		$this->assertEquals('one', ToolBox::swapValue($original, 'one', 'two'));
		$this->assertEquals('two', ToolBox::swapValue($original, 'one', 'two'));
	}
	//-------------------------------------------------------------------------
	
	
	public function test_conditionalHeader() {
		$_SESSION = array();
		ob_start();
		ToolBox::conditional_header('/');
		$checkThis = ob_get_contents();
		ob_end_clean();
		$this->assertTrue(strlen($checkThis) > 0);
	}

	/**
	 * @expectedException InvalidArgumentException
	*/
	public function test_conditionalHeaderWithNoUrl() {
		ToolBox::conditional_header(null);
	}
	
	public function test_truncateString() {
		$testString = "1234567890abcdefghijklmnopqrstuvwxyz";
		$this->assertEquals('1234567890a...', ToolBox::truncate_string($testString, 11));
		$this->assertEquals('1234567890a...', ToolBox::truncate_string($testString, 11, '...'));
		$this->assertEquals('1234567890a test', ToolBox::truncate_string($testString, 11, ' test'));
		$this->assertEquals('12345678...', ToolBox::truncate_string($testString, 11, '...', true));
		$this->assertEquals('1234567890a...', ToolBox::truncate_string($testString, 11, '...', false));
	}
	
	public function test_create_list() {
		$this->assertEquals("first", ToolBox::create_list("", "first"));
		$this->assertEquals("first, second", ToolBox::create_list("first", "second"));
		$this->assertEquals("1-2", ToolBox::create_list("1", "2", "-"));
	}
	
	/**
	 * TODO: this one needs a LOT more testing, or the underlying function needs to be 
	 * refactored.
	 */
	public function test_stringFromArray() {
		$this->assertEquals('one, two, three', ToolBox::string_from_array(array('one','two','three')));
	}
	
	public function test_cleanString() {
	}

	public function test_mini_parser() {
		$replacements = array(	
			'1'	=> 't',
			'2'	=> 'e',
			'3'	=> 's',
		);
		$this->assertEquals("test", ToolBox::mini_parser('%1%%2%%3%%1%', $replacements, '%', '%'));
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function test_interpret_bool__badArgs() {
		ToolBox::interpret_bool(true, array());
	}
	
	public function test_interpret_bool() {
		$this->assertEquals(true, ToolBox::interpret_bool('1'));
		$this->assertEquals(true, ToolBox::interpret_bool('1', array(false, true)));
		$this->assertEquals(true, ToolBox::interpret_bool('1', array('0'=>false, '1'=>true)));
		$this->assertEquals(false, ToolBox::interpret_bool('1', array(true, false)));
	}
	
	public function test_whereCalled() {
		$data = crazedsanity\get_where_called();
		$this->assertFalse(is_null($data));
	}
	
}//end TestOfToolBox
//=============================================================================


