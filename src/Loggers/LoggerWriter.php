<?php

namespace Jue\Cupid\Loggers;



class LoggerWriter implements ILogger
{
    private $monolog;

    public function __construct(\Monolog\Logger $logger)
    {
        $this->monolog = $logger;
    }

    /**
     *
     * @param string $message
     * @param array|object $context
     *
     * @return bool
     */
    public function error($message, $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     *
     * @param string $message
     * @param array|object $context
     *
     * @return bool
     */
    public function warning($message, $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     *
     * @param string $message
     * @param array|object $context
     *
     * @return bool
     */
    public function notice($message, $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     *
     * @param string $message
     * @param array|object $context
     *
     * @return bool
     */
    public function info($message, $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     *
     * @param string $message
     * @param array|object $context
     *
     * @return bool
     */
    public function debug($message, $context = [])
    {
        return $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     *
     * @param string $level
     * @param string $message
     * @param array|object $context
     *
     * @return bool
     */
    private function writeLog($level, $message, $context)
    {
        return $this->monolog->{$level}($message, $this->formatContext($context));
    }

    /**
     * Format the parameters for the logger.
     *
     * @param mixed $context
     *
     * @return mixed
     */
    protected function formatContext($context)
    {
        if (is_array($context)) {
            return $context;
        } elseif (method_exists($context, 'toArray')) {
            return $context->toArray();
        } elseif ($context instanceof \Exception) {
            $ret = $this->getFullExceptionAsArray($context);
            $ret['exception'] = $context;
            return $ret;
        } elseif (is_object($context)) {
            return get_object_vars($context);
        }

        return (array)$context;
    }

    public function getFullExceptionAsArray(\Exception $e)
    {
        $result = [
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'exception_class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'previous' => $e->getPrevious(),
            'trace' => [],
        ];
        $count = 0;
        foreach ($e->getTrace() as $frame) {
            $args = '';
            if (isset($frame['args'])) {
                $args = [];
                foreach ($frame['args'] as $arg) {
                    if (is_string($arg)) {
                        $args[] = "'" . $arg . "'";
                    } elseif (is_array($arg)) {
                        $args[] = 'Array';
                    } elseif (is_null($arg)) {
                        $args[] = 'NULL';
                    } elseif (is_bool($arg)) {
                        $args[] = ($arg) ? 'true' : 'false';
                    } elseif (is_object($arg)) {
                        $args[] = get_class($arg);
                    } elseif (is_resource($arg)) {
                        $args[] = get_resource_type($arg);
                    } else {
                        $args[] = $arg;
                    }
                }
                $args = implode(', ', $args);
            }
            $result['trace']['#' . $count] = sprintf('%s(%s): %s(%s)',
                isset($frame['file']) ? $frame['file'] : '',
                isset($frame['line']) ? $frame['line'] : '',
                isset($frame['function']) ? $frame['function'] : '',
                $args);
            ++$count;
        }

        return $result;
    }
}