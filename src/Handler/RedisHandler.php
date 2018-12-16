<?php
namespace Bee\Log\Handler;

use Bee\Log\Logger;

/**
 * Logs to a Redis key using rpush
 *
 * usage example:
 *
 *   $log = new Logger('application');
 *   $redis = new RedisHandler(new Predis\Client("tcp://localhost:6379"), "logs", "prod");
 *   $log->pushHandler($redis);
 *
 * @author Thomas Tourlourat <thomas@tourlourat.com>
 */
class RedisHandler extends AbstractProcessingHandler
{
    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  array $record
     * @return void
     */
    protected function write(array $record)
    {
    }
}
