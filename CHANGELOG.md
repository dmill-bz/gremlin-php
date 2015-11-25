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
           'retryAttempts' => 5,
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
