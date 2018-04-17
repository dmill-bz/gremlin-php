<?php

namespace Brightzone\GremlinDriver\Tests;

use Brightzone\GremlinDriver\Connection;
use Brightzone\GremlinDriver\Serializers\Gson3;


/**
 * Unit testing of Gremlin-php
 *
 * @category DB
 * @package  Tests
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 */
class RexsterWithGS3Test extends RexsterTest
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
     * Lets test returning a large set back from the database
     *
     * @return void
     */
    public
    function testTree()
    {
        $db = new Connection([
            'host'          => 'localhost',
            'port'          => 8182,
            'graph'         => 'graph',
            'retryAttempts' => 5,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $db->open();

        $expected = [
            [
                1 => [
                    'key'   => [
                        'id'         => 1,
                        'label'      => 'vertex',
                        'type'       => 'vertex',
                        'properties' => [
                            'name' => [['id' => 0, 'value' => 'marko', 'label' => 'name']],
                            'age'  => [['id' => 2, 'value' => 29, 'label' => 'age']],
                        ],
                    ],
                    'value' => [
                        2 => [
                            'key'   => [
                                'id'         => 2,
                                'label'      => 'vertex',
                                'type'       => 'vertex',
                                'properties' => [
                                    'name' => [['id' => 3, 'value' => 'vadas', 'label' => 'name']],
                                    'age'  => [['id' => 4, 'value' => 27, 'label' => 'age']],
                                ],
                            ],
                            'value' => [
                                3 => [
                                    'key'   => ['id' => 3, 'value' => 'vadas', 'label' => 'name'],
                                    'value' => [],
                                ],
                            ],
                        ],
                        3 => [
                            'key'   => [
                                'id'         => 3,
                                'label'      => 'vertex',
                                'type'       => 'vertex',
                                'properties' => [
                                    'name' => [['id' => 5, 'value' => 'lop', 'label' => 'name']],
                                    'lang' => [['id' => 6, 'value' => 'java', 'label' => 'lang']],
                                ],
                            ],
                            'value' => [
                                5 => [
                                    'key'   => ['id' => 5, 'value' => 'lop', 'label' => 'name'],
                                    'value' => [],
                                ],
                            ],
                        ],
                        4 => [
                            'key'   => [
                                'id'         => 4,
                                'label'      => 'vertex',
                                'type'       => 'vertex',
                                'properties' => [
                                    'name' => [['id' => 7, 'value' => 'josh', 'label' => 'name']],
                                    'age'  => [['id' => 8, 'value' => 32, 'label' => 'age']],
                                ],
                            ],
                            'value' => [
                                7 => [
                                    'key'   => ['id' => 7, 'value' => 'josh', 'label' => 'name'],
                                    'value' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $db->send('g.V(1).out().properties("name").tree()');
        $this->ksortTree($result);
        $this->ksortTree($expected);

        $this->assertEquals($expected, $result, "the response is not formated as expected.");

        $db->close();
    }

    private function ksortTree(&$array)
    {
        if(!is_array($array))
        {
            return FALSE;
        }

        ksort($array);
        foreach($array as $k => $v)
        {
            $this->ksortTree($array[$k]);
        }

        return TRUE;
    }
}
