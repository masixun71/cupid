<?php

namespace Jue\Cupid\Loggers;



class LoggerManager implements ILoggerManagerInterface
{

    private static $logger;


    public static function newLogger($dir, $workerId)
    {
        switch ($workerId) {
            case 0:
                $extName = 'manager-process';
                break;
            case 1:
                $extName = 'callback-process';
                break;
            default:
                $extName = 'worker' . $workerId . '-process';
                break;
        }

        self::$logger = Logger::getInstance($dir, $extName);
    }

    public static function getLogger() {
        return self::$logger;
    }

}