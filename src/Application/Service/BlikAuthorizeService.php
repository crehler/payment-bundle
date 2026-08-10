<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service;

use Crehler\PaymentBundle\Application\Port\Driven\OrderTransactionRepositoryInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;

final readonly class BlikAuthorizeService
{
    public function __construct(
        private OrderTransactionRepositoryInterface $orderTransactionRepository,
    ) {
    }

    public function execute(string $orderTransactionId, ?Context $context = null): ?OrderTransactionEntity
    {
        return $this->orderTransactionRepository->getOrderTransactionWithAssociations(
            orderTransactionId: $orderTransactionId,
            // paymentMethod: the authorize page reads the provider's shared config
            // (waiting window) off the handler identifier.
            associations: ['order', 'order.orderCustomer', 'paymentMethod'],
            context: $context
        );
    }
}
