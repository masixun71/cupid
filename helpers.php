<?php

use Jue\Cupid\Loggers\ILogger;
use Jue\Cupid\Loggers\LoggerManager;

if (!function_exists('logger')) {

    /**
     * 读取logger实例.
     *
     * @return ILogger
     */
    function logger()
    {
        return LoggerManager::getLogger();
    }
}