<?php

namespace brightzone\rexpro;

use brightzone\rexpro\serializers\Json;
use brightzone\rexpro\ServerException;

/**
 * Gremlin-server PHP Driver client Connection class
 *
 * Example of basic use:
 *
 * ~~~
 * $connection = new Connection;
 * $connection->open('localhost:8182','g');
 * $resultSet = $connection->send('g.V'); //returns array with results
 * ~~~
 *
 * Some more customising of message to send can be done with the message object
 *
 * ~~~
 * $connection = new Connection;
 * $connection->open('localhost:8182','g');
 * $connection->message->gremlin = 'g.V';
 * $connection->send();
 * ~~~
 *
 * See Messages for more details
 *
 * @category DB
 * @package  gremlin-php
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki
 */
class Connection
{
    /**
     * @var string Contains the host information required to connect to the database.
     * format: server:port
     * If [port] is ommited 8182 will be assumed
     *
     * Example: localhost:8182
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
     * @var string the graphObject to use.
     */
    public $graphObj;

    /**
     * @var float timeout to use for connection to Rexster. If not set the timeout set in php.ini will be used: ini_get("default_socket_timeout")
     */
    public $timeout;

    /**
     * @var Messages Message object
     */
    public $message;

    /**
     * @var array Bindings for the gremlin script
     */
    public $bindings;

    /**
     * @var bool tells us if we're inside a transaction
     */
    private $_inTransaction = FALSE;

    /**
     * @var string The session ID for this connection.
     * Session ID allows us to access variable bindings made in previous requests. It is binary data
     */
    private $_sessionUuid;

    /**
     * @var resource rexpro socket connection
     */
    private $_socket;


    /**
     * Overloading constructor to instantiate a Messages instance and
     * provide it with a default serializer.
     *
     * @return void
     */
    public function __construct()
    {
        //create a message object
        $this->message = new Messages;
        //assign a default serializer to it
        $this->message->registerSerializer(new Json, TRUE);
    }

    /**
     * Connects to socket and starts a session with gremlin-server
     *
     * @param string $host        host and port seperated by ":"
     * @param string $graphObj    graph to load into session. defaults to graph
     * @param string $username    username for authentification
     * @param string $password    password to use for authentification
     * @param array  $config      extra required configuration
     *
     * @return bool TRUE on success FALSE on error
     */
    public function open($host = 'localhost:8182', $graphObj = 'graph', $username = NULL, $password = NULL, $config = [])
    {
        if($this->_socket === NULL)
        {
            $this->graphObj = $graphObj;
            $this->username = $username;
            $this->password = $password;
            $this->host = strpos($host, ':') === FALSE ? $host . ':8182' : $host;

            if(!$this->connectSocket())
            {
                return FALSE;
            }

            return $this->makeHandshake();
        }
    }

    /**
     * Sends data over socket
     *
     * @param Messages $msg Object containing the message to send
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
     * @return bool TRUE on succes FALSE on failure
     */
    protected function makeHandshake($origin = FALSE)
    {
        try
        {
            $key = base64_encode(Helper::generateRandomString(16, FALSE, TRUE));
            $header = "GET /gremlin HTTP/1.1\r\n";
            $header .= "Upgrade: websocket\r\n";
            $header .= "Connection: Upgrade\r\n";
            $header .= "Sec-WebSocket-Key: " . $key . "\r\n";
            $header .= "Host: " . $this->host . "\r\n";
            if($origin !== TRUE)
            {
                $header .= "Sec-WebSocket-Origin: http://" . $this->host . "\r\n";
            }
            $header .= "Sec-WebSocket-Version: 13\r\n\r\n";

            @fwrite($this->_socket, $header);
            $response = @fread($this->_socket, 1500);

            preg_match('#Sec-WebSocket-Accept:\s(.*)$#mU', $response, $matches);
            $keyAccept = trim($matches[1]);
            $expectedResponse = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
            return ($keyAccept === $expectedResponse) ? TRUE : FALSE;
        }
        catch(\Exception $e)
        {
            $this->error("Could not finalise handshake, Maybe the server was unreachable", 500, TRUE);
        }
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
            $data = $head = @stream_get_contents($this->_socket, 1);
            $head = unpack('C*', $head);

            //extract opcode from first byte
            $isBinary = ($head[1] & 15) == 2;

            $data .= $maskAndLength = @stream_get_contents($this->_socket, 1);
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
                $data .= $payloadLength = @stream_get_contents($this->_socket, 2);
                list($payloadLength) = array_values(unpack('n', $payloadLength)); // S for 16 bit int
            }
            elseif($length == 127)
            {
                $data .= $payloadLength = @stream_get_contents($this->_socket, 8);
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
                $data .= $mask = @stream_get_contents($this->_socket, 4);
            }

            //get payload
            $data .= $payload = @stream_get_contents($this->_socket, $payloadLength);

            if($maskSet)
            {
                //unmask payload
                $payload = $this->unmask($payload, $mask);
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
                $unpacked = $this->message->parse($payload, FALSE/*$isBinary*/); // currently unsupported diff return type by gremlin server
            }
            catch(\Exception $e)
            {
                $this->error($e->getMessage(), $e->getCode(), TRUE);
            }
            //handle errors
            if($unpacked['status']['code'] !== 200 && $unpacked['status']['code'] !== 206)
            {
                $this->error($unpacked['status']['message'] . " > " . implode("\n", $unpacked['status']['attributes']), $unpacked['status']['code']);
            }
            if($unpacked['status']['code'] == 200)
            {
                $fullData = array_merge($fullData, $unpacked['result']['data']);
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
        $this->_socket = @stream_socket_client(
                                    'tcp://' . $this->host,
                                    $errno,
                                    $errorMessage,
                                    $this->timeout ? $this->timeout : ini_get("default_socket_timeout")
                                    );
        if(!$this->_socket)
        {
            $this->error($errorMessage, $errno, TRUE);
        }

        return TRUE;
    }

    /**
     * Constructs and sends a Messages entity or gremlin script to the server.
     *
     *
     * @param mixed  $msg       (Messages|String|NULL) the message to send, NULL means use $this->message
     * @param string $op        Operation to run against opProcessor.
     * @param string $processor opProcessor to use.
     * @param array  $args      Arguments to overwrite.
     *
     * @return array reply from server.
     */
    public function send($msg = NULL, $processor = '', $op = 'eval', $args = [])
    {
        try
        {
            if(!($msg instanceof Messages) || $msg === NULL)
            {
                //lets make a script message:

                $this->message->gremlin = $msg === NULL ? $this->message->gremlin : $msg;
                $this->message->op = $op === 'eval' ? $this->message->op : $op;
                $this->message->processor = $processor === '' ? $this->message->processor : $processor;

                if($this->_inTransaction === TRUE || $this->message->processor == 'session')
                {
                    $this->getSession();
                    $this->message->processor = $processor == '' ? 'session' : $processor;
                    $this->message->setArguments(['session'=>$this->_sessionUuid]);
                }

                $this->message->setArguments($args);
            }
            else
            {
                $this->message = $msg;
            }

            $this->writeSocket();

            //lets get the response
            $response = $this->socketGetUnpack();

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
        if($this->_socket !== NULL)
        {
            if($this->_inTransaction === TRUE)
            {
                //do not commit changes changes;
                $this->transactionStop(FALSE);
            }
            $write = @fwrite($this->_socket, $this->webSocketPack("", 'close'));
            if($write === FALSE)
            {
                $this->error('Could not write to socket', 500, TRUE);
            }
            @stream_socket_shutdown($this->_socket, STREAM_SHUT_RDWR); //ignore error
            $this->_socket = NULL;
            $this->_sessionUuid = NULL;

            return TRUE;
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
        if(!isset($this->graphObj) || (isset($this->graphObj) && $this->graphObj == ''))
        {
            $this->error("A graph object needs to be specified", 500, TRUE);
        }

        if($this->_inTransaction)
        {
            $this->message->gremlin = $this->graphObj . '.tx().rollback()';
            $this->send();
            $this->_inTransaction = FALSE;
            $this->error(__METHOD__ . ': already in transaction, rolling changes back.', 500, TRUE);
        }

        //if we aren't in transaction we need to start a session
        $this->getSession();
        $this->message->setArguments(['session'=>$this->_sessionUuid]);
        $this->message->processor = 'session';
        $this->message->gremlin = 'if(!' . $this->graphObj . '.tx().isOpen()){' . $this->graphObj . '.tx().open()}';
        $this->send();
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
        if(!$this->_inTransaction || !isset($this->_sessionUuid))
        {
            $this->error(__METHOD__ . ' : No ongoing transaction/session.', 500, TRUE);
        }
        //send message to stop transaction
        if($success)
        {
            $this->message->gremlin = $this->graphObj . '.tx().commit()';
        }
        else
        {
            $this->message->gremlin = $this->graphObj . '.tx().rollback()';
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
     * @param string  $payload string or binary, the message to insert into the packet.
     * @param string $type the type of frame you send (usualy text or string) ie: opcode
     * @param bool   $masked whether to mask the packet or not.
     *
     * @return string Binary message to pump into the socket
     */
    private function webSocketPack($payload, $type = 'binary', $masked = TRUE)
    {
        $frameHead = array();
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
            $mask = array();
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
     * We use this to run rollbacks when errors occure
     *
     * @return void
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
        if(!isset($this->_sessionUuid))
        {
            $this->_sessionUuid = Helper::createUuid();
        }
        return $this->_sessionUuid;
    }
}
