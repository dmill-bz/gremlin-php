<?php
namespace brightzone\rexpro\tests;

use brightzone\rexpro\Connection;
use brightzone\rexpro\Messages;
use brightzone\rexpro\Exceptions;
use brightzone\rexpro\Helper;


/**
 * Unit testing of Rexpro-php documentation examples
 *
 * @category DB
 * @package  gremlin-php-tests
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki
 */
class RexsterTestExamples extends RexsterTestCase
{
    /**
     * Testing Example 1
     *
     * @return void
     */
    public function testExample1()
    {
        $db = new Connection;
        //you can set $db->timeout = 0.5; if you wish
        $db->open('localhost', 'graph');
        $db->send('g.V(2)');
        //do something with result
        $db->close();
    }

    /**
     * Testing Example 1 bis
     *
     * @return void
     */
    public function testExample1B()
    {
        $db = new Connection;
        //you can set $db->timeout = 0.5; if you wish
        $db->open('localhost', 'graph');
        $db->message->gremlin = 'g.V(2)';
        $db->send(); //automatically fetches the message
        //do something with result
        $db->close();
    }

    /**
     * Testing Example 2
     *
     * @return void
     */
    public function testExample2()
    {
        $db = new Connection;
        $db->open('localhost:8182', 'graph');

        $db->message->bindValue('CUSTO_BINDING', 2);
        $db->send('g.V(CUSTO_BINDING)'); //mix between Example 1 and 1B
        //do something with result
        $db->close();
    }

    /**
     * Testing Example 3
     *
     * @return void
     */
    public function testExample3()
    {
        $db = new Connection;
        $db->open('localhost:8182');
        $db->send('cal = 5+5', 'session');
        $result = $db->send('cal', 'session'); // result = [10]
        $this->assertEquals($result[0], 10, 'Error asserting proper result for example 3');
        //do something with result
        $db->close();
    }

    /**
     * Testing Example 4
     *
     * @return void
     */
    public function testExample4()
    {
        $db = new Connection;
        $db->open('localhost:8182', 'graphT');
        $originalCount = $db->send('n.V().count()');

        $db->transactionStart();

        $db->send('n.addVertex("name","michael")');
        $db->send('n.addVertex("name","john")');

        $db->transactionStop(FALSE); //rollback changes. Set to true to commit.

        $newCount = $db->send('n.V().count()');
        $this->assertEquals($newCount, $originalCount, 'Rollback was not done for eample 4');

        $db->close();
    }


    /**
     * Testing Example 5
     *
     * @return void
     */
    public function testExample5()
    {
        $message = new Messages;
        $message->gremlin = 'g.V()';
        $message->op = 'eval';
        $message->processor = '';
        $message->setArguments([
                        'language' => 'gremlin-groovy',
                        // .... etc
        ]);
        $message->registerSerializer('\brightzone\rexpro\serializers\Json');

        $db = new Connection;
        $db->open();
        $db->send($message);
        //do something with result
        $db->close();
    }
}
