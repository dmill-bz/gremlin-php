<?php
/**
 * This file is meant to run PHPUNIT from a web interface. (ie: by running this file)
 * It is useful for those who do not have access to the command line (wamp, etc.)
 *
 * Just make the gremlin-php folder accessible on the web path and load this php file in your browser
 */


require_once(__DIR__ . '/../vendor/autoload.php');

echo "<pre>";
if(class_exists("PHPUnit_TextUI_Command"))
{
    $command = new \PHPUnit_TextUI_Command;
}
else
{

    $command = new \PHPUnit\TextUI\Command;
}
$command->run(['phpunit', '--conf', '../build/phpunit.xml'], TRUE);
echo "</pre>";