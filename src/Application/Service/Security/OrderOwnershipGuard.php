<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service\Security;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Central object-level authorization check for customer-facing payment routes.
 *
 * Every store-api / storefront endpoint that accepts an order id or transaction
 * id from the request MUST run the relevant entity through this guard before
 * acting on it. The order repositories default to an admin-scoped
 * Context::createDefaultContext() and do NOT filter by owner, so without this
 * check any logged-in or guest session can reference another customer's order
 * (IDOR). Callers should treat a `false` result as "not found" (404 / neutral
 * response) and never reveal whether the foreign id exists.
 *
 * Guests are supported: an active guest checkout has a (guest) customer in the
 * SalesChannelContext, and that customer id is what owns the order.
 */
final class OrderOwnershipGuard
{
    public function isOrderOwnedByContext(OrderEntity $order, SalesChannelContext $context): bool
    {
        if ($order->getSalesChannelId() !== $context->getSalesChannelId()) {
            return false;
        }

        $customer = $context->getCustomer();

        if ($customer === null) {
            return false;
        }

        return $order->getOrderCustomer()?->getCustomerId() === $customer->getId();
    }

    /**
     * Convenience check for transaction-keyed routes. Requires the transaction's
     * `order` (with `order.orderCustomer`) association to be loaded.
     */
    public function isTransactionOwnedByContext(OrderTransactionEntity $transaction, SalesChannelContext $context): bool
    {
        $order = $transaction->getOrder();

        return $order !== null && $this->isOrderOwnedByContext($order, $context);
    }
}
