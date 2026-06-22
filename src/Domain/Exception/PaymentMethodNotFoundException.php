<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Exception;

final class PaymentMethodNotFoundException extends DomainException
{
    public function __construct(string $message = 'Payment method not found')
    {
        parent::__construct($message);
    }
}
