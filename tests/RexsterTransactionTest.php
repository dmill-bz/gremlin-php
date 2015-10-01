<?php
namespace Brightzone\GremlinDriver\tests;

use Brightzone\GremlinDriver\Connection;


/**
 * Unit testing of gremlin-php for transactional graph
 *
 * @category DB
 * @package  Tests
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 */
class RexsterTransactionTest extends RexsterTestCase
{
    /**
     * Testing transactions
     *
     *
     * @return void
     */
    public function testTransactions()
    {
        $db = new Connection([
            'graph' => 'graphT',
        ]);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $db->message->gremlin = 't.V().count()';
        $result = $db->send();

        $this->assertNotEquals($result, FALSE, 'Script request throws an error in transaction mode');
        $elementCount = $result[0];

        $db->transactionStart();

        $db->message->gremlin = 't.addV()';
        $db->send();

        $db->transactionStop(FALSE);

        $db->message->gremlin = 't.V().count()';
        $result = $db->send();
        $elementCount2 = $result[0];

        $this->assertNotEquals($result, FALSE, 'Script request throws an error in transaction mode');
        $this->AssertEquals($elementCount, $elementCount2, 'Transaction rollback didn\'t work');

        $db->transactionStart();
        $db->send('t.addV("name","michael").next()');

        $db->transactionStop(TRUE);

        $elementCount2 = $db->send('t.V().count()');
        $this->AssertEquals($elementCount + 1, $elementCount2[0], 'Transaction submition didn\'t work');
    }

    /**
     * Testing transactions accross multiple script launches
     *
     * @return void
     */
    public function testTransactionsMultiRun()
    {
        $db = new Connection([
            'graph' => 'graphT',
            'username' => $this->username,
            'password' => $this->password
        ]);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $result = $db->send('t.V().count()');
        $elementCount = $result[0];

        $db->transactionStart();

        $db->send('t.addV("name","michael").next()');
        $db->send('t.addV("name","michael").next()');

        $db->transactionStop(FALSE);

        $db->message->gremlin = 't.V().count()';
        $result = $db->send();
        $elementCount2 = $result[0];
        $this->AssertEquals($elementCount, $elementCount2, 'Transaction rollback didn\'t work');

        $db->transactionStart();

        $db->send('t.addV("name","michael").next()');
        $db->send('t.addV("name","michael").next()');

        $db->transactionStop(TRUE);

        $db->message->gremlin = 't.V().count()';
        $result = $db->send();
        $elementCount2 = $result[0];
        $this->AssertEquals($elementCount + 2, $elementCount2, 'Transaction submition didn\'t work');
    }

    /**
     * Testing Transaction without graphObj
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     *
     * @return void
     */
    public function testTransactionWithNoGraphObj()
    {
        $db = new Connection();
        $db->open();
        $db->transactionStart();
    }

    /**
     * Testing transactionStart() with an other running transaction
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     *
     * @return void
     */
    public function testSeveralRunningTransactionStart()
    {
        $db = new Connection([
            'graph' => 'graphT',
            'username' => $this->username,
            'password' => $this->password
        ]);
        $db->open();
        $db->transactionStart();
        $db->transactionStart();
    }

    /**
     * Testing transactionStop() with no running transaction
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     *
     * @return void
     */
    public function testTransactionStopWithNoTransaction()
    {
        $db = new Connection([
            'graph' => 'graphT',
            'username' => $this->username,
            'password' => $this->password
        ]);
        $db->open();
        $db->transactionStop();
    }
}
