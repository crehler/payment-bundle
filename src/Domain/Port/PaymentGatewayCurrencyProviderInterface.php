<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Port;

interface PaymentGatewayCurrencyProviderInterface
{
    /**
     * Get the unique identifier for this payment gateway
     */
    public function getGatewayIdentifier(): string;

    /**
     * Get the rule ID for this payment gateway's currency rule
     */
    public function getRuleId(): string;

    /**
     * Get the ISO codes of currencies supported by this payment gateway
     *
     * @return array<string> Array of currency ISO codes (e.g. ['PLN', 'EUR', 'USD'])
     */
    public function getSupportedCurrencyIsoCodes(): array;

    /**
     * Get translations for the rule name and description
     *
     * @return array<string, array<string, string>> Array of translations by locale
     */
    public function getTranslations(): array;
}
