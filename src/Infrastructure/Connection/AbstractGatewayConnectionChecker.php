<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Connection;

use Crehler\PaymentBundle\Application\Port\Driven\GatewayConnectionCheckerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

use function trim;

/**
 * Shared base for provider connection checkers. Handles domain matching and the
 * "use the value from the form, fall back to the stored one" resolution so each
 * provider only implements the actual gateway probe in check().
 *
 * The fallback matters for password fields the operator didn't re-type: the admin
 * may post them empty, in which case the saved credential is used.
 */
abstract class AbstractGatewayConnectionChecker implements GatewayConnectionCheckerInterface
{
    public function __construct(
        protected readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function supports(string $configDomain): bool
    {
        return $configDomain === $this->configDomain();
    }

    /**
     * Full config domain this checker handles, e.g. "CrehlerPayNowPayment.config".
     */
    abstract protected function configDomain(): string;

    /**
     * Resolve a single credential: the posted (unsaved) value when present, otherwise
     * the value stored in system config for this sales channel.
     *
     * @param array<string, mixed> $config posted form slice (short field names)
     */
    protected function resolveValue(array $config, string $shortKey, ?string $salesChannelId): string
    {
        $posted = trim((string) ($config[$shortKey] ?? ''));

        if ($posted !== '') {
            return $posted;
        }

        return trim($this->systemConfigService->getString(
            $this->configDomain() . '.' . $shortKey,
            $salesChannelId,
        ));
    }
}
