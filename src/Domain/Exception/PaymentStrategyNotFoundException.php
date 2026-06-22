<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Exception;

final class PaymentStrategyNotFoundException extends DomainException
{
    public function __construct(string $handlerIdentifier, $message = 'Payment submethod not found for this payment method')
    {
        parent::__construct("$message: $handlerIdentifier");
    }
}
