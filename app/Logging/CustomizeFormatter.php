<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;

class CustomizeFormatter
{
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $formatter = new LineFormatter(
                null,
                null,
                true,
                true
            );

            // 🔥 THIS disables stack traces
            $formatter->includeStacktraces(false);

            $handler->setFormatter($formatter);
        }
    }
}