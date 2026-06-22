<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service;

use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\{OrderTransactionEntity, OrderTransactionStateHandler, OrderTransactionStates};
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function in_array;
use function round;

/**
 * Reflects the refunds recorded against a capture onto the parent order-transaction
 * state — the single source of truth for "is this payment refunded?".
 *
 * Shopware's native PaymentRefundProcessor only drives the refund's own state machine;
 * it never touches the order transaction. So without this the order would stay "Paid"
 * even after a full refund. Both refund paths delegate here:
 *   - in-shop refunds (AbstractPaymentMethodHandler::refund)
 *   - external/gateway refunds replayed from webhooks (RefundSynchronizer)
 *
 * A refund counts as soon as the gateway ACCEPTS it (in_progress), not only once it is
 * completed — gateways such as PayU settle refunds asynchronously, and the order
 * transaction has no intermediate "refund pending" state. Acceptance is therefore
 * treated as committal: the core state machine offers no path back from refunded* to
 * paid, so a rare post-acceptance gateway failure is left for the operator to resolve
 * manually (consistent with the gateway-as-source-of-truth model).
 *
 * Idempotent: re-running for the same set of refunds is a no-op, and illegal
 * transitions (e.g. re-entering refunded_partially) are swallowed.
 */
final class RefundStateReflector
{
    // Refund states that represent money committed to leave the merchant.
    private const ACCEPTED_REFUND_STATES = [
        OrderTransactionCaptureRefundStates::STATE_IN_PROGRESS,
        OrderTransactionCaptureRefundStates::STATE_COMPLETED,
    ];

    public function __construct(
        #[Autowire(service: 'order_transaction_capture.repository')]
        private readonly EntityRepository $captureRepository,
        #[Autowire(service: 'order_transaction.repository')]
        private readonly EntityRepository $orderTransactionRepository,
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly EnhancedLogger $logger,
    ) {
    }

    /**
     * Recompute and apply the order-transaction refund state from the (freshly loaded)
     * capture. Reads from the database so the most recent refund state transitions are
     * already visible.
     */
    public function reflect(string $orderTransactionId, string $captureId, Context $context): void
    {
        $capture = $this->loadCapture($captureId, $context);
        if ($capture === null) {
            return;
        }

        $captureMinor = $this->toMinor($capture->getAmount()->getTotalPrice());
        $acceptedMinor = $this->acceptedRefundMinor($capture);

        if ($acceptedMinor <= 0) {
            // No accepted refunds (only failed/cancelled/open) — nothing to reflect. We never
            // downgrade a refunded* transaction: the core state machine has no reverse path.
            return;
        }

        $target = $acceptedMinor >= $captureMinor
            ? OrderTransactionStates::STATE_REFUNDED
            : OrderTransactionStates::STATE_PARTIALLY_REFUNDED;

        $current = $this->currentTransactionState($orderTransactionId, $context);

        // Already there (or already fully refunded) → idempotent no-op. Guards against the
        // missing refunded_partially → refunded_partially self-transition.
        if ($current === $target || $current === OrderTransactionStates::STATE_REFUNDED) {
            return;
        }

        try {
            if ($target === OrderTransactionStates::STATE_REFUNDED) {
                $this->transactionStateHandler->refund($orderTransactionId, $context);
            } else {
                $this->transactionStateHandler->refundPartially($orderTransactionId, $context);
            }

            $this->logger->info('Reflected refund onto order-transaction state', [
                'orderTransactionId' => $orderTransactionId,
                'from' => $current,
                'to' => $target,
                'acceptedMinor' => $acceptedMinor,
                'captureMinor' => $captureMinor,
            ]);
        } catch (IllegalTransitionException $e) {
            $this->logger->info('Skipping illegal order-transaction refund transition', [
                'orderTransactionId' => $orderTransactionId,
                'from' => $current,
                'to' => $target,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function loadCapture(string $captureId, Context $context): ?OrderTransactionCaptureEntity
    {
        $criteria = new Criteria([$captureId]);
        $criteria->addAssociation('refunds.stateMachineState');

        $capture = $this->captureRepository->search($criteria, $context)->getEntities()->first();

        return $capture instanceof OrderTransactionCaptureEntity ? $capture : null;
    }

    private function acceptedRefundMinor(OrderTransactionCaptureEntity $capture): int
    {
        $sum = 0;

        foreach ($capture->getRefunds() ?? [] as $refund) {
            $state = $refund->getStateMachineState()?->getTechnicalName();

            if (in_array($state, self::ACCEPTED_REFUND_STATES, true)) {
                $sum += $this->toMinor($refund->getAmount()->getTotalPrice());
            }
        }

        return $sum;
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

    private function toMinor(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
