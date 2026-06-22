<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Repository\Order;

use Crehler\PaymentBundle\Application\Port\Driven\OrderTransactionRepositoryInterface;
use Crehler\PaymentBundle\Domain\Constant\PaymentCustomFields;
use Crehler\PaymentBundle\Domain\Exception\{OrderTransactionCreationFailedException, OrderTransactionNotFoundException};
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\{OrderTransactionEntity, OrderTransactionStateHandler, OrderTransactionStates};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class OrderTransactionRepository implements OrderTransactionRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'order_transaction.repository')]
        private EntityRepository $orderTransactionRepository,
        #[Autowire(service: 'order.repository')]
        private EntityRepository $orderRepository,
        private InitialStateIdLoader $initialStateIdLoader,
        private OrderTransactionStateHandler $orderTransactionStateHandler,
    ) {
    }

    public function getOrderTransaction(string $orderTransactionId, ?Context $context): ?OrderTransactionEntity
    {
        $context = $context ?? Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('order.lineItems.product');
        $criteria->addAssociation('order.billingAddress.country');
        $criteria->addAssociation('order.addresses.country');
        $criteria->addAssociation('order.language.locale');
        $criteria->addAssociation('order.orderCustomer');
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->addAssociation('paymentMethod');
        $criteria->addAssociation('stateMachineState');
        $criteria->addFilter(new EqualsFilter('id', $orderTransactionId));

        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($orderTransaction === null) {
            throw new OrderTransactionNotFoundException();
        }

        return $orderTransaction;
    }

    /**
     * @throws OrderTransactionNotFoundException
     */
    public function getOrderTransactionWithAssociations(
        string $orderTransactionId,
        array $associations = [],
        ?Context $context = null,
    ): OrderTransactionEntity {
        $context = $context ?? Context::createDefaultContext();

        $criteria = new Criteria();

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        $criteria->addFilter(new EqualsFilter('id', $orderTransactionId));

        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($orderTransaction === null) {
            throw new OrderTransactionNotFoundException();
        }

        return $orderTransaction;
    }

    public function updateGatewayPaymentId(
        string $orderTransactionId,
        string $gatewayPaymentId,
        ?Context $context = null,
    ): void {
        $context = $context ?? Context::createDefaultContext();

        $this->orderTransactionRepository->update([
            [
                'id' => $orderTransactionId,
                'customFields' => [
                    PaymentCustomFields::GATEWAY_PAYMENT_ID => $gatewayPaymentId,
                ],
            ],
        ], $context);
    }

    public function updateReturnUrls(
        string $orderTransactionId,
        ?string $finishUrl,
        ?string $errorUrl,
        ?Context $context = null,
    ): void {
        $context = $context ?? Context::createDefaultContext();

        $this->orderTransactionRepository->update([
            [
                'id' => $orderTransactionId,
                'customFields' => [
                    'crehler_payment_finish_url' => $finishUrl,
                    'crehler_payment_error_url' => $errorUrl,
                ],
            ],
        ], $context);
    }

    public function createRetryTransactionForOrder(string $orderId, ?Context $context = null): string
    {
        $context = $context ?? Context::createDefaultContext();

        $template = $this->getLatestTransactionForOrder($orderId, $context);
        $paymentMethodId = $template->getPaymentMethodId();
        $templateAmount = $template->getAmount();

        $amount = new CalculatedPrice(
            $templateAmount->getUnitPrice(),
            $templateAmount->getTotalPrice(),
            $templateAmount->getCalculatedTaxes(),
            $templateAmount->getTaxRules(),
            $templateAmount->getQuantity(),
        );

        $newTransactionId = Uuid::randomHex();
        $initialStateId = $this->initialStateIdLoader->get(OrderTransactionStates::STATE_MACHINE);

        $payload = [
            'id' => $orderId,
            'primaryOrderTransactionId' => $newTransactionId,
            'transactions' => [
                [
                    'id' => $newTransactionId,
                    'paymentMethodId' => $paymentMethodId,
                    'stateId' => $initialStateId,
                    'amount' => $amount,
                ],
            ],
        ];

        $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $scoped) => $this->orderRepository->update([$payload], $scoped),
        );

        $created = $this->orderTransactionRepository
            ->search(new Criteria([$newTransactionId]), $context)
            ->first();

        if ($created === null) {
            throw new OrderTransactionCreationFailedException($orderId, 'new OrderTransaction is not visible in the DAL after update');
        }

        return $newTransactionId;
    }

    public function markTransactionFailed(string $orderTransactionId, ?Context $context = null): void
    {
        $context = $context ?? Context::createDefaultContext();

        $this->orderTransactionStateHandler->fail($orderTransactionId, $context);
    }

    private function getLatestTransactionForOrder(string $orderId, Context $context): OrderTransactionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        $latest = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($latest === null) {
            throw new OrderTransactionNotFoundException();
        }

        return $latest;
    }
}
