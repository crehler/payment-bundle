<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Exception;

use function sprintf;

final class OrderTransactionCreationFailedException extends DomainException
{
    public function __construct(string $orderId, ?string $reason = null)
    {
        $message = sprintf('Failed to create a new OrderTransaction for order "%s"', $orderId);

        if ($reason !== null) {
            $message .= sprintf(' (%s)', $reason);
        }

        parent::__construct($message);
    }
}
