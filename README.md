This is a Gremlin server client for PHP. It allows you to run gremlin queries against graph databases (including Neo4j, Titan, etc.). You can find a beginner tutorial by reading the [Get up and running with Tinkerpop 3 and PHP](https://dylanmillikin.wordpress.com/2015/07/20/get-up-and-running-with-tinkerpop-3-and-php/) article.

This driver currently supports TP3+.

For a TP2 compatible php driver please check [rexpro-php](https://github.com/PommeVerte/rexpro-php)

[![Build Status](https://travis-ci.org/PommeVerte/gremlin-php.svg?branch=master)](https://travis-ci.org/PommeVerte/gremlin-php) [![Latest Stable Version](https://poser.pugx.org/brightzone/gremlin-php/v/stable)](https://packagist.org/packages/brightzone/gremlin-php) [![Coverage Status](https://coveralls.io/repos/PommeVerte/gremlin-php/badge.svg?branch=master)](https://coveralls.io/github/PommeVerte/gremlin-php?branch=master) [![Total Downloads](https://poser.pugx.org/brightzone/gremlin-php/downloads)](https://packagist.org/packages/brightzone/gremlin-php) [![License](https://poser.pugx.org/brightzone/gremlin-php/license)](https://packagist.org/packages/brightzone/gremlin-php)

[![Join the chat at https://gitter.im/PommeVerte/gremlin-php](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/PommeVerte/gremlin-php?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

Installation
============


### PHP Gremlin-Server Client

Preferred method is through composer.

Either run :

```bash
php composer.phar require brightzone/gremlin-php "3.*"
```

Or add:

```json
"brightzone/gremlin-php": "3.*"
```

to the `require` section of your `composer.json` file

### Tinkerpop 3.3.x server Configuration 

This driver now supports `GraphSON 3.0` with a basic beta serializer. You can use this serializer by doing : 

```php
  $db = ne Connection();
  $db->message->registerSerializer('\Brightzone\GremlinDriver\Serializers\Gson3', TRUE);
```

If you wish to continue using the stable `GraphSON 1.0` serializer it is necessary to configure the server to use `GraphSON 1.0`. To do this, make sure to replace the `# application/json` serializer in your `gremlin-server.yaml` configuration file with the following:

```yaml
- { className: org.apache.tinkerpop.gremlin.driver.ser.GraphSONMessageSerializerV1d0, config: { ioRegistries: [org.apache.tinkerpop.gremlin.tinkergraph.structure.TinkerIoRegistryV1d0]  }}        # application/json
``` 

Upgrading
=========
BC breaking changes are introduced between major version changes. So if you're upgrading to `2.0.0` from `1.0`. Please read the [CHANGELOG](CHANGELOG.md)

Usage
=========

The Connection class exists within the `GremlinDriver` namespace.

```php
require_once('vendor/autoload.php');
use \Brightzone\GremlinDriver\Connection;

$db = new Connection;
```

Features
========

You can find more information by reading the [API](http://pommeverte.github.io/gremlin-php/).

### Basic connection

A basic connection can be created by creating a new `Connection` as follows.

```php
$db = new Connection([
    'host' => 'localhost',
    'graph' => 'graph'
]);
//you can set $db->timeout = 0.5; if you wish
$db->open();

$result = $db->send('g.V(2)');
//do something with result
$db->close();
```

Note that "graph" is the name of the graph configured in gremlin-server (not the reference to the traversal which is `g = graph.traversal()`)

It is also possible to specify authentication credentials as follows:

```php
$db = new Connection([
    'host' => 'localhost',
    'graph' => 'graph',
    'username' => 'pomme',
    'password' => 'hardToCrack'
]);
//you can set $db->timeout = 0.5; if you wish
$db->open();
$db->send('g.V(2)');
//do something with result
$db->close();
```

Check the SSL section for an example using the configuration files provided by TP.

You can find all the options available to the `Connection` class [here](http://pommeverte.github.io/gremlin-php/brightzone-gremlindriver-connection.html).

## Bindings

Bindings are important for several reasons. They protect from code injections, but they also prevent the server from having to compile scripts on every run.

The following example illustrates both of these points:

```php
$unsafeUserValue = 2; //This could be anything submitted via form.
$db = new Connection([
    'host' => 'localhost',
    'port' => 8182,
    'graph' => 'graph',
]);
$db->open();

$db->message->bindValue('CUSTO_BINDING', $unsafeUserValue); // protects from injections
$result1 = $db->send('g.V(CUSTO_BINDING)'); // The server compiles this script and adds it to cache

$db->message->bindValue('CUSTO_BINDING', 5);
$result2 = $db->send('g.V(CUSTO_BINDING)'); // The server already has this script so gets it from cache without compiling it, but runs it with 5 instead of $unsafeUserValue
$result3 = $db->send('g.V(5)'); // The script is different so the server compiles this script and adds it to cache

//do something with result
$db->close();
```

As you can see from the example above, not using bindings can be costly as the server needs to compile every new script.

## Sessions

Sessions allow you to maintain variables and bindings accross multiple requests.

```php
$db = new Connection([
    'host' => 'localhost',
    'port' => 8182,
]);
$db->open();
$db->send('cal = 5+5', 'session'); // first query sets the `cal` variable
$result = $db->send('cal', 'session'); // result = [10]
//do something with result
$db->close();
```

## Transactions

Transactions will allow you to revert or confirm a set of changes made accross multiple requests.

```php
$db = new Connection([
    'host' => 'localhost',
    'port' => 8182,
    'graph' => 'graphT',
]);
$db->open();

$db->transactionStart();

$db->send('t.addV().property("name","michael")');
$db->send('t.addV().property("name","john")');

$db->transactionStop(FALSE); //rollback changes. Set to TRUE to commit.
$db->close();
```

Note that "graphT" above refers to a graph that supports transactions. And that transactions start a session automatically. You can check which features are supported by your graph with `graph.features()`.

It is also possible to express transactions with a lambda notation:

```php
$db = new Connection([
    'host' => 'localhost',
    'port' => 8182,
    'graph' => 'graphT',
]);
$db->open();

$db->transaction(function($db){
    $db->send('t.addV().property("name","michael")');
    $db->send('t.addV().property("name","john")');
}, [$db]);

$db->close();
```

This will commit these changes or return an `Exception` if an error occured (and automatically rollback changes). The advantage of using this syntax is that it allows you to handle fail-retry scenarios.

It is sometimes important to implement a fail-retry strategy for your transactional queries. One such example is in the event of concurrent writes to the same elements, the databases (such as titan) will throw an error when elements are locked. When this happens you will most likely want the driver to retry the query a few times until the element is unlocked and the write can proceed. For such instances you can do:

```php
$db = new Connection([
    'host' => 'localhost',
    'port' => 8182,
    'graph' => 'graphT',
    'retryAttempts' => 10
]);
$db->open();

$db->transaction(function($db){
    $db->send('t.addV().property("name","michael")');
    $db->send('t.addV().property("name","john")');
}, [$db]);

$db->close();
```

This will attempt to run the query 10 times before fully failing.
It is worth noting that `retryAttempts` also works with -out of session- queries:

```php
$db->send('gremlin.code.here'); // will retry multiple times if 'retryAttempts' is set
```
Advanced features
=================

## Message objects

Sometimes you may need to have greater control over individual requests. The reasons for this can range from using custom serializers, different query languages (`gremlin-python`, `gremlin-scala`, `java`), to specifying a request timeout limit or a local alias.
For these cases you can construct a custom `Message` object as follows:

```php
$message = new Message;
$message->gremlin = 'custom.V()'; // note that custom refers to the graph traversal set on the server as g (see alias bellow)
$message->op = 'eval'; // operation we want to run
$message->processor = ''; // the opProcessor the server should use
$message->setArguments([
                'language' => 'gremlin-groovy',
                'aliases' => ['custom' => 'g'],
                // ... etc
]);
$message->registerSerializer('\Brightzone\GremlinDriver\Serializers\Json');

$db = new Connection();
$db->open();
$db->send($message);
//do something with result
$db->close();
```

Of course you can affect the current db message in the same manner through `$db->message`.

For a full list of arguments and values available please refer to the [TinkerPop documentation for drivers](http://tinkerpop.apache.org/docs/current/dev/provider/#_opprocessors_arguments).

## SSL

When security is important you will want to use the SSL features available. you can do so as follows:

```php
$db = new Connection([
    'host' => 'localhost',
    'graph' => 'graph',
    'ssl' => TRUE
]);
//you can set $db->timeout = 0.5; if you wish
$db->open();
$db->send('g.V(2)');
//do something with result
$db->close();
```

*Note that with php 5.6+ you will need to provide certificate information in the same manner you would to a `stream_context_create()`. In which case your `Connection()` call could look something like the following (replace with your own certificates and/or bundles):*

```php
$db = new Connection([
    'host' => 'localhost',
    'graph' => 'graph',
    'ssl' => [
        "ssl"=> [
            "cafile" => "/path/to/bundle/ca-bundle.crt",
            "verify_peer"=> true,
            "verify_peer_name"=> true,
        ]
    ]
]);
```
If you're using the bundled `gremlin-server-secure.yaml` file, you can use [this configuration](https://github.com/PommeVerte/gremlin-php/blob/master/tests/AuthTest.php#L23-L35) to connect to it.
For dev and testing purposes you can use [this configuration](https://github.com/PommeVerte/gremlin-php/blob/master/tests/AuthTest.php#L29-L34)

## Serializers

Serializers can be changed on the gremlin-server level. This allows users to set their own serializing rules. 
This library comes by default with a Json serializer. Any other serializer that implements `SerializerInterface` can be added dynamically with:

```php
$db = new Connection;
$serializer = $db->message->getSerializer() ; // returns an instance of the default JSON serializer
echo $serializer->getName(); // JSON
echo $serializer->getMimeType(); // application/json

$db->message->registerSerializer('namespace\to\my\CustomSerializer', TRUE); // sets this as default
$serializer = $db->message->getSerializer(); // returns an instance of the CustomSerializer serializer (default)
$serializer = $db->message->getSerializer('application/json'); // returns an instance of the JSON serializer
```
You can add many serializers in this fashion. When gremlin-server responds to your requests, gremlin-php will be capable of using the appropriate one to unserialize the message.

API
============

You can find the full api [here](http://pommeverte.github.io/gremlin-php/).

Unit testing
============

Neo4J is required for the full test suit. It is not bundled with gremlin-server by default so you will need to manually install it with:

```bash
bin/gremlin-server.sh -i org.apache.tinkerpop neo4j-gremlin 3.2.8
```
(replace the version number by the one that corresponds to your gremlin-server version)

Copy the following files :

```bash
cp <gremlin-php-root-dir>/build/server/gremlin-server-php.yaml <gremlin-server-root-dir>/conf/
cp <gremlin-php-root-dir>/build/server/neo4j-empty.properties <gremlin-server-root-dir>/conf/
cp <gremlin-php-root-dir>/build/server/gremlin-php-script.groovy <gremlin-server-root-dir>/scripts/
```

You will then need to run gremlin-server in the following manner :

```bash
bin/gremlin-server.sh conf/gremlin-server-php.yaml
```

Then run the unit test via:

```bash
# make sure test dependecies are installed 
composer install # PHP >=5.6
composer update # PHP 5.5

# Run the tests
phpunit -c build/phpunit.xml
```

### Browser /tests/webtest.php file

If your gremlin-php folder is on the web path. You can also load `tests/webtest.php` instead of using the command line to run PHPUNIT tests.

This is useful in some wamp or limited access command line situations. 
