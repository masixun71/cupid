<?php

use GuzzleHttp\DefaultHandler;
use Jue\Cupid\Commands\CupidCommand;
use Symfony\Component\Console\Application;
use Yurun\Util\Swoole\Guzzle\SwooleHandler;

require __DIR__.'/vendor/autoload.php';
DefaultHandler::setDefaultHandler(SwooleHandler::class);

$application = new Application();

$application->add(new CupidCommand());
$application->run();
