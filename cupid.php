<?php

use Jue\Cupid\Commands\CupidCommand;
use Symfony\Component\Console\Application;

require __DIR__.'/vendor/autoload.php';

$application = new Application();

$application->add(new CupidCommand());
$application->run();
