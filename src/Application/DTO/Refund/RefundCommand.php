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
 * Gateway-agnostic refund request handed to a RefundProviderPort.
 *
 * $amount is in minor units (e.g. grosze) — providers convert to their SDK's unit.
 * $positions is non-empty only for line-item refunds; amount-only refunds leave it empty.
 * $refundId is the Shopware refund entity id — a stable, per-refund token a provider can
 * reuse as its gateway idempotency key (unique per refund, identical across retries).
 *
 * $reason is the operator's free-text note (stored on the Shopware refund entity).
 * $reasonCode is the predefined reason chosen from the provider's RefundReasonProviderInterface
 * list — the gateway-specific value (e.g. PayNow "RMA"); null when the provider offers none.
 */
final readonly class RefundCommand
{
    /**
     * @param RefundPositionCommand[] $positions
     */
    public function __construct(
        public string $orderTransactionId,
        public string $gatewayPaymentId,
        public int $amount,
        public string $currencyIso,
        public ?string $reason = null,
        public array $positions = [],
        public ?string $refundId = null,
        public ?string $reasonCode = null,
    ) {
        // Fail fast on invalid invariants instead of letting a bad payload reach the gateway.
        if ($amount <= 0) {
            throw new InvalidArgumentException('Refund amount must be a positive minor-unit value.');
        }

        foreach ($positions as $position) {
            if (!$position instanceof RefundPositionCommand) {
                throw new InvalidArgumentException('Refund positions must be RefundPositionCommand instances.');
            }
        }
    }
}
