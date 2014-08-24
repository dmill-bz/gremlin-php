<?php
namespace brightzone\rexpro\tests;

/**
 * Unit testing test case
 * Allows for developpers to use command line args to set username and password for test database
 * 
 * @category DB
 * @package  Rexpro
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki
 */
class RexsterTestCase extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var mixed the database username to use with tests, if any
	 */
	protected $username = NULL;
	
	/**
	 * @var mixed the database password to use with tests, if any
	 */
	protected $password = NULL;

	/**
	 * Overriding setup to catch database arguments if set.
	 *
	 * @return void
	 */
	protected function setUp()
	{
		global $argv, $argc;
		
		foreach($argv as $key => $argument)
		{
			if($argument == "--db-user")
			{
				$this->username = $argv[$key+1];
			}
			if($argument == "--db-password")
			{
				$this->password = $argv[$key+1];
			}

		}
		parent::setUp();
	}	
}
