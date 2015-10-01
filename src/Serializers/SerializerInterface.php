<?php

namespace Brightzone\GremlinDriver\Serializers;

/**
 * Gremlin-server PHP Interface for Serializer classes
 * Builds and parses message body for Messages class
 *
 * @category DB
 * @package  Serializers
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 */
interface SerializerInterface
{
    /**
     * Serializes the data
     *
     * @param array &$data data to be serialized
     *
     * @return int length of generated string
     */
    public function serialize(&$data);

    /**
     * Unserializes the data
     *
     * @param array $data data to be unserialized
     *
     * @return array unserialized message
     */
    public function unserialize($data);

    /**
     * Get this serializer's Name
     *
     * @return string name of serializer
     */
    public function getName();

    /**
     * Get this serializer's value
     * This will be deprecated with TP3 Gremlin-server
     *
     * @return string name of serializer
     */
    public function getMimeType();
}
