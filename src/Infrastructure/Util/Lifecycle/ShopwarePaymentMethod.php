<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle;

abstract class ShopwarePaymentMethod
{
    public function __construct(
        public readonly string $handlerIdentifier,
        public readonly int $position,
        public readonly string $technicalName,
        public readonly array $translations = [],
        public readonly bool $afterOrderEnabled = false,
        public readonly ?string $iconName = null,
        public readonly bool $subMethodsEnabled = false,
    ) {
    }
}
