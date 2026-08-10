<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Resolver;

use Crehler\PaymentBundle\Domain\Constant\PaymentCustomFields;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

use function is_string;

/**
 * Answers "which payment method actually handled this order".
 *
 * An order can carry several transactions — retries and payment-method changes each add
 * one — so this needs a policy, not a lucky pick. Two facts shape it: a retry leaves the
 * gateway-id custom field on the SUPERSEDED transaction and makes the fresh one primary,
 * and the loaded collection has no guaranteed order. Taking the first match would
 * therefore return a historical attempt as often as the live one.
 *
 * Order of preference:
 *  1. the primary transaction, when it already reached a gateway — that is the live attempt;
 *  2. otherwise the most recent transaction that reached a gateway, compared by createdAt
 *     rather than by collection position (split-payment safe);
 *  3. otherwise the primary transaction, then the most recent one;
 *  4. null, leaving the default to the caller.
 */
final readonly class OrderPaymentMethodResolver
{
    public function resolve(OrderEntity $order): ?PaymentMethodEntity
    {
        $primary = $order->getPrimaryOrderTransaction();

        if ($primary !== null && $this->hasGatewayPaymentId($primary)) {
            return $primary->getPaymentMethod();
        }

        $transactions = $order->getTransactions() ?? [];

        $transaction = $this->newest($transactions, true)
            ?? $primary
            ?? $this->newest($transactions, false);

        return $transaction?->getPaymentMethod();
    }

    /**
     * Most recent transaction by createdAt, optionally limited to those that reached a
     * gateway. Transactions with no createdAt (never expected — the DAL always sets it)
     * lose against dated ones and otherwise keep iteration order.
     *
     * @param iterable<OrderTransactionEntity> $transactions
     */
    private function newest(iterable $transactions, bool $requireGatewayPaymentId): ?OrderTransactionEntity
    {
        $newest = null;

        foreach ($transactions as $transaction) {
            if ($requireGatewayPaymentId && !$this->hasGatewayPaymentId($transaction)) {
                continue;
            }

            if ($newest === null || $this->isNewer($transaction, $newest)) {
                $newest = $transaction;
            }
        }

        return $newest;
    }

    private function isNewer(OrderTransactionEntity $candidate, OrderTransactionEntity $current): bool
    {
        $candidateCreatedAt = $candidate->getCreatedAt();

        if ($candidateCreatedAt === null) {
            return false;
        }

        $currentCreatedAt = $current->getCreatedAt();

        return $currentCreatedAt === null || $candidateCreatedAt > $currentCreatedAt;
    }

    /**
     * "Reached a gateway" means a real identifier, not just the key being present.
     * persistGatewayPaymentId() already refuses to store an empty one, but the field is
     * writable through the Admin API too — and every other reader (gateway details,
     * admin refund tab) tests the value, not the key.
     */
    private function hasGatewayPaymentId(OrderTransactionEntity $transaction): bool
    {
        $gatewayPaymentId = ($transaction->getCustomFields() ?? [])[PaymentCustomFields::GATEWAY_PAYMENT_ID] ?? null;

        return is_string($gatewayPaymentId) && $gatewayPaymentId !== '';
    }
}
