<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Port\Driven;

use Crehler\PaymentBundle\Application\DTO\Refund\RefundCommand;
use Crehler\PaymentBundle\Domain\ValueObjects\RefundResult;
use Shopware\Core\Framework\Context;

/**
 * Driven port a provider implements once per gateway to execute refunds.
 *
 * The bundle's AbstractPaymentMethodHandler owns the native Shopware refund flow
 * (entity loading, state transitions, transaction state) and delegates only the
 * gateway SDK call to this port. The handler selects the matching port by handler
 * identifier (see supports()), so providers register it without DI wiring —
 * autoconfigure tags it by interface and the handler collects all via AutowireIterator.
 */
interface RefundProviderPort
{
    /**
     * Whether this port handles refunds for the given payment handler identifier
     * (the fully-qualified handler class resolved by Shopware from payment_method_id).
     */
    public function supports(string $handlerIdentifier): bool;

    /**
     * Execute the refund against the gateway and map the response to a RefundResult.
     *
     * Implementations should throw on a hard failure (the native PaymentRefundProcessor
     * transitions the refund to "failed" and rethrows) or return RefundResult::failed()
     * for a soft, reportable failure.
     */
    public function refund(RefundCommand $command, Context $context): RefundResult;
}
