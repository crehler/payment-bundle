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
use Crehler\PaymentBundle\Application\Port\Driving\OrderServicePort;
use Crehler\PaymentBundle\Domain\Entity\Order\Order;
use Crehler\PaymentBundle\Domain\Exception\{OrderNotFoundException, OrderTransactionNotFoundException};
use Crehler\PaymentBundle\Domain\Factory\OrderFactory;
use Shopware\Core\Framework\Context;

final readonly class OrderService implements OrderServicePort
{
    public function __construct(
        private OrderTransactionRepositoryInterface $orderTransactionRepository,
        private OrderFactory $orderFactory,
    ) {
    }

    /**
     * @throws OrderNotFoundException
     * @throws OrderTransactionNotFoundException
     */
    public function getOrder(string $orderTransactionId, ?Context $context = null): Order
    {
        $orderTransaction = $this->orderTransactionRepository->getOrderTransaction(
            orderTransactionId: $orderTransactionId,
            context: $context ?? Context::createDefaultContext(),
        );

        if (!$orderTransaction) {
            throw new OrderTransactionNotFoundException();
        }

        $order = $orderTransaction->getOrder();

        if (!$order) {
            throw new OrderNotFoundException();
        }

        return $this->orderFactory->createOrder(orderEntity: $order);
    }
}
