<?php

namespace Brightzone\GremlinDriver\Tests\Stubs;


use Brightzone\GremlinDriver\InternalException;
use \Brightzone\GremlinDriver\Message;
use Brightzone\GremlinDriver\RequestMessage;

/**
 * Class IncorrectlyFormattedMessage
 * Will incorrectly pack the message
 *
 * @author  Dylan Millikin <dylan.millikin@brightzone.com>
 * @package Brightzone\GremlinDriver\Tests\Stubs
 */
class IncorrectlyFormattedMessage extends Message
{
    /**
     * @var bool throw an error on parse or not
     */
    public $throwErrorOnParse = FALSE;

    /**
     * incorrectly format sent message
     * @return string
     */
    public function buildMessage()
    {
        $finalMessage = pack('C', strlen("application/json")) . 5 . "somethinglongerthan5";

        return $finalMessage;
    }

    /**
     * Throw an error for testing purposes
     *
     * @param string $payload
     * @param bool   $isBinary
     *
     * @return void
     * @throws InternalException
     */
    public function parse($payload, $isBinary)
    {
        if($this->throwErrorOnParse)
        {
            throw new InternalException("Some test error", 500);
        }

        return parent::parse($payload, $isBinary);
    }
}