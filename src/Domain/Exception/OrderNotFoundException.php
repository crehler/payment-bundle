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

/**
 * Domain exception for order not found
 * Must extend base domain exception class, not framework exceptions
 */
final class OrderNotFoundException extends DomainException
{
    public function __construct(?string $orderId = null)
    {
        parent::__construct(sprintf('Order with ID "%s" not found', $orderId));
    }
}
