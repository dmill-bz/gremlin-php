<?php

namespace Brightzone\GremlinDriver\Tests;

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
        $db->message->registerSerializer(static::$serializer, TRUE);
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
        $db->send('t.addV().property("name","stephen").next()');

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
            'graph'    => 'graphT',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $result = $db->send('t.V().count()');
        $elementCount = $result[0];

        $db->transactionStart();

        $db->send('t.addV().property("name","michael").next()');
        $db->send('t.addV().property("name","michael").next()');

        $db->transactionStop(FALSE);

        $db->message->gremlin = 't.V().count()';
        $result = $db->send();
        $elementCount2 = $result[0];
        $this->AssertEquals($elementCount, $elementCount2, 'Transaction rollback didn\'t work');

        $db->transactionStart();

        $db->send('t.addV().property("name","michael").next()');
        $db->send('t.addV().property("name","michael").next()');

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
        $db->message->registerSerializer(static::$serializer, TRUE);
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
            'graph'    => 'graphT',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $db->open();
        $db->transactionStart();
        $db->transactionStart();
    }

    /**
     * Testing db close during transaction running transaction
     *
     * @return void
     */
    public function testClosingDbOnRunningTransaction()
    {
        $db = new Connection([
            'graph'    => 'graphT',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $db->open();
        $db->transactionStart();
        $db->close();

        $this->assertTrue(TRUE); // should execute this without hitting any errors
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
            'graph'    => 'graphT',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $db->open();
        $db->transactionStop();
    }

    /**
     * Testing transaction retry error on already open transaction
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     * @return void
     * @throws \Exception
     */
    public function testTransactionRetryAlreadyOpen()
    {
        $db = new Connection([
            'graph'    => 'graphT',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $db->open();
        $db->transactionStart();
        $count = 0;
        try
        {
            $db->transaction(function(&$c) {
                $c++;
            }, [&$count]);
        }
        catch(\Exception $e)
        {
            $this->assertEquals(0, $count, "the workload has been executed when it shouldn't have");
            throw $e;
        }
    }

    /**
     * Testing transaction retry
     *
     * @expectedException \Brightzone\GremlinDriver\ServerException
     * @return void
     * @throws \Exception
     */
    public function testTransactionRetry()
    {
        $db = new Connection([
            'host'          => 'localhost',
            'port'          => 8182,
            'graph'         => 'graphT',
            'retryAttempts' => 5,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $db->open();

        $count = 0;
        try
        {
            $db->transaction(function(&$c) {
                $c++;
                throw new \Brightzone\GremlinDriver\ServerException('transaction test error', 597);
            }, [&$count]);
        }
        catch(\Exception $e)
        {
            $this->assertEquals(5, $count, "the workload has been executed when it shouldn't have");
            throw $e;
        }
    }

    /**
     * Test callable transaction
     *
     * @return void
     */
    public function testCallableTransaction()
    {
        $db = new Connection([
            'host'          => 'localhost',
            'port'          => 8182,
            'graph'         => 'graphT',
            'retryAttempts' => 5,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $db->message->gremlin = 't.V().count()';
        $result = $db->send();
        $elementCount = $result[0];

        $count = 0;
        try
        {
            $db->transaction(function(&$db, &$c) {
                $db->message->gremlin = 't.addV()';
                $db->send();
                $c++;
                throw new \Brightzone\GremlinDriver\ServerException('transaction callable test error', 597);
            }, [&$db, &$count]);
        }
        catch(\Exception $e)
        {
            $this->assertEquals(5, $count, "the code didn't execute properly");
        }

        $db->message->gremlin = 't.V().count()';
        $result = $db->send();
        $elementCount2 = $result[0];

        $this->assertNotEquals($result, FALSE, 'Script request throws an error in transaction mode');
        $this->AssertEquals($elementCount, $elementCount2, 'Transaction rollback didn\'t work');

        $count = 0;
        $db->transaction(function(&$db, &$c) {
            $db->send('t.addV().property("name","michael").next()');
            $c++;
            if($c < 3)
            {
                throw new \Brightzone\GremlinDriver\ServerException('transaction callable test error', 597);
            }
        }, [&$db, &$count]);

        $this->assertEquals(3, $count, "the code didn't execute the proper amount of times");

        $elementCount2 = $db->send('t.V().count()');
        $this->AssertEquals($elementCount + 1, $elementCount2[0], 'Transaction submition didn\'t work');
    }

    /**
     * Testing combination of session and sessionless requests
     */
    public function testSessionSessionlessTransactionIsOpenSingleClient()
    {
        $db = new Connection([
            'graph'    => 'graphT',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);

        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $db->transactionStart();

        $db->send('t.addV().property("name","stephen").next()');

        $db->transactionStop(TRUE);
        $isOpen = $db->send("graphT.tx().isOpen()", "session")[0];
        $this->assertTrue(!$isOpen, "transaction should be closed");

        $db->message->gremlin = 'graphT.traversal().V()';
        $result = $db->send();

        $isOpen = $db->send("graphT.tx().isOpen()", "session")[0];
        $this->assertTrue(!$isOpen, "transaction should still be closed after sessionless request");
    }

    /**
     * Testing combination of session and sessionless requests
     */
    public function testSessionSessionlessTransactionIsOpenMultiClient()
    {
        $db = new Connection([
            'graph'    => 'graphT',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);

        $db2 = new Connection([
            'graph'    => 'graphT',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db2->message->registerSerializer(static::$serializer, TRUE);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $db->transactionStart();

        $db->send('t.addV().property("name","stephen").next()');

        $db->transactionStop(TRUE);
        $isOpen = $db->send("graphT.tx().isOpen()", "session")[0];
        $this->assertTrue(!$isOpen, "transaction should be closed");

        $db2->message->gremlin = 'graphT.traversal().V()';
        $result = $db->send();

        $isOpen = $db->send("graphT.tx().isOpen()", "session")[0];
        $this->assertTrue(!$isOpen, "transaction should still be closed after sessionless request");
    }

    /**
     * Testing combination of session and sessionless requests
     */
    public function testSessionSessionlessCombinationConcurrentCommit()
    {
        $db = new Connection([
            'graph'    => 'graphT',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $db2 = new Connection([
            'graph'    => 'graphT',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db2->message->registerSerializer(static::$serializer, TRUE);
        $message = $db2->open();
        $this->assertNotEquals($message, FALSE);

        $result = $db->send('t.V().count()');
        $elementCount = $result[0];

        $db->transactionStart();

        $db->send('t.addV().property("name","michael").next()');

        $db2->message->gremlin = 't.V()';
        $db2->send();

        $db->send('t.addV().property("name","michael").next()');
        $db->transactionStop(TRUE);

        $db->message->gremlin = 't.V().count()';
        $result = $db->send();
        $elementCount2 = $result[0];
        $this->AssertEquals($elementCount + 2, $elementCount2, 'Transaction submition didn\'t work');
    }

    /**
     * Testing combination of session and sessionless requests
     */
    public function testSessionSessionlessCombinationConcurrentRollback()
    {
        $db = new Connection([
            'graph'    => 'graphT',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $db2 = new Connection([
            'graph'    => 'graphT',
            'username' => $this->username,
            'password' => $this->password,
        ]);
        $db2->message->registerSerializer(static::$serializer, TRUE);
        $message = $db2->open();
        $this->assertNotEquals($message, FALSE);

        $result = $db->send('t.V().count()');
        $elementCount = $result[0];

        $db->transactionStart();

        $db->send('t.addV().property("name","michael").next()');

        $db2->message->gremlin = 't.V()';
        $db2->send();

        $db->send('t.addV().property("name","michael").next()');
        $db->transactionStop(FALSE);

        $db->message->gremlin = 't.V().count()';
        $result = $db->send();
        $elementCount2 = $result[0];
        $this->AssertEquals($elementCount, $elementCount2, 'Transaction rollback didn\'t work');
    }

    /**
     * Test transaction management
     * This was introduced in GS 3.1.2. It should make session requests handle like sessionless
     * Thus keeping bindings but managing transactions automatically
     */
    public function testAutoTransactionManagement()
    {
        $db = new Connection([
            'graph' => 'graphT',
        ]);
        $db->message->registerSerializer(static::$serializer, TRUE);
        $message = $db->open();
        $this->assertNotEquals($message, FALSE);

        $db->message->gremlin = 't.V().count()';
        $result = $db->send();

        $this->assertNotEquals($result, FALSE, 'Script request throws an error in transaction mode');
        $elementCount = $result[0];

        $db->transactionStart();

        $db->message->setArguments([
            'manageTransaction' => TRUE,
        ]);
        $db->message->gremlin = 't.addV()';
        $db->send();

        $db->transactionStop(FALSE);

        $db->message->gremlin = 't.V().count()';
        $result = $db->send();
        $elementCount2 = $result[0];

        $this->assertNotEquals($result, FALSE, 'Script request throws an error in transaction mode');
        $this->AssertNotEquals($elementCount, $elementCount2, 'Transaction rollback worked when instead the transaction should have been committed prior to that.');
    }
}
