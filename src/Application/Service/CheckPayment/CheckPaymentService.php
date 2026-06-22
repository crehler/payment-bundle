<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service\CheckPayment;

use Crehler\PaymentBundle\Application\DTO\CheckPayment\CheckPaymentStatusResponseDTO;
use Crehler\PaymentBundle\Application\Port\Driven\PaymentGatewayStatusProviderInterface;
use Crehler\PaymentBundle\Application\Service\Security\OrderOwnershipGuard;
use Crehler\PaymentBundle\Domain\Repository\OrderRepositoryInterface;
use Crehler\PaymentBundle\Domain\ValueObjects\PaymentStatus;
use Crehler\PaymentBundle\Infrastructure\StoreApi\CheckPaymentStatus\CheckPaymentStatusRequest;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\{OrderTransactionEntity, OrderTransactionStates};
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

use function array_key_first;
use function usort;

final class CheckPaymentService
{
    /**
     * @param iterable<PaymentGatewayStatusProviderInterface> $gatewayStatusProviders
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderOwnershipGuard $orderOwnershipGuard,
        #[TaggedIterator('crehler.payment.gateway_status_provider')]
        private readonly iterable $gatewayStatusProviders = [],
    ) {
    }

    public function execute(CheckPaymentStatusRequest $request, SalesChannelContext $context): CheckPaymentStatusResponseDTO
    {
        $order = $this->orderRepository->getOrderWithTransactions($request->orderId, $context->getContext());

        // Object-level authorization: the caller may only poll their own order.
        // A missing or foreign order returns the same neutral "failed" status so
        // this endpoint cannot be used as a cross-tenant order-existence oracle.
        if ($order === null || !$this->orderOwnershipGuard->isOrderOwnedByContext($order, $context)) {
            return new CheckPaymentStatusResponseDTO(paymentStatus: PaymentStatus::failed());
        }

        // The order's primary transaction is the active one — retry flows
        // create a new OT and set it as primary. Fallback to createdAt sort
        // for legacy orders that pre-date the primary-OT design.
        $transaction = $order->getPrimaryOrderTransaction() ?? $this->getLatestTransaction($order);

        if ($transaction === null) {
            return new CheckPaymentStatusResponseDTO(paymentStatus: PaymentStatus::failed());
        }

        $gatewayPaymentStatus = $this->getGatewayPaymentStatus($transaction);

        if ($gatewayPaymentStatus !== null) {
            return new CheckPaymentStatusResponseDTO(paymentStatus: $gatewayPaymentStatus);
        }

        $stateName = $transaction->getStateMachineState()?->getTechnicalName();

        $paymentStatus = match ($stateName) {
            OrderTransactionStates::STATE_OPEN,
            OrderTransactionStates::STATE_IN_PROGRESS => PaymentStatus::waiting(),
            OrderTransactionStates::STATE_PAID => PaymentStatus::paid(),
            default => PaymentStatus::failed(),
        };

        return new CheckPaymentStatusResponseDTO(paymentStatus: $paymentStatus);
    }

    private function getLatestTransaction(OrderEntity $order): ?OrderTransactionEntity
    {
        $transactions = $order->getTransactions();

        if ($transactions === null) {
            return null;
        }

        $elements = $transactions->getElements();

        if ($elements === []) {
            return null;
        }

        usort(
            $elements,
            static fn (OrderTransactionEntity $a, OrderTransactionEntity $b): int => ($b->getCreatedAt()?->getTimestamp() ?? 0) <=> ($a->getCreatedAt()?->getTimestamp() ?? 0),
        );

        return $elements[array_key_first($elements)];
    }

    private function getGatewayPaymentStatus(OrderTransactionEntity $transaction): ?PaymentStatus
    {
        foreach ($this->gatewayStatusProviders as $provider) {
            if (!$provider->supports($transaction)) {
                continue;
            }

            $paymentStatus = $provider->getPaymentStatus($transaction);

            if ($paymentStatus !== null) {
                return $paymentStatus;
            }
        }

        return null;
    }
}
