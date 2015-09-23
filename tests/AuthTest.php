<?php
namespace Brightzone\GremlinDriver\Tests;

use Brightzone\GremlinDriver\Connection;
use Brightzone\GremlinDriver\Messages;

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
    public function testAuthenticationSimple()
    {
        $this->markTestSkipped("Skipping test until we sort out DB setup");
        $db = new Connection([
            'host' => 'localhost',
            'port' => 8182,
            'graph' => 'graph',
            'username' => 'stephen',
            'password' => 'password',
            'ssl' => TRUE,
        ]);
        $message = $db->open();

        $result = $db->send('5+5');
        $this->assertEquals($result[0], 10, 'Script response message is not the right type. (Maybe it\'s an error)'); //check it's a session script reply

    }

    public function testAuthenticationComplex()
    {
        $this->markTestSkipped("Skipping test until we sort out DB setup");
        $db = new Connection([
            'host' => 'localhost',
            'port' => 8182,
            'graph' => 'graph',
            'username' => 'stephen',
            'password' => 'password',
            'ssl' => TRUE,
        ]);
        $message = $db->open();

        $result = $db->send('g.V().emit().repeat(__.both()).times(5)');
        $this->assertEquals(count($result), 714, 'Did not find the correct amounts of vertices'); //check it's a session script reply
    }
}
