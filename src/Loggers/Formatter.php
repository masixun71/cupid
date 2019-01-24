<?php

namespace Jue\Cupid\Loggers;


use Monolog\Formatter\LineFormatter;

class Formatter extends LineFormatter
{
    private $colors = [
        \Monolog\Logger::DEBUG => "\033[37m%s\033[0m",
        \Monolog\Logger::INFO => "\033[32m%s\033[0m",
        \Monolog\Logger::NOTICE => "\033[36m%s\033[0m",
        \Monolog\Logger::WARNING => "\033[33m%s\033[0m",
        \Monolog\Logger::ERROR => "\033[31m%s\033[0m",
        \Monolog\Logger::CRITICAL => "\033[31m%s\033[0m",
        \Monolog\Logger::ALERT => "\033[101;37m%s\033[0m",
        \Monolog\Logger::EMERGENCY => "\033[37;101m%s\033[0m",
    ];

    /**
     * Formats a log record.
     *
     * @param array $record A record to format
     *
     * @return mixed The formatted record
     */
    public function format(array $record)
    {
        $record['level_name'] = sprintf($this->colors[$record['level']], $record['level_name']);

        return parent::format($record);
    }
}