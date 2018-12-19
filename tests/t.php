<?php
require dirname(__DIR__) . '/vendor/autoload.php';

$stream = new \Bee\Logger\Adapter\Stream();

$stream->setFormatter((new \Bee\Logger\Formatter\Json(
    [
        new \Bee\Logger\Processor\MemoryPeakUsageProcessor(),
        new \Bee\Logger\Processor\MemoryUsageProcessor(),
        new \Bee\Logger\Processor\ProcessIdProcessor()
    ]
)));

$stream->debug('我是一个debug');