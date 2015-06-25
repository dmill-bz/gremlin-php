<?php
namespace brightzone\rexpro\tests;

use brightzone\rexpro\Connection;


/**
 * Unit testing of gremlin-php for transactional graph
 *
 * @category DB
 * @package  gremlin-php-tests
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki
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
        $db = new Connection;
        $message = $db->open('localhost:8182', 'graphT', $this->username, $this->password);
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
        $db = new Connection;
        $message = $db->open('localhost:8182', 'graphT', $this->username, $this->password);
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
     * @expectedException \brightzone\rexpro\InternalException
     *
     * @return void
     */
    public function testTransactionWithNoGraphObj()
    {
        $db = new Connection;
        $db->open('localhost:8182', '', '', '', '');
        $db->transactionStart();
    }

    /**
     * Testing transactionStart() with an other running transaction
     *
     * @expectedException \brightzone\rexpro\InternalException
     *
     * @return void
     */
    public function testSeveralRunningTransactionStart()
    {
        $db = new Connection;
        $db->open('localhost:8182', 'graphT', $this->username, $this->password);
        $db->transactionStart();
        $db->transactionStart();
    }

    /**
     * Testing transactionStop() with no running transaction
     *
     * @expectedException \brightzone\rexpro\InternalException
     *
     * @return void
     */
    public function testTransactionStopWithNoTransaction()
    {
        $db = new Connection;
        $db->open('localhost:8182', 'graphT', $this->username, $this->password);
        $db->transactionStop();
    }
}