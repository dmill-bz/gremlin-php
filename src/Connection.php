<?php

namespace Brightzone\GremlinDriver;

use Brightzone\GremlinDriver\Serializers\Json;
use Brightzone\GremlinDriver\Serializers\SerializerInterface;

/**
 * Gremlin-server PHP Driver client Connection class
 *
 * Example of basic use:
 *
 * ~~~
 * $connection = new Connection(['host' => 'localhost']);
 * $connection->open();
 * $resultSet = $connection->send('g.V'); //returns array with results
 * ~~~
 *
 * Some more customising of message to send can be done with the message object
 *
 * ~~~
 * $connection = new Connection(['host' => 'localhost']);
 * $connection->open();
 * $connection->message->gremlin = 'g.V';
 * $connection->send();
 * ~~~
 *
 * See Message for more details
 *
 * @category DB
 * @package  GremlinDriver
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki
 */
class Connection
{
    /**
     * @var string Contains the host information required to connect to the database.
     *
     * Default: localhost
     */
    public $host = 'localhost';

    /**
     * @var string Contains port information to connect to the database
     *
     * Default : 8182
     */
    public $port = 8182;

    /**
     * @var string the username for establishing DB connection. Defaults to NULL.
     */
    public $username;

    /**
     * @var string the password for establishing DB connection. Defaults to NULL.
     */
    public $password;

    /**
     * @var string the graph to use.
     */
    public $graph;

    /**
     * @var float timeout to use for connection to Rexster. If not set the timeout set in php.ini will be used:
     *      ini_get("default_socket_timeout")
     */
    public $timeout;

    /**
     * @var Message Message object
     */
    public $message;

    /**
     * @var array Aliases to be used for this connection.
     * Allows users to set up whichever character on the db end such as "g" and reference it with another alias.
     */
    public $aliases = [];

    /**
     * @var bool whether or not the driver should return empty result sets as an empty array
     * (the default behavior is to propagate the exception from the server - yes the server throws exceptions on empty
     * result sets.)
     */
    public $emptySet = FALSE;

    /**
     * @var bool tells us if we're inside a transaction
     */
    protected $_inTransaction = FALSE;

    /**
     * @var string The session ID for this connection.
     * Session ID allows us to access variable bindings made in previous requests. It is binary data
     */
    protected $_sessionUuid;

    /**
     * @var resource rexpro socket connection
     */
    protected $_socket;

    /**
     * @var bool|array whether or not we're using ssl. If an array is set it should correspond to a
     *      stream_context_create() array.
     */
    public $ssl = FALSE;

    /**
     * @var string which sasl mechanism to use for authentication. Can be either PLAIN or GSSAPI.
     * This is ignored by gremlin-server by default but some custom server implementations may use this
     */
    public $saslMechanism = "PLAIN";

    /**
     * @var string the strategy to use for retry
     */
    public $retryStrategy = 'linear';

    /**
     * @var int the number of attempts before total failure
     */
    public $retryAttempts = 1;

    /**
     * @var bool whether or not to accept different return formats from the one the message was sent with.
     * This is an extremely rare occurrence. Only happens if using a custom serializer.
     */
    private $_acceptDiffResponseFormat = FALSE;

    /**
     * Overloading constructor to instantiate a Message instance and
     * provide it with a default serializer.
     *
     * @param array $options The class options
     */
    public function __construct($options = [])
    {
        foreach($options as $key => $value)
        {
            $this->$key = $value;
        }
        //create a message object
        $this->message = new Message();
        //assign a default serializer to it
        $this->message->registerSerializer(new Json, TRUE);
    }

    /**
     * Connects to socket and starts a session with gremlin-server
     *
     * @return bool TRUE on success FALSE on error
     */
    public function open()
    {
        if($this->_socket === NULL)
        {
            $this->connectSocket(); // will throw error on failure.

            return $this->makeHandshake();
        }

        return FALSE;
    }

    /**
     * Sends data over socket
     *
     * @param Message $msg Object containing the message to send
     *
     * @return bool TRUE if success
     */
    private function writeSocket($msg = NULL)
    {
        if($msg === NULL)
        {
            $msg = $this->message;
        }

        $msg = $this->webSocketPack($msg->buildMessage());
        $write = @fwrite($this->_socket, $msg);
        if($write === FALSE)
        {
            $this->error(__METHOD__ . ': Could not write to socket', 500, TRUE);
        }

        return TRUE;
    }

    /**
     * Make Original handshake with the server
     *
     * @param bool $origin whether or not to provide the origin header. currently unsupported
     *
     * @return bool TRUE on success FALSE on failure
     */
    protected function makeHandshake($origin = FALSE)
    {
        try
        {
            $protocol = 'http';
            if($this->ssl)
            {
                $protocol = 'ssl';
            }

            $key = base64_encode(Helper::generateRandomString(16, FALSE, TRUE));
            $header = "GET /gremlin HTTP/1.1\r\n";
            $header .= "Upgrade: websocket\r\n";
            $header .= "Connection: Upgrade\r\n";
            $header .= "Sec-WebSocket-Key: " . $key . "\r\n";
            $header .= "Host: " . $this->host . "\r\n";
            if($origin !== TRUE)
            {
                $header .= "Sec-WebSocket-Origin: " . $protocol . "://" . $this->host . ":" . $this->port . "\r\n";
            }
            $header .= "Sec-WebSocket-Version: 13\r\n\r\n";

            @fwrite($this->_socket, $header);
            $response = @fread($this->_socket, 1500);
            if(!$response)
            {
                $this->error("Couldn't get a response from server", 500);
            }
            
            preg_match('#Sec-WebSocket-Accept:\s(.*)$#miU', $response, $matches);
            $keyAccept = trim($matches[1]);
            $expectedResponse = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

            return ($keyAccept === $expectedResponse) ? TRUE : FALSE;
        }
        catch(\Exception $e)
        {
            $this->error("Could not finalise handshake, Maybe the server was unreachable", 500, TRUE);

            return FALSE;
        }
    }

    /**
     * Get and parse message from the socket
     *
     * @return array the message returned from the db
     */
    private function socketGetMessage()
    {
        $data = $head = $this->streamGetContent(1);
        $head = unpack('C*', $head);

        //extract opcode from first byte
        $isBinary = (($head[1] & 15) == 2) && $this->_acceptDiffResponseFormat;

        $data .= $maskAndLength = $this->streamGetContent(1);
        list($maskAndLength) = array_values(unpack('C', $maskAndLength));

        //set first bit to 0
        $length = $maskAndLength & 127;

        $maskSet = FALSE;
        if($maskAndLength & 128)
        {
            $maskSet = TRUE;
        }

        if($length == 126)
        {
            $data .= $payloadLength = $this->streamGetContent(2);
            list($payloadLength) = array_values(unpack('n', $payloadLength)); // S for 16 bit int
        }
        elseif($length == 127)
        {
            $data .= $payloadLength = $this->streamGetContent(8);
            //lets save it as two 32 bit integers and reatach using bitwise operators
            //we do this as pack doesn't support 64 bit packing/unpacking.
            list($higher, $lower) = array_values(unpack('N2', $payloadLength));
            $payloadLength = $higher << 32 | $lower;
        }
        else
        {
            $payloadLength = $length;
        }

        //get mask
        if($maskSet)
        {
            $data .= $mask = $this->streamGetContent(4);
        }

        // get payload
        $data .= $payload = $this->streamGetContent($payloadLength);

        if($maskSet)
        {
            //unmask payload
            $payload = $this->unmask($mask, $payload);
        }

        //ugly code but we can seperate the two errors this way
        if($head === FALSE || $payload === FALSE || $maskAndLength === FALSE
            || $payloadLength === FALSE || ($maskSet === TRUE && $mask === FALSE)
        )
        {
            $this->error('Could not stream contents', 500);
        }
        if(empty($head) || empty($payload) || empty($maskAndLength)
            || empty($payloadLength) || ($maskSet === TRUE && empty($mask)))
        {
            $this->error('Empty reply. Most likely the result of an irregular request. (Check custom Meta, or lack of in the case of a non-isolated query)', 500);
        }

        //now that we have the payload lets parse it
        try
        {
            $unpacked = $this->message->parse($payload, $isBinary);
        }
        catch(\Exception $e)
        {
            $this->error($e->getMessage(), $e->getCode(), TRUE);
        }

        return $unpacked;
    }

    /**
     * Recieves binary data over the socket and parses it
     *
     * @return array PHP native result from server
     */
    protected function socketGetUnpack()
    {
        $fullData = [];
        do
        {
            $unpacked = $this->socketGetMessage();
            // If this is an authentication challenge, lets meet it and return the result
            if($unpacked['status']['code'] === 407)
            {
                return $this->authenticate();
            }

            if($unpacked['status']['code'] === 204 && $this->emptySet)
            {
                return [];
            }

            //handle errors
            if($unpacked['status']['code'] !== 200 && $unpacked['status']['code'] !== 206)
            {
                $this->error($unpacked['status']['message']
                    . "\n\n ===================  SERVER TRACE  ========================= \n"
                    . var_export($unpacked['status']['attributes'], TRUE)
                    . "\n ============================================================ \n", $unpacked['status']['code']);
            }

            foreach($unpacked['result']['data'] as $row)
            {
                $fullData[] = $row;
            }
        }
        while($unpacked['status']['code'] === 206);

        return $fullData;
    }

    /**
     * Opens socket
     *
     * @return bool TRUE on success
     */
    private function connectSocket()
    {
        $protocol = 'tcp';
        $context = stream_context_create([]);
        if($this->ssl)
        {
            $protocol = 'ssl';
            if(is_array($this->ssl) && !empty($this->ssl))
            {
                $context = stream_context_create($this->ssl);
            }
        }
        $fp = @stream_socket_client(
            $protocol . '://' . $this->host . ':' . $this->port,
            $errno,
            $errorMessage,
            $this->timeout ? $this->timeout : ini_get("default_socket_timeout"),
            STREAM_CLIENT_CONNECT,
            $context
        );

        if(!$fp)
        {
            $this->error($errorMessage, $errno, TRUE);

            return FALSE;
        }

        $this->_socket = $fp;

        return TRUE;
    }

    /**
     * Constructs and sends a Message entity or gremlin script to the server without waiting for a response.
     *
     *
     * @param mixed  $msg            (Message|String|NULL) the message to send, NULL means use $this->message
     * @param string $processor      opProcessor to use.
     * @param string $op             Operation to run against opProcessor.
     * @param array  $args           Arguments to overwrite.
     * @param bool   $expectResponse Arguments to overwrite.
     *
     * @return void
     * @throws \Exception
     */
    public function run($msg = NULL, $processor = '', $op = 'eval', $args = [], $expectResponse = TRUE)
    {
        try
        {
            $this->prepareWrite($msg, $processor, $op, $args);
            if($expectResponse)
            {
                $this->socketGetUnpack();
            }
        }
        catch(\Exception $e)
        {
            // on run lets ignore anything coming back from the server
            if(!($e instanceof ServerException))
            {
                throw $e;
            }
        }

        //reset message and remove binds
        $this->message->clear();
    }

    /**
     * Private function that Constructs and sends a Message entity or gremlin script to the server and then waits for
     * response
     *
     * The main use here is to centralise this code for run() and send()
     *
     *
     * @param mixed  $msg       (Message|String|NULL) the message to send, NULL means use $this->message
     * @param string $processor opProcessor to use.
     * @param string $op        Operation to run against opProcessor.
     * @param array  $args      Arguments to overwrite.
     *
     * @return array reply from server.
     */
    private function prepareWrite($msg, $processor, $op, $args)
    {
        if(!($msg instanceof Message) || $msg === NULL)
        {
            //lets make a script message:

            $this->message->gremlin = $msg === NULL ? $this->message->gremlin : $msg;
            $this->message->op = $op === 'eval' ? $this->message->op : $op;
            $this->message->processor = $processor === '' ? $this->message->processor : $processor;

            if($this->_inTransaction === TRUE || $this->message->processor == 'session')
            {
                $this->getSession();
                $this->message->processor = $processor == '' ? 'session' : $processor;
                $this->message->setArguments(['session' => $this->_sessionUuid]);
            }

            if((isset($args['aliases']) && !empty($args['aliases'])) || !empty($this->aliases))
            {
                $args['aliases'] = isset($args['aliases']) ? array_merge($args['aliases'], $this->aliases) : $this->aliases;
            }

            $this->message->setArguments($args);
        }
        else
        {
            $this->message = $msg;
        }
        $this->writeSocket();
    }

    /**
     * Constructs and sends a Message entity or gremlin script to the server and then waits for response
     *
     *
     * @param mixed  $msg       (Message|String|NULL) the message to send, NULL means use $this->message
     * @param string $processor opProcessor to use.
     * @param string $op        Operation to run against opProcessor.
     * @param array  $args      Arguments to overwrite.
     *
     * @return array reply from server.
     * @throws ServerException
     */
    public function send($msg = NULL, $processor = '', $op = 'eval', $args = [])
    {
        try
        {
            $workload = new Workload(function($db, $msg, $processor, $op, $args) {
                $db->prepareWrite($msg, $processor, $op, $args);

                //lets get the response
                return $db->socketGetUnpack();
            }, [$this, $msg, $processor, $op, $args]);

            $response = $workload->linearRetry($this->retryAttempts);

            //reset message and remove binds
            $this->message->clear();

            return $response;
        }
        catch(\Exception $e)
        {
            if(!($e instanceof ServerException))
            {
                $this->error($e->getMessage(), $e->getCode(), TRUE);
            }
            throw $e;
        }
    }

    /**
     * Close connection to server
     * This closes the current session on the server then closes the socket
     *
     * @return bool TRUE on success
     */
    public function close()
    {
        if(is_resource($this->_socket))
        {
            if($this->_inTransaction === TRUE)
            {
                //do not commit changes changes;
                $this->transactionStop(FALSE);
            }

            $this->closeSession();

            $write = @fwrite($this->_socket, $this->webSocketPack("", 'close'));

            if($write === FALSE)
            {
                $this->error('Could not write to socket', 500, TRUE);
            }
            @stream_socket_shutdown($this->_socket, STREAM_SHUT_RDWR); //ignore error
            $this->_socket = NULL;

            return TRUE;
        }

        return FALSE;
    }

    /**
     * Closes an open session if it exists
     * You can use this to close open sessions on the server end. This allows to free up threads on the server end.
     *
     * @return void
     */
    public function closeSession()
    {
        if($this->isSessionOpen())
        {
            $msg = new Message();
            $msg->op = "close";
            $msg->processor = "session";
            $msg->setArguments(['session' => $this->getSession()]);
            $msg->registerSerializer(new Json());
            $this->run($msg, NULL, NULL, NULL, FALSE); // Tell run not to expect a return
            $this->_sessionUuid = NULL;
        }
    }

    /**
     * Start a transaction.
     * Transaction start only makes sens if committing is set to manual on the server.
     * Manual is not the default setting so we will assume a session UUID must exists
     *
     * @return bool TRUE on success FALSE on failure
     */
    public function transactionStart()
    {
        if(!isset($this->graph) || (isset($this->graph) && $this->graph == ''))
        {
            $this->error("A graph object needs to be specified", 500, TRUE);
        }

        if($this->_inTransaction)
        {
            $this->message->gremlin = $this->graph . '.tx().rollback()';
            $this->send();
            $this->_inTransaction = FALSE;
            $this->error(__METHOD__ . ': already in transaction, rolling changes back.', 500, TRUE);
        }

        //if we aren't in transaction we need to start a session
        $this->getSession();
        $this->message->setArguments(['session' => $this->_sessionUuid]);
        $this->message->processor = 'session';
        $this->message->gremlin = $this->graph . '.tx().open()';
        $this->send();
        $this->_inTransaction = TRUE;

        return TRUE;
    }

    /**
     * Run a callback in a transaction.
     * The advantage of this is that it allows for fail-retry strategies
     *
     * @param callable $callback the code to execute within the scope of this transaction
     * @param array    $params   The params to pass to the callback
     *
     * @return mixed the return value of the provided callable
     */
    public function transaction(callable $callback, $params = [])
    {
        //create a callback from the callback to introduce transaction handling
        $workload = new Workload(function($db, $callback, $params) {
            try
            {
                $db->transactionStart();
                $result = call_user_func_array($callback, $params);

                if($db->_inTransaction)
                {
                    $db->transactionStop(TRUE);
                }

                return $result;
            }
            catch(\Exception $e)
            {
                /**
                 * We need to catch the exception again before the workload catches it
                 * this allows us to terminate the transaction properly before each retry attempt
                 */
                if($db->_inTransaction)
                {
                    $db->transactionStop(FALSE);
                }
                throw $e;
            }
        },
            [$this, $callback, $params]
        );

        $result = $workload->linearRetry($this->retryAttempts);

        return $result;
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
        if(!$this->_inTransaction || !isset($this->_sessionUuid))
        {
            $this->error(__METHOD__ . ' : No ongoing transaction/session.', 500, TRUE);
        }
        //send message to stop transaction
        if($success)
        {
            $this->message->gremlin = $this->graph . '.tx().commit()';
        }
        else
        {
            $this->message->gremlin = $this->graph . '.tx().rollback()';
        }

        $this->send();
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
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Constructs the Websocket packet frame
     *
     * @param string $payload string or binary, the message to insert into the packet.
     * @param string $type    the type of frame you send (usualy text or string) ie: opcode
     * @param bool   $masked  whether to mask the packet or not.
     *
     * @return string Binary message to pump into the socket
     */
    private function webSocketPack($payload, $type = 'binary', $masked = TRUE)
    {
        $frameHead = [];
        $payloadLength = strlen($payload);

        switch($type)
        {
            case 'text':
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;

            case 'binary':
                // first byte indicates FIN, Binary-Frame (10000010):
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
            $frameHead[1] = ($masked === TRUE) ? 255 : 127;
            for($i = 0; $i < 8; $i++)
            {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }
            // most significant bit MUST be 0 (close connection if frame too big)
            if($frameHead[2] > 127)
            {
                $this->close();

                return FALSE;
            }
        }
        elseif($payloadLength > 125)
        {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === TRUE) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        }
        else
        {
            $frameHead[1] = ($masked === TRUE) ? $payloadLength + 128 : $payloadLength;
        }

        // convert frame-head to string:
        foreach(array_keys($frameHead) as $i)
        {
            $frameHead[$i] = chr($frameHead[$i]);
        }
        if($masked === TRUE)
        {
            // generate a random mask:
            $mask = [];
            for($i = 0; $i < 4; $i++)
            {
                $mask[$i] = chr(rand(0, 255));
            }

            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);

        // append payload to frame:
        for($i = 0; $i < $payloadLength; $i++)
        {
            $frame .= ($masked === TRUE) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }

        return $frame;
    }

    /**
     * Unmask data. Usualy the payload
     *
     * @param string $mask binary key to use to unmask
     * @param string $data data that needs unmasking
     *
     * @return string unmasked data
     */
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

    /**
     * Custom error throwing method.
     * We use this to run rollbacks when errors occur
     *
     * @param string $description Description of the error
     * @param int    $code        The error code
     * @param bool   $internal    true for internal, false for server error
     *
     * @return void
     * @throws InternalException
     * @throws ServerException
     */
    private function error($description, $code, $internal = FALSE)
    {
        //Errors will rollback once the connection is destroyed. No need to rollback here.
        if($internal)
        {
            throw new InternalException($description, $code);
        }
        else
        {
            throw new ServerException($description, $code);
        }
    }

    /**
     * Return whether or not this cnnection object is in transaction mode
     *
     * @return bool TRUE if in transaction, FALSE otherwise
     */
    public function inTransaction()
    {
        return $this->_inTransaction;
    }

    /**
     * Retrieve the current session UUID
     *
     * @return string current UUID
     */
    public function getSession()
    {
        if(!$this->isSessionOpen())
        {
            $this->_sessionUuid = Helper::createUuid();
        }

        return $this->_sessionUuid;
    }

    /**
     * Checks if the session is currently open
     *
     * @return bool TRUE if it's open is FALSE if not.
     */
    public function isSessionOpen()
    {
        if(!isset($this->_sessionUuid))
        {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Builds an authentication message when challenged by the server
     * Once you respond and authenticate you will receive the response for the request you made prior to the challenge
     *
     * @return array The server response.
     */
    protected function authenticate()
    {
        $msg = new Message();
        $msg->op = "authentication";

        $args = [
            'sasl'          => base64_encode(utf8_encode("\x00" . trim($this->username) . "\x00" . trim($this->password))),
            'saslMechanism' => $this->saslMechanism,
        ];

        if($this->isSessionOpen())
        {
            $msg->processor = "session";
            $args["session"] = $this->getSession();
        }
        else
        {
            $msg->processor = "";
        }

        $msg->setArguments($args);

        //['session' => $this->_sessionUuid]
        $msg->registerSerializer($this->message->getSerializer());

        return $this->send($msg);
    }

    /**
     * Custom stream get content that will wait for the content to be ready before streaming it
     * This corrects an issue with hhvm handling streams in non-blocking mode.
     *
     * @param int $limit  the length of the data we want to get from the stream
     * @param int $offset the offset to start reading the stream from
     *
     * @return string the data streamed from the socket
     */
    private function streamGetContent($limit, $offset = -1)
    {
        $buffer = NULL;
        $bufferedSize = 0;
        do
        {
            $buffer .= stream_get_contents($this->_socket, $limit - $bufferedSize, $offset);
            $bufferedSize = strlen($buffer);
        }
        while($bufferedSize < $limit);

        return $buffer;
    }
}
