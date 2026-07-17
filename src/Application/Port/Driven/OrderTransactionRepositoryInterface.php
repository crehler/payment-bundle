<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Port\Driven;

use Crehler\PaymentBundle\Domain\Exception\{OrderTransactionCreationFailedException, OrderTransactionNotFoundException};
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface OrderTransactionRepositoryInterface
{
    /**
     * @throws OrderTransactionNotFoundException
     */
    public function getOrderTransaction(string $orderTransactionId, ?Context $context): ?OrderTransactionEntity;

    /**
     * @throws OrderTransactionNotFoundException
     */
    public function getOrderTransactionWithAssociations(
        string $orderTransactionId,
        array $associations = [],
        ?Context $context = null,
    ): OrderTransactionEntity;

    /**
     * Update order transaction custom fields with gateway payment ID.
     */
    public function updateGatewayPaymentId(
        string $orderTransactionId,
        string $gatewayPaymentId,
        ?Context $context = null,
    ): void;

    /**
     * Persist the storefront-supplied finishUrl/errorUrl on the order transaction
     * so the short-URL payment-return endpoint can honor them after the gateway
     * 302-redirects the browser back (the original Shopware JWT does not survive
     * that round-trip — see PaymentReturnController).
     */
    public function updateReturnUrls(
        string $orderTransactionId,
        ?string $finishUrl,
        ?string $errorUrl,
        ?Context $context = null,
    ): void;

    /**
     * Create a new OrderTransaction on an existing order for retry flows
     * (e.g. failed BLIK code, customer wants to try another) and mark it
     * as the order's primary transaction. Copies paymentMethodId and amount
     * from the most recent transaction but starts with a fresh stateId
     * (OPEN) and empty customFields — caller's downstream handler must
     * re-populate gateway IDs.
     *
     * @throws OrderTransactionCreationFailedException when the new transaction
     *                                                 could not be created or read back from the DAL
     *
     * @return string the new order transaction id (hex UUID)
     */
    public function createRetryTransactionForOrder(string $orderId, ?Context $context = null): string;

    /**
     * Move an OrderTransaction to the FAILED state through the official
     * state machine. Used to clean up a retry transaction when the
     * downstream PaymentProcessor refuses to pay it — otherwise the OT
     * remains in OPEN and pollutes /cr/payment/check polling.
     */
    public function markTransactionFailed(string $orderTransactionId, ?Context $context = null): void;

    /**
     * Find an order transaction by the gateway payment id stored on
     * customFields.crehler_payment_gateway_id. Safe without a sales-channel
     * filter as long as the gateway's own transaction id is globally unique.
     */
    public function findByGatewayPaymentId(string $gatewayPaymentId, ?Context $context = null): ?OrderTransactionEntity;
}
