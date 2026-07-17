<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\DTO\GatewayDetails;

use JsonSerializable;

/**
 * One refund as reported live by the gateway (not the Shopware refund entity) — shown
 * in the admin order "Szczegóły" tab so an operator sees the gateway's own view of
 * refund status without waiting for the hourly reconciliation task or a webhook.
 */
final readonly class GatewayRefundSummary implements JsonSerializable
{
    public function __construct(
        public ?string $gatewayRefundId,
        public float $amount,
        public string $rawStatus,
        public string $statusLevel,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'gatewayRefundId' => $this->gatewayRefundId,
            'amount' => $this->amount,
            'rawStatus' => $this->rawStatus,
            'statusLevel' => $this->statusLevel,
        ];
    }
}
