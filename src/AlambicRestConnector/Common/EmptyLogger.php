<?php

namespace AlambicRestConnector\Common;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Class EmptyLogger
 */
class EmptyLogger extends AbstractLogger implements LoggerInterface
{
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return null
     */
    public function log($level, $message, array $context = array())
    {
        return;
    }
}
