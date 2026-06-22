<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Factory;

use Crehler\PaymentBundle\Domain\Entity\Order\Order;
use Crehler\PaymentBundle\Domain\Entity\OrderTransaction\{OrderTransaction, PaymentMethod, PaymentStatus};
use Crehler\PaymentBundle\Domain\ValueObjects\Money;
use Crehler\PaymentBundle\Shared\AmountFormat;
use InvalidArgumentException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

final readonly class OrderTransactionFactory
{
    public function __construct(
        private AmountFormat $amountFormat,
    ) {
    }

    /**
     * Create an OrderTransaction entity from Shopware's OrderTransactionEntity
     *
     * @param OrderTransactionEntity $orderTransactionEntity The Shopware order transaction entity
     *
     * @return OrderTransaction The domain OrderTransaction entity
     */
    public function createOrderTransaction(OrderTransactionEntity $orderTransactionEntity, Order $order): OrderTransaction
    {
        $orderEntity = $orderTransactionEntity->getOrder();
        $paymentMethodEntity = $orderTransactionEntity->getPaymentMethod();

        if (!$order->isTheSame($orderEntity->getId())) {
            throw new InvalidArgumentException('OrderTransaction does not belong to the given order');
        }

        $paymentMethod = new PaymentMethod(
            id: $paymentMethodEntity->getId(),
            handlerIdentifier: $paymentMethodEntity->getHandlerIdentifier(),
            active: $paymentMethodEntity->getActive(),
            technicalName: $paymentMethodEntity->getTechnicalName(),
        );

        $stateMachineState = $orderTransactionEntity->getStateMachineState();
        $paymentStatus = new PaymentStatus(
            stateId: $stateMachineState->getId(),
            name: $stateMachineState->getTechnicalName()
        );

        // Use the transaction's own amount (not the order total) so hybrid
        // payments like Trade Credit + Tpay charge only the remaining balance.
        $transactionAmount = $orderTransactionEntity->getAmount()?->getTotalPrice() ?? 0.0;

        return new OrderTransaction(
            id: $orderTransactionEntity->getId(),
            paymentMethod: $paymentMethod,
            paymentStatus: $paymentStatus,
            totalAmount: new Money(
                amount: $this->amountFormat->floatToInt((float) $transactionAmount),
                currency: $order->currencyCode,
            ),
            order: $order
        );
    }
}
