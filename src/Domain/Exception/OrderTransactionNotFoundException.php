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

final class OrderTransactionNotFoundException extends DomainException
{
    public function __construct(?string $transactionId = null)
    {
        parent::__construct(sprintf('Order transaction with ID "%s" not found', $transactionId));
    }
}
