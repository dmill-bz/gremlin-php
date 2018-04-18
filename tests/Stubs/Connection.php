<?php

namespace Brightzone\GremlinDriver\Tests\Stubs;

use Brightzone\GremlinDriver\Connection as BaseConnection;

class Connection extends BaseConnection
{
    /**
     * Allow stub to set socket with any data
     *
     * @param string $frame binary data to set up as stream.
     */
    public function setSocket($frame)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $frame);
        rewind($stream);
        $this->_socket = $stream;
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
            fclose($this->_socket); //ignore error
            $this->_socket = NULL;
            $this->_sessionUuid = NULL;

            return TRUE;
        }

        return TRUE;
    }
}
