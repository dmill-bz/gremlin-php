<?php
/**
 * RexPro PHP client Exceptions class
 * Builds Error object
 * 
 * @author Dylan Millikin <dylan.millikin@gmail.com>
 * @link https://github.com/tinkerpop/rexster/wiki/RexPro-Messages
 */

namespace yii\helpers\rexster;

class Exception
{
	/**
	 * Error message types
	 */
	const INVALID_MESSAGE_ERROR = 0;
    const INVALID_SESSION_ERROR = 1;
    const SCRIPT_FAILURE_ERROR = 2;
    const AUTH_FAILURE_ERROR = 3;
    const GRAPH_CONFIG_ERROR = 4;
    const CHANNEL_CONFIG_ERROR = 5;
    const RESULT_SERIALIZATION_ERROR = 6;
	
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
	 * Checks if the un packed message contains an error
	 * 
	 * @return mixed false if there is no error or an Exceptions object if there is an error 
	 */
	public static function checkError($unpacked)
	{
		if($unpacked[1]==0)
		{
			switch($unpacked[3][2]['flag'])
			{
				
				case self::INVALID_MESSAGE_ERROR:
					$err = "The message sent to the RexPro Server was malformed.";
					break;
				case self::INVALID_SESSION_ERROR:
					$err = "A session was requested that has either expired or no longer exists.";
					break;
				case self::SCRIPT_FAILURE_ERROR:
					$err = "A script failed to execute (likely cause is syntax error).";
					break;
				case self::AUTH_FAILURE_ERROR:
					$err = "Invalid username/password if authentication is turned on.";
					break;
				case self::GRAPH_CONFIG_ERROR:
					$err = "A graph requested via 'graphName' meta attribute did not exist";
					break;
				case self::CHANNEL_CONFIG_ERROR:
					$err = "The channel requested did not exist or the channel was changed after being established on the session. ";
					break;
				case self::RESULT_SERIALIZATION_ERROR:
					$err = "The result or an item in the bindings could not be serialized properly.";
					break;
				
			}
			return new Exceptions($unpacked[3][2]['flag'],$err.' > '.$unpacked[3][3]);
		}
		else
			return false;
		
	}
	
}
