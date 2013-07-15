This is a rexpro client for PHP. It's main purpose was for it to be integrated into frameworks, and therefore it will fail silently and not throw any exceptions. See Error handling section 


Installation
============

### Requirements


First you'll need to install the required dependencies. Which is to say : [MsgPack](http://msgpack.org/) .

Install MsgPack from git:
```bash
git clone https://github.com/msgpack/msgpack-php.git
cd msgpack-php
phpize
./configure && make && make install
```

Install MsgPack from PEAR:
```bash
pecl channel-discover php-msgpack.googlecode.com/svn/pecl
pecl install msgpack/msgpack-beta
```

### PHP Rexster Client

##### For Rexster 2.4

```bash
git clone https://github.com/PommeVerte/rexpro-php.git
```

##### For Rexster 2.3

```bash
git clone https://github.com/PommeVerte/rexpro-php.git -b 2.3
```


Error Handling
==============

The PHP Client does not throw Exceptions. It was built with the goal of being wrapped into a PHP framework and therefore fails silently (you can still get errors by checking method return values).

For instance:

```php
if($db->open('localhost:8184','tinkergraph',null,null) === false)
  throw Exception($db->error->code . ' : ' . $db->error->description);
$db->script = 'g.v(2)';
$result = $db->runScript();
if($result === false)
   throw Exception($db->error->code . ' : ' . $db->error->description);
//do something with result
```

Namespace
=========

The Connection class exists within the `rexpro` namespace. This means that you have to do either of the two following:

```php
require_once 'rexpro-php/rexpro/Connection.php';
use \rexpro\Connection;
 
$db = new Connection;
```

Or

```php
require_once 'rexpro-php/rexpro/Connection.php';

$db = new \rexpro\Connection;
```
Examples
========

You can find more information by reading the API in the wiki. 

Here are a few basic usages.

Example 1:

```php
$db = new Connection;
//you can set $db->timeout = 0.5; if you wish
$db->open('localhost:8184','tinkergraph',null,null);
$db->script = 'g.v(2)';
$result = $db->runScript();
//do something with result
$db->close();
```

Example 2 (with bindings):

```php
$db = new Connection;
$db->open('localhost:8184','tinkergraph',null,null);

$db->script = 'g.v(CUSTO_BINDING)';
$db->bindValue('CUSTO_BINDING',2);
$result = $db->runScript();
//do something with result
$db->close();
```

Example 3 (sessionless):

```php
$db = new Connection;
$db->open('localhost:8184');
$db->script = 'g.v(2).map()';
$db->graph = 'tinkergraph'; //need to provide graph
$result = $db->runScript(false);
//do something with result
$b->close();
```

Example 4 (transaction):

```php
$db = new Connection;
$db->open('localhost:8184','neo4jsample',null,null);
  	
$db->transactionStart();

$db->script = 'g.addVertex([name:"michael"])';
$result = $db->runScript();
$db->script = 'g.addVertex([name:"john"])';
$result = $db->runScript();

$db->transactionStop(true);//accept commit of changes. set to false if you wish to cancel changes
$db->close();
```
