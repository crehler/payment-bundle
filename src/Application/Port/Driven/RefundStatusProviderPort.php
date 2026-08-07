<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Port\Driven;

use Crehler\PaymentBundle\Domain\ValueObjects\RefundStatus;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Driven port a provider implements when its gateway accepts a refund asynchronously
 * and offers no webhook to announce the outcome.
 *
 * Such a refund is created in the shop as "in progress" and, without this port, stays
 * there forever: the gateway finishes the transfer on its side and the shop never finds
 * out (WT-910 — three PayNow refunds stuck in progress, no refund notifications, nothing
 * to close them with, while the docs promise the state advances automatically).
 *
 * The bundle owns the loop: PendingRefundFinalizer collects the shop's in-progress
 * refunds, asks the matching provider about each one and applies the outcome through
 * RefundSynchronizer. A provider only answers the narrow question "what is the state of
 * this refund at the gateway right now".
 *
 * Single-winner, like RefundProviderPort: exactly one provider handles a given payment
 * handler identifier. Distinct from RefundReconciliationProviderPort, which is fan-out
 * and answers the opposite question — which gateway-side refunds the shop does not know
 * about at all.
 */
#[AutoconfigureTag]
interface RefundStatusProviderPort
{
    /**
     * Does this provider own refunds made through the given payment handler?
     */
    public function supports(string $handlerIdentifier): bool;

    /**
     * Current state of an accepted refund at the gateway.
     *
     * Returns null when it cannot be determined (unknown id, API error, unmapped
     * gateway status). Must not throw: the caller processes many refunds in one run
     * and a single unreachable account may not abort the rest. Returning
     * RefundStatus::IN_PROGRESS means "still processing, ask again later".
     *
     * @param string      $gatewayRefundId the gateway's own refund identifier, stored on
     *                                     the Shopware refund as externalReference
     * @param string|null $salesChannelId  channel whose credentials/environment apply
     */
    public function getRefundStatus(
        string $gatewayRefundId,
        ?string $salesChannelId,
        Context $context,
    ): ?RefundStatus;
}
