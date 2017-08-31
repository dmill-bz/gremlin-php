<?php

namespace Brightzone\GremlinDriver\Tests;

use Brightzone\GremlinDriver\Connection;
use Brightzone\GremlinDriver\Helper;
use Brightzone\GremlinDriver\Message;
use Brightzone\GremlinDriver\Workload;

/**
 * Unit testing of Gremlin-php
 *
 * @category DB
 * @package  Tests
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 */
class RexsterTest extends RexsterTestCase
{
    /**
     * Testing UUID
     *
     * @return void
     */
    public function testCreateUuid()
    {
        $uuid1 = Helper::createUuid();
        $this->assertTRUE(mb_strlen($uuid1, 'ISO-8859-1') == 36, 'The generated UUID is not the correct length ');
        $this->assertTRUE(count(str_split($uuid1, 1)) == 36, 'The generated UUID is not the correct length');

        $uuid = Helper::uuidToBin($uuid1);
        $this->assertTRUE(mb_strlen($uuid, 'ISO-8859-1') == 16, 'The conversion to bin of the UUID is not the correct length (16 bytes)');
        $this->assertTRUE(count(str_split($uuid, 1)) == 16, 'The conversion to bin of the UUID is not the correct length (16 bytes)');
        //test that the bin format is correct
        $this->assertEquals(bin2hex($uuid), str_replace('-', '', trim($uuid1)), 'The conversion to bin of the UUID is incorrect');

        $uuid = Helper::binToUuid($uuid);
        $this->assertTRUE(mb_strlen($uuid, 'ISO-8859-1') == 36, 'The conversion of bin UUID to UUID is not the correct length');
        $this->assertTRUE(count(str_split($uuid, 1)) == 36, 'The conversion of bin UUID to UUID is not the correct length');
        $this->assertEquals($uuid, $uuid1, 'UUID before and after convertion do not match');
    }

    /**
     * Testing binary conversion TO
     *
     * @return void
     */
    public function testConvertIntTo32Bit()
    {
        $converted = Helper::convertIntTo32Bit(84);
        $this->assertEquals(mb_strlen($converted, 'ISO-8859-1'), 4, 'The converted int is not the correct byte length (4 bytes)'); //should be 32 bits / 4 bytes
        $this->assertEquals(bin2hex($converted), '00000054', 'The converted int is incorrect');

        $converted = Helper::convertIntTo32Bit(9999);
        $this->assertEquals(mb_strlen($converted, 'ISO-8859-1'), 4, 'The converted int is not the correct byte length (4 bytes)'); //should be 32 bits / 4 bytes
        $this->assertEquals(bin2hex($converted), '0000270f', 'The converted int is incorrect');

        $converted = Helper::convertIntTo32Bit(10000000000);
        $this->assertEquals(mb_strlen($converted, 'ISO-8859-1'), 4, 'The converted int is not the correct byte length (4 bytes)'); //should be 32 bits / 4 bytes
        $this->assertNotEquals(bin2hex($converted), '2540BE400', 'The converted int is incorrect. ints above 4 bytes should have the extra bytes truncated'); // hex for 10000000000
        //the extra 3 bits should be taken off the begining of binary data. This test checks this
        $this->assertEquals(bin2hex($converted), '540be400', 'The converted int is incorrect. ints above 4 bytes should have the extra bytes truncated');
    }

    /**
     * Testing binary conversion FROM
     *
     * @return void
     */
    public function testConvertIntFrom32Bit()
    {
        $converted = Helper::convertIntFrom32Bit(Helper::convertIntTo32Bit(84));
        $this->assertEquals($converted, 84, 'The conversion of 32bit int to int is incorrect');

        $converted = Helper::convertIntFrom32Bit(Helper::convertIntTo32Bit(9999));
        $this->assertEquals($converted, 9999, 'The conversion of 32bit int to int is incorrect');

        $converted = Helper::convertIntFrom32Bit(Helper::convertIntTo32Bit(10000000000));
        $this->assertEquals($converted, 1410065408, 'The conversion of 32bit int to int is incorrect. Bit truncating issue'); //bit truncating check
    }

    /**
     * Testing Connection
     *
     * @return void
     */
    public function testConnectSuccess()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $this->assertTrue($db->open(), "did not succesfully connect");

        $db = new Connection([
            'host'     => 'localhost',
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $this->assertTrue($db->open(), "did not succesfully connect");

        $db = new Connection([
            'host'     => 'localhost',
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $this->assertTrue($db->open(), "did not succesfully connect");
    }

    /**
     * Testing unknown host connection errors
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     *
     * @return void
     */
    public function testConnectErrorsUnknownHost()
    {
        $db = new Connection([
            'host' => 'unknownhost',
        ]);
        $db->timeout = 0.5;
        $db->open();
    }

    /**
     * Testing unknown host connection errors try catch scenario
     * This is meant to cover issue #37
     *
     * @return void
     */
    public function testConnectErrorsUnknownHostTryCatch()
    {
        $db = new Connection([
            'host' => 'unknownhost',
        ]);
        $db->timeout = 0.5;
        try
        {
            $db->open();
        }
        catch(\Brightzone\GremlinDriver\InternalException $e)
        {
            $error_message = $e->getMessage();
        }
        $this->assertTrue(!empty($error_message), "An error message should be set");
    }

    /**
     * Testing wrong port connection errors
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     *
     * @return void
     */
    public function testConnectErrorsWrongPort()
    {
        $db = new Connection([
            'port' => 8187,
        ]);
        $db->timeout = 0.5;
        $db->open();
    }

    /**
     * Testing connection close
     *
     * @return void
     */
    public function testConnectCloseSuccess()
    {
        //do all connection checks
        $db = new Connection([
            'host'     => 'localhost',
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db->open();

        //check disconnection
        $db->close();
        $this->assertFALSE($db->isConnected(), 'Despite not throwing errors, Socket connection is still established');
    }

    /**
     * Testing Script run against DB
     *
     * @return void
     */
    public function testRunScriptNoSession()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE, 'Failed to connect to db');

        $result = $db->send('5+5');
        $this->assertEquals(10, $result[0], 'Script response message is not the right type. (Maybe it\'s an error)');

        $result = $db->send('g.V()');
        $this->assertEquals(6, count($result), 'Script response message is not the right type. (Maybe it\'s an error)');

        //check disconnection
        $db->close();
        $this->assertFALSE($db->isConnected(), 'Despite not throwing errors, Socket connection is not established');
    }

    /**
     * Testing Script run against DB
     * Sessions and transactions are linked ATM
     *
     * @return void
     */
    public function testRunScriptSession()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $message = $db->open();

        $this->assertNotEquals($message, FALSE, 'Failed to connect to db');

        $result = $db->send('cal = 5+5', 'session', 'eval');

        $this->assertEquals($result[0], 10, 'Script response message is not the right type. (Maybe it\'s an error)'); //check it's a session script reply

        $result = $db->send('cal', 'session', 'eval');
        $this->assertEquals($result[0], 10, 'Script response message is not the right type. (Maybe it\'s an error)'); //check it's a session script reply

        //check disconnection
        $db->close();
        $this->assertFALSE($db->isConnected(), 'Despite not throwing errors, Socket connection is not established');
        $this->assertFALSE($db->inTransaction(), 'Despite closing, transaction not closed');
    }

    /**
     * Testing That the server closes the session
     *
     * @expectedException \Brightzone\GremlinDriver\ServerException
     *
     * @return void
     */
    public function testSessionClose()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $message = $db->open();

        $this->assertNotEquals($message, FALSE, 'Failed to connect to db');

        $result = $db->send('cal = 5+5', 'session', 'eval');
        $sessionUid = $db->getSession();
        $this->assertEquals($result[0], 10, 'Script response message is not the right type. (Maybe it\'s an error)'); //check it's a session script reply

        $result = $db->send('cal', 'session', 'eval');
        $this->assertEquals($result[0], 10, 'Script response message is not the right type. (Maybe it\'s an error)'); //check it's a session script reply

        //check disconnection
        try
        {
            $db->close();
        }
        catch(\Exception $e)
        {
            $this->fail("Close shouldn't throw an exception");
        }
        $this->assertFALSE($db->isConnected(), 'Despite not throwing errors, Socket connection is established');
        $this->assertFALSE($db->inTransaction(), 'Despite closing, transaction not closed');

        $db2 = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $message = $db2->open();

        $this->assertNotEquals($message, FALSE, 'Failed to connect to db');
        $msg = new Message();
        $msg->registerSerializer('\Brightzone\GremlinDriver\Serializers\Json');
        $msg->gremlin = 'cal';
        $msg->op = 'eval';
        $msg->processor = 'session';
        $msg->setArguments(['session' => $sessionUid]);
        $result = $db2->send($msg); // should throw an error as this should be next session
        $this->fail("Second request should have failed and this assert never run");
    }

    /**
     * Testing Script run with bindings
     *
     * @return void
     */
    public function testRunScriptWithBindings()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $db->message->gremlin = 'g.V(CUSTO_BINDING)';
        $db->message->bindValue('CUSTO_BINDING', 2);
        $result = $db->send();

        $this->assertNotEquals($result, FALSE, 'Running a script with bindings produced an error');

        //check disconnection
        $message = $db->close();
        $this->assertNotEquals($message, FALSE, 'Disconnecting from a session where bindings were used created an error');
    }

    /**
     * Testing Script run with bindings
     *
     * @return void
     */
    public function testRunScriptWithVarsInSession()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $db->message->gremlin = 'cal = 5+5';
        $db->message->processor = 'session';
        $db->message->setArguments(['session' => $db->getSession()]);
        $result = $db->send(NULL);

        $this->assertNotEquals($result, FALSE, 'Running a script with bindings produced an error');

        $db->message->gremlin = 'cal = 5+5';
        $result = $db->send(NULL, 'session', 'eval');
        $this->assertEquals($result, [10], 'Running a script with bindings produced an error');


        //check disconnection
        $message = $db->close();
        $this->assertNotEquals($message, FALSE, 'Disconnecting from a session where bindings were used created an error');
    }

    /**
     * Testing Script run with bindings
     *
     * @return void
     */
    public function testRunScriptWithBindingsInSession()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $db->message->gremlin = 'g.V(CUSTO_BIND)';
        $db->message->bindValue('CUSTO_BIND', 2);
        $result = $db->send(NULL, 'session', 'eval');

        $this->assertNotEquals($result, [], 'Running a script with bindings produced an error');

        $db->message->gremlin = 'g.V(CUSTO_BIND)';
        $result = $db->send(NULL, 'session', 'eval');
        $this->assertNotEquals($result, [], 'Running a script with bindings produced an error');


        //check disconnection
        $message = $db->close();
        $this->assertNotEquals($message, FALSE, 'Disconnecting from a session where bindings were used created an error');
    }

    /**
     * Testing sendMessage without previous connection
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     *
     * @return void
     */
    public function testSendMessageWithoutConnection()
    {
        $db = new Connection();
        $msg = new Message();
        $db->send($msg);
    }

    /**
     * Testing runScript() without making a previous
     * socket connection with open()
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     *
     * @return void
     */
    public function testRunScriptWithoutConnection()
    {
        $db = new Connection();
        $db->send();
    }

    /**
     * Testing getSerializer
     *
     * @return void
     */
    public function testgetSerializer()
    {
        $db = new Connection();
        $serializer = $db->message->getSerializer();

        $this->assertTRUE($serializer instanceof \Brightzone\GremlinDriver\Serializers\Json, 'Initial serializer set failed');
        $db->message->registerSerializer('\Brightzone\GremlinDriver\Tests\Stubs\TestSerializer');
        $this->assertTRUE($db->message->getSerializer() instanceof \Brightzone\GremlinDriver\Tests\Stubs\TestSerializer, 'Failed to change serializer');
    }

    /**
     * Testing getSerializer name
     *
     * @return void
     */
    public function testgetSerializerName()
    {
        $db = new Connection();
        $serializer = $db->message->getSerializer();

        $this->assertEquals('JSON', $serializer->getName(), 'Incorrect serializer name');
    }

    /**
     * Testing getSerializer by mimeType
     *
     * @return void
     */
    public function testgetSerializerByMimeType()
    {
        $db = new Connection();
        $db->message->registerSerializer('\Brightzone\GremlinDriver\Tests\Stubs\TestSerializer');
        $db->message->registerSerializer('\Brightzone\GremlinDriver\Serializers\Json');
        $serializer = $db->message->getSerializer('application/json');
        $this->assertEquals('JSON', $serializer->getName(), 'Incorrect serializer name');
        $serializer = $db->message->getSerializer('application/test');
        $this->assertEquals('TEST', $serializer->getName(), 'Incorrect serializer name');
    }

    /**
     * Testing getSerializer
     *
     * @expectedException \Brightzone\GremlinDriver\ServerException
     *
     * @return void
     */
    public function testIncorrectGremlin()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE, 'Failed to connect to db');

        $db->send('g.V().incorect()');
    }

    /**
     * Testing getSerializer
     *
     * @expectedException \Brightzone\GremlinDriver\ServerException
     *
     * @return void
     */
    public function testEmptyResult()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE, 'Failed to connect to db');

        $db->send('g.V().has("idontexists")');
    }

    /**
     * Testing Helper random string generator with spaces
     *
     * @return void
     */
    public function testRandomGenerator()
    {
        $string = Helper::generateRandomString(10, TRUE, FALSE);
        $this->assertTrue(strlen($string) == 10, "string should contain 10 characters");
        $this->assertTrue(strpos($string, ' ') !== FALSE, "spaces should have been found");
    }

    /**
     * Testing Message isset
     *
     * @return void
     */
    public function testMessageIsset()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db->open();
        $this->assertTrue(isset($db->message->gremlin), 'gremlin should not be set');
        $db->message->gremlin = "5 + 5";
        $this->assertTrue(isset($db->message->gremlin), 'gremlin should be set');
        $this->assertTrue(isset($db->message->op), 'op should be set');
    }

    /**
     * Testing Message getter error
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     *
     * @return void
     */
    public function testMessageGetError()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db->open();
        $this->assertTrue(isset($db->message->gremlin), 'gremlin should not be set');
        $what = $db->message->something;
    }

    /**
     * Test Connection Construct
     *
     * @return void
     */
    public function testConnectionConstruct()
    {
        $db = new Connection(['host' => 'localhost', 'port' => 8182, 'graph' => 'graph']);
        $this->assertEquals($db->host, 'localhost', 'incorrect host');
        $this->assertEquals($db->port, 8182, 'incorrect port');
        $this->assertEquals($db->graph, 'graph', 'incorrect graph');
    }

    /**
     * Test merging of streamed results.
     *
     * @return void
     */
    public function testMergedStream()
    {
        $db = new Connection([
            'host'  => 'localhost',
            'port'  => 8182,
            'graph' => 'graph',
        ]);
        $message = $db->open();

        $result = $db->send('g.V().emit().repeat(__.both()).times(5)');
        $this->assertEquals(714, count($result), 'Did not find the correct amounts of vertices'); //check it's a session script reply
    }

    /**
     * Test Workload retry strategy
     *
     * @expectedException \Brightzone\GremlinDriver\ServerException
     *
     * @return void
     */
    public function testRetry()
    {
        $db = new Connection([
            'host'          => 'localhost',
            'port'          => 8182,
            'graph'         => 'graph',
            'retryAttempts' => 5,
        ]);
        $db->open();

        $count = 0;
        $workload = new Workload(function(&$count) {
            $count++;
            throw new \Brightzone\GremlinDriver\ServerException("test error", 597);
        }, [&$count]);

        try
        {
            $response = $workload->linearRetry($db->retryAttempts);
        }
        catch(\Exception $e)
        {
            $this->assertEquals(5, $count, "incorrect number of attempts executed");
            throw $e;
        }
    }

    /**
     * Test opening multiple times
     *
     * @return void
     */
    public function testMultipleOpen()
    {
        $db = new Connection([
            'host'          => 'localhost',
            'port'          => 8182,
            'graph'         => 'graph',
            'retryAttempts' => 5,
        ]);
        $db->open();
        $db->open();
    }

    /**
     * Test unmasking of packet
     *
     * @return void
     */
    public function testPackUnpack()
    {
        $message = new Message();
        //$message->status = ["code" => 200];
        $message->gremlin = "something";
        $message->registerSerializer('\Brightzone\GremlinDriver\Serializers\Json');

        $payload = $message->buildMessage();

        $expected = [
            'requestId' => $message->requestUuid,
            'processor' => '',
            'op'        => 'eval',
            'args'      => [
                'gremlin' => 'something',
            ],
        ];

        $connection = new \Brightzone\GremlinDriver\Tests\Stubs\Connection(["_acceptDiffResponseFormat" => TRUE]);
        $connection->setSocket($this->invokeMethod($connection, 'webSocketPack', [$payload, $type = 'binary', $masked = TRUE]));

        $data = $this->invokeMethod($connection, 'socketGetMessage');
        $this->assertEquals($expected, $data, "could not unpack properly");
    }

    /**
     * Test configuration for empty result sets instead of error
     *
     * @return void
     */
    public function testEmptyResultSetNoException()
    {
        $db = new Connection([
            'host'          => 'localhost',
            'port'          => 8182,
            'graph'         => 'graph',
            'retryAttempts' => 5,
            'emptySet'      => TRUE,
        ]);
        $db->open();

        $result = $db->send("g.V().has('name', 'doesnotexist')");
        $this->assertTrue(empty($result), "the result set should be empty");
    }

    /**
     * Test configuration for empty result sets instead of error
     *
     * @expectedException \Brightzone\GremlinDriver\ServerException
     *
     * @return void
     */
    public function testEmptyResultSetException()
    {
        $db = new Connection([
            'host'          => 'localhost',
            'port'          => 8182,
            'graph'         => 'graph',
            'retryAttempts' => 5,
        ]);
        $db->open();

        $result = $db->send("g.V().has('name', 'doesnotexist')");
    }

    /**
     * Lets test returning a large set back from the database
     *
     * @return void
     */
    public function testLargeResponseSet()
    {
        $db = new Connection([
            'host'          => 'localhost',
            'port'          => 8182,
            'graph'         => 'graph',
            'retryAttempts' => 5,
            //'emptySet' => TRUE
        ]);
        $db->open();

        $db->send("
            for(i in 1..35){
                graph.addVertex('name', 'john', 'age', 25, 'somefillertext', 'FFFFFFFFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF FFFFFFFFFFFFFFFFFF')
            }"
        );

        $result = $db->send("g.V()");

        $db->run("g.V().has('name', 'john').sideEffect{it.get().remove()}.iterate()");

        $db->close();
    }

    /**
     * Lets test returning a large set back from the database
     *
     * @return void
     */
    public function testTree()
    {
        $db = new Connection([
            'host'          => 'localhost',
            'port'          => 8182,
            'graph'         => 'graph',
            'retryAttempts' => 5,
        ]);
        $db->open();

        $expected = [
            [
                1 => [
                    'key'   => [
                        'id'         => 1,
                        'label'      => 'vertex',
                        'type'       => 'vertex',
                        'properties' => [
                            'name' => [['id' => 0, 'value' => 'marko']],
                            'age'  => [['id' => 2, 'value' => 29]],
                        ],
                    ],
                    'value' => [
                        2 => [
                            'key'   => [
                                'id'         => 2,
                                'label'      => 'vertex',
                                'type'       => 'vertex',
                                'properties' => [
                                    'name' => [['id' => 3, 'value' => 'vadas']],
                                    'age'  => [['id' => 4, 'value' => 27]],
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
                                    'name' => [['id' => 5, 'value' => 'lop']],
                                    'lang' => [['id' => 6, 'value' => 'java']],
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
                                    'name' => [['id' => 7, 'value' => 'josh']],
                                    'age'  => [['id' => 8, 'value' => 32]],
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

        $this->assertEquals($expected, $result, "the response is not formated as expected.");

        $db->close();
    }

    /**
     * Testing Aliases in message
     */
    public function testLocalAliases()
    {
        $db = new Connection([
            'graph' => 'graph',
        ]);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $db->message->gremlin = 'crazyname.V().count()';
        $db->message->setArguments([
            'aliases' => ['crazyname' => 'g'],
        ]);
        $result = $db->send();

        $this->assertEquals($result[0], 6, 'Script request did not return the correct count');
    }

    /**
     * Testing Global connection Aliases
     */
    public function testGlobalAliases()
    {
        $db = new Connection([
            'graph'   => 'graph',
            'aliases' => ['crazyname' => 'g'],
        ]);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $db->message->gremlin = 'crazyname.V().count()';
        $result = $db->send();

        $this->assertEquals($result[0], 6, 'Script request did not return the correct count');
    }

    /**
     * Testing script evaluation timeout
     *
     * @expectedException \Brightzone\GremlinDriver\ServerException
     */
    public function testScriptEvalTimeout()
    {
        $db = new Connection([
            'graph' => 'graph',
        ]);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $db->message->gremlin = 'Thread.sleep(4000);g.V().count()';
        $db->message->setArguments([
            'scriptEvaluationTimeout' => 100,
        ]);
        $result = $db->send();

        $this->fail('We should get a timeout error.');
    }

    /**
     * Bindings should be cleared in between requests
     */
    public function testClearBindings()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $db->message->gremlin = 'g.V(CUSTO_BIND)';
        $db->message->bindValue('CUSTO_BIND', 2);
        $result = $db->send(NULL, 'session', 'eval');

        $this->assertNotEquals($result, [], 'Running a script with bindings produced an error');


        //the binding should no longer reside on the client side but instead only be on the server.
        $this->assertTrue(!isset($db->message->args['bindings']), "there should be no registered bindings in the message");

        $db->message->gremlin = 'g.V(CUSTO_BIND)';
        $result = $db->send(NULL, 'session', 'eval');
        $this->assertNotEquals($result, [], 'Running a script with bindings produced an error');


        //check disconnection
        $message = $db->close();
        $this->assertNotEquals($message, FALSE, 'Disconnecting from a session where bindings were used created an error');
    }
}
