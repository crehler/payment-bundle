<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Shared;

use Psr\Log\{AbstractLogger, LoggerInterface};
use Stringable;
use Throwable;

use function get_class;

class EnhancedLogger extends AbstractLogger
{
    public function __construct(
        private readonly LoggerInterface $decoratedLogger,
    ) {
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $exception = $context['exception'];

            $context['trace'] = $exception->getTraceAsString();
            $context['exceptionClass'] = get_class($exception);
            $context['file'] = $exception->getFile();
            $context['line'] = $exception->getLine();
        }

        $this->decoratedLogger->error(message: $message, context: $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $exception = $context['exception'];

            $context['trace'] = $exception->getTraceAsString();
            $context['exceptionClass'] = get_class($exception);
            $context['file'] = $exception->getFile();
            $context['line'] = $exception->getLine();
        }

        $this->decoratedLogger->critical(message: $message, context: $context);
    }

    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        if ($level === 'error') {
            $this->error($message, $context);

            return;
        }

        if ($level === 'critical') {
            $this->critical($message, $context);

            return;
        }

        $this->decoratedLogger->log($level, $message, $context);
    }
}
