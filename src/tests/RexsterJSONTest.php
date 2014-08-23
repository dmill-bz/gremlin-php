<?php
namespace brightzone\rexpro\tests;

require_once __DIR__.'/../Connection.php';

use \brightzone\rexpro\Connection;
use \brightzone\rexpro\Messages;
use \brightzone\rexpro\Exceptions;
use \brightzone\rexpro\Helper;


/**
 * Unit testing of Rexpro-php
 * 
 * @category DB
 * @package  Rexpro
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki
 */
class RexsterJSONTest extends \PHPUnit_Framework_TestCase
{	
	/**
	 * Testing Connection
	 * 
	 * @return void
	 */
	public function testConnectSuccess()
	{
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$result = $db->open('localhost', 'tinkergraph', 'test', 'ghJK5-hG');
		//print_r($result);
		//print_r($db);
		$this->assertNotEquals($result, FALSE, 'Failed to connect with no params');
		$this->assertTRUE($db->response[2] == 2, 'Result for session connection (without params) is not a session start response packet');//check it's a session start server packet
		
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$result = $db->open('localhost', 'tinkergraph', 'test', 'ghJK5-hG');
		$this->assertNotEquals($result, FALSE, 'Failed to connect with "localhost"');
		$this->assertTRUE($db->response[2] == 2, 'Result for session connection (with localhost) is not a session start response packet');//check it's a session start server packet
		
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$result = $db->open('localhost', 'graph', 'test', 'ghJK5-hG');
		$this->assertNotEquals($result, FALSE, 'Failed to connect with localhost and titan graph');
		$this->assertTRUE($db->response[2] == 2, 'Result for session connection (with localhost and titan graph) is not a session start response packet');//check it's a session start server packet
	}
	
	/**
	 * Testing connection errors
	 * 
	 * @return void
	 */
	public function testConnectErrors()
	{	
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$db->timeout = 0.5;
		$result = $db->open('unknownhost');
		$this->assertEquals($result, FALSE, 'Connecting to an unknown host does not throw an error');
		
		$this->assertNotEquals($db->error, NULL, 'Error object was not properly populated for unknown host');
		$this->assertTRUE($db->error instanceof Exceptions, 'Error object is not an Exceptions Object for unknown host');
		$this->assertFALSE(NULL === $db->error->code, 'Error object does not contain an error code for unknown host');
		$this->assertFALSE(NULL === $db->error->description, 'Error object does not contain an error description for unknown host');
		
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$db->timeout = 0.5;
		$result = $db->open('localhost:8787');
		$this->assertEquals($result, FALSE, 'Connecting to the wrong port for localhost does not throw an error');
		
		$this->assertTRUE($db->error instanceof Exceptions, 'Error object is not an Exceptions Object for unknown port');
		$this->assertFALSE(NULL === $db->error->code, 'Error object does not contain an error code for unknown port');
		$this->assertFALSE(NULL === $db->error->description, 'Error object does not contain an error description for unknown port');
		
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$result = $db->open('localhost', 'doesnt exist', 'test', 'ghJK5-hG');
		$this->assertEquals($result, FALSE, 'Loading a non-existing graph doesn\'t throw error');
		
		$this->assertTRUE($db->error instanceof Exceptions, 'Error object is not an Exceptions Object for unknown graph');
		$this->assertFALSE(NULL === $db->error->code, 'Error object does not contain an error code for unknown graph');
		$this->assertFALSE(NULL === $db->error->description, 'Error object does not contain an error description for unknown graph');
		
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$result = $db->open('localhost', 'doesnt exist', 'test', 'g-hG');
		$this->assertEquals($result, FALSE, 'Loading a non-existing user doesn\'t throw error');
		
		$this->assertTRUE($db->error instanceof Exceptions, 'Error object is not an Exceptions Object for unknown user');
		$this->assertFALSE(NULL === $db->error->code, 'Error object does not contain an error code for unknown user');
		$this->assertFALSE(NULL === $db->error->description, 'Error object does not contain an error description for unknown user');
	}
	
	/**
	 * Testing connection close
	 * 
	 * @return void
	 */
	public function testConnectCloseSuccess()
	{
		//do all connection checks
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$db->open('localhost', 'tinkergraph', 'test', 'ghJK5-hG');

		//check disconnection
		$response = $db->close();
		
		$this->assertNotEquals($response, FALSE, 'Closing connection on empty param connect creates an Error');
		$this->assertFALSE($db->isConnected(), 'Despite not throwing errors, Socket connection is not established');
		$this->assertTRUE($db->response[2] == 2, 'Response packet for closing session is not the proper type. (Maybe it\'s an error)');//check it's a session stop server packet
	}	
	
	/**
	 * Testing Script run against DB
	 * 
	 * @return void
	 */
	public function testRunScript()
	{
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$message = $db->open('localhost:8184', 'tinkergraph', 'test', 'ghJK5-hG');
		
		$db->script = '5+5';
		$result = $db->runScript();

		$this->assertNotEquals($result, FALSE, 'Script request throws an error');
		$this->assertTRUE($db->response[2] == 5, 'Script response message is not the right type. (Maybe it\'s an error)');//check it's a session script reply
		
		$db->script = 'g.v(2)';
		$result = $db->runScript();
		$this->assertNotEquals($result, FALSE, 'Script request throws an error');
		$this->assertTRUE($db->response[2] == 5, 'Script response message is not the right type. (Maybe it\'s an error)');//check it's a session script reply
		
		//check disconnection
		$message = $db->close();
		$this->assertNotEquals($message, FALSE, 'Closing connection on script creates an Error');
		$this->assertFALSE($db->isConnected(), 'Despite not throwing errors, Socket connection is not established');
		$this->assertTRUE($db->response[2] == 2, 'Response packet for closing session is not the proper type. (Maybe it\'s an error)');//check it's a session stop server packet
	}	
	
	/**
	 * Testing Script run with bindings
	 * 
	 * @return void
	 */
	public function testRunScriptWithBindings()
	{
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$message = $db->open('localhost:8184', 'tinkergraph', 'test', 'ghJK5-hG');
		$this->assertNotEquals($message, FALSE);
		
		$db->script = 'g.v(CUSTO_BINDING)';
		$db->bindValue('CUSTO_BINDING', 2);
		$result = $db->runScript();
		//print_r($db->error);
		$this->assertNotEquals($result, FALSE, 'Running a script with bindings produced an error');
		$this->assertTRUE($db->response[2] == 5, 'Script response message is not the right type. (Maybe it\'s an error)');//check it's a session script reply
		
		//check disconnection
		$message = $db->close();
		$this->assertNotEquals($message, FALSE, 'Disconnecting from a session where bindings were used created an error');
		$this->assertTRUE($db->response[2] == 2, 'Response packet for closing session with bindings is not the proper type. (Maybe it\'s an error)');//check it's a session stop server packet
	}
	
	/**
	 * Testing Script run without isolation
	 * 
	 * @return void
	 */
	public function testRunScriptWithoutIsolation()
	{
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$message = $db->open('localhost:8184', 'tinkergraph', 'test', 'ghJK5-hG');
		$this->assertNotEquals($message, FALSE);
		
		$db->script = 'g.v(CUSTO_BINDING)';
		$db->bindValue('CUSTO_BINDING', 2);
		$result = $db->runScript(TRUE, FALSE);
		
		$this->assertNotEquals($result, FALSE, 'There was an error when running a script with bindings in non-isolated mode');
		$this->assertTRUE($db->response[2] == 5, 'Script response message is not the right type. (Maybe it\'s an error)');//check it's a session script reply
	
		$db->script = 'g.v(CUSTO_BINDING)';
		$result = $db->runScript(TRUE, FALSE); // would return an error if isolate was TRUE
		$this->assertNotEquals($result, FALSE, 'Script created an error when using bindings that were set in a previous script call in non-isolation mode' );
		$this->assertTRUE($db->response[2] == 5, 'Script response message is not the right type. (Maybe it\'s an error)');//check it's a session script reply
		
		/*$db->script = 'g.v(CUSTO_BINDING)';
		$result = $db->runScript(); // would return an error if isolate was TRUE
		print_r($result);
		$this->assertEquals($result,FALSE,'No error occured when trying to use a binding from a previous message in isolated mode' );
		$this->assertTRUE($db->response[2] == 5,'Script response message is not the right type. (Maybe it\'s an error)');//check it's a session script reply
		*/
		
		//check disconnection
		$message = $db->close();
		$this->assertNotEquals($message, FALSE, 'Disconnecting from a session where bindings were used in consequent non-isolated scripts created an error');
		$this->assertTRUE($db->response[2] == 2, 'Response packet for closing session with bindings used in consequent non-isolated scripts is not the proper type. (Maybe it\'s an error)');//check it's a session stop server packet
	}	
	
	/**
	 * Testing sessionless script run
	 * 
	 * @return void
	 */
	public function testRunSessionlessScript()
	{
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$message = $db->open('localhost:8184', 'tinkergraph', 'test', 'ghJK5-hG');
		
		$db->script = 'g.v(2).map()';
		$db->graph = 'tinkergraph';
		$result = $db->runScript(FALSE); //need to provide graph
		$this->assertNotEquals($result, FALSE, 'Running a sessionless script returned an error');
		$this->assertTRUE($db->response[2] == 5, 'Script response message is not the right type. (Maybe it\'s an error)');//check it's a session script reply
		
		//check disconnection
		$message = $db->close();
		$this->assertNotEquals($message, FALSE, 'Disconnecting from a session after sessionless script created an error');
		$this->assertTRUE($db->response[2] == 2, 'Response packet for closing session after sessionless script is not the proper type. (Maybe it\'s an error)');//check it's a session stop server packet		
	}
	
	/**
	 * Testing transactions
	 * 
	 * @return void
	 */
	public function testTransactions()
	{
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$message = $db->open('localhost:8184', 'graph', 'test', 'ghJK5-hG');
		$this->assertNotEquals($message, FALSE);

		$db->script = 'g.V';
		$elementCount = count($db->runScript());
		
		$db->transactionStart();

		$db->script = 'g.addVertex([name:"michael"])';
		$result = $db->runScript();
		$this->assertNotEquals($result, FALSE, 'Script request throws an error in transaction mode');
		$this->assertTRUE($db->response[2] == 5, 'Script response message in transaction mode is not the right type. (Maybe it\'s an error)');//check it's a session script reply
		
		$db->transactionStop(FALSE);
		
		$db->script = 'g.V';
		$elementCount2 = count($db->runScript());
		$this->AssertEquals($elementCount, $elementCount2, 'Transaction rollback didn\'t work');
		
		$db->transactionStart();

		$db->script = 'g.addVertex([name:"michael"])';
		$result = $db->runScript();
		$this->assertNotEquals($result, FALSE, 'Script request throws an error in transaction mode');
		$this->assertTRUE($db->response[2] == 5, 'Script response message in transaction mode is not the right type. (Maybe it\'s an error)');//check it's a session script reply
		
		$db->transactionStop(TRUE);
		
		$db->script = 'g.V';
		$elementCount2 = count($db->runScript());
		$this->AssertEquals($elementCount + 1, $elementCount2, 'Transaction submition didn\'t work');
	}

	/**
	 * Testing getResponse() without making a previous 
	 * socket connection with open()
	 * 
	 * @return void
	 */
	public function testGetResponseWithoutConnection()
	{
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$result = $db->getResponse();
		$this->assertFalse($result, 'Failed to return false with no established connection');
	}

	/**
	 * Testing sendMessage without previous connection
	 * 
	 * @return void
	 */
	public function testSendMessageWithoutConnection()
	{
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$msg = new Messages(1);
		$result = $db->send($msg);
		$this->assertFalse($result, 'Failed to return false with no established connection');
	}

	/**
	 * Testing runScript() without making a previous 
	 * socket connection with open()
	 * 
	 * @return void
	 */
	public function testRunScriptWithoutConnection()
	{
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$result = $db->runScript();
		$this->assertFalse($result, 'Failed to return false with no established connection');
	}

	/**
	 * Testing runScript() with wrong message parameters sent
	 * 
	 * @return void
	 */
	public function testRunScriptWithWrongParameters()
	{
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$db->open('localhost:8184', '', '', '', '');
		$result = $db->runScript();
		$this->assertFalse($result, 'Failed to return false with a connection failed');
	}

	/**
	 * Testing transactionStart() with an other running transaction
	 * 
	 * @return void
	 */
	public function testSeveralRunningTransactionStart()
	{
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$db->open('localhost:8184', 'graph', 'test', 'ghJK5-hG');
		$db->transactionStart();
		$result = $db->transactionStart();
		$this->assertFalse($result, 'Failed to return false with an other started transaction');
	}

	/**
	 * Testing transactionStop() with no running transaction
	 * 
	 * @return void
	 */
	public function testTransactionStopWithNoTransaction()
	{
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$db->open('localhost:8184', 'graph', 'test', 'ghJK5-hG');
		$result = $db->transactionStop();
		$this->assertFalse($result, 'Failed to return false with no transaction started');
	}

	/**
	 * Testing failing message connection close()
	 * 
	 * @return void
	 */
	public function testConnectCloseFailingMessage()
	{
		$db = new Connection;
		$db->setSerializer(Messages::SERIALIZER_JSON);
		$db->open('localhost', 'tinkergraph', 'test', 'ghJK5-hG');
		$db->sessionUuid = '';
		$result = $db->close();
		$this->assertFalse($result, 'Failed to return false with no transaction started');
	}
}
