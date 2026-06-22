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
use Crehler\PaymentBundle\Application\Port\Driving\{OrderServicePort, OrderTransactionServicePort};
use Crehler\PaymentBundle\Domain\Entity\OrderTransaction\OrderTransaction;
use Crehler\PaymentBundle\Domain\Factory\OrderTransactionFactory;
use Shopware\Core\Framework\Context;

final readonly class OrderTransactionService implements OrderTransactionServicePort
{
    public function __construct(
        private OrderTransactionRepositoryInterface $orderTransactionRepository,
        private OrderServicePort $orderServicePort,
        private OrderTransactionFactory $orderTransactionFactory,
    ) {
    }

    public function getOrderTransaction(string $orderTransactionId, ?Context $context = null): OrderTransaction
    {
        $orderTransaction = $this->orderTransactionRepository->getOrderTransaction(
            orderTransactionId: $orderTransactionId,
            context: $context ?? Context::createDefaultContext(),
        );

        $order = $this->orderServicePort->getOrder(
            orderTransactionId: $orderTransaction->getId(),
            context: $context ?? Context::createDefaultContext(),
        );

        return $this->orderTransactionFactory->createOrderTransaction(
            orderTransactionEntity: $orderTransaction,
            order: $order,
        );
    }
}
