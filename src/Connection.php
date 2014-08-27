<?php

namespace brightzone\rexpro;

require_once('vendor/autoload.php');

use \brightzone\rexpro\Connection;
use \brightzone\rexpro\Messages;
use \brightzone\rexpro\Exceptions;
use \brightzone\rexpro\Helper;
use \brightzone\rexpro\serializers\Msgpack;
use \brightzone\rexpro\serializers\Json;

/**
 * RexPro PHP client Connection class
 * 
 * Example of use:
 *
 * ~~~
 * $connection = new Connection;
 * $connection->open('localhost:8184','tinkergraph'); //can return FALSE on error
 * $connection->script = 'g.V';
 * $resultSet = $connection->runScript(); //returns array with results or FALSE on error
 * //error handling: (It is worth noting that open() can also return errors in this way)
 * if($resultSet === FALSE)
 * {
 * 		$errorCode = $connection->error->code;
 * 		$errorDescription = $connection->error->description;
 * 		//etc.
 * }
 * ~~~
 * 
 * @category DB
 * @package  Rexpro
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki
 */
class Connection
{
	/**
	 * @var string Contains the host information required to connect to the database.
	 * format: server:port
	 * If [port] is ommited 8184 will be assumed
	 * 
	 * Example: localhost:8184
	 */
	public $host;
	
	/**
	 * @var string the username for establishing DB connection. Defaults to NULL.
	 */
	public $username;
	
	/**
	 * @var string the password for establishing DB connection. Defaults to NULL.
	 */
	public $password;
	
	/**
	 * @var string The session ID for this connection. 
	 * Session ID allows us to access variable bindings made in previous requests. It is binary data
	 */
	public $sessionUuid;
	
	/**
	 * @var float timeout to use for connection to Rexster. If not set the timeout set in php.ini will be used: ini_get("default_socket_timeout")
	 */
	public $timeout;

	/**
	 * @var string Gremlin script to be run (groovy flavor)
	 * See link for more details on the language
	 * @link https://github.com/tinkerpop/gremlin/wiki/Using-Gremlin-through-Groovy
	 */
	public $script;
	
	/**
	 * @var array Bindings for the gremlin script
	 */
	public $bindings;
	
	/**
	 * @var bool tells us if we're inside a transaction
	 */
	private $_inTransaction = FALSE;
	
	/**
	 * @var array -Complete- parsed response message from the server. Also contains headers and additional data
	 */
	public $response;
	
	/**
	 * @var resource rexpro socket connection
	 */
	private $_socket;
	
	/**
	 * @var resource rexpro socket connection
	 */
	private $_serializer;
	
	/**
	 * @var Exceptions contains error information 
	 * @see Exceptions
	 */
	public $error;


	/**
	 * Overloading constructor to set the proper default serializer
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->_serializer = new Json;
	}
	
	/**
	 * Connects to socket and starts a session on RexPro
	 * 
	 * @param string $host        host and port seperated by ":"
	 * @param string $graph       graph to load into session. defaults to tinkergraph
	 * @param string $username    username for authentification
	 * @param string $password    password to use for authentification
	 * @param string $graphObject Graph object name. defaults to 'g'
	 * 
	 * @return bool TRUE on success FALSE on error
	 */
	public function open($host='localhost:8182', $username = NULL, $password = NULL, $origin = NULL)
	{
		if($this->_socket === NULL)
		{
			$this->error = NULL;
			$this->username = $username;
			$this->password = $password;
			$this->host = strpos($host, ':')===FALSE ? $host.':8182': $host;
			
			if(!$this->connectSocket())
			{
				return FALSE;
			}

			$key = base64_encode(Helper::generateRandomString(16, false, true));				
			$header = "GET /gremlin HTTP/1.1\r\n";
			$header.= "Upgrade: websocket\r\n";
			$header.= "Connection: Upgrade\r\n";
			$header.= "Sec-WebSocket-Key: " . $key . "\r\n";
			$header.= "Host: ".$this->host."\r\n";
			if($origin !== NULL)
			{
				$header.= "Sec-WebSocket-Origin: http://".$this->host."\r\n";
			}
			$header.= "Sec-WebSocket-Version: 13\r\n\r\n";

			@fwrite($this->_socket, $header); 
			$response = @fread($this->_socket, 1500);		

			preg_match('#Sec-WebSocket-Accept:\s(.*)$#mU', $response, $matches);
			$keyAccept = trim($matches[1]);
			$expectedResonse = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

			return ($keyAccept === $expectedResonse) ? TRUE : FALSE;
		}
	}
	
	/**
	 * Sends binary data over socket
	 * 
	 * @param Messages $msg Object containing the message to send
	 * 
	 * @return bool TRUE if success FALSE on error
	 */
	public function send($msg)
	{	
		$write = @fwrite($this->_socket, $this->webSocketPack($msg->message));
		if($write === FALSE)
		{
			$this->error = new Exceptions(0, 'Could not write to socket');
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Recieves binary data over socket and parses it
	 * 
	 * @return mixed unpacked message if TRUE, FALSE on error
	 */
	public function getResponse()
	{
		$fullData = [];
		do
		{
			$data = $head = @stream_get_contents($this->_socket, 1);
			$head = unpack('C*', $head);

			$isBinary = ($head & 15)==2;
			
			$data .= $maskAndLength = @stream_get_contents($this->_socket, 1);
			list($maskAndLength) = array_values(unpack('C', $maskAndLength));

			//set first bit to 0
			$length =  $maskAndLength & 127;

			$maskSet = FALSE;
			if($maskAndLength & 128)
			{
				$maskSet = TRUE;
			}
			
			if($length == 126 )
			{
				$data .= $payloadLength = @stream_get_contents($this->_socket, 2);
				list($payloadLength) = array_values(unpack('n', $payloadLength)); // S for 16 bit int
			}
			elseif($length == 127 )
			{
				$data .= $payloadLength = @stream_get_contents($this->_socket, 8);
				//lets save it as two 32 bit integers and reatach using bitwise operators
				//we do this as pack doesn't support 64 bit packing/unpacking.
				list($higher, $lower) = array_values(unpack('N2',$payloadLength));
				$payloadLength = $higher << 32 | $lower;
			}
			else
			{
				$payloadLength = $length;
			}

			//get mask
			if($maskSet)
			{
				$data .= $mask = @stream_get_contents($this->_socket, 4);
			}

			//get payload
			$data .= $payload = @stream_get_contents($this->_socket, $payloadLength);

			if($maskSet)
			{
				//unmask payload
				$payload = $this->unmask($payload, $mask);
			}
			
			if($head === FALSE || $payload === FALSE || $maskAndLength === FALSE
			|| $payloadLength === FALSE || ($maskSet === TRUE && $mask === FALSE) 
			)
			{
				$this->error = new Exceptions(0, 'Could not stream contents');
				return FALSE;
			} 
			if(empty($head) || empty($payload) || empty($maskAndLength)
			|| empty($payloadLength) || ($maskSet === TRUE && empty($mask) ))
			{
				$this->error = new Exceptions(0, 'Empty reply. Most likely the result of an irregular request. (Check custom Meta, or lack of in the case of a non-isolated query)');
				return FALSE;
			} 

			//now that we have the payload lets parse it
			$message = new Messages($this->_serializer);

			$unpacked = $message->parse($payload,$isBinary);

			//TODO ERROR HANDLING
			$error = Exceptions::checkError($unpacked);
			if( $error !== FALSE)
			{
				$this->_error = $error;
				return FALSE;
			}

			if($unpacked['type'] !== 0) // will have to change with server upgrade.
			{
				$fullData = array_merge($fullData, $unpacked['result']);
			}
		}
		while($unpacked['code'] !== 299);
		
		return $fullData;
	}
	
	/**
	 * Opens socket
	 * 
	 * @return bool TRUE on success FALSE on error
	 */
	protected function connectSocket()
	{	
		$this->_socket = @stream_socket_client(
									'tcp://'.$this->host,
									$errno, 
									$errorMessage,
									$this->timeout ? $this->timeout : ini_get("default_socket_timeout")
									);
		if(!$this->_socket)
		{
			$this->error = new Exceptions($errno, $errorMessage);
			return FALSE;
		}	
			
		return TRUE;
	}
	
	/**
	 * runs a gremlin script against the graph
	 * 
	 * @param bool $inSession whether or not to run this script without session. 
	 * @param bool $isolated  whether or not to run this script without acces to variable binds made previously. 
	 * 
	 * @return mixed message on success FALSE on error.
	 */
	public function runScript($inSession = TRUE, $op = 'eval', $processor = 'session', $args = [])
	{	
		//lets make a script message:
		$msg = new Messages($this->_serializer);

		if($inSession && !isset($this->sessionUuid))
		{
			$processor = 'session';
			$this->sessionUuid = Helper::createUuid();
		}
		
		$arguments = array(	'session'=>$inSession?$this->sessionUuid:'00000000-0000-0000-0000-000000000000',
							'gremlin'=>$this->script,
							'bindings'=>$this->bindings,
							'language'=>"gremlin-groovy");

		if(!empty($args))
		{
			$arguments = array_merge($arguments, $args);
		}
		
		$msg->buildScriptMessage(	($inSession ? $this->sessionUuid : NULL),
									$op,
									$processor,
									$arguments);
		
		//reset script information after building
		$this->bindings = NULL;
		$this->script = NULL;
		$this->response = NULL;
		print_r($msg);
		if(!$this->send($msg))
		{
			return FALSE;
		}
		//lets get the response
		$response = $this->getResponse();
		
		if($response === FALSE)
		{
			return FALSE;
		}
		
		$this->response = $response;
		return $this->response;
	}
	
	/**
	 * Close connection to server
	 * This closes the current session on the server then closes the socket
	 * 
	 * @return bool TRUE on success FALSE on error
	 */
	public function close()
	{
		if($this->_socket !== NULL)
		{
			$this->error = NULL;
			//lets make opening session message:
			$write = @fwrite($this->_socket, $this->webSocketPack("",'close'));
			if($write === FALSE)
			{
				$this->error = new Exceptions(0, 'Could not write to socket');
				return FALSE;
			}
			@stream_socket_shutdown($this->_socket, STREAM_SHUT_RDWR); //ignore error
			$this->_socket = NULL;
			$this->sessionUuid = NULL;
			
			return TRUE;
		}
	}
	
	/**
	 * Binds a value to be used inside gremlin script
	 * 
	 * @param string $bind  The binding name
	 * @param mixed  $value the value that the binding name refers to
	 * 
	 * @return void
	 */
	public function bindValue($bind,$value)
	{
		if($this->bindings === NULL)
		{
			$this->bindings = array();
		}
		$this->bindings[$bind]=$value;
	}
	
	/**
	 * Start a transaction
	 * 
	 * @return bool TRUE on success FALSE on failure
	 */
	public function transactionStart()
	{
		if($this->_inTransaction)
		{
			$this->error = array(0,'already in transaction');
			$this->script='g.tx().rollback()';
			$this->runScript();
			return FALSE;
		}
		//~ $this->script='g.tx().open()';
		//~ $this->runScript();
		$this->_inTransaction = TRUE;
		return TRUE;
	}
	
	/**
	 * End a transaction
	 * 
	 * @param bool $success should the transaction commit or revert changes
	 * 
	 * @return bool TRUE on success FALSE on failure.
	 */
	public function transactionStop($success = TRUE)
	{
		if(!$this->_inTransaction || !isset($this->sessionUuid))
		{
			$this->error = array(0,'No ongoing transaction');
			return FALSE;
		}
		//send message to stop transaction
		if($success)
		{
			$this->script='g.tx().commit()';
		}
		else
		{
			$this->script='g.tx().rollback()';
		}
		
		$this->runScript();
		
		$this->_inTransaction = FALSE;
		return TRUE;
	}
	
	/**
	 * Checks if the socket is currently open
	 * 
	 * @return bool TRUE if it is open FALSE if not
	 */
	public function isConnected()
	{
		return $this->_socket !== NULL;
	}
	
	/**
	 * Make sure the session is closed on destruction of the object
	 * 
	 * @return void
	 */
	public function __destroy()
	{
		$this->close();
	}

	/**
	 * Setter for serializer. Allows us to run checks
	 *
	 * @param int $value the serializer to use, either SERIALIZER_JSON or SERIALIZER_MSGPACK
	 * 
	 * @return void
	 */
	public function setSerializer($value)
	{
		if($value === Messages::SERIALIZER_JSON || strtolower($value) === 'json')
		{
			$this->_serializer = new Json;
		}
		elseif($value === Messages::SERIALIZER_MSGPACK || strtolower($value) === 'msgpack')
		{
			$this->_serializer = new Msgpack;
		}
		else
		{
			throw new \Exception('Serializer type unsupported');
		}
	}

	/**
	 * Getter for serializer.
	 * 
	 * @return void
	 */
	public function getSerializer()
	{
		return $this->_serializer->getName();
	}

	private function webSocketPack($payload, $type = 'binary', $masked = TRUE)
	{
		$frameHead = array();
		$frame = '';
		$payloadLength = strlen($payload);
		
		switch($type)
		{		
			case 'text':
				// first byte indicates FIN, Text-Frame (10000001):
				$frameHead[0] = 129;				
			break;			
		
			case 'binary':
				// first byte indicates FIN, Text-Frame (10000001):
				$frameHead[0] = 130;				
			break;			
		
			case 'close':
				// first byte indicates FIN, Close Frame(10001000):
				$frameHead[0] = 136;
			break;
		
			case 'ping':
				// first byte indicates FIN, Ping frame (10001001):
				$frameHead[0] = 137;
			break;
		
			case 'pong':
				// first byte indicates FIN, Pong frame (10001010):
				$frameHead[0] = 138;
			break;
		}
		
		// set mask and payload length (using 1, 3 or 9 bytes) 
		if($payloadLength > 65535)
		{
			$payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
			$frameHead[1] = ($masked === true) ? 255 : 127;
			for($i = 0; $i < 8; $i++)
			{
				$frameHead[$i+2] = bindec($payloadLengthBin[$i]);
			}
			// most significant bit MUST be 0 (close connection if frame too big)
			if($frameHead[2] > 127)
			{
				$this->close();
				return false;
			}
		}
		elseif($payloadLength > 125)
		{
			$payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
			$frameHead[1] = ($masked === true) ? 254 : 126;
			$frameHead[2] = bindec($payloadLengthBin[0]);
			$frameHead[3] = bindec($payloadLengthBin[1]);
		}
		else
		{
			$frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
		}

		// convert frame-head to string:
		foreach(array_keys($frameHead) as $i)
		{
			$frameHead[$i] = chr($frameHead[$i]);
		}
		if($masked === true)
		{
			// generate a random mask:
			$mask = array();
			for($i = 0; $i < 4; $i++)
			{
				$mask[$i] = chr(rand(0, 255));
			}
			
			$frameHead = array_merge($frameHead, $mask);			
		}						
		$frame = implode('', $frameHead);

		// append payload to frame:
		$framePayload = array();	
		for($i = 0; $i < $payloadLength; $i++)
		{		
			$frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
		}

		return $frame;
	}

	private function webSocketUnpack($section, $data)
	{


	}

	private function unmask($mask, $data)
	{
		$unmaskedPayload = '';
		for($i = 0; $i < strlen($data); $i++)
			{
				if(isset($data[$i]))
				{
					$unmaskedPayload .= $data[$i] ^ $mask[$i % 4];
				}
			}
			return $unmaskedPayload;
	}
}
