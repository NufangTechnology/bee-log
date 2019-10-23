<?php

namespace Bee\Logger\Adapter;

use Psr\Log\AbstractLogger;
use Swoole\Coroutine\System;

/**
 * Swoole 日志记录器
 *
 * @package Bee\Logger
 */
class Swoole extends AbstractLogger
{
    /**
     * 目标日志文件
     *
     * @var string
     */
    protected $logFile;

    /**
     * 设置日志文件路径
     *
     * @return void
     */
    public function setLogFile($filePath)
    {
        $this->logFile = $filePath;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function log($level, $message, array $context = array())
    {
        if (!$context) {
            return $this->write($level, $message);
        } else {
            $replace = [];
            foreach ($context as $key => $val) {
                // 检查该值是否可以转换为字符串
                if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                    $replace['{' . $key . '}'] = $val;
                }
            }

            return $this->write($level, strtr($message, $replace));
        }
    }

    /**
     * 将日志写入文本
     *
     * @return void
     */
    protected function write($level, $message)
    {
        $message  = sprintf('%s %s', $level, $message);
        $message .= PHP_EOL;

        return System::writeFile($this->logFile, $message, FILE_APPEND);
    }
}
