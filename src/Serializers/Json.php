<?php

namespace Brightzone\GremlinDriver\Serializers;

/**
 * Gremlin-server PHP JSON Serializer class
 * Builds and parses message body for Messages class
 *
 * @category DB
 * @package  Serializers
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 */
class Json implements SerializerInterface
{
    /**
     * @var string the name of the serializer
     */
    public static $name = 'JSON';

    /**
     * @var int Value of this serializer. Will be deprecated in TP3
     */
    public static $mimeType = 'application/json';

    /**
     * Serializes the data
     *
     * @param array &$data data to be serialized
     *
     * @return int length of generated string
     */
    public function serialize(&$data)
    {
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);

        return mb_strlen($data, 'ISO-8859-1');
    }

    /**
     * Unserializes the data
     *
     * @param array $data data to be unserialized
     *
     * @return array unserialized message
     */
    public function unserialize($data)
    {
        $mssg = json_decode($data, TRUE, JSON_UNESCAPED_UNICODE);

        return $mssg;
    }

    /**
     * Get this serializer's Name
     *
     * @return string name of serializer
     */
    public function getName()
    {
        return self::$name;
    }

    /**
     * Get this serializer's value
     * This will be deprecated with TP3 Gremlin-server
     *
     * @return string name of serializer
     */
    public function getMimeType()
    {
        return self::$mimeType;
    }
}
