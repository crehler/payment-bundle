<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\ValueObjects;

/**
 * Result a provider returns from RefundProviderPort::refund(). The gateway stays the
 * source of truth: $gatewayRefundId is persisted to the refund entity's externalReference.
 */
final readonly class RefundResult
{
    public function __construct(
        public RefundStatus $status,
        public ?string $gatewayRefundId = null,
        public ?string $message = null,
    ) {
    }

    public static function completed(?string $gatewayRefundId = null, ?string $message = null): self
    {
        return new self(RefundStatus::COMPLETED, $gatewayRefundId, $message);
    }

    public static function inProgress(?string $gatewayRefundId = null, ?string $message = null): self
    {
        return new self(RefundStatus::IN_PROGRESS, $gatewayRefundId, $message);
    }

    public static function failed(?string $message = null): self
    {
        return new self(RefundStatus::FAILED, null, $message);
    }

    public function isFailed(): bool
    {
        return $this->status === RefundStatus::FAILED;
    }
}
