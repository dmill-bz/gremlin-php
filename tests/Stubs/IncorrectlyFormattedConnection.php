<?php

namespace Brightzone\GremlinDriver\Tests\Stubs;


use \Brightzone\GremlinDriver\Connection;

/**
 * Class IncorrectlyFormattedConnection
 * Will incorrectly pack the websocket message
 *
 * @author  Dylan Millikin <dylan.millikin@brightzone.com>
 * @package Brightzone\GremlinDriver\Tests\Stubs
 */
class IncorrectlyFormattedConnection extends Connection
{
    /**
     * Incorrectly packing the message
     *
     * @param string $payload
     * @param string $type
     * @param bool   $masked
     *
     * @return string
     */
    protected function webSocketPack($payload, $type = 'binary', $masked = TRUE)
    {
        return "not a correctly formatted message";
    }
}