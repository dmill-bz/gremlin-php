<?php
namespace Brightzone\GremlinDriver\Tests;

use Brightzone\GremlinDriver\Connection;
use Brightzone\GremlinDriver\Helper;
use Brightzone\GremlinDriver\Message;
use Brightzone\GremlinDriver\Workload;

/**
 * Unit testing of Gremlin-php
 * This actually runs tests against the server
 *
 * @category DB
 * @package  Tests
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 */
class GremlinServerTest extends RexsterTestCase
{
    /**
     * Sends a param, saves it to a node then retrieves it and compares it to the original
     *
     * @param mixed $param the parameter we would like to submit
     *
     * @return void
     */
    private function sendParam($param)
    {
        $db = new Connection([
            'host' => 'localhost',
            'port' => 8182,
            'graph' => 'graph',
            'username' => $this->username,
            'password' => $this->password
        ]);
        $db->open();
        $message = $db->message;
        $message->gremlin = "graph.addVertex('paramTest', B_PARAM_VALUE);";
        $message->bindValue('B_PARAM_VALUE', $param);

        $db->send();

        $result = $db->send("g.V().has('paramTest').values('paramTest')");
        $db->run("g.V().has('paramTest').sideEffect{it.get().remove()}.iterate()");

        $this->assertTrue(!empty($result), "the result should contain a vertex");
        $this->assertSame($param, $result[0], "the param retrieved was different from the one sent");
    }

    /**
     * Test sending a mixed List param
     */
    public function testListParam()
    {
        $this->sendParam(["string1", "string2", "12", 3]);
    }

    /**
     * Test sending a mixed Map param
     */
    public function testMapParam()
    {
        $this->sendParam(["key1" => "string1", "key2"=>"string2", "1"=>"12", 2=>3]);
    }

    /**
     * Test sending a mixed Map param containing other maps
     */
    public function testMapofMapsParam()
    {
        $this->sendParam([
            "key1" => "string1",
            "key2"=>"string2",
            "1"=>"12",
            2=>3,
            "map"=>[
                "map"=>[
                    "map"=>[
                        "id"=>"lala"
                    ]
                ]
            ]
        ]);
    }

    /**
     * Test sending a mixed Map param
     */
    public function testListofMapsAndListParam()
    {
        $this->sendParam([
            [
                "map"=>[
                    "map"=>[
                        "id"=>"first"
                    ]
                ]
            ],
            [
                "map"=>[
                    "map"=>[
                        "id"=>"second"
                    ]
                ]
            ],
            [1,2,3,4],
            [
                "map"=>[
                    "map"=>[
                        "id"=>"third"
                    ]
                ]
            ]
        ]);
    }

    /**
     * Test sending a mixed Map param
     */
    public function testListofMixedParam()
    {
        $this->sendParam([
            "string1",
            "string2",
            "12",
            3,
            ["item1","item2"],
            [
                "map"=>[
                    "map"=>[
                        "id"=>"lala"
                    ]
                ]
            ]
        ]);
    }

}
