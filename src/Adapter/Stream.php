<?php
namespace Bee\Logger\Adapter;

use Bee\Logger\Exception;
use Psr\Log\AbstractLogger;

/**
 * 记录有效的 PHP Stream 至日志
 *
 * <code>
 * use Phalcon\Logger;
 * use Phalcon\Logger\Adapter\Stream;
 *
 * $logger = new Stream("php://stderr");
 *
 * $logger->log("This is a message");
 * $logger->log(Logger::ERROR, "This is an error");
 * $logger->error("This is another error");
 * </code>
 */
class Stream extends AbstractLogger
{
    /**
     * @var string
     */
    protected $_logExt = '.log';

    /**
     * @var string
     */
    protected $_basePath;

    /**
     * Stream constructor.
     *
     * @param string $basePath
     */
    public function __construct($basePath)
    {
        $this->_basePath = trim($basePath, '/');
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        $today = date('Y-m-d');
        $file = "/{$this->_basePath}/{$today}-{$this->_logExt}";

        file_put_contents($file, json_encode($context, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }
}