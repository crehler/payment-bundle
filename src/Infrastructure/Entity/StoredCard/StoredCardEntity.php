<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Entity\StoredCard;

use Shopware\Core\Framework\DataAbstractionLayer\{Entity, EntityIdTrait};

/**
 * Shared persisted card token for all payment providers.
 *
 * Fields are nullable because providers store different subsets: gateways with
 * encrypted card tokens (e.g. Tpay) use token/brand/tail/expiration, while
 * device-fingerprint based gateways (e.g. PayNow) use deviceFingerprint only.
 */
class StoredCardEntity extends Entity
{
    use EntityIdTrait;

    protected string $customerId;
    protected ?string $salesChannelId = null;
    protected ?string $token = null;
    protected ?string $tokenHash = null;
    protected ?string $brand = null;
    protected ?string $tail = null;
    protected ?int $expirationMonth = null;
    protected ?int $expirationYear = null;
    protected ?string $deviceFingerprint = null;

    /**
     * Fingerprint of the encryption key the stored {@see $token} was encrypted with.
     * When it no longer matches the current key (key lost/rotated), the row is
     * pruned instead of kept as undecryptable junk.
     */
    protected ?string $keyFingerprint = null;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function getTokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(?string $tokenHash): void
    {
        $this->tokenHash = $tokenHash;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): void
    {
        $this->brand = $brand;
    }

    public function getTail(): ?string
    {
        return $this->tail;
    }

    public function setTail(?string $tail): void
    {
        $this->tail = $tail;
    }

    public function getExpirationMonth(): ?int
    {
        return $this->expirationMonth;
    }

    public function setExpirationMonth(?int $expirationMonth): void
    {
        $this->expirationMonth = $expirationMonth;
    }

    public function getExpirationYear(): ?int
    {
        return $this->expirationYear;
    }

    public function setExpirationYear(?int $expirationYear): void
    {
        $this->expirationYear = $expirationYear;
    }

    public function getDeviceFingerprint(): ?string
    {
        return $this->deviceFingerprint;
    }

    public function setDeviceFingerprint(?string $deviceFingerprint): void
    {
        $this->deviceFingerprint = $deviceFingerprint;
    }

    public function getKeyFingerprint(): ?string
    {
        return $this->keyFingerprint;
    }

    public function setKeyFingerprint(?string $keyFingerprint): void
    {
        $this->keyFingerprint = $keyFingerprint;
    }
}
