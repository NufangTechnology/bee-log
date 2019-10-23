<?php

require __DIR__ . '/../vendor/autoload.php';

use Bee\Logger\Adapter\Swoole;

go(function () {

    $logger = new Swoole;
    $logger->setLogFile(__DIR__ . '/accesss.log');
    $logger->info('Test');

    # INFO 7sd3j23h8f2h32h233f "GET /dev/stamp/getStamp/0/" 200 
    # INFO time[2019-10-24 10:23:00] id[7sd3j23h8f2h32h233f] method[POST] url[/dev/stamp/getStamp/0/] code[200] total[50] resize[301]
});
