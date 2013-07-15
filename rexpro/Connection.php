<?php
/**
 * RexPro PHP client Connection class
 * 
 * Example of use:
 * 
 * $connection = new Connection;
 * $connection->open('localhost:8184','tinkergraph'); //can return false on error
 * $connection->script = 'g.V';
 * $resultSet = $connection->runScript(); //returns array with results or false on error
 * //error handling: (It is worth noting that open() can also return errors in this way)
 * if($resultSet === false)
 * {
 * 		$errorCode = $connection->error->code;
 * 		$errorDescription = $connection->error->description;
 * 		//etc..
 * }
 * 
 * @author Dylan Millikin <dylan.millikin@brightzone.fr>
 * @link https://github.com/tinkerpop/rexster/wiki
 */
 
namespace rexpro;

require_once 'Exceptions.php';
require_once 'Helper.php';
require_once 'Messages.php';

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
	 * @var string the username for establishing DB connection. Defaults to null.
	 */
	public $username;
	
	/**
	 * @var string the password for establishing DB connection. Defaults to null.
	 */
	public $password;
	
	/**
	 * @var string the graph in use.
	 */
	public $graph;
	
	/**
	 * @var string the name of the graph object to use. defaults to 'g' for establishing DB connection.
	 */
	public $graphObject;
	
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
	private $_inTransaction = false;
	
	/**
	 * @var array -Complete- parsed response message from the server. Also contains headers and additional data
	 */
	public $response;
	
	/**
	 * @var int Protocol version to use. Currently only 0 should be used
	 */
	public $protocolVersion = 1;
	
	/**
	 * @var resource rexpro socket connection
	 */
	private $_socket;
	
	/**
	 * @var Exceptions contains error information 
	 * @see Exceptions
	 */
	public $error;
	
	/**
	 * Connects to socket and starts a session on RexPro
	 * 
	 * @param string $host host and port seperated by ":"
	 * @param string $graph graph to load into session. defaults to tinkergraph
	 * @param string $username username for authentification
	 * @param string $password password to use for authentification
	 * @param string $graphObject Graph object name. defaults to 'g'
	 * 
	 * @return bool true on success false on error
	 */
	public function open($host='localhost:8184',$graph='tinkergraph',$username=null,$password=null,$graphObject='g')
	{
		if($this->_socket === null)
		{
			$this->error = null;
			$this->host = $host;
			$this->graph = $graph;
			$this->graphObject = $graphObject;
			$this->username = $username;
			$this->password = $password;

			if(!$this->connectSocket())
				return false;
				
			//lets make opening session message:
			$msg = new Messages;
			$msg->buildSessionMessage(	Helper::createUuid(),
										$this->username,
										$this->password,
										array('graphName'=>$this->graph,'graphObjName'=>$this->graphObject),
										$this->protocolVersion);
			
			if(!$this->send($msg))
				return false;
			
			//lets get the response
			$response = $this->getResponse();
			if($response === false)
				return false;
				
			$this->response = $response;
			$this->sessionUuid = $this->response[4][0];
			
			return true;
		}
	}
	
	/**
	 * Sends binary data over socket
	 * 
	 * @param $msg Messages Object containing the message to send
	 * @return bool true if success false on error
	 */
	public function send($msg)
	{	
		$write = @fwrite($this->_socket,$msg->msgPack);
		if($write === false)
		{
			$this->error = new Exceptions(0,'Could not write to socket');
			return false;
		}
		return true;
	}

	/**
	 * Recieves binary data over socket and parses it
	 * 
	 * @param $msg Messages Object containing the message to send
	 * @return mixed unpacked message if true, false on error
	 */
	public function getResponse()
	{	
		$header = @stream_get_contents($this->_socket,7);
		$messageLength = @stream_get_contents($this->_socket,4);
		$body = @stream_get_contents($this->_socket,(int)hexdec(bin2hex($messageLength)));
	
		if($header === false || $messageLength === false || $body === false )
		{
			$this->error = new Exceptions(0,'Could not stream contents');
			return false;
		} 
		if(empty($header) || empty($messageLength) || empty($body) )
		{
			$this->error = new Exceptions(0,'Empty reply. Most likely the result of an irregular request. (Check custom Meta, or lack of in the case of a non-isolated query)');
			return false;
		} 
		
		$msgPack = $header.$messageLength.$body;
	
		//now that we have the binary package lets parse it
		$message = new Messages;
		$unpacked = $message->parse($msgPack);
		//lets check if this is an error message from the server
		$error = Exceptions::checkError($unpacked);
		if( $error === false)
			return $unpacked;
		$this->error = $error;
		return false;

	}
	
	/**
	 * Opens socket
	 * 
	 * @return bool true on success false on error
	 */
	protected function connectSocket()
	{
		if (strpos($this->host, ':')===false) {
				$this->host .= ':8184';
		}
		
		$this->_socket = @stream_socket_client(
									'tcp://'.$this->host,
									$errno, 
									$errorMessage,
									$this->timeout ? $this->timeout : ini_get("default_socket_timeout")
									);
		if(!$this->_socket)
		{
			$this->error = new Exceptions($errno,$errorMessage);
			return false;
		}	
			
		return true;
	}
	
	/**
	 * runs a gremlin script against the graph
	 * 
	 * @param bool $sessionless whether or not to run this script without session. 
	 * @return mixed message on success false on error.
	 */
	public function runScript($inSession=true,$isolated=true)
	{	
		//lets make a script message:
		$msg = new Messages;
		
		$meta = array(	'inSession'=>$inSession,
						'transaction'=>$this->_inTransaction?false:true,
						'isolate'=>$inSession?$isolated:false);
		if($inSession===false)
			$meta = array_merge($meta,array('graphName'=>$this->graph,
											'graphObjName'=>$this->graphObject?$this->graphObject:'g'));
		
		$msg->buildScriptMessage(	($inSession?$this->sessionUuid:'00000000-0000-0000-0000-000000000000'),
									$this->script,
									$this->bindings,
									$meta,
									$this->protocolVersion);
		
		//reset script information after building
		$this->bindings = null;
		$this->script = null;
		
		if(!$this->send($msg))
			return false;

		//lets get the response
		$response = $this->getResponse();
		
		if($response === false)
			return false;
		$this->response = $response;
		return $this->response[4][3];
	}
	
	/**
	 * Close connection to server
	 * This closes the current session on the server then closes the socket
	 * 
	 * @return bool true on success false on error
	 */
	public function close()
	{
		if($this->_socket !== null)
		{
			$this->error = null;
			//lets make opening session message:
			$msg = new Messages;
			$msg->buildSessionMessage(	$this->sessionUuid,
										$this->username,
										$this->password,
										array('killSession'=>true),
										$this->protocolVersion);
			
			if(!$this->send($msg))
				return false;
			
			//lets get the response
			$response = $this->getResponse();
			if($response === false)
				return false;
			$this->response = $response;
			@stream_socket_shutdown($this->_socket, STREAM_SHUT_RDWR); //ignore error
			$this->_socket = null;
			$this->sessionUuid = null;
			
			return true;
		}
	}
	
	/**
	 * Binds a value to be used inside gremlin script
	 * 
	 * @param string $bind The binding name
	 * @param mixed value the value that the binding name refers to
	 * @return void
	 */
	public function bindValue($bind,$value)
	{
		if($this->bindings === null)
			$this->bindings = array();
		$this->bindings[$bind]=$value;
	}
	 
	/**
	 * Start a transaction
	 * 
	 * @return bool true on success false on failure
	 */
	 public function transactionStart()
	 {
		if($this->_inTransaction)
		{
			$this->error = array(0,'already in transaction');
			$this->script='g.stopTransaction(FAILURE)';
			$this->runScript();
			return false;
		}
		$this->_inTransaction = true;
		return true;
	 }
	
	/**
	 * End a transaction
	 * 
	 * @param bool $success should the transaction commit or revert changes
	 * @return bool true on success false on failure.
	 */
	 public function transactionStop($success = true)
	 {
		if(!$this->_inTransaction)
		{
			$this->error = array(0,'No ongoing transaction');
			return false;
		}
		//send message to stop transaction
		$this->script='g.stopTransaction('.($success?'SUCCESS':'FAILURE').')';
		$this->runScript();
			
		$this->_inTransaction = false;
		return true;
	 }
	
	/**
	 * Checks if the socket is currently open
	 * 
	 * @return bool true if it is open false if not
	 */
	public function isConnected()
	{
		return $this->_socket !== null;
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
	
	
	
} 
