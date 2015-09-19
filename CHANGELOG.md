2.0
===
2.0 supports TP 3.0.1 with authentication features. There was a major overhaul of the code in order to make the API clearer and to stick to PSR-4 namespaces. Bellow are the BC breaking changes you will need to make if you are upgrading from v1.0 :

- Namespaces currently changed from `\brightzone\rexpro\*` to `\Brightzone\GremlinDriver\*` and so forth all in CamelCase. You will need to change these in your code to reflect the change.
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
   This is reflected in README.md examples.


1.0
===

Original release.
