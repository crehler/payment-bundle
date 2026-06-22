<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\DTO\Refund;

/**
 * A single predefined refund reason a provider exposes for selection in the admin.
 *
 * $code is the gateway-specific value sent back to the provider (and on to its SDK);
 * $label is the human-readable text shown in the reason selector.
 */
final readonly class RefundReason
{
    public function __construct(
        public string $code,
        public string $label,
    ) {
    }
}
