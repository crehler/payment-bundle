<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Client;

use Crehler\PaymentBundle\Domain\Exception\GatewayConfigurationException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

use function trim;

/**
 * Base for provider SDK client factories. Centralizes the repeated pattern of
 * reading per-sales-channel credentials from the system config, toggling
 * sandbox/production and failing fast when a required value is missing.
 *
 * Subclasses keep their own config-key constants and the actual SDK
 * instantiation; they call requireString()/isSandbox() for the plumbing.
 */
abstract class AbstractGatewayClientFactory
{
    public function __construct(
        protected readonly SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * Read a required string config value, trimming it. Throws when empty.
     *
     * @throws GatewayConfigurationException
     */
    protected function requireString(string $configKey, ?string $salesChannelId = null): string
    {
        $value = trim($this->systemConfigService->getString($configKey, $salesChannelId));

        if ($value === '') {
            throw GatewayConfigurationException::missingValue($configKey);
        }

        return $value;
    }

    /**
     * Read an optional string config value (empty string when unset).
     */
    protected function optionalString(string $configKey, ?string $salesChannelId = null): string
    {
        return trim($this->systemConfigService->getString($configKey, $salesChannelId));
    }

    /**
     * Whether the gateway should run in sandbox mode for this sales channel.
     */
    protected function isSandbox(string $configKey, ?string $salesChannelId = null): bool
    {
        return $this->systemConfigService->getBool($configKey, $salesChannelId);
    }

    /**
     * Pick the production or sandbox config key for the active environment. Keeps the
     * "two credential sets selected by a flag" model (Model B) out of every subclass:
     * read the flag once with isSandbox(), then requireString()/optionalString() the
     * key this returns.
     */
    protected function selectKey(bool $sandbox, string $productionKey, string $sandboxKey): string
    {
        return $sandbox ? $sandboxKey : $productionKey;
    }
}
