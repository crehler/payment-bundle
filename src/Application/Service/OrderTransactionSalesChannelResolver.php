<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves the sales-channel id for an order transaction.
 *
 * Every gateway provider needs this to instantiate its SDK client with the
 * per-sales-channel credentials (refund providers, notification subscribers,
 * status providers). The lookup was byte-for-byte duplicated across all three
 * provider plugins; it lives here so providers compose it instead of copying it.
 */
final class OrderTransactionSalesChannelResolver
{
    public function __construct(
        #[Autowire(service: 'order_transaction.repository')]
        private readonly EntityRepository $orderTransactionRepository,
    ) {
    }

    public function resolve(string $orderTransactionId, Context $context): ?string
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order');

        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();

        if (!$orderTransaction instanceof OrderTransactionEntity) {
            return null;
        }

        return $orderTransaction->getOrder()?->getSalesChannelId();
    }
}
