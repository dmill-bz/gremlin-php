<?php

namespace Brightzone\GremlinDriver;

/**
 * Gremlin-server PHP Driver client Workload class
 *
 * Workload class will store some executable code and run it against the database.
 * It also allows for fail-retry strategies in the event of concurrency errors
 *
 * ~~~
 * $workload = new Workload(function(&$db, $msg, $processor, $op, $args){
 *          return $db->send($msg, $processor, $op, $args);
 *      },
 *      [&$this, $msg, $processor, $op, $args]
 * );
 *
 * $response = $workload->linearRetry($this->retryAttempts);
 * ~~~
 *
 * @category DB
 * @package  GremlinDriver
 * @author   Dylan Millikin <dylan.millikin@brightzone.fr>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 apache2
 * @link     https://github.com/tinkerpop/rexster/wiki
 */
class Workload
{
    /**
     * @var callable the callback code to be executed
     * Bellow is a common example:
     * ~~~
     * function(&db, $msg, $processor, $op, $processor, $args){}
     * ~~~
     * It must return something other than void. (the desired result)
     */
    protected $callback;

    /**
     * @var array paramteres required for the workload
     *
     * Ideas of params would be :
     * - Connection &db        connection object to operate on
     * - Message    $msg       possible message to operate on (optional defaults to NULL)
     * - String     $processor processor to use (optional defaults to "")
     * - String     $op        operation to perform (optional defaults to "eval")
     * - array      $args      arguments for the message. (optional defaults to [])
     */
    protected $params;

    /**
     * Override Constructor
     *
     * @param callable $callback the portion of code to execute within the scope of this workload
     * @param array    $params   the paramters to pass to the callback.
     *
     * @return void
     */
    public function __construct(callable $callback, $params)
    {
        $this->callback = $callback;
        $this->params = $params;
    }

    /**
     * Linear retry strategy.
     *
     * @param int $attempts the number of times to retry
     *
     * @return mixed the result of the executable code
     * @throws ServerException
     */
    public function linearRetry($attempts)
    {
        $result = NULL;
        while($attempts >= 1)
        {
            try
            {
                $result = call_user_func_array($this->callback, $this->params);
                break;
            }
            catch(\Exception $e)
            {
                if($e instanceof ServerException && $e->getCode() == 597 && $attempts > 1)
                {
                    usleep(200);
                    $attempts--;
                }
                else
                {
                    throw $e;
                }
            }
        }

        return $result;
    }
}
