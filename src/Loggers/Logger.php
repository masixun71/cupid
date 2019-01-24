<?php

namespace Jue\Cupid\Loggers;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\RotatingFileHandler;

class Logger
{
    /**
     * @return ILogger
     */
    public static function getInstance($dir, $extName = '')
    {
        $logger = new \Monolog\Logger('logger');
        $logger = self::configureHandlers($logger, $dir, $extName);

        return new LoggerWriter($logger);
    }


    /**
     * 获取日志文件名称
     * Get log name
     *
     * @param string $dir
     *
     * @return string
     */
    protected static function getFilename($dir)
    {
        return rtrim($dir, '/').'/cupid';
    }

    /**
     * 配置处理器.
     *
     * Set Monolog Config
     * @param \Monolog\Logger $logger
     * @param array  $config
     *
     * @return \Monolog\Logger
     */
    protected static function configureHandlers(\Monolog\Logger $logger, $dir, $extName)
    {
        $logFile = self::getFilename($dir);
        $logFile .= "-$extName" . '.log';

        $normalHandler = new RotatingFileHandler($logFile, 0);
        $normalHandler = self::configureFormatters($normalHandler);
        $logger->pushHandler($normalHandler);

        return $logger;
    }

    /**
     * 配置格式.
     *  Formatter
     *
     * @param HandlerInterface $handler
     *
     * @return HandlerInterface
     */
    protected static function configureFormatters(HandlerInterface $handler)
    {
        $handler->setFormatter(new Formatter(null, 'Y-m-d H:i:s.u'));

        return $handler;
    }

}