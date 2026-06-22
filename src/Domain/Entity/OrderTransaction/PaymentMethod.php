<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Entity\OrderTransaction;

final readonly class PaymentMethod
{
    public function __construct(
        public string $id,
        public string $handlerIdentifier,
        public bool $active,
        public string $technicalName,
    ) {
    }

    public function isSame(string $id): bool
    {
        return $this->id === $id;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
