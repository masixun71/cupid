<?php

namespace Jue\Cupid\Loggers;

interface ILogger
{
    /**
     * error级别日志.
     *
     * @param string       $message
     * @param array|object $context
     */
    public function error($message, $context = []);

    /**
     * warning级别日志.
     *
     * @param string       $message
     * @param array|object $context
     */
    public function warning($message, $context = []);

    /**
     * notice级别日志.
     *
     * @param string       $message
     * @param array|object $context
     */
    public function notice($message, $context = []);

    /**
     * info级别日志.
     *
     * @param string       $message
     * @param array|object $context
     */
    public function info($message, $context = []);

    /**
     * debug级别日志.
     *
     * @param string       $message
     * @param array|object $context
     */
    public function debug($message, $context = []);



}