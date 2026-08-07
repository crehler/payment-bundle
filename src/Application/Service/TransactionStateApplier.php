<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service;

use Crehler\PaymentBundle\Domain\ValueObjects\TransactionStateTransition;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\{OrderTransactionEntity, OrderTransactionStateHandler};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Translates a canonical TransactionStateTransition into the matching Shopware
 * order-transaction state change. Swallows IllegalTransitionException so that
 * duplicate/out-of-order notifications (e.g. a webhook arriving after the state
 * was already set) are idempotent rather than fatal.
 *
 * The gateway operator panel is the source of truth: a gateway-confirmed "paid"
 * must win even when the local transaction is stuck in "failed"/"cancelled" (e.g.
 * after an interrupted flow). A plain paid() would throw IllegalTransition there
 * and the payment would be silently dropped — so we reopen first, then pay.
 *
 * Being the source of truth cuts both ways, which is why both directions are
 * cross-checked against the gateway before a suspicious boundary is crossed
 * (WT-910): paid → cancelled is a legal Shopware transition, so nothing in the
 * state machine stopped a stale signed "expired" from wiping out a confirmed
 * payment. See GatewayStateVerifier for why an inconclusive answer means "trust
 * the notification" going forward and "keep the settled state" going back.
 */
final class TransactionStateApplier
{
    public function __construct(
        private readonly OrderTransactionStateHandler $orderTransactionStateHandler,
        #[Autowire(service: 'order_transaction.repository')]
        private readonly EntityRepository $orderTransactionRepository,
        private readonly GatewayStateVerifier $gatewayStateVerifier,
        private readonly EnhancedLogger $logger,
    ) {
    }

    public function apply(
        TransactionStateTransition $transition,
        string $orderTransactionId,
        Context $context,
    ): void {
        if ($transition === TransactionStateTransition::NONE) {
            return;
        }

        try {
            match ($transition) {
                TransactionStateTransition::PAID => $this->transitionToPaid($orderTransactionId, $context),
                TransactionStateTransition::FAILED,
                TransactionStateTransition::CANCELLED => $this->transitionToUnpaid($transition, $orderTransactionId, $context),
                TransactionStateTransition::REFUNDED => $this->orderTransactionStateHandler->refund(
                    transactionId: $orderTransactionId,
                    context: $context,
                ),
            };
        } catch (IllegalTransitionException $e) {
            $this->logger->info('Skipping illegal order-transaction state transition', [
                'orderTransactionId' => $orderTransactionId,
                'transition' => $transition->name,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Move a transaction to a terminal non-paid state, but undo a payment only when the
     * gateway agrees it failed.
     *
     * paid → failed/cancelled are legal Shopware transitions, so the state machine offers
     * no protection here: a stale or replayed signed notification would silently undo a
     * confirmed payment. On a settled transaction we therefore require the gateway to
     * confirm the failure; anything inconclusive keeps the settled state (WT-910).
     * Non-settled transactions transition as before — nothing is at stake there.
     *
     * The state is read twice on purpose. Shopware serializes the individual transition()
     * call, not the read-decide-write sequence around it, and the gateway round trip in
     * between is a full HTTP request — wide enough for a concurrent CONFIRMED webhook to
     * settle the transaction after our first read. Without the second read that stale read
     * would still wave the undo through: exactly the defect this guard exists for, returning
     * as a race. Re-reading narrows the window to two DB reads with no I/O between them, it
     * does not close it — closing it fully needs a lock spanning the whole sequence.
     */
    private function transitionToUnpaid(
        TransactionStateTransition $transition,
        string $orderTransactionId,
        Context $context,
    ): void {
        $currentState = $this->currentTransactionState($orderTransactionId, $context);
        // Remembered instead of re-asked: reportsFailure() calls the gateway over HTTP.
        // Stays false unless the settled branch below actually asked and got a failure.
        $gatewayConfirmedFailure = false;

        if ($this->gatewayStateVerifier->isSettled($currentState)) {
            $gatewayConfirmedFailure = $this->gatewayStateVerifier->reportsFailure($orderTransactionId, $context);

            if (!$gatewayConfirmedFailure) {
                $this->logger->warning('Refused to undo a settled transaction the gateway does not report as failed', [
                    'orderTransactionId' => $orderTransactionId,
                    'currentState' => $currentState,
                    'transition' => $transition->name,
                ]);

                return;
            }
        }

        $stateBeforeMutation = $this->currentTransactionState($orderTransactionId, $context);

        if (!$gatewayConfirmedFailure && $this->gatewayStateVerifier->isSettled($stateBeforeMutation)) {
            $this->logger->warning('Refused to undo a transaction settled by a concurrent notification', [
                'orderTransactionId' => $orderTransactionId,
                'currentState' => $currentState,
                'stateBeforeMutation' => $stateBeforeMutation,
                'transition' => $transition->name,
            ]);

            return;
        }

        if ($transition === TransactionStateTransition::FAILED) {
            $this->orderTransactionStateHandler->fail(
                transactionId: $orderTransactionId,
                context: $context,
            );

            return;
        }

        $this->orderTransactionStateHandler->cancel(
            transactionId: $orderTransactionId,
            context: $context,
        );
    }

    /**
     * Drive the transaction to "paid". If a direct transition is illegal because the
     * transaction is stuck in failed/cancelled, reopen it first and pay — the gateway
     * confirmation is authoritative. Already-settled states are left untouched.
     */
    private function transitionToPaid(string $orderTransactionId, Context $context): void
    {
        $currentState = $this->currentTransactionState($orderTransactionId, $context);

        if ($this->gatewayStateVerifier->isSettled($currentState)) {
            return; // payment already recognized — a repeated "paid" is a no-op
        }

        // Only a terminal contradiction blocks booking. A status API lagging its own
        // webhook reports "pending", which stays inconclusive and must not block.
        if ($this->gatewayStateVerifier->reportsFailure($orderTransactionId, $context)) {
            $this->logger->warning('Refused to book a payment the gateway reports as failed', [
                'orderTransactionId' => $orderTransactionId,
                'currentState' => $currentState,
            ]);

            return;
        }

        try {
            $this->orderTransactionStateHandler->paid($orderTransactionId, $context);
        } catch (IllegalTransitionException) {
            // failed / cancelled / other non-direct state → reopen, then pay.
            $this->orderTransactionStateHandler->reopen($orderTransactionId, $context);
            $this->orderTransactionStateHandler->paid($orderTransactionId, $context);

            $this->logger->info('Reopened transaction to honor gateway-confirmed payment', [
                'orderTransactionId' => $orderTransactionId,
                'previousState' => $currentState,
            ]);
        }
    }

    private function currentTransactionState(string $orderTransactionId, Context $context): ?string
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('stateMachineState');

        $transaction = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();

        return $transaction instanceof OrderTransactionEntity
            ? $transaction->getStateMachineState()?->getTechnicalName()
            : null;
    }
}
