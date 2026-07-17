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
 * Provider-agnostic view of a single gateway transaction, shown in the admin order
 * "Szczegóły" tab. Each payment provider maps its gateway response onto this shape;
 * fields the gateway does not expose stay null (the UI renders "—"). amount/currency
 * may be enriched from the Shopware order transaction by the controller when the
 * gateway omits them.
 *
 * statusLevel normalises the raw gateway status to a badge level:
 * paid | pending | failed | refunded | unknown.
 */
final readonly class GatewayPaymentDetails implements JsonSerializable
{
    /**
     * @param GatewayRefundSummary[] $refunds
     */
    public function __construct(
        public string $provider,
        public string $gatewayId,
        public string $rawStatus,
        public string $statusLevel,
        public ?float $amount = null,
        public ?string $currency = null,
        public ?string $method = null,
        public ?string $createdAt = null,
        public ?string $title = null,
        public bool $sandbox = false,
        public array $refunds = [],
    ) {
    }

    public function withAmount(?float $amount, ?string $currency): self
    {
        return new self(
            provider: $this->provider,
            gatewayId: $this->gatewayId,
            rawStatus: $this->rawStatus,
            statusLevel: $this->statusLevel,
            amount: $amount,
            currency: $currency,
            method: $this->method,
            createdAt: $this->createdAt,
            title: $this->title,
            sandbox: $this->sandbox,
            refunds: $this->refunds,
        );
    }

    /**
     * @param GatewayRefundSummary[] $refunds
     */
    public function withRefunds(array $refunds): self
    {
        return new self(
            provider: $this->provider,
            gatewayId: $this->gatewayId,
            rawStatus: $this->rawStatus,
            statusLevel: $this->statusLevel,
            amount: $this->amount,
            currency: $this->currency,
            method: $this->method,
            createdAt: $this->createdAt,
            title: $this->title,
            sandbox: $this->sandbox,
            refunds: $refunds,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'provider' => $this->provider,
            'gatewayId' => $this->gatewayId,
            'rawStatus' => $this->rawStatus,
            'statusLevel' => $this->statusLevel,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method,
            'createdAt' => $this->createdAt,
            'title' => $this->title,
            'sandbox' => $this->sandbox,
            'refunds' => $this->refunds,
        ];
    }
}
