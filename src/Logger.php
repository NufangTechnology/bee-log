<?php
namespace Bee\Log;

use Bee\Log\Handler\HandlerInterface;
use Bee\Log\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\InvalidArgumentException;
use Exception;

/**
 * 日志记录器
 *
 * @package Bee\Log
 */
class Logger implements LoggerInterface, ResettableInterface
{
    /**
     * 详细的调试信息
     */
    const DEBUG = 100;

    /**
     * 有趣的事件
     *
     * 例如: 用户登录，SQL日志。
     */
    const INFO = 200;

    /**
     * 正常但重要的事件
     */
    const NOTICE = 250;

    /**
     * 例外事件不是错误
     *
     * 例如: 使用过时的API，API使用不当，不合理的东西不一定是错误。
     */
    const WARNING = 300;

    /**
     * 运行时错误不需要马上处理
     *
     * 但通常应该被记录和监控。
     */
    const ERROR = 400;

    /**
     * 临界条件
     *
     * 例如: 应用组件不可用，意外的异常。
     */
    const CRITICAL = 500;

    /**
     * 必须立即采取行动
     *
     * 例如: 整个网站宕机了，数据库挂了，等等。 这应该发送短信通知警告你.
     */
    const ALERT = 550;

    /**
     * 系统无法使用
     */
    const EMERGENCY = 600;

    /**
     * Logging levels from syslog protocol defined in RFC 5424
     *
     * @var array $levels Logging levels
     */
    protected static $levels = [
        self::DEBUG     => 'DEBUG',
        self::INFO      => 'INFO',
        self::NOTICE    => 'NOTICE',
        self::WARNING   => 'WARNING',
        self::ERROR     => 'ERROR',
        self::CRITICAL  => 'CRITICAL',
        self::ALERT     => 'ALERT',
        self::EMERGENCY => 'EMERGENCY',
    ];

    /**
     * FIXME: 暂时写死东八区
     *
     * @var \DateTimeZone
     */
    protected static $timezone = 'PRC';

    /**
     * The handler stack
     *
     * @var HandlerInterface[]
     */
    protected $handlers;

    /**
     * Processors that will process all log records
     *
     * To process records of a single handler instead, add the processor on that specific handler
     *
     * @var callable[]
     */
    protected $processors;

    /**
     * @var bool
     */
    protected $microsecondTimestamps = true;

    /**
     * @var callable
     */
    protected $exceptionHandler;

    /**
     * @param callable[] $processors 日志内容中间处理器
     */
    public function __construct(array $processors = [])
    {
        $this->processors = $processors;
    }

    /**
     * Pushes a handler on to the stack.
     *
     * @param  HandlerInterface $handler
     * @return $this
     */
    public function pushHandler(HandlerInterface $handler)
    {
        array_unshift($this->handlers, $handler);

        return $this;
    }

    /**
     * Pops a handler from the stack
     *
     * @return HandlerInterface
     */
    public function popHandler()
    {
        if (!$this->handlers) {
            throw new \LogicException('You tried to pop from an empty handler stack.');
        }

        return array_shift($this->handlers);
    }

    /**
     * Set handlers, replacing all existing ones.
     *
     * If a map is passed, keys will be ignored.
     *
     * @param  HandlerInterface[] $handlers
     * @return $this
     */
    public function setHandlers(array $handlers)
    {
        $this->handlers = array();
        foreach (array_reverse($handlers) as $handler) {
            $this->pushHandler($handler);
        }

        return $this;
    }

    /**
     * @return HandlerInterface[]
     */
    public function getHandlers()
    {
        return $this->handlers;
    }

    /**
     * Adds a processor on to the stack.
     *
     * @param  callable $callback
     * @return $this
     */
    public function pushProcessor($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException(
                'Processors must be valid callables (callback or object with an __invoke method), ' . var_export($callback, true) . ' given'
            );
        }

        array_unshift($this->processors, $callback);

        return $this;
    }

    /**
     * Removes the processor on top of the stack and returns it.
     *
     * @return callable
     */
    public function popProcessor()
    {
        if (!$this->processors) {
            throw new \LogicException('You tried to pop from an empty processor stack.');
        }

        return array_shift($this->processors);
    }

    /**
     * @return callable[]
     */
    public function getProcessors()
    {
        return $this->processors;
    }

    /**
     * 新日志记录使用微妙进行时间记录
     *
     * 与通过 microtime(true) 获取，然后 sprintf() 进行微妙格式化相比，
     * \DateTime::createFromFormat()可带来客观的运行时开销
     *
     * @param bool $micro 为 true 时通过microtime(true)生成时间戳
     */
    public function useMicrosecondTimestamps($micro)
    {
        $this->microsecondTimestamps = (bool) $micro;
    }


    public function addRecord($level, $message, array $context = [])
    {
        if (!$this->handlers) {
            $this->pushHandler(new StreamHandler('php://stderr', static::DEBUG));
        }

        $levelName = static::getLevelName($level);

        // 一旦日志登录匹配尽快的处理完成记录周期
        $handlerKey = null;
        reset($this->handlers);
        while ($handler = current($this->handlers)) {
            if ($handler->isHandling(['level' => $level])) {
                $handlerKey = key($this->handlers);
                break;
            }

            next($this->handlers);
        }

        if (null === $handlerKey) {
            return false;
        }

        // php7.1+ always has microseconds enabled, so we do not need this hack
        $ts = new \DateTime(null, static::$timezone);
        $ts->setTimezone(static::$timezone);

        $record = [
            'unique_id'  => '',
            'auth_token' => '',
            'label'      => '',
            'level'      => $levelName,
            'code'       => 0,
            'datetime'   => $ts,
            'extra'      => [],
        ];

        try {
            foreach ($this->processors as $processor) {
                $record = call_user_func($processor, $record);
            }

            while ($handler = current($this->handlers)) {
                if (true === $handler->handle($record)) {
                    break;
                }

                next($this->handlers);
            }
        } catch (Exception $e) {
            $this->handleException($e, $record);
        }

        return true;
    }

    /**
     * Ends a log cycle and frees all resources used by handlers.
     *
     * Closing a Handler means flushing all buffers and freeing any open resources/handles.
     * Handlers that have been closed should be able to accept log records again and re-open
     * themselves on demand, but this may not always be possible depending on implementation.
     *
     * This is useful at the end of a request and will be called automatically on every handler
     * when they get destructed.
     */
    public function close()
    {
        foreach ($this->handlers as $handler) {
            if (method_exists($handler, 'close')) {
                $handler->close();
            }
        }
    }

    /**
     * Ends a log cycle and resets all handlers and processors to their initial state.
     *
     * Resetting a Handler or a Processor means flushing/cleaning all buffers, resetting internal
     * state, and getting it back to a state in which it can receive log records again.
     *
     * This is useful in case you want to avoid logs leaking between two requests or jobs when you
     * have a long running process like a worker or an application server serving multiple requests
     * in one process.
     */
    public function reset()
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof ResettableInterface) {
                $handler->reset();
            }
        }

        foreach ($this->processors as $processor) {
            if ($processor instanceof ResettableInterface) {
                $processor->reset();
            }
        }
    }

    /**
     * Gets all supported logging levels.
     *
     * @return array Assoc array with human-readable level names => level codes.
     */
    public static function getLevels()
    {
        return array_flip(static::$levels);
    }

    /**
     * Gets the name of the logging level.
     *
     * @param  int    $level
     * @return string
     */
    public static function getLevelName($level)
    {
        if (!isset(static::$levels[$level])) {
            throw new InvalidArgumentException('Level "'.$level.'" is not defined, use one of: '.implode(', ', array_keys(static::$levels)));
        }

        return static::$levels[$level];
    }

    /**
     * Converts PSR-3 levels to Bee\Log ones if necessary
     *
     * @param string|int Level number (Bee\Log) or name (PSR-3)
     * @return int
     */
    public static function toMonologLevel($level)
    {
        if (is_string($level) && defined(__CLASS__.'::'.strtoupper($level))) {
            return constant(__CLASS__.'::'.strtoupper($level));
        }

        return $level;
    }

    /**
     * Checks whether the Logger has a handler that listens on the given level
     *
     * @param  int     $level
     * @return bool
     */
    public function isHandling($level)
    {
        $record = array(
            'level' => $level,
        );

        foreach ($this->handlers as $handler) {
            if ($handler->isHandling($record)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set a custom exception handler
     *
     * @param  callable $callback
     * @return $this
     */
    public function setExceptionHandler($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Exception handler must be valid callable (callback or object with an __invoke method), '.var_export($callback, true).' given');
        }
        $this->exceptionHandler = $callback;

        return $this;
    }

    /**
     * @return callable
     */
    public function getExceptionHandler()
    {
        return $this->exceptionHandler;
    }

    /**
     * Delegates exception management to the custom exception handler,
     * or throws the exception if no custom handler is set.
     */
    protected function handleException(Exception $e, array $record)
    {
        if (!$this->exceptionHandler) {
            throw $e;
        }

        call_user_func($this->exceptionHandler, $e, $record);
    }

    /**
     * Adds a log record at an arbitrary level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  mixed   $level   The log level
     * @param  string $message The log message
     * @param  array  $context The log context
     * @return bool   Whether the record has been processed
     */
    public function log($level, $message, array $context = array())
    {
        $level = static::toMonologLevel($level);

        return $this->addRecord($level, $message, $context);
    }

    /**
     * Adds a log record at the DEBUG level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     * @return bool   Whether the record has been processed
     */
    public function debug($message, array $context = array())
    {
        return $this->addRecord(static::DEBUG, $message, $context);
    }

    /**
     * Adds a log record at the INFO level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     * @return bool   Whether the record has been processed
     */
    public function info($message, array $context = array())
    {
        return $this->addRecord(static::INFO, $message, $context);
    }

    /**
     * Adds a log record at the NOTICE level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     * @return bool   Whether the record has been processed
     */
    public function notice($message, array $context = array())
    {
        return $this->addRecord(static::NOTICE, $message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     * @return bool   Whether the record has been processed
     */
    public function warn($message, array $context = array())
    {
        return $this->addRecord(static::WARNING, $message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     * @return bool   Whether the record has been processed
     */
    public function warning($message, array $context = array())
    {
        return $this->addRecord(static::WARNING, $message, $context);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     * @return bool   Whether the record has been processed
     */
    public function err($message, array $context = array())
    {
        return $this->addRecord(static::ERROR, $message, $context);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     * @return bool   Whether the record has been processed
     */
    public function error($message, array $context = array())
    {
        return $this->addRecord(static::ERROR, $message, $context);
    }

    /**
     * Adds a log record at the CRITICAL level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     * @return bool   Whether the record has been processed
     */
    public function crit($message, array $context = array())
    {
        return $this->addRecord(static::CRITICAL, $message, $context);
    }

    /**
     * Adds a log record at the CRITICAL level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     * @return bool   Whether the record has been processed
     */
    public function critical($message, array $context = array())
    {
        return $this->addRecord(static::CRITICAL, $message, $context);
    }

    /**
     * Adds a log record at the ALERT level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     * @return bool   Whether the record has been processed
     */
    public function alert($message, array $context = array())
    {
        return $this->addRecord(static::ALERT, $message, $context);
    }

    /**
     * Adds a log record at the EMERGENCY level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     * @return bool   Whether the record has been processed
     */
    public function emerg($message, array $context = array())
    {
        return $this->addRecord(static::EMERGENCY, $message, $context);
    }

    /**
     * Adds a log record at the EMERGENCY level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string $message The log message
     * @param  array  $context The log context
     * @return bool   Whether the record has been processed
     */
    public function emergency($message, array $context = array())
    {
        return $this->addRecord(static::EMERGENCY, $message, $context);
    }

    /**
     * Set the timezone to be used for the timestamp of log records.
     *
     * This is stored globally for all Logger instances
     *
     * @param \DateTimeZone $tz Timezone object
     */
    public static function setTimezone(\DateTimeZone $tz)
    {
        self::$timezone = $tz;
    }
}
