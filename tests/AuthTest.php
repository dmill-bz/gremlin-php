<?php

namespace Brightzone\GremlinDriver\Tests;

use Brightzone\GremlinDriver\Connection;

/**
 * Unit testing of Gremlin-php authentication
 *
 * @category DB
 * @package  Tests
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 */
class AuthTest extends RexsterTestCase
{
    /**
     * Testing a simple authentication
     * @return void
     */
    public function testAuthenticationSimple()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8184,
            'graph'    => 'graph',
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
        $message = $db->open();

        $result = $db->send('5+5');
        $this->assertEquals($result[0], 10, 'Script response message is not the right type. (Maybe it\'s an error)'); //check it's a session script reply

    }

    /**
     * Test a simple auth with a more complex query
     * @return void
     */
    public function testAuthenticationComplex()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8184,
            'graph'    => 'graph',
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
        $message = $db->open();

        $result = $db->send('g.V().emit().repeat(__.both()).times(5)');
        $this->assertEquals(count($result), 714, 'Did not find the correct amounts of vertices'); //check it's a session script reply
    }

    /**
     * testing an in session authentication
     * @return void
     */
    public function testAuthenticationSession()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8184,
            'graph'    => 'graph',
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
}
