2.3.0
=====
- Added support for Aliases. You can implement them either globally or locally as shown bellow:

   ```php
   // Global
   $db = new Connection([
        'graph' => 'graph',
        'aliases' => ["somethingcrazy" => "g"]
   ]);
   $db->open();

   $result = $db->send("somethingcrazy.V().count()");
   ```

   ```php
   // Local
   $db = new Connection([
        'graph' => 'graph',
   ]);
   $db->open();

   $db->message->setArguments([
        'aliases' => ['somethingcrazy'=>'g'],
   ]);
   $result = $db->send("somethingcrazy.V().count()");
   ```

- Added tests for aliases
- Added tests to manageTransaction which allows session requests to auto commit transactions on each request (or rollback if error). The code bellow will actually commit the added vertex to the graph since the transaction is auto managed on the request level:

   ```php
   $db = new Connection([
        'graph' => 'graphT',
   ]);
   $db->open();

   $db->transactiontart();
   $db->transactionStart();

   $db->message->setArguments([
        'manageTransaction' => TRUE,
   ]);
   $db->message->gremlin = 't.addV()';
   $db->send();

   $db->transactionStop(FALSE);
   ```

- Added a test for scriptEvaluationTimeout.
- Added support for a custom saslMechanism for authentication. By default gremlin-server ignores this feature. But custom gremlin-server builds may require it. You can simply define it as follows:

   ```php
   $db = new Connection([
        'graph' => 'graphT',
        'saslMechanism' => 'GSSAPI', // defaults to 'PLAIN'
   ]);
   $db->open();
   ```

- Updated testing and travis to use gremlin-server `3.2.1`

2.2.3
=====
- Corrected an issue where using a custom mimeType for requests was producing an error.
- Updated testing build to use gremlin-server `v3.1.1`
- Added several new test cases.

2.2.2
=====
- Generalized some stream get behavior to be consistant accross all operations. This can correct some issues with hhvm streaming content before it's done loading in the socket.

2.2.1
=====
- Added a fix for large result sets in hhvm (and a test case)

2.2.0
=====
- Added a few more test cases around existing features
- Added support for a `Connection::$emptySet` property. When this property is set to `TRUE` then empty result sets will no longer throw a ServerException (this was default). Instead they will return an empty array:

   ```php
   $db = new Connection([
       'host' => 'localhost',
       'port' => 8182,
       'graph' => 'graph',
       'emptySet' => TRUE
   ]);
   $db->open();

   $result = $db->send("g.V().has('name', 'doesnotexist')");
   print_r($result); // Array()
   ```

2.1.2
=====
- Upgraded the testing process to use gremlin-server 3.0.2
- Corrected a bug where ssl would not work for users of PHP 5.6+. These users will now need to configure ssl as follows:

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

2.1.1
=====
- Made changes to explicit some server errors that were previously hidden from the user

2.1.0
=====

- Added support for fail-retry strategies on transactions:

   ```php
   $db = new Connection([
       'host' => 'localhost',
       'port' => 8182,
       'graph' => 'graphT',
       'retryAttempts' => 10
   ]);
   $db->open();

   $db->transaction(function() use($db){
       $db->send('n.addVertex("name","michael")');
       $db->send('n.addVertex("name","john")');
   });

   $db->close();
   ```

2.0.0
=====
2.0 supports TP 3.0.1 with authentication features. There was a major overhaul of the code in order to make the API clearer and to stick to PSR-4 namespaces. Bellow are the BC breaking changes you will need to make if you are upgrading from v1.0 :

#####Breaking changes
- Namespaces currently changed from `\brightzone\rexpro\*` to `\Brightzone\GremlinDriver\*` and so forth all in CamelCase. You will need to change these in your code to reflect the change.
- The Message class has been changed from `\Brightzone\GremlinDriver\Messages` to `\Brightzone\GremlinDriver\Message`. I you use messages directly in your code you will need to switch for the newer one.
- Tests have been moved from `src/tests` to `tests/`. This probably doesn't affect you, but just incase.
- `Connection::open()` does not take any params anymore. You need to define all connection parameters by providing them to the Connection constructor. For example:

   ```php
   $db = Connection();
   $db->open('localhost:8182', 'graph', 'username', 'password');
   ```
   becomes :
   ```php
   $db = Connection([
      'host' => 'localhost', //default
      'port' => 8182, //default
      'graph' => 'graph',
      'username' => 'username',
      'password' => 'password'
   ]);
   $db->open();
   ```
   This is reflected in the [README](README.md) examples.


1.0
===

Original release.
