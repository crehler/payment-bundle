<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\DTO\Refund;

use InvalidArgumentException;

/**
 * A single line-item position included in a partial refund.
 *
 * $amount is in minor units (e.g. grosze) to keep currency handling integer-safe
 * across gateways; providers convert to the SDK's expected unit.
 */
final readonly class RefundPositionCommand
{
    public function __construct(
        public string $orderLineItemId,
        public int $quantity,
        public int $amount,
    ) {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Refund position quantity must be greater than zero.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Refund position amount must be a positive minor-unit value.');
        }
    }
}
