<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\DTO\PaymentRequest;

/**
 * Generic order item DTO for payment requests.
 */
final readonly class OrderItemDTO
{
    public function __construct(
        public string $name,
        public int $quantity,
        public int $unitPrice,
        public ?string $category = null,
    ) {
    }

    public function getTotalPrice(): int
    {
        return $this->unitPrice * $this->quantity;
    }
}
