<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Storefront\ChangePaymentMethod;

use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\{OrderTransactionStateHandler, OrderTransactionStates};
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

use function in_array;

/**
 * Guarded "change payment method / retry" redirect.
 *
 * When a customer is sent to an external gateway (or is mid-BLIK), the order
 * transaction is left in `in_progress`. Shopware's edit-order page then refuses to
 * change the payment method, because `in_progress` is not in
 * OrderService::ALLOWED_TRANSACTION_STATES — so the retry buttons would dead-end on
 * "this order is already being processed".
 *
 * This action reopens such an abandoned attempt and forwards to edit-order. It is the
 * only entry point that mutates the transaction state for a retry, so it is locked
 * down rather than exposing a raw "set status by order id" endpoint:
 *  - login required (guests allowed, like edit-order itself);
 *  - the order must belong to the logged-in customer (else 404, no existence leak);
 *  - the order must be in this sales channel;
 *  - ONLY an `in_progress` transaction is touched — paid / refunded / authorized states
 *    are never reopened; already-editable states are passed straight through.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
final class ChangePaymentMethodController extends StorefrontController
{
    /**
     * Transaction states from which Shopware already allows changing the payment method
     * (mirror of OrderService::ALLOWED_TRANSACTION_STATES). In these we hand straight
     * over to edit-order without touching the state.
     */
    private const EDITABLE_STATES = [
        OrderTransactionStates::STATE_OPEN,
        OrderTransactionStates::STATE_CANCELLED,
        OrderTransactionStates::STATE_REMINDED,
        OrderTransactionStates::STATE_FAILED,
        OrderTransactionStates::STATE_CHARGEBACK,
        OrderTransactionStates::STATE_UNCONFIRMED,
    ];

    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly EnhancedLogger $logger,
    ) {
    }

    #[Route(
        path: '/account/order/cr-change-payment/{orderId}',
        name: 'frontend.cr.payment.change-method',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        requirements: ['orderId' => '[0-9a-fA-F]{32}'],
        methods: ['GET'],
    )]
    public function changePaymentMethod(string $orderId, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            // _loginRequired already guarantees this; defensive fallback.
            return $this->redirectToRoute('frontend.account.login');
        }

        $order = $this->loadOrder($orderId, $context);

        // Ownership + sales-channel guard. Respond 404 (not 403) so we don't reveal
        // whether someone else's order id exists.
        if ($order === null || $order->getOrderCustomer()?->getCustomerId() !== $customer->getId()) {
            $this->logger->warning('Change-payment denied: order missing or not owned by customer', [
                'orderId' => $orderId,
                'customerId' => $customer->getId(),
            ]);

            throw new NotFoundHttpException();
        }

        $transaction = $order->getPrimaryOrderTransaction() ?? $order->getTransactions()?->last();
        $state = $transaction?->getStateMachineState()?->getTechnicalName();

        // Already editable → straight to edit-order, no state change.
        if ($transaction === null || $state === null || in_array($state, self::EDITABLE_STATES, true)) {
            return $this->redirectToEditOrder($orderId);
        }

        // Only an abandoned in-progress attempt is reopened. Terminal / money-bearing
        // states (paid, refunded, authorized, …) are off-limits.
        if ($state !== OrderTransactionStates::STATE_IN_PROGRESS) {
            $this->addFlash(self::DANGER, $this->trans('payment.changeMethod.notEditable'));

            return $this->redirectToRoute('frontend.account.order.page');
        }

        $this->moveToEditableState($transaction->getId(), $context);

        return $this->redirectToEditOrder($orderId);
    }

    private function loadOrder(string $orderId, SalesChannelContext $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()));
        $criteria->getAssociation('transactions')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        return $this->orderRepository->search($criteria, $context->getContext())->first();
    }

    /**
     * Move the abandoned in-progress transaction to a state from which the payment
     * method is changeable. Cancel is the right semantics for an abandoned attempt;
     * reopen is a fallback should a gateway expose a different state machine path.
     */
    private function moveToEditableState(string $transactionId, SalesChannelContext $context): void
    {
        $ctx = $context->getContext();

        try {
            $this->transactionStateHandler->cancel($transactionId, $ctx);
        } catch (IllegalTransitionException) {
            try {
                $this->transactionStateHandler->reopen($transactionId, $ctx);
            } catch (IllegalTransitionException $e) {
                $this->logger->error('Could not move transaction to an editable state', [
                    'transactionId' => $transactionId,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    private function redirectToEditOrder(string $orderId): Response
    {
        return $this->redirectToRoute('frontend.account.edit-order.page', ['orderId' => $orderId]);
    }
}
