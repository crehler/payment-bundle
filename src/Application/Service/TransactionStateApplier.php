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
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\{OrderTransactionEntity, OrderTransactionStateHandler, OrderTransactionStates};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function in_array;

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
 */
final class TransactionStateApplier
{
    // States where payment is already recognized (or moved beyond it): a late/stale
    // gateway "paid" must NOT churn these back through reopen→paid.
    private const ALREADY_SETTLED = [
        OrderTransactionStates::STATE_PAID,
        OrderTransactionStates::STATE_REFUNDED,
        OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
        OrderTransactionStates::STATE_CHARGEBACK,
    ];

    public function __construct(
        private readonly OrderTransactionStateHandler $orderTransactionStateHandler,
        #[Autowire(service: 'order_transaction.repository')]
        private readonly EntityRepository $orderTransactionRepository,
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
                TransactionStateTransition::CANCELLED => $this->orderTransactionStateHandler->cancel(
                    transactionId: $orderTransactionId,
                    context: $context,
                ),
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
     * Drive the transaction to "paid". If a direct transition is illegal because the
     * transaction is stuck in failed/cancelled, reopen it first and pay — the gateway
     * confirmation is authoritative. Already-settled states are left untouched.
     */
    private function transitionToPaid(string $orderTransactionId, Context $context): void
    {
        $currentState = $this->currentTransactionState($orderTransactionId, $context);

        if (in_array($currentState, self::ALREADY_SETTLED, true)) {
            return; // payment already recognized — a repeated "paid" is a no-op
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
