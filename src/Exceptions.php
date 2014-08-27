<?php

namespace brightzone\rexpro;

/**
 * RexPro PHP client Messages class
 * Builds and parses binary messages for communication with RexPro
 * 
 * @category DB
 * @package  Rexpro
 * @author   Dylan Millikin <dylan.millikin@brihtzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki/RexPro-Messages
 * @link     https://github.com/tinkerpop/rexster/wiki
 */
class Exceptions
{
	/**
	 * ERROR TYPES
	 */
	const INVALID_MESSAGE_ERROR = 0;
	const INVALID_SESSION_ERROR = 1;
	const SCRIPT_FAILURE_ERROR = 2;
	const AUTH_FAILURE_ERROR = 3;
	const GRAPH_CONFIG_ERROR = 4;
	const CHANNEL_CONFIG_ERROR = 5;
	const RESULT_SERIALIZATION_ERROR = 6;
	const UNKNOWN_ERROR = 7;
	
	/**
	 * @var int code for the current error
	 */
	public $code;
	
	/**
	 * @var string description for the current error
	 */
	public $description;
	
	/**
	 * Overriding construct to set error code and description
	 * 
	 * @param int    $code        code for the error
	 * @param string $description description for the error
	 */
	public function __construct($code,$description)
	{
		$this->code = $code;
		$this->description = $description;
	}
	
	/**
	 * Checks if an Error occured
	 * 
	 * @param string $unpacked Parsed response message from server
	 * 
	 * @return mixed FALSE if no error or an array with error message of type array(code,description);
	 */
	public static function checkError($unpacked)
	{
		if($unpacked['code'] !== 299 && $unpacked['code'] !== 200)
		{
			$error = new static($unpacked['code'],$unpacked['result']);
			return $error;
		}
		return FALSE;
	}
}
