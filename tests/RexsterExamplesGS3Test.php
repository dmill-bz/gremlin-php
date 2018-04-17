<?php

namespace Brightzone\GremlinDriver\Tests;

use Brightzone\GremlinDriver\Serializers\Gson3;


/**
 * Unit testing of Gremlin-php
 *
 * @category DB
 * @package  Tests
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 */
class RexsterExamplesGS3Test extends RexsterExamplesTest
{
    /**
     * Set the serializer up here
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        static::$serializer = new Gson3;
    }
}
