<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle;

final readonly class ShopwarePaymentMethodDescription
{
    public function __construct(
        public string $language,
        public string $name,
        public ?string $description = null,
    ) {
    }
}
