<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Provider;

/**
 * Provider-supplied raw sub-method, before bundle-side filtering and mapping
 * to the PaymentSubMethod value object. A provider produces these from its
 * gateway API in AbstractPaymentSubMethodProvider::fetchRawSubMethods().
 */
final readonly class RawSubMethod
{
    public function __construct(
        public string $providerId,
        public string $name,
        public string $mediaUrl,
        public ?int $minAmount = null,
        public ?int $maxAmount = null,
    ) {
    }
}
