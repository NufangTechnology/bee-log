<?php
namespace Bee\Logger\Formatter;

use Phalcon\Logger\Formatter;
use Bee\Logger\Processor\ProcessIdProcessor;

class Json extends Formatter
{
    /**
     * @var ProcessIdProcessor[]
     */
    protected $_processors;

    /**
     * Json constructor.
     *
     * @param array $processors
     */
    public function __construct(array $processors = [])
    {
        $this->_processors = $processors;
    }

    /**
     * Adds a processor on to the stack.
     *
     * @param  callable $callback
     * @return Json
     */
    public function pushProcessor($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Processors must be valid callables (callback or object with an __invoke method), '.var_export($callback, true).' given');
        }
        array_unshift($this->_processors, $callback);

        return $this;
    }

    /**
     * Removes the processor on top of the stack and returns it.
     *
     * @return callable
     */
    public function popProcessor()
    {
        if (!$this->_processors) {
            throw new \LogicException('You tried to pop from an empty processor stack.');
        }

        return array_shift($this->_processors);
    }

    /**
     * 格式化日志
     *
     * @param string $message
     * @param int $type
     * @param int $timestamp
     * @param array|null $context
     *
     * @return false|string
     */
    public function format($message, $type, $timestamp, $context = null)
    {
        $record = [
            'level'     => $this->getTypeString($type),
            'message'   => $message,
            'context'   => $context,
            'timestamp' => $timestamp,
            'extra'     => [],
        ];

        foreach ($this->_processors as $processor) {
            $record = call_user_func($processor, $record);
        }

        return json_encode($record, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}