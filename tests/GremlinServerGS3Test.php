<?php

namespace Brightzone\GremlinDriver\Tests;

use Brightzone\GremlinDriver\Serializers\Gson3;
use Brightzone\GremlinDriver\Connection;


/**
 * Unit testing of Gremlin-php
 *
 * @category DB
 * @package  Tests
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 */
class GremlinServerGS3Test extends GremlinServerTest
{
    /**
     * Set the serializer up here
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        static::$serializer = new Gson3;
    }

    /**
     * Test the vertex format for changes
     * Different from GSON 1 because serializing server end adds data
     */
    public function testVertexFormat()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $db->open();
        $result = $db->send("g.V(1)");
        $vertex = [
            0 => [
                "id"         => 1,
                "label"      => "vertex",
                "properties" => [
                    "name" => [
                        0 => [
                            "id"    => 0,
                            "value" => "marko",
                            "label" => "name",
                        ],
                    ],
                    "age"  => [
                        0 => [
                            "id"    => 2,
                            "value" => 29,
                            "label" => "age",
                        ],
                    ],
                ],
                "type"       => "vertex",
            ],
        ];
        $this->ksortTree($vertex);
        $this->ksortTree($result);
        $this->assertEquals($vertex, $result, "vertex property wasn't as expected");
    }

    /**
     * Test the edge format for changes
     */
    public function testEdgeFormat()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $db->open();
        $result = $db->send("g.E(12)");
        $edge = [
            0 => [
                "id"         => 12,
                "label"      => "created",
                "inVLabel"   => "vertex",
                "outVLabel"  => "vertex",
                "inV"        => 3,
                "outV"       => 6,
                "properties" => [
                    "weight" => [
                        "key"   => "weight",
                        "value" => 0.2,
                    ],
                ],
                "type"       => "edge",
            ],
        ];
        $this->ksortTree($edge);
        $this->ksortTree($result);
        $this->assertEquals($edge, $result, "vertex property wasn't as expected");
    }
}
