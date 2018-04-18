<?php

namespace Brightzone\GremlinDriver\Tests;

use Brightzone\GremlinDriver\Connection;
use Brightzone\GremlinDriver\RequestMessage;
use Brightzone\GremlinDriver\Serializers\Gson3;
use stdClass;

/**
 * Unit testing of Gremlin-php
 * This actually runs tests against the server
 *
 * @category DB
 * @package  Tests
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 */
class GraphSon3Test extends RexsterTestCase
{
    /**
     * Gets the int type (32b-64b) for this system
     *
     * @return string int type, either g:Int32 or g:Int64
     */
    protected function getIntType()
    {
        if(PHP_INT_SIZE == 4)
        {
            return "g:Int32";
        }
        else if(PHP_INT_SIZE == 8)
        {
            return "g:Int64";
        }
        else
        {
            $this->fail("PHP_IN_SIZE is " . PHP_INT_SIZE . "and is not supported by the serializer");
        }

        return FALSE;
    }

    /**
     * Test converting a String to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertString()
    {
        $serializer = new Gson3;

        $converted = $serializer->convertString("testing");
        $this->assertEquals("testing", $converted, "Incorrect GS3 conversion for String");
    }

    /**
     * Test converting a Boolean to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertBoolean()
    {
        $serializer = new Gson3;

        $converted = $serializer->convertBoolean(TRUE);
        $this->assertEquals(TRUE, $converted, "Incorrect GS3 conversion for Bool");
    }

    /**
     * Test converting an Integer to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertInteger()
    {
        $serializer = new Gson3;
        $intType = $this->getintType();

        $converted = $serializer->convertInteger(5);
        $this->assertEquals(["@type" => $intType, "@value" => 5], $converted, "Incorrect GS3 conversion for Integer");
    }

    /**
     * Test converting an float/double to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertDouble()
    {
        $serializer = new Gson3;

        $converted = $serializer->convertDouble(5.3);
        $this->assertEquals(["@type" => "g:Double", "@value" => 5.3], $converted, "Incorrect GS3 conversion for Double");
    }

    /**
     * Test converting an object to graphson 3.0 format
     * This is unsupported and should throw an error.
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     */
    public function testConvertObject()
    {
        $serializer = new Gson3;

        $object = new stdClass();
        $object->title = 'Test Object';

        // List
        $serializer->convertObject($object);
    }

    /**
     * Test converting a Request message object to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertObjectRequestMessage()
    {
        $serializer = new Gson3;
        $intType = $this->getintType();
        $object = new RequestMessage([3 => [123, TRUE, 5.34, "string"]]);


        $converted = $serializer->convertObject($object);

        $this->assertEquals([
            3 => [
                "@type"  => "g:List",
                "@value" => [
                    ["@type" => $intType, "@value" => 123],
                    TRUE,
                    ["@type" => "g:Double", "@value" => 5.34],
                    "string",
                ],
            ],
        ], $converted, "Incorrect GS3 conversion for RequestMessage object");
    }

    /**
     * Test converting an NULL to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertNull()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->convertNULL(NULL);
        $this->assertEquals(NULL, $deconverted, "Incorrect deconversion for Double");
    }

    /**
     * Test converting a List to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertList()
    {
        $serializer = new Gson3;

        $converted = $serializer->convertList(["test", "test2"]);
        $this->assertEquals(["@type" => "g:List", "@value" => ["test", "test2"]], $converted, "Incorrect GS3 conversion for List");
    }

    /**
     * Test converting an empty List to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertEmptyList()
    {
        $serializer = new Gson3;

        $converted = $serializer->convertList([]);
        $this->assertEquals(["@type" => "g:List", "@value" => []], $converted, "Incorrect GS3 conversion for an empty List");
    }

    /**
     * Test converting a Map with string keys to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertListWithMixValue()
    {
        $serializer = new Gson3;
        $intType = $this->getintType();

        $converted = $serializer->convertList([123, TRUE, 5.34, "string"]);
        $this->assertEquals([
            "@type"  => "g:List",
            "@value" => [
                ["@type" => $intType, "@value" => 123],
                TRUE,
                ["@type" => "g:Double", "@value" => 5.34],
                "string",
            ],
        ], $converted, "Incorrect GS3 conversion for List with mixed values");
    }

    /**
     * Test converting a Map with no keys to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertMapWithNoKey()
    {
        $serializer = new Gson3;
        $intType = $this->getintType();

        $converted = $serializer->convertMap(["test", "test2"]);
        $this->assertEquals([
            "@type"  => "g:Map",
            "@value" => [
                ["@type" => $intType, "@value" => 0], "test",
                ["@type" => $intType, "@value" => 1], "test2",
            ],
        ], $converted, "Incorrect GS3 conversion for Map without keys");
    }

    /**
     * Test converting an empty Map with no keys to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertEmptyMap()
    {
        $serializer = new Gson3;

        $converted = $serializer->convertMap([]);
        $this->assertEquals([
            "@type"  => "g:Map",
            "@value" => [],
        ], $converted, "Incorrect GS3 conversion for an empty Map");
    }

    /**
     * Test converting a Map with string keys to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertMapWithStringKey()
    {
        $serializer = new Gson3;

        $converted = $serializer->convertMap(["string1" => "test", "02" => "test2"]);
        $this->assertEquals([
            "@type"  => "g:Map",
            "@value" => [
                "string1", "test",
                "02", "test2",
            ],
        ], $converted, "Incorrect GS3 conversion for Map with string keys");
    }

    /**
     * Test converting a Map with string keys to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertMapWithMixKey()
    {
        $serializer = new Gson3;
        $intType = $this->getintType();

        $converted = $serializer->convertMap(["string1" => "test", 2 => "test2"]);
        $this->assertEquals([
            "@type"  => "g:Map",
            "@value" => [
                "string1", "test",
                ["@type" => $intType, "@value" => 2], "test2",
            ],
        ], $converted, "Incorrect GS3 conversion for Map with mixed keys");
    }

    /**
     * Test converting a Map with string keys to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertMapWithMixValue()
    {
        $serializer = new Gson3;
        $intType = $this->getintType();

        $converted = $serializer->convertMap(["string1" => 123, "02" => TRUE, "03" => 5.34]);
        $this->assertEquals([
            "@type"  => "g:Map",
            "@value" => [
                "string1", ["@type" => $intType, "@value" => 123],
                "02", TRUE,
                "03", ["@type" => "g:Double", "@value" => 5.34],
            ],
        ], $converted, "Incorrect GS3 conversion for Map with mixed values");
    }

    /**
     * Test converting a Map to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertMapWithIntKey()
    {
        $serializer = new Gson3;
        $intType = $this->getintType();

        $converted = $serializer->convertMap([2 => "test", 7 => "test2"]);
        $this->assertEquals([
            "@type"  => "g:Map",
            "@value" => [
                ["@type" => $intType, "@value" => 2], "test",
                ["@type" => $intType, "@value" => 7], "test2",
            ],
        ], $converted, "Incorrect GS3 conversion for Map with int keys");
    }

    /**
     * Test converting an array to map to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertArrayToMap()
    {
        $serializer = new Gson3;
        $intType = $this->getintType();

        $converted = $serializer->convertArray([2 => "test", 7 => "test2"]);
        $this->assertEquals([
            "@type"  => "g:Map",
            "@value" => [
                ["@type" => $intType, "@value" => 2], "test",
                ["@type" => $intType, "@value" => 7], "test2",
            ],
        ], $converted, "Incorrect GS3 conversion for Map");
    }

    /**
     * Test converting an Array to List to graphson 3.0 format
     *
     * @return void
     */
    public function testConvertArrayToList()
    {
        $serializer = new Gson3;

        $converted = $serializer->convertArray(["test", "test2"]);
        $this->assertEquals(["@type" => "g:List", "@value" => ["test", "test2"]], $converted, "Incorrect GS3 conversion for List");
    }

    /**
     * Test converting any item
     *
     * @return void
     */
    public function testConvert()
    {
        $serializer = new Gson3;
        $intType = $this->getintType();

        // List
        $converted = $serializer->convert(["test", "test2"]);
        $this->assertEquals(["@type" => "g:List", "@value" => ["test", "test2"]], $converted, "Incorrect GS3 conversion for List");

        // Map
        $converted = $serializer->convert([2 => "test", 7 => "test2"]);
        $this->assertEquals([
            "@type"  => "g:Map",
            "@value" => [
                ["@type" => $intType, "@value" => 2], "test",
                ["@type" => $intType, "@value" => 7], "test2",
            ],
        ], $converted, "Incorrect GS3 conversion for Map");

        // String
        $converted = $serializer->convert("testing");
        $this->assertEquals("testing", $converted, "Incorrect GS3 conversion for String");

        // bool
        $converted = $serializer->convert(TRUE);
        $this->assertEquals(TRUE, $converted, "Incorrect GS3 conversion for Bool");

        // Double
        $converted = $serializer->convert(5.3);
        $this->assertEquals(["@type" => "g:Double", "@value" => 5.3], $converted, "Incorrect GS3 conversion for Double");

        // Int
        $converted = $serializer->convert(5);
        $this->assertEquals(["@type" => $intType, "@value" => 5], $converted, "Incorrect GS3 conversion for Integer");
    }

    /**
     * Test converting an unsupported object
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     */
    public function testConvertUnsupportedObject()
    {
        $serializer = new Gson3;

        $object = new stdClass();
        $object->title = 'Test Object';

        $serializer->convert($object);
    }

    /**
     * Test converting an unsupported type
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     */
    public function testConvertUnsupportedType()
    {
        $serializer = new Gson3;

        $type = stream_context_create();

        $serializer->convert($type);
    }

    /**
     * Test converting a complex item
     *
     */
    public function testConvertComplex()
    {
        $serializer = new Gson3;
        $converted = $serializer->convert([
            [
                "something",
                32,
            ],
            TRUE,
            [
                "lala",
                33    => 21,
                "key" => [
                    "lock",
                    "door",
                    [
                        "again" => "inside",
                    ],
                ],
            ],
            30,
        ]);

        $this->assertEquals([
            "@type"  => "g:List",
            "@value" => [
                [
                    "@type"  => "g:List",
                    "@value" => [
                        "something",
                        ["@type" => "g:Int64", "@value" => 32],
                    ],
                ],
                TRUE,
                [
                    "@type"  => "g:Map",
                    "@value" => [
                        ["@type" => "g:Int64", "@value" => 0],
                        "lala",
                        ["@type" => "g:Int64", "@value" => 33],
                        ["@type" => "g:Int64", "@value" => 21],
                        "key",
                        [
                            "@type"  => "g:List",
                            "@value" => [
                                "lock",
                                "door",
                                [
                                    "@type"  => "g:Map",
                                    "@value" => [
                                        "again",
                                        "inside",
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                ["@type" => "g:Int64", "@value" => 30],
            ],
        ], $converted, "Incorrect GS3 conversion for Complex object");
    }

    /**
     * Test serializing array to GraphSON 3.0
     *
     * @return void
     */
    public function testSerialize()
    {
        $serializer = new Gson3;
        $data = [
            [
                "something",
                32,
            ],
            TRUE,
            [
                "lala",
                33    => 21,
                "key" => [
                    "lock",
                    "door",
                    [
                        "again" => "inside",
                    ],
                ],
            ],
            30,
        ];

        $length = $serializer->serialize($data);
        $this->assertEquals($length, strlen($data), "serialized message length was incorrect");
        $this->assertEquals('{"@type":"g:List","@value":[{"@type":"g:List","@value":["something",{"@type":"g:Int64","@value":32}]},true,{"@type":"g:Map","@value":[{"@type":"g:Int64","@value":0},"lala",{"@type":"g:Int64","@value":33},{"@type":"g:Int64","@value":21},"key",{"@type":"g:List","@value":["lock","door",{"@type":"g:Map","@value":["again","inside"]}]}]},{"@type":"g:Int64","@value":30}]}', $data, "incorrect GraphSON 3.0 was generated");
    }

    /**
     * Test deconverting an Int32 from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertInt32()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertInt32(5);
        $this->assertEquals(5, $deconverted, "Incorrect deconversion for Int32");
    }

    /**
     * Test deconverting an Int64 from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertInt64()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertInt64(5);
        $this->assertEquals(5, $deconverted, "Incorrect deconversion for Int64");
    }

    /**
     * Test deconverting an Date from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertDate()
    {
        $serializer = new Gson3;
        $time = time();
        $deconverted = $serializer->deconvertTimestamp($time);
        $this->assertEquals($time, $deconverted, "Incorrect deconversion for Date");
    }

    /**
     * Test deconverting an Timestamp from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertTimestamp()
    {
        $serializer = new Gson3;
        $time = time();
        $deconverted = $serializer->deconvertTimestamp($time);
        $this->assertEquals($time, $deconverted, "Incorrect deconversion for Timestamp");
    }

    /**
     * Test deconverting an Double from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertDouble()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertDouble(5.34);
        $this->assertEquals(5.34, $deconverted, "Incorrect deconversion for Double");
    }

    /**
     * Test deconverting an Float from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertFloat()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertFloat(5.34);
        $this->assertEquals(5.34, $deconverted, "Incorrect deconversion for Float");
    }

    /**
     * Test deconverting an UUID from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertUUID()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertUUID("41d2e28a-20a4-4ab0-b379-d810dede3786");
        $this->assertEquals("41d2e28a-20a4-4ab0-b379-d810dede3786", $deconverted, "Incorrect deconversion for UUID");
    }

    /**
     * Test deconverting an VertexProperty from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertVertexProperty()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertVertexProperty([
            "id"    => [
                "@type"  => "g:Int64",
                "@value" => 0,
            ],
            "value" => "marko",
            "label" => "name",
        ]);

        $this->assertEquals([
            "id"    => 0,
            "value" => "marko",
            "label" => "name",
        ], $deconverted, "Incorrect deconversion for VertexProperty");
    }

    /**
     * Test deconverting an Property from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertProperty()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertProperty([
            "key"   => "since",
            "value" => [
                "@type"  => "g:Int32",
                "@value" => 2009,
            ],
        ]);

        $this->assertEquals([
            "key"   => "since",
            "value" => 2009,
        ], $deconverted, "Incorrect deconversion for Property");
    }

    /**
     * Test deconverting an Vertex from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertVertex()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertVertex([
            "label" => "person",
            "value" => [
                "@type"  => "g:Int32",
                "@value" => 2009,
            ],

        ]);

        $this->assertEquals([
            "label" => "person",
            "value" => 2009,
            "type"  => 'vertex',
        ], $deconverted, "Incorrect deconversion for Vertex");
    }

    /**
     * Test deconverting an Edge from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertEdge()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertEdge([
            "label" => "friend",
            "value" => [
                "@type"  => "g:Int32",
                "@value" => 2009,
            ],

        ]);

        $this->assertEquals([
            "label" => "friend",
            "value" => 2009,
            "type"  => 'edge',
        ], $deconverted, "Incorrect deconversion for Edge");
    }

    /**
     * Test deconverting a List from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertList()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertList([
            "friend",
            [
                "@type"  => "g:Int32",
                "@value" => 2009,
            ],
            TRUE,
        ]);

        $this->assertEquals([
            "friend",
            2009,
            TRUE,
        ], $deconverted, "Incorrect deconversion for List");
    }

    /**
     * Test deconverting a Path from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertPath()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertPath([
            "@type"  => "g:Path",
            "@value" => [
                "labels"  => [[], [], []],
                "objects" => [
                    [
                        "@type"  => "g:Vertex",
                        "@value" => [
                            "id"    => [
                                "@type"  => "g:Int32",
                                "@value" => 1,
                            ],
                            "label" => "person",
                        ],
                    ], [
                        "@type"  => "g:Vertex",
                        "@value" => [
                            "id"         => [
                                "@type"  => "g:Int32",
                                "@value" => 10,
                            ],
                            "label"      => "software",
                            "properties" => [
                                "name" => [
                                    [
                                        "@type"  => "g:VertexProperty",
                                        "@value" => [
                                            "id"     => [
                                                "@type"  => "g:Int64",
                                                "@value" => 4,
                                            ],
                                            "value"  => "gremlin",
                                            "vertex" => [
                                                "@type"  => "g:Int32",
                                                "@value" => 10,
                                            ],
                                            "label"  => "name",
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ], [
                        "@type"  => "g:Vertex",
                        "@value" => [
                            "id"         => [
                                "@type"  => "g:Int32",
                                "@value" => 11,
                            ],
                            "label"      => "software",
                            "properties" => [
                                "name" => [
                                    [
                                        "@type"  => "g:VertexProperty",
                                        "@value" => [
                                            "id"     => [
                                                "@type"  => "g:Int64",
                                                "@value" => 5,
                                            ],
                                            "value"  => "tinkergraph",
                                            "vertex" => [
                                                "@type"  => "g:Int32",
                                                "@value" => 11,
                                            ],
                                            "label"  => "name",
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertEquals([

            "labels"  => [[], [], []],
            "objects" => [
                [
                    "id"    => 1,
                    "label" => "person",
                    "type"  => "vertex",

                ], [
                    "id"         => 10,
                    "label"      => "software",
                    "properties" => [
                        "name" => [
                            [
                                "id"     => 4,
                                "value"  => "gremlin",
                                "vertex" => 10,
                                "label"  => "name",
                            ],
                        ],
                    ],
                    "type"       => "vertex",
                ], [
                    "id"         => 11,
                    "label"      => "software",
                    "properties" => [
                        "name" => [
                            [
                                "id"     => 5,
                                "value"  => "tinkergraph",
                                "vertex" => 11,
                                "label"  => "name",
                            ],
                        ],
                    ],
                    "type"       => "vertex",
                ],
            ],

        ], $deconverted, "Incorrect deconversion for Path");
    }

    /**
     * Test deconverting a Tree from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertTree()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertTree([
            '@type'  => 'g:List',
            '@value' => [
                0 => [
                    '@type'  => 'g:Tree',
                    '@value' => [
                        0 => [
                            'key'   => [
                                '@type'  => 'g:Vertex',
                                '@value' => [
                                    'id'         => [
                                        '@type'  => 'g:Int64',
                                        '@value' => 1,
                                    ],
                                    'label'      => 'vertex',
                                    'properties' => [
                                        'name' => [
                                            0 => [
                                                '@type'  => 'g:VertexProperty',
                                                '@value' => [
                                                    'id'    => [
                                                        '@type'  => 'g:Int64',
                                                        '@value' => 0,
                                                    ],
                                                    'value' => 'marko',
                                                    'label' => 'name',
                                                ],
                                            ],
                                        ],
                                        'age'  => [
                                            0 => [
                                                '@type'  => 'g:VertexProperty',
                                                '@value' => [
                                                    'id'    => [
                                                        '@type'  => 'g:Int64',
                                                        '@value' => 2,
                                                    ],
                                                    'value' => [
                                                        '@type'  => 'g:Int32',
                                                        '@value' => 29,
                                                    ],
                                                    'label' => 'age',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'value' => [
                                '@type'  => 'g:Tree',
                                '@value' => [
                                    0 => [
                                        'key'   => [
                                            '@type'  => 'g:Vertex',
                                            '@value' => [
                                                'id'         => [
                                                    '@type'  => 'g:Int64',
                                                    '@value' => 2,
                                                ],
                                                'label'      => 'vertex',
                                                'properties' => [
                                                    'name' => [
                                                        0 => [
                                                            '@type'  => 'g:VertexProperty',
                                                            '@value' => [
                                                                'id'    => [
                                                                    '@type'  => 'g:Int64',
                                                                    '@value' => 3,
                                                                ],
                                                                'value' => 'vadas',
                                                                'label' => 'name',
                                                            ],
                                                        ],
                                                    ],
                                                    'age'  => [
                                                        0 => [
                                                            '@type'  => 'g:VertexProperty',
                                                            '@value' => [
                                                                'id'    => [
                                                                    '@type'  => 'g:Int64',
                                                                    '@value' => 4,
                                                                ],
                                                                'value' => [
                                                                    '@type'  => 'g:Int32',
                                                                    '@value' => 27,
                                                                ],
                                                                'label' => 'age',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '@type'  => 'g:Tree',
                                            '@value' => [
                                                0 => [
                                                    'key'   => [
                                                        '@type'  => 'g:VertexProperty',
                                                        '@value' => [
                                                            'id'    => [
                                                                '@type'  => 'g:Int64',
                                                                '@value' => 3,
                                                            ],
                                                            'value' => 'vadas',
                                                            'label' => 'name',
                                                        ],
                                                    ],
                                                    'value' => [
                                                        '@type'  => 'g:Tree',
                                                        '@value' => [],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    1 => [
                                        'key'   => [
                                            '@type'  => 'g:Vertex',
                                            '@value' => [
                                                'id'         => [
                                                    '@type'  => 'g:Int64',
                                                    '@value' => 3,
                                                ],
                                                'label'      => 'vertex',
                                                'properties' => [
                                                    'name' => [
                                                        0 => [
                                                            '@type'  => 'g:VertexProperty',
                                                            '@value' => [
                                                                'id'    => [
                                                                    '@type'  => 'g:Int64',
                                                                    '@value' => 5,
                                                                ],
                                                                'value' => 'lop',
                                                                'label' => 'name',
                                                            ],
                                                        ],
                                                    ],
                                                    'lang' => [
                                                        0 => [
                                                            '@type'  => 'g:VertexProperty',
                                                            '@value' => [
                                                                'id'    => [
                                                                    '@type'  => 'g:Int64',
                                                                    '@value' => 6,
                                                                ],
                                                                'value' => 'java',
                                                                'label' => 'lang',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '@type'  => 'g:Tree',
                                            '@value' => [
                                                0 => [
                                                    'key'   => [
                                                        '@type'  => 'g:VertexProperty',
                                                        '@value' => [
                                                            'id'    => [
                                                                '@type'  => 'g:Int64',
                                                                '@value' => 5,
                                                            ],
                                                            'value' => 'lop',
                                                            'label' => 'name',
                                                        ],
                                                    ],
                                                    'value' => [
                                                        '@type'  => 'g:Tree',
                                                        '@value' => [],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    2 => [
                                        'key'   => [
                                            '@type'  => 'g:Vertex',
                                            '@value' => [
                                                'id'         => [
                                                    '@type'  => 'g:Int64',
                                                    '@value' => 4,
                                                ],
                                                'label'      => 'vertex',
                                                'properties' => [
                                                    'name' => [
                                                        0 => [
                                                            '@type'  => 'g:VertexProperty',
                                                            '@value' => [
                                                                'id'    => [
                                                                    '@type'  => 'g:Int64',
                                                                    '@value' => 7,
                                                                ],
                                                                'value' => 'josh',
                                                                'label' => 'name',
                                                            ],
                                                        ],
                                                    ],
                                                    'age'  => [
                                                        0 => [
                                                            '@type'  => 'g:VertexProperty',
                                                            '@value' => [
                                                                'id'    => [
                                                                    '@type'  => 'g:Int64',
                                                                    '@value' => 8,
                                                                ],
                                                                'value' => [
                                                                    '@type'  => 'g:Int32',
                                                                    '@value' => 32,
                                                                ],
                                                                'label' => 'age',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        'value' => [
                                            '@type'  => 'g:Tree',
                                            '@value' => [
                                                0 => [
                                                    'key'   => [
                                                        '@type'  => 'g:VertexProperty',
                                                        '@value' => [
                                                            'id'    => [
                                                                '@type'  => 'g:Int64',
                                                                '@value' => 7,
                                                            ],
                                                            'value' => 'josh',
                                                            'label' => 'name',
                                                        ],
                                                    ],
                                                    'value' => [
                                                        '@type'  => 'g:Tree',
                                                        '@value' => [],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'meta'   => [
                '@type'  => 'g:Map',
                '@value' => [],
            ],
        ]);

        $this->assertEquals([
            [
                1 => [
                    'key'   => [
                        'id'         => 1,
                        'label'      => 'vertex',
                        'type'       => 'vertex',
                        'properties' => [
                            'name' => [['id' => 0, 'value' => 'marko', 'label' => 'name']],
                            'age'  => [['id' => 2, 'value' => 29, 'label' => 'age']],
                        ],
                    ],
                    'value' => [
                        2 => [
                            'key'   => [
                                'id'         => 2,
                                'label'      => 'vertex',
                                'type'       => 'vertex',
                                'properties' => [
                                    'name' => [['id' => 3, 'value' => 'vadas', 'label' => 'name']],
                                    'age'  => [['id' => 4, 'value' => 27, 'label' => 'age']],
                                ],
                            ],
                            'value' => [
                                3 => [
                                    'key'   => ['id' => 3, 'value' => 'vadas', 'label' => 'name'],
                                    'value' => [],
                                ],
                            ],
                        ],
                        3 => [
                            'key'   => [
                                'id'         => 3,
                                'label'      => 'vertex',
                                'type'       => 'vertex',
                                'properties' => [
                                    'name' => [['id' => 5, 'value' => 'lop', 'label' => 'name']],
                                    'lang' => [['id' => 6, 'value' => 'java', 'label' => 'lang']],
                                ],
                            ],
                            'value' => [
                                5 => [
                                    'key'   => ['id' => 5, 'value' => 'lop', 'label' => 'name'],
                                    'value' => [],
                                ],
                            ],
                        ],
                        4 => [
                            'key'   => [
                                'id'         => 4,
                                'label'      => 'vertex',
                                'type'       => 'vertex',
                                'properties' => [
                                    'name' => [['id' => 7, 'value' => 'josh', 'label' => 'name']],
                                    'age'  => [['id' => 8, 'value' => 32, 'label' => 'age']],
                                ],
                            ],
                            'value' => [
                                7 => [
                                    'key'   => ['id' => 7, 'value' => 'josh', 'label' => 'name'],
                                    'value' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $deconverted, "Incorrect deconversion for Path");
    }

    /**
     * Test deconverting a Complex List from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertComplexList()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertList([
            "friend",
            [
                "@type"  => "g:Int32",
                "@value" => 2009,
            ],
            [
                "@type"  => "g:List",
                "@value" => [
                    "friend",
                    [
                        "@type"  => "g:Int32",
                        "@value" => 2009,
                    ],
                ],
            ],
            TRUE,
        ]);

        $this->assertEquals([
            "friend",
            2009,
            ["friend", 2009],
            TRUE,
        ], $deconverted, "Incorrect deconversion for ComplexList");
    }

    /**
     * Test deconverting a Set from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertSet()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertSet([
            "friend",
            [
                "@type"  => "g:Int32",
                "@value" => 2009,
            ],
            TRUE,
        ]);

        $this->assertEquals([
            "friend",
            2009,
            TRUE,
        ], $deconverted, "Incorrect deconversion for Set");
    }

    /**
     * Test deconverting an empty Set from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertEmptySet()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertSet([]);

        $this->assertEquals([], $deconverted, "Incorrect deconversion for Empty Set");
    }

    /**
     * Test deconverting a Complex Set from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertComplexSet()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertSet([
            "friend",
            [
                "@type"  => "g:Int32",
                "@value" => 2009,
            ],
            [
                "@type"  => "g:List",
                "@value" => [
                    "friend",
                    [
                        "@type"  => "g:Int32",
                        "@value" => 2009,
                    ],
                ],
            ],
            TRUE,
        ]);

        $this->assertEquals([
            "friend",
            2009,
            ["friend", 2009],
            TRUE,
        ], $deconverted, "Incorrect deconversion for Complex Set");
    }

    /**
     * Test deconverting a Map from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertMap()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertMap([
            "test",
            "friend",
            ["@type" => "g:Int32", "@value" => 2009],
            ["@type" => "g:Int32", "@value" => 20],
            ["@type" => "g:Int32", "@value" => 20],
            TRUE,
            "time",
            ["@type" => "g:Date", "@value" => 456789],
        ]);

        $this->assertEquals([
            "test" => "friend",
            2009   => 20,
            20     => TRUE,
            "time" => 456789,
        ], $deconverted, "Incorrect deconversion for Map");
    }

    /**
     * Test deconverting a broken Map from graphson 3.0 format to native
     * If a map has an odd number of items it should throw an error
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     */
    public function testDeconvertOddNumberMap()
    {
        $serializer = new Gson3;

        $serializer->deconvertMap([
            "test",
            "friend",
            ["@type" => "g:Int32", "@value" => 2009],
            ["@type" => "g:Int32", "@value" => 20],
            ["@type" => "g:Int32", "@value" => 20],
            TRUE,
            "time",
        ]);
    }

    /**
     * Test deconverting a broken Map from graphson 3.0 format to native
     * If a map has a key other than int or string
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     */
    public function testDeconvertNonStandardKeyMap()
    {
        $serializer = new Gson3;

        $serializer->deconvertMap([
            "test",
            "friend",
            ["@type" => "g:List", "@value" => [20]],
            ["@type" => "g:Int32", "@value" => 2009],
            ["@type" => "g:Int32", "@value" => 20],
            TRUE,
            "time",
        ]);
    }

    /**
     * Test deconverting a complex Map from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertComplexMap()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertMap([
            "test",
            "friend",
            ["@type" => "g:Int32", "@value" => 2009],
            ["@type" => "g:Int64", "@value" => 20],
            "test",
            TRUE,
            "time",
            [
                "@type" => "g:Map", "@value" => [
                "test",
                "friend",
                ["@type" => "g:Int32", "@value" => 2009],
                ["@type" => "g:Int32", "@value" => 20],
            ],
            ],
        ]);

        $this->assertEquals([
            "test" => "friend",
            2009   => 20,
            "test" => TRUE,
            "time" => [
                "test" => "friend",
                2009   => 20,
            ],
        ], $deconverted, "Incorrect deconversion for Map");
    }

    /**
     * Test deconverting a Empty Map from graphson 3.0 format to native
     *
     * @return void
     */
    public function testDeconvertEmptyMap()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertMap([]);
        $this->assertEquals([], $deconverted, "Incorrect deconversion for Empty Map");
    }

    /**
     * Test deconverting a Empty Map from graphson 3.0 format to native
     *
     * @return void
     * @expectedException \Brightzone\GremlinDriver\InternalException
     */
    public function testDeconvertMapOddElements()
    {
        $serializer = new Gson3;

        $deconverted = $serializer->deconvertMap([
            "test",
            "friend",
            ["@type" => "g:Int32", "@value" => 2009],
        ]);
    }

    /**
     * Test deconverting any item
     *
     * @return void
     */
    public function testDeconvert()
    {
        $serializer = new Gson3;
        $intType = $this->getintType();

        // List
        $deconverted = $serializer->deconvert(["@type" => "g:List", "@value" => ["test", "test2"]]);
        $this->assertEquals(["test", "test2"], $deconverted, "Incorrect GS3 deconversion for List");

        // Map
        $deconverted = $serializer->deconvert([
            "@type"  => "g:Map",
            "@value" => [
                ["@type" => $intType, "@value" => 2], "test",
                ["@type" => $intType, "@value" => 7], "test2",
            ],
        ]);
        $this->assertEquals([2 => "test", 7 => "test2"], $deconverted, "Incorrect GS3 deconversion for Map");

        // String
        $deconverted = $serializer->deconvert("testing");
        $this->assertEquals("testing", $deconverted, "Incorrect GS3 deconversion for String");

        // bool
        $deconverted = $serializer->deconvert(TRUE);
        $this->assertEquals(TRUE, $deconverted, "Incorrect GS3 deconversion for Bool");

        // Double
        $deconverted = $serializer->deconvert(["@type" => "g:Double", "@value" => 5.3]);
        $this->assertEquals(5.3, $deconverted, "Incorrect GS3 deconversion for Double");

        // Int
        $deconverted = $serializer->deconvert(["@type" => $intType, "@value" => 5]);
        $this->assertEquals(5, $deconverted, "Incorrect GS3 deconversion for Integer");
    }

    /**
     * Test deconverting an unsupported item
     *
     * @expectedException \Brightzone\GremlinDriver\InternalException
     */
    public function testDeconvertUnsupported()
    {
        $serializer = new Gson3;

        $serializer->deconvert(["@type" => "g:Unknown", "@value" => 5]);
    }

    /**
     * Test deconverting a complex item
     *
     */
    public function testDeconvertComplex()
    {
        $serializer = new Gson3;
        $deconverted = $serializer->deconvert([
            "@type"  => "g:List",
            "@value" => [
                [
                    "@type"  => "g:List",
                    "@value" => [
                        "something",
                        ["@type" => "g:Int64", "@value" => 32],
                    ],
                ],
                TRUE,
                [
                    "@type"  => "g:Map",
                    "@value" => [
                        ["@type" => "g:Int64", "@value" => 0],
                        "lala",
                        ["@type" => "g:Int64", "@value" => 33],
                        ["@type" => "g:Int64", "@value" => 21],
                        "key",
                        [
                            "@type"  => "g:List",
                            "@value" => [
                                "lock",
                                "door",
                                [
                                    "@type"  => "g:Map",
                                    "@value" => [
                                        "again",
                                        "inside",
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                ["@type" => "g:Int64", "@value" => 30],
            ],
        ]);

        $this->assertEquals([
            [
                "something",
                32,
            ],
            TRUE,
            [
                "lala",
                33    => 21,
                "key" => [
                    "lock",
                    "door",
                    [
                        "again" => "inside",
                    ],
                ],
            ],
            30,
        ], $deconverted, "Incorrect GS3 deconversion for Complex object");
    }

    /**
     * Test unserializing array to GraphSON 3.0
     *
     * @return void
     */
    public function testUnserialize()
    {
        $serializer = new Gson3;
        $data = '{"@type":"g:List","@value":[{"@type":"g:List","@value":["something",{"@type":"g:Int64","@value":32}]},true,{"@type":"g:Map","@value":[{"@type":"g:Int64","@value":0},"lala",{"@type":"g:Int64","@value":33},{"@type":"g:Int64","@value":21},"key",{"@type":"g:List","@value":["lock","door",{"@type":"g:Map","@value":["again","inside"]}]}]},{"@type":"g:Int64","@value":30}]}';

        $data = $serializer->unserialize($data);
        $this->assertEquals([
            [
                "something",
                32,
            ],
            TRUE,
            [
                "lala",
                33    => 21,
                "key" => [
                    "lock",
                    "door",
                    [
                        "again" => "inside",
                    ],
                ],
            ],
            30,
        ], $data, "incorrect GraphSON 3.0 was generated");
    }

    /**
     * Test a basic graphson3 client-server exchange
     * @return void
     */
    public function testConnect()
    {
        $db = new Connection([
            'host'     => 'localhost',
            'port'     => 8182,
            'graph'    => 'graph',
            'username' => $this->username,
            'password' => $this->password,
        ]);

        $db->message->registerSerializer(new Gson3, TRUE);

        $message = $db->open();
        $this->assertNotEquals($message, FALSE, 'Failed to connect to db');

        $result = $db->send('5+5');
        $this->assertEquals(10, $result[0], 'Script response message is not the right type. (Maybe it\'s an error)');

        $result = $db->send('g.V()');
        $this->assertEquals(6, count($result), 'Script response message is not the right type. (Maybe it\'s an error)');

        //check disconnection
        $db->close();
        $this->assertFALSE($db->isConnected(), 'Despite not throwing errors, Socket connection is not established');
    }
}
