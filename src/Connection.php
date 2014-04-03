<?php

namespace brightzone\rexpro;

require_once 'Exceptions.php';
require_once 'Helper.php';
require_once 'Messages.php';

/**
 * RexPro PHP client Connection class
 * 
 * Example of use:
 *
 * <code>
 * $connection = new Connection;
 * $connection->open('localhost:8184','tinkergraph'); //can return FALSE on error
 * $connection->script = 'g.V';
 * $resultSet = $connection->runScript(); //returns array with results or FALSE on error
 * //error handling: (It is worth noting that open() can also return errors in this way)
 * if($resultSet === FALSE)
 * {
 * 		$errorCode = $connection->error->code;
 * 		$errorDescription = $connection->error->description;
 * 		//etc..
 * }
 * </code>
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
	private $_inTransaction = FALSE;
	
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
	 * @param string $host        host and port seperated by ":"
	 * @param string $graph       graph to load into session. defaults to tinkergraph
	 * @param string $username    username for authentification
	 * @param string $password    password to use for authentification
	 * @param string $graphObject Graph object name. defaults to 'g'
	 * 
	 * @return bool TRUE on success FALSE on error
	 */
	public function open($host='localhost:8184', $graph='tinkergraph', $username=NULL, $password=NULL, $graphObject='g')
	{
		if($this->_socket === NULL)
		{
			$this->error = NULL;
			$this->host = $host;
			$this->graph = $graph;
			$this->graphObject = $graphObject;
			$this->username = $username;
			$this->password = $password;

			if(!$this->connectSocket())
			{
				return FALSE;
			}
				
			//lets make opening session message:
			$msg = new Messages;
			$msg->buildSessionMessage(	Helper::createUuid(),
										$this->username,
										$this->password,
										array('graphName'=>$this->graph,'graphObjName'=>$this->graphObject),
										$this->protocolVersion);
			
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
			$this->sessionUuid = $this->response[4][0];
			
			return TRUE;
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
		$write = @fwrite($this->_socket, $msg->msgPack);
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
		$header = @stream_get_contents($this->_socket, 7);
		$messageLength = @stream_get_contents($this->_socket, 4);
		$body = @stream_get_contents($this->_socket, (int)hexdec(bin2hex($messageLength)));
	
		if($header === FALSE || $messageLength === FALSE || $body === FALSE )
		{
			$this->error = new Exceptions(0, 'Could not stream contents');
			return FALSE;
		} 
		if(empty($header) || empty($messageLength) || empty($body) )
		{
			$this->error = new Exceptions(0, 'Empty reply. Most likely the result of an irregular request. (Check custom Meta, or lack of in the case of a non-isolated query)');
			return FALSE;
		} 
		
		$msgPack = $header.$messageLength.$body;
	
		//now that we have the binary package lets parse it
		$message = new Messages;
		$unpacked = $message->parse($msgPack);
		//lets check if this is an error message from the server
		$error = Exceptions::checkError($unpacked);
		if( $error === FALSE)
		{
			return $unpacked;
		}
		$this->error = $error;
		return FALSE;

	}
	
	/**
	 * Opens socket
	 * 
	 * @return bool TRUE on success FALSE on error
	 */
	protected function connectSocket()
	{
		if (strpos($this->host, ':')===FALSE)
		{
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
	public function runScript($inSession=TRUE, $isolated=TRUE)
	{	
		//lets make a script message:
		$msg = new Messages;
		
		$meta = array(	'inSession'=>$inSession,
						'transaction'=>$this->_inTransaction?FALSE:TRUE,
						'isolate'=>$inSession?$isolated:FALSE);
		if($inSession===FALSE)
		{
			$meta = array_merge($meta, array(	'graphName'=>$this->graph,
												'graphObjName'=>$this->graphObject?$this->graphObject:'g'));
		}
		
		$msg->buildScriptMessage(	($inSession?$this->sessionUuid:'00000000-0000-0000-0000-000000000000'),
									$this->script,
									$this->bindings,
									$meta,
									$this->protocolVersion);
		
		//reset script information after building
		$this->bindings = NULL;
		$this->script = NULL;
		
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
		return $this->response[4][3];
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
			$msg = new Messages;
			$msg->buildSessionMessage(	$this->sessionUuid,
										$this->username,
										$this->password,
										array('killSession'=>TRUE),
										$this->protocolVersion);
			
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
			$this->script='g.stopTransaction(FAILURE)';
			$this->runScript();
			return FALSE;
		}
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
		if(!$this->_inTransaction)
		{
			$this->error = array(0,'No ongoing transaction');
			return FALSE;
		}
		//send message to stop transaction
		$this->script='g.stopTransaction('.($success?'SUCCESS':'FAILURE').')';
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
}
