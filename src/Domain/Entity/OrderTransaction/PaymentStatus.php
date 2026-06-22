<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Entity\OrderTransaction;

final readonly class PaymentStatus
{
    public function __construct(
        public string $stateId,
        public string $name,
    ) {
    }
}
