<?php

namespace Brightzone\GremlinDriver;

use \Exception;

/**
 * Gremlin-server PHP Driver client Exception class
 *
 * @category DB
 * @package  GremlinDriver
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     http://tinkerpop.incubator.apache.org/docs/3.0.1-incubating/#_developing_a_driver
 */
class ServerException extends Exception
{
    const NO_CONTENT = 204;
    const UNAUTHORIZED = 401;
    const MALFORMED_REQUEST = 498;
    const INVALID_REQUEST_ARGUMENTS = 499;
    const SERVER_ERROR = 500;
    const SCRIPT_EVALUATION_ERROR = 597;
    const SERVER_TIMEOUT = 598;
    const SERVER_SERIALIZATION_ERROR = 599;

    /**
     * Overriding construct
     *
     * @param string    $message  The error message to throw
     * @param int       $code     The error code to throw
     * @param Exception $previous The previous exception if there is one that triggered this error
     *
     * @return void
     */
    public function __construct($message, $code = 0, Exception $previous = NULL)
    {
        $message = $this->getMessagePerCode($code) . ' : ' . $message;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Lets add more information to the error message thrown by using the Error Code
     *
     * @param int $code The error code we want to generate a message for.
     *
     * @return string The error message that corresponds to the error code
     */
    private function getMessagePerCode($code)
    {
        $messages = [
            self::NO_CONTENT => "The server processed the request but there is no result to return (e.g. an {@link Iterator} with no elements).",
            self::UNAUTHORIZED => "The request attempted to access resources that the requesting user did not have access to.",
            self::MALFORMED_REQUEST => "The request message was not properly formatted which means it could not be parsed at all or the 'op' code was not recognized such that Gremlin Server could properly route it for processing. Check the message format and retry the request.",
            self::INVALID_REQUEST_ARGUMENTS => "The request message was parseable, but the arguments supplied in the message were in conflict or incomplete. Check the message format and retry the request.",
            self::SERVER_ERROR => "A general server error occurred that prevented the request from being processed.",
            self::SCRIPT_EVALUATION_ERROR => "The script submitted for processing evaluated in the ScriptEngine with errors and could not be processed. Check the script submitted for syntax errors or other problems and then resubmit.",
            self::SERVER_TIMEOUT => "The server exceeded one of the timeout settings for the request and could therefore only partially responded or did not respond at all.",
            self::SERVER_SERIALIZATION_ERROR => "The server was not capable of serializing an object that was returned from the script supplied on the request. Either transform the object into something Gremlin Server can process within the script or install mapper serialization classes to Gremlin Server.",
        ];

        return isset($messages[$code]) ? $messages[$code] : '';
    }
}
