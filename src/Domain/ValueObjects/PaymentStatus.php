<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\ValueObjects;

final readonly class PaymentStatus
{
    public function __construct(
        public bool $isPaid,
        public bool $isWaiting,
        public bool $isPaidNotBooked = false,
    ) {
    }

    public static function paid(): self
    {
        return new self(true, false);
    }

    public static function waiting(): self
    {
        return new self(false, true);
    }

    public static function failed(): self
    {
        return new self(false, false);
    }

    /**
     * Confirmed paid at the gateway but not booked in the shop (state mismatch).
     * Surfaced only after the wait window elapses, so the storefront can tell the
     * customer the payment went through and to contact support — instead of just
     * showing a generic timeout.
     */
    public static function paidNotBooked(): self
    {
        return new self(false, false, true);
    }

    /**
     * Terminal failure: the gateway/transaction reached a final non-paid state
     * (cancelled, declined, failed, chargeback). Distinct from "waiting" so the
     * storefront can stop polling and surface an error instead of timing out.
     */
    public function hasFailed(): bool
    {
        return !$this->isPaid && !$this->isWaiting && !$this->isPaidNotBooked;
    }
}
