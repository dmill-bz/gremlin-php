<?php

namespace brightzone\rexpro;

use \Exception;

/**
 * Gremlin-server PHP Driver client Exception class for internal exceptions
 *
 * @category DB
 * @package  gremlin-php
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki/RexPro-Messages
 */
class InternalException extends Exception
{
    /**
     * Overriding construct
     *
     * @param string    $message  The error message to throw
     * @param int       $code     The error code to throw
     * @param Exception $previous The previous exception if there is one that triggered this error
     *
     * @return void
     */
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        $message = 'gremlin-php driver has thrown the following error : '. $message;
        parent::__construct($message, $code, $previous);
    }
}
