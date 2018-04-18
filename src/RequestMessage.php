<?php

namespace Brightzone\GremlinDriver;

use JsonSerializable;


/**
 * Class RequestMessage
 * The frame object that we use to make requests to the server.
 * This class allows us to specify different serializing for this class.
 * It's a glorified map.
 *
 * @author  Dylan Millikin <dylan.millikin@brightzone.com>
 * @package Brightzone\GremlinDriver
 */
class RequestMessage implements JsonSerializable
{
    /**
     * @var array the information contained in the message
     */
    private $data;

    /**
     * Overriding construct to populate data
     *
     * @param array $data the data contained in this message
     */
    public function __construct($data)
    {
        $this->setData($data);
    }

    /**
     * Getter for data
     *
     * @return array the data contained in the RequestMessage
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Setter for data
     *
     * @return void
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * The json serialize method
     * @return mixed
     */
    public function jsonSerialize()
    {
        return $this->getData();
    }
}