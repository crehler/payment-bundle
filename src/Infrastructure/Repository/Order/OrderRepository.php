<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Repository\Order;

use Crehler\PaymentBundle\Domain\Repository\OrderRepositoryInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
    ) {
    }

    public function getOrderWithTransactions(string $orderId, ?Context $context = null): ?OrderEntity
    {
        $context ??= Context::createDefaultContext();

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('primaryOrderTransaction.stateMachineState');
        $criteria->addAssociation('primaryOrderTransaction.paymentMethod');
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('transactions.paymentMethod');

        return $this->orderRepository->search($criteria, $context)->first();
    }

    public function updateCustomFields(string $orderId, array $customFields, ?Context $context = null): void
    {
        if ($customFields === []) {
            return;
        }

        $context ??= Context::createDefaultContext();

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'customFields' => $customFields,
            ],
        ], $context);
    }
}
