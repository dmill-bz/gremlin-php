<?php

namespace Brightzone\GremlinDriver\Tests;

use Brightzone\GremlinDriver\Connection;
use Brightzone\GremlinDriver\Message;
use Brightzone\GremlinDriver\Exceptions;
use Brightzone\GremlinDriver\Helper;


/**
 * Unit testing of Gremlin-php documentation examples
 *
 * @category DB
 * @package  Tests
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki
 */
class RexsterExamplesTest extends RexsterTestCase
{
    /**
     * Testing Basic feature example 1
     *
     * @return void
     */
    public function testBasicFeatures1()
    {
        $db = new Connection([
            'host'  => 'localhost',
            'graph' => 'graph',
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        //you can set $db->timeout = 0.5; if you wish
        $db->open();

        $result = $db->send('g.V(2)');
        //do something with result
        $db->close();
        $this->assertTrue(TRUE); // should always get here
    }

    /**
     * Testing Basic feature example 2 bis
     *
     * @return void
     */
    public function testBasicfeatures2()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'graph'    => 'graph',
            'port'     => 8184,
            'username' => 'stephen',
            'password' => 'password',
            'ssl'      => [
                "ssl" => [
                    "verify_peer"      => FALSE,
                    "verify_peer_name" => FALSE,
                ],
            ],
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        //you can set $db->timeout = 0.5; if you wish
        $db->open();
        $db->send('g.V(2)');
        //do something with result
        $db->close();

        $this->assertTrue(TRUE); // should always get here
    }

    /**
     * Testing Binding example 1
     *
     * @return void
     */
    public function testBindings1()
    {
        $unsafeUserValue = 2; //This could be anything submitted via form.
        $db = new Connection([
            'host'  => 'localhost',
            'port'  => 8182,
            'graph' => 'graph',
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $db->open();

        $db->message->bindValue('CUSTO_BINDING', $unsafeUserValue); // protects from injections
        $result1 = $db->send('g.V(CUSTO_BINDING)'); // The server compiles this script and adds it to cache

        $this->assertNotEmpty($result1, "the result should be populated by vertex 2");

        $db->message->bindValue('CUSTO_BINDING', 5);
        $result2 = $db->send('g.V(CUSTO_BINDING)'); // The server already has this script so gets it from cache without compiling it, but runs it with 5 instead of $unsafeUserValue
        $result3 = $db->send('g.V(5)'); // The script is different so the server compiles this script and adds it to cache

        $this->assertEquals($result2, $result3, "both results should be equivalent");

        //do something with result
        $db->close();
    }

    /**
     * Testing Sessions example 1
     *
     * @return void
     */
    public function testSessions1()
    {
        $db = new Connection([
            'host' => 'localhost',
            'port' => 8182,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $db->open();
        $db->send('cal = 5+5', 'session'); // first query sets the `cal` variable
        $result = $db->send('cal', 'session'); // result = [10]
        //do something with result
        $this->assertEquals(10, $result[0], "should equal 10"); // should always get here

        $db->close();
    }

    /**
     * Testing Transaction Example 1
     *
     * @return void
     */
    public function testTransaction1()
    {
        $db = new Connection([
            'host'  => 'localhost',
            'port'  => 8182,
            'graph' => 'graphT',
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $db->open();

        $result = $db->send("t.V().count()");

        $db->transactionStart();

        $db->send('graphT.addVertex("name","michael")');
        $db->send('graphT.addVertex("name","john")');

        $db->transactionStop(FALSE); //rollback changes. Set to TRUE to commit.
        $result2 = $db->send("t.V().count()");

        $this->assertEquals($result, $result2, "Vertices were added when they should've been rolled back");
        $db->close();
    }

    /**
     * Testing Transaction example 2
     *
     * @return void
     */
    public function testTransaction2()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graphT',
            'emptySet' => TRUE,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $db->open();

        $result = $db->send("t.V().count()");

        $db->transaction(function($db) {
            $db->send('t.addV().property("name","crazy")');
            $db->send('t.addV().property("name","crazy")');
        }, [$db]);

        $result2 = $db->send("t.V().count()");
        $db->run("t.V().has('name', 'crazy').drop()");
        $this->assertEquals($result[0], $result2[0] - 2, "vertices were not properly added");

        $db->close();
    }

    /**
     * Testing Message objects example 1
     *
     * @return void
     */
    public function testMessage1()
    {
        $message = new Message;
        $message->gremlin = 'custom.V()'; // note that custom refers to the graph traversal set on the server as g (see alias bellow)
        $message->op = 'eval'; // operation we want to run
        $message->processor = ''; // the opProcessor the server should use
        $message->setArguments([
            'language' => 'gremlin-groovy',
            'aliases'  => ['custom' => 'g'],
            // ... etc
        ]);
        $message->registerSerializer(static::$serializer);

        $db = new Connection();
        $db->open();
        $db->send($message);
        //do something with result
        $db->close();

        $this->assertTrue(TRUE); // should always get here

    }

    /**
     * Testing Serializer example 1
     *
     * @return void
     */
    public function testSerializer1()
    {
        $db = new Connection;
        $s = static::$serializer;
        $db->message->registerSerializer($s, TRUE);
        $serializer = $db->message->getSerializer(); // returns an instance of the default serializer
        $this->assertEquals($s->getName(), $serializer->getName(), "incorrect serializer name");
        $this->assertEquals("application/json", $serializer->getMimeType(), "incorrect mimtype found. should be application/json");

        $db->message->registerSerializer('\Brightzone\GremlinDriver\Tests\Stubs\TestSerializer', TRUE);

        $this->assertEquals("Brightzone\GremlinDriver\Tests\Stubs\TestSerializer", get_class($db->message->getSerializer()), "incorrect serializer found");
        $this->assertEquals(get_class(static::$serializer), get_class($db->message->getSerializer('application/json')), "incorrect serializer found");
    }
}
