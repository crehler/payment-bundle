<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Configuration;

use DateTimeImmutable;
use DateTimeInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

use function hash;
use function substr;

/**
 * Persists the "last synced at" cursor for RefundReconciliationProviderPort
 * implementations, keyed per (provider, gateway account) so a shared Tpay account
 * across sales channels only needs one cursor. Backed by SystemConfigService — the
 * volume here is a handful of scalars, not worth a dedicated table/migration.
 */
final readonly class RefundReconciliationCursorStore
{
    private const KEY_PREFIX = 'CrehlerPaymentBundle.refundReconciliation.';

    public function __construct(
        private SystemConfigService $systemConfigService,
    ) {
    }

    public function getLastSyncedAt(string $providerId, string $accountKey): ?DateTimeImmutable
    {
        $raw = $this->systemConfigService->getString($this->buildKey($providerId, $accountKey));

        if ($raw === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $raw);

        return $parsed !== false ? $parsed : null;
    }

    public function setLastSyncedAt(string $providerId, string $accountKey, DateTimeImmutable $timestamp): void
    {
        $this->systemConfigService->set(
            $this->buildKey($providerId, $accountKey),
            $timestamp->format(DateTimeInterface::ATOM),
        );
    }

    /**
     * accountKey (e.g. "production:abc123") is hashed to a short, key-safe suffix —
     * arbitrary provider credentials should never end up verbatim in a config key.
     */
    private function buildKey(string $providerId, string $accountKey): string
    {
        return self::KEY_PREFIX . $providerId . '.' . substr(hash('sha256', $accountKey), 0, 16) . '.lastSyncedAt';
    }
}
