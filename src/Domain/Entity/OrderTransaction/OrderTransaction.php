<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Entity\OrderTransaction;

use Crehler\PaymentBundle\Domain\Entity\Order\Order;
use Crehler\PaymentBundle\Domain\ValueObjects\Money;

final readonly class OrderTransaction
{
    public function __construct(
        public string $id,
        public PaymentMethod $paymentMethod,
        public PaymentStatus $paymentStatus,
        public Money $totalAmount,
        public Order $order,
    ) {
    }
}
