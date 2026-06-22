<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Port\Driven;

use Crehler\PaymentBundle\Application\DTO\Connection\ConnectionCheckResult;

/**
 * Driven port: a provider plugin implements this to verify its API credentials
 * against the live gateway. Backs the shared admin "test connection" component
 * (one button per credential set), which posts the UNSAVED form values so the
 * operator can validate before saving.
 *
 * Implementations are tagged automatically (see CrehlerPaymentBundle::build) and
 * collected by ConnectionTestController via #[AutowireIterator].
 */
interface GatewayConnectionCheckerInterface
{
    /**
     * Whether this checker handles the given plugin config domain
     * (e.g. "CrehlerTpay.config", "CrehlerPayNowPayment.config").
     */
    public function supports(string $configDomain): bool;

    /**
     * Run a real authenticated probe against the gateway.
     *
     * @param string               $environment    'live' or 'sandbox' — which credential set to test
     * @param array<string, mixed> $config         unsaved form values for the plugin domain (field name => value);
     *                                             empty password fields fall back to the stored config
     * @param string|null          $salesChannelId selected sales channel scope (null = global/default)
     */
    public function check(string $environment, array $config, ?string $salesChannelId): ConnectionCheckResult;
}
