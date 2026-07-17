<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Port\Driven;

use Crehler\PaymentBundle\Application\DTO\RefundReconciliation\RefundReconciliationReport;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Driven port a provider implements to detect refunds created directly at the
 * gateway (e.g. manually in the provider's own panel) that never reached Shopware
 * through a webhook, and to record them via RefundSynchronizer::syncExternalRefund().
 *
 * Unlike RefundProviderPort (single-winner, one provider handles a given payment
 * handler identifier), this port is fan-out: RefundReconciliationTaskHandler queries
 * every registered implementation on each scheduled run, so there is no supports().
 * Providers register without DI wiring — autoconfigure tags by interface and the
 * handler collects all via AutowireIterator.
 */
#[AutoconfigureTag]
interface RefundReconciliationProviderPort
{
    /**
     * Stable provider identifier (e.g. "tpay") — used for logging and to namespace
     * the reconciliation cursor per provider/account.
     */
    public function getProviderId(): string;

    /**
     * Query the gateway for refunds since the last successful run and synchronize
     * the ones Shopware does not know about yet. Must not throw for a single
     * account/page failure — isolate and report errors in the returned report so
     * one broken account does not stop reconciliation for the rest.
     */
    public function reconcile(Context $context): RefundReconciliationReport;
}
