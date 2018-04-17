<?php

namespace Brightzone\GremlinDriver\Tests;

use Brightzone\GremlinDriver\Serializers\Json;
use Brightzone\GremlinDriver\Serializers\SerializerInterface;

/**
 * Unit testing test case
 * Allows for developpers to use command line args to set username and password for test database
 *
 * @category DB
 * @package  Tests
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 */
class RexsterTestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var mixed the database username to use with tests, if any
     */
    protected $username;

    /**
     * @var mixed the database password to use with tests, if any
     */
    protected $password;

    /**
     * @var SerializerInterface the serializer to use
     */
    protected static $serializer;

    /**
     * Set the serializer up here
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        static::$serializer = new Json();
    }

    /**
     * Overriding setup to catch database arguments if set.
     *
     * @return void
     */
    protected function setUp()
    {
        $this->username = getenv('DBUSER') ? getenv('DBUSER') : NULL;
        $this->password = getenv('DBPASS') ? getenv('DBPASS') : NULL;
        parent::setUp();
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(TRUE);

        return $method->invokeArgs($object, $parameters);
    }
}
