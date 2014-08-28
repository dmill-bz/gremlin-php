This is a Gremlin server client for PHP.

Changes
=======
There are many changes but bellow are the most noticeable if you've used rexpro-php before

- Client now throw errors that you will need to catch
- Connection params have changes
- Messages class has been revamped and is independant from Connection (see documentation on how to use this)
- Unit testing will require some more configuration
- Runs sessionless by default (rexpro-php 2.3 & 2.4+ ran with sessions as the default)


Installation
============

### PHP Gremlin-Server Client

##### For Gremlin-Server 3.0.0-M1

Prefered method is through composer. Add the following to your **composer.json** file:

```json
{
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/PommeVerte/rexpro-php.git"
        }
    ],
    "require": {
        "brightzone/rexpro": "3.0"
    }
}
```

If you just want to pull and use the library do:

```bash
git clone https://github.com/PommeVerte/rexpro-php.git -b 3.0
cd rexpro-php
composer install --no-dev # required to set autoload files
```

Namespace
=========

The Connection class exists within the `rexpro` namespace. This means that you have to do either of the two following:

```php
require_once('vendor/autoload.php');
use \brightzone\rexpro\Connection;
 
$db = new Connection;
```

Or

```php
require_once('vendor/autoload.php');

$db = new \brightzone\rexpro\Connection;
```

Examples
========

You can find more information by reading the API in the wiki. 

Here are a few basic usages.

Example 1 :

```php
$db = new Connection;
//you can set $db->timeout = 0.5; if you wish
$db->open('localhost', 'g');

$result = $db->send('g.v(2)');
//do something with result
$db->close();
```

Example 1 bis (Writing the same with message object) :
```php
$db = new Connection;
//you can set $db->timeout = 0.5; if you wish
$db->open('localhost', 'g');

$db->message->gremlin = 'g.v(2)';
$result = $db->send(); //automatically fetches the message
//do something with result
$db->close();
```


Example 2 (with bindings) :

```php
$db = new Connection;
$db->open('localhost:8182', 'g');

$db->message->bindValue('CUSTO_BINDING', 2);
$result = $db->send('g.v(CUSTO_BINDING)'); //mix between Example 1 and 1B
//do something with result
$db->close();
```

Example 3 (with session) :

```php
$db = new Connection;
$db->open('localhost:8182');
$db->send('cal = 5+5', 'session');
$result = $db->send('cal', 'session'); // result = [10]
//do something with result
$db->close();
```

Example 4 (transaction) :

```php
$db = new Connection;
$db->open('localhost:8182','n');
  	
$db->transactionStart();

$db->send('n.addVertex("name","michael")');
$db->send('n.addVertex("name","john")');

$db->transactionStop(FALSE); //rollback changes. Set to true to commit.
$db->close();
```
Note that "n" above refers to a graph that supports transactions. And that transactions start a session automatically.
Also, as of today g.addVertex() on Neo4j graphs is buggy.

Example 5 (Using message object) :

```php
$message = new Messages;
$message->gremlin = 'g.V';
$message->op = 'eval';
$message->processor = '';
$message->setArguments([
				'language' => 'gremlin-groovy',
				// .... etc
]);
$message->registerSerializer('\brightzone\rexpro\serializers\Json');

$db = new Connection;
$db->open();
$result = $db->send($message);
//do something with result
$db->close();
```
Of course you can affect the current db message in the same manner through $db->message.

Adding Serializers
==================

This library comes with a Json and an unused legacy Msgpack serializer. Any other serializer that implements SerializerInterface can be added dynamically with:

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

Unit testing
============

To have the unit tests pass you will have to run your gremlin-server with the following configuration file : src/tests/gremlin-server-neo4j.yaml

This requires that you have the neo4j jar. You can get it by doing:

```bash
bin/gremlin-server.sh -i com.tinkerpop neo4j-gremlin 3.0.0.M1
```
