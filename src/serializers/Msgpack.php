<?php

namespace brightzone\rexpro\serializers;

use \brightzone\rexpro\Messages;
use \brightzone\rexpro\Helper;

/**
 * RexPro PHP MSGPACK Serializer class
 * Builds and parses message body for Messages class
 *
 * @category DB
 * @package  Rexpro
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki/RexPro-Messages
 */
class Msgpack implements SerializerInterface
{
    /**
     * @var string the name of the serializer
     */
    public static $name = 'MSGPACK';

    /**
     * @var int Value of this serializer. Will be deprecated in TP3
     */
    public static $mimeType = 'application/msgpack';

    /**
     * Serializes the data
     *
     * @param array &$data data to be serialized
     *
     * @return int length of generated string
     */
    public function serialize(&$data)
    {
        $data[0] = Helper::uuidToBin($data[0]);
        $data[1] = Helper::uuidToBin($data[1]);
        $data = msgpack_pack($data);

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
        $mssg = msgpack_unpack($data);
        //lets just make UUIDs readable incase we need to debug
        $mssg[0] = Helper::binToUuid($mssg[0]);
        $mssg[1] = Helper::binToUuid($mssg[1]);

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