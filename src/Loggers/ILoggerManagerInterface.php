<?php

namespace Jue\Cupid\Loggers;


interface ILoggerManagerInterface
{
    public static function newLogger($dir, $workerId);
}