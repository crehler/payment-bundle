<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Exception;

final class CustomerNotFoundException extends DomainException
{
    public function __construct(string $customerId, string $message = 'Customer not found with given id')
    {
        parent::__construct(message: "$message: $customerId");
    }
}
