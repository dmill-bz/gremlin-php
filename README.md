This is a Gremlin server client for PHP. It allows you to run gremlin queries against graph databases (including Neo4j, Titan, etc.). You can find a beginner tutorial by reading the [Get up and running with Tinkerpop 3 and PHP](https://dylanmillikin.wordpress.com/2015/07/20/get-up-and-running-with-tinkerpop-3-and-php/) article.

This driver currently supports TP3+.

For a TP2 compatible php driver please check [rexpro-php](https://github.com/PommeVerte/rexpro-php)

[![Build Status](https://travis-ci.org/PommeVerte/gremlin-php.svg?branch=master)](https://travis-ci.org/PommeVerte/gremlin-php) [![Latest Stable Version](https://poser.pugx.org/brightzone/gremlin-php/v/stable)](https://packagist.org/packages/brightzone/gremlin-php) [![Coverage Status](https://coveralls.io/repos/PommeVerte/gremlin-php/badge.svg?branch=master&service=github)](https://coveralls.io/github/PommeVerte/gremlin-php?branch=master) [![Total Downloads](https://poser.pugx.org/brightzone/gremlin-php/downloads)](https://packagist.org/packages/brightzone/gremlin-php) [![License](https://poser.pugx.org/brightzone/gremlin-php/license)](https://packagist.org/packages/brightzone/gremlin-php)

[![Join the chat at https://gitter.im/PommeVerte/gremlin-php](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/PommeVerte/gremlin-php?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

Installation
============


### PHP Gremlin-Server Client

Prefered method is through composer.

Either run :

```bash
php composer.phar require brightzone/gremlin-php "*"
```

Or add:

```json
"brightzone/gremlin-php": "*"
```

to the `require` section of your `composer.json` file

Usage
=========

The Connection class exists within the `rexpro` namespace. (history: rexpro used to be the old protocol used by the driver in Tinkerpop2).

```php
require_once('vendor/autoload.php');
use \brightzone\rexpro\Connection;

$db = new Connection;
```

Examples
========

You can find more information by reading the [API](http://pommeverte.github.io/gremlin-php/).

Here are a few basic usages.

Example 1 :

```php
$db = new Connection;
//you can set $db->timeout = 0.5; if you wish
$db->open('localhost', 'graph');

$result = $db->send('g.V(2)');
//do something with result
$db->close();
```

Note that "graph" is the name of the graph configured in gremlin-server (not the reference to the traversal which is `g = graph.traversal()`)

Example 1 bis (Writing the same with message object) :
```php
$db = new Connection([
    'host' => 'localhost',
    'graph' => 'graph',
]);
//you can set $db->timeout = 0.5; if you wish
$db->open();
$db->send('g.V(2)');
//do something with result
$db->close();
```


Example 2 (with bindings) :

```php
$db = new Connection([
    'host' => 'localhost',
    'port' => 8182,
    'graph' => 'graph',
]);
$db->open();

$db->message->bindValue('CUSTO_BINDING', 2);
$db->send('g.V(CUSTO_BINDING)'); //mix between Example 1 and 1B
//do something with result
$db->close();
```

Example 3 (with session) :

```php
$db = new Connection([
    'host' => 'localhost',
    'port' => 8182,
]);
$db->open();
$db->send('cal = 5+5', 'session');
$result = $db->send('cal', 'session'); // result = [10]
//do something with result
$db->close();
```

Example 4 (transaction) :

```php
$db = new Connection([
    'host' => 'localhost',
    'port' => 8182,
    'graph' => 'graphT',
]);
$db->open();
$originalCount = $db->send('n.V().count()');

$db->transactionStart();

$db->send('n.addVertex("name","michael")');
$db->send('n.addVertex("name","john")');

$db->transactionStop(FALSE); //rollback changes. Set to true to commit.
$db->close();
```
Note that "graphT" above refers to a graph that supports transactions. And that transactions start a session automatically.

Example 5 (Using message object) :

```php
$message = new Messages;
$message->gremlin = 'g.V()';
$message->op = 'eval';
$message->processor = '';
$message->setArguments([
                'language' => 'gremlin-groovy',
                // ... etc
]);
$message->registerSerializer('\Brightzone\GremlinDriver\Serializers\Json');

$db = new Connection();
$db->open();
$db->send($message);
//do something with result
$db->close();
```
Of course you can affect the current db message in the same manner through $db->message.

Adding Serializers
==================

This library comes with a Json serializer. Any other serializer that implements SerializerInterface can be added dynamically with:

```php
$db = new Connection;
$serializer = $db->message->getSerializer() ; // returns an instance of the default JSON serializer
echo $serializer->getName(); // JSON
echo $serializer->getMimeType(); // application/json

$db->message->registerSerializer('namespace\to\my\CustomSerializer', TRUE); // sets this as default
$serializer = $db->message->getSerializer(); // returns an instance the CustomSerializer serializer (default)
$serializer = $db->message->getSerializer('application/json'); // returns an instance the JSON serializer
```
You can add many serializers in this fashion. When gremlin-server responds to your requests, gremlin-client-php will be capable of using the appropriate one to unserialize the message.

API
============

You can find the api [here](http://pommeverte.github.io/gremlin-php/).

Unit testing
============

Neo4J is required for the full test suit. It is not bundled with gremlin-server by default so you will need to manually install it with:

```bash
bin/gremlin-server.sh -i org.apache.tinkerpop neo4j-gremlin 3.0.1-incubating
```
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
phpunit -c build/phpunit.xml
```
