<?php

namespace rexpro;

/**
 * RexPro PHP client Messages class
 * Builds and parses binary messages for communication with RexPro
 * 
 * @category DB
 * @package  rexproPhp
 * @author   Dylan Millikin <dylan.millikin@brihtzone.fr>
 * @link     https://github.com/tinkerpop/rexster/wiki/RexPro-Messages
 * @link     https://github.com/tinkerpop/rexster/wiki
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
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
	 * @return mixed false if no error or an array with error message of type array(code,description);
	 */
	public static function checkError($unpacked)
	{
		if($unpacked[2]==0)
		{
			$error_array = array(
				self::INVALID_MESSAGE_ERROR		=> "The message sent to the RexPro Server was malformed.",
				self::INVALID_SESSION_ERROR		=> "A session was requested that has either expired or no longer exists.",
				self::SCRIPT_FAILURE_ERROR		=> "A script failed to execute (likely cause is syntax error).",
				self::AUTH_FAILURE_ERROR		=> "Invalid username/password if authentication is turned on.",
				self::GRAPH_CONFIG_ERROR		=> "A graph requested via 'graphName' meta attribute did not exist",
				self::CHANNEL_CONFIG_ERROR		=> "The channel requested did not exist or the channel was changed after being established on the session. ",
				self::RESULT_SERIALIZATION_ERROR => "The result or an item in the bindings could not be serialized properly.",
				self::UNKNOWN_ERROR				=> "Unknown error returned by server.",
			);
			
			if(isset($error_array[$unpacked[4][2]['flag']]))
			{
				$err = $error_array[$unpacked[4][2]['flag']];
			}
			else
			{
				$err = "Unknown error type.";
			}
			
			return new self($unpacked[4][2]['flag'],$err.' > '.$unpacked[4][3]);
		}
		else
			return false;
		
	}
	
}
