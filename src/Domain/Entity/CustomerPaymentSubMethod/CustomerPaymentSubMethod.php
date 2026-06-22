<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Entity\CustomerPaymentSubMethod;

final readonly class CustomerPaymentSubMethod
{
    public function __construct(
        public string $paymentMethodId,
        public string $subPaymentMethodId,
    ) {
    }
}
