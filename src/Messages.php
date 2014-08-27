<?php

namespace brightzone\rexpro;

/**
 * RexPro PHP client Messages class
 * Builds and parses binary messages for communication with RexPro
 * 
 * @category DB
 * @package  Rexpro
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki/RexPro-Messages
 */
class Messages
{
	/**
	 * Serializer types
	 */
	const SERIALIZER_MSGPACK = 0;
	const SERIALIZER_JSON = 1;
	
	
	/**
	 * Message types
	 */
	const ERROR = 0;
	const SESSION_REQUEST = 1;
	const SESSION_RESPONSE = 2;
	const SCRIPT_REQUEST = 3;
	const CONSOLE_SCRIPT_RESPONSE = 4;
	const SCRIPT_RESPONSE_MESSAGE = 5;
	const GRAPHSON_SCRIPT_RESPONSE = 6;
	
	/**
	 * Error types
	 */
	const CHANNEL_CONSOLE = 1;
	const CHANNEL_MSGPACK = 2;
	const CHANNEL_GRAPHSON = 3;
	
	/**
	 * @var string 16 byte request identifier
	 * Has no particular use at the moment but could be used in non-blocking mode
	 */
	public $requestUuid;
	
	/**
	 * @var string Most recently built binary message
	 */
	public $msgPack;
	
	/**
	 * @var string Most recently built binary message
	 */
	private $_serializer;

	/**
	 * Overriding construct to populate _serializer
	 *
	 * @param int $serializer serializer object to use for packing and unpacking of messages
	 * 
	 * @return void
	 */
	public function __construct($serializer)
	{
		$this->_serializer = $serializer;
	}
	
	/**
	 * Create and set request UUID
	 * 
	 * @return string the UUID
	 */
	public function createUuid()
	{
		return $this->requestUuid = Helper::createUuid();
	}
	
	/**
	 * Constructs full binary message (including outter envelope) For use in script execution
	 * 
	 * @param string $sessionUuid     session ID. This is not necessary at this stage but still included
	 * @param string $script          Gremlin (groovy flavored) script to run
	 * @param array  $bindings        Associated bindings
	 * @param array  $meta            Metadata to add to request message
	 * @param int    $protocolVersion Protocol to use, only current option is 0
	 * 
	 * @return string Returns binary data to be written to socket
	 */
	public function buildScriptMessage($sessionUuid, $op, $processor, $args)
	{
		//lets start by packing message
		$this->createUuid();
		
		//build message array
		$message = array(
				'requestId' => $this->requestUuid,
				'processor' => $processor,
				'op' => $op,
				'args' => $args
				);
		//serialize message
		$this->_serializer->serialize($message);
		$mimeType = $this->_serializer->getMimeType();

		$this->message =  pack('C',16).$mimeType.$message;
		return $this->message;	
	}	
	
	/**
	 * Parses full message (including outter envelope)
	 * 
	 * @param string $payload payload from the server response
	 * 
	 * @return array Array containing all results
	 */
	public function parse($payload, $isBinary)
	{
		if($isBinary)
		{
			//do nothing for now. Assume same as we sent with.
			list($mimeLength) = unpack('C',$payload[0]);
			$payload = substr($payload, 0, $mimeLength + 1);
		}
		return $this->_serializer->unserialize($payload);
	}
}
