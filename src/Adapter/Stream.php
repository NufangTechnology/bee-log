<?php
namespace Bee\Logger\Adapter;

use Bee\Logger\Exception;
use Bee\Logger\Formatter\Json;
use Phalcon\Logger\Adapter;
use Phalcon\Logger\FormatterInterface;

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
class Stream extends Adapter
{
    /**
     * File handler resource
     *
     * @var resource
     */
    protected $_stream;

    /**
     * 日志文件名
     *
     * @var string
     */
    protected $_name = '';

    /**
     * @var bool
     */
    protected $_split = true;

    /**
     * @var string
     */
    protected $_mode = 'ab';

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
     * @param string $name
     * @param null|array $options
     * @throws Exception
     */
    public function __construct($basePath, $name = '', $options = null)
    {
        $mode = 'ab';

        if (isset($options['mode'])) {
            $mode = $options['mode'];
        }
        if (stripos($mode, 'r')) {
            throw new Exception('Stream must be opened in append or write mode');
        }

        if (isset($options['ext'])) {
            $this->_logExt = $options['ext'];
        }

        if (!empty($name)) {
            $this->_name  = $name;
            $this->_split = false;
        }

        if (empty($basePath)) {
            throw new Exception('Base path is required');
        }
        if (!is_dir($basePath)) {
            throw new Exception("'{$basePath}' is not exists");
        }

        $this->_basePath = trim($basePath, '/');
        $this->_mode     = $mode;
    }

    /**
     * Returns the internal formatter
     *
     * @return FormatterInterface
     */
    public function getFormatter() : FormatterInterface
    {
        if (!is_object($this->_formatter)) {
            $this->_formatter = new Json;
        }

        return $this->_formatter;
    }

    /**
     * 获取日志写入 stream
     *
     * @return bool|resource
     * @throws Exception
     */
    public function getStream()
    {
        if ($this->_split) {
            $today = date('Y-m-d');

            if ($this->_name != $today) {
                $this->close();
                $this->_name   = $today . $this->_logExt;
                $this->_stream = $this->createStream($this->_name, $this->_mode);
            }
        } else {
            if (empty($this->_stream)) {
                $this->_stream = $this->createStream($this->_name, $this->_mode);
            }
        }

        return $this->_stream;
    }

    /**
     * 创建 stream 流
     *
     * @param string $name
     * @param string $model
     * @return bool|resource
     * @throws Exception
     */
    protected function createStream($name, $model)
    {
        $file   = $this->_basePath . '/' . $name;
        $stream = fopen($file, $model);
        if (!$stream) {
            throw new Exception("Can't open stream '" . $this->_name . "'");
        }

        return $stream;
    }

    /**
     * Writes the log to the stream itself
     *
     * @param string $message
     * @param int $type
     * @param int $time
     * @param array $context
     * @throws Exception
     */
    public function logInternal(string $message, int $type, int $time, array $context)
    {
        $stream = $this->getStream();

        if (!is_resource($stream)) {
            throw new Exception('Cannot send message to the log because it is invalid');
        }

        fwrite($stream, $this->getFormatter()->format($message, $type, $time, $context));
    }

    /**
     * Closes the logger
     *
     * @return bool
     */
    public function close() : bool
    {
        if (is_resource($this->_stream)) {
            return fclose($this->_stream);
        }

        return false;
    }
}