<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service;

use Crehler\PaymentBundle\Domain\Constant\PaymentCustomFields;
use Crehler\PaymentBundle\Domain\ValueObjects\{RefundOrigin, RefundStatus};
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\{OrderTransactionCaptureRefundEntity, OrderTransactionCaptureRefundStateHandler, OrderTransactionCaptureRefundStates};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Records refunds executed outside the shop (e.g. a Tpay CHARGEBACK done in the
 * gateway panel) as native refund entities, so they appear in the "Payment refunds"
 * module just like in-shop refunds. Idempotent by gateway refund id.
 *
 * Refunds created here are written directly in their final state (no state-machine
 * transition / no user), which is exactly how the UI distinguishes them as
 * "gateway/automatic" rather than operator-initiated.
 */
final class RefundSynchronizer
{
    public function __construct(
        private readonly CaptureManager $captureManager,
        #[Autowire(service: 'order_transaction_capture.repository')]
        private readonly EntityRepository $captureRepository,
        #[Autowire(service: 'order_transaction_capture_refund.repository')]
        private readonly EntityRepository $refundRepository,
        #[Autowire(service: 'state_machine_state.repository')]
        private readonly EntityRepository $stateMachineStateRepository,
        private readonly OrderTransactionCaptureRefundStateHandler $refundStateHandler,
        private readonly RefundStateReflector $refundStateReflector,
        private readonly EnhancedLogger $logger,
    ) {
    }

    /**
     * @param int|null $amountMinor refunded amount in minor units; null means the full capture amount
     *
     * @return string|null the refund id, or null if it could not be synchronized
     */
    public function syncExternalRefund(
        string $orderTransactionId,
        ?int $amountMinor,
        ?string $gatewayRefundId,
        RefundStatus $status,
        Context $context,
    ): ?string {
        // null amountMinor is the "full refund" sentinel (resolved to the capture amount
        // below); an explicit non-positive amount is invalid and must not create a record.
        if ($amountMinor !== null && $amountMinor <= 0) {
            $this->logger->warning('Cannot sync external refund: non-positive amount', [
                'orderTransactionId' => $orderTransactionId,
                'amountMinor' => $amountMinor,
            ]);

            return null;
        }

        $captureId = $this->captureManager->ensureCapture($orderTransactionId, $context);
        if ($captureId === null) {
            $this->logger->warning('Cannot sync external refund: no capture for transaction', [
                'orderTransactionId' => $orderTransactionId,
            ]);

            return null;
        }

        $capture = $this->loadCapture($captureId, $context);
        if ($capture === null) {
            return null;
        }

        if ($gatewayRefundId !== null && $gatewayRefundId !== '') {
            $existing = $this->findRefundByExternalReference($capture, $gatewayRefundId);
            if ($existing !== null) {
                // An in-shop refund already exists for this gateway id (created PENDING by
                // the provider). The async webhook finalizes it in place instead of creating
                // a duplicate; for an already-final refund this is an idempotent no-op.
                $this->finalizeExistingRefund($existing, $orderTransactionId, $capture, $status, $context);

                return $existing->getId();
            }
        }

        $captureAmount = $capture->getAmount();
        $amount = $amountMinor === null
            ? $captureAmount
            : new CalculatedPrice(
                $amountMinor / 100,
                $amountMinor / 100,
                $captureAmount->getCalculatedTaxes(),
                $captureAmount->getTaxRules(),
            );

        $stateId = $this->resolveStateId(
            OrderTransactionCaptureRefundStates::STATE_MACHINE,
            $this->mapStatusToState($status),
            $context,
        );
        if ($stateId === null) {
            return null;
        }

        $refundId = Uuid::randomHex();
        $this->refundRepository->create([[
            'id' => $refundId,
            'captureId' => $captureId,
            'stateId' => $stateId,
            'amount' => $amount,
            'externalReference' => $gatewayRefundId,
            'reason' => 'External refund (payment gateway)',
            'customFields' => [
                PaymentCustomFields::REFUND_ORIGIN => RefundOrigin::GATEWAY_SYNC->value,
            ],
        ]], $context);

        // Reflect the refund onto the order-transaction state. Accepted refunds
        // (in_progress or completed) flip the payment to refunded / refunded_partially.
        $this->refundStateReflector->reflect($orderTransactionId, $captureId, $context);

        $this->logger->info('Synchronized external refund', [
            'orderTransactionId' => $orderTransactionId,
            'refundId' => $refundId,
            'gatewayRefundId' => $gatewayRefundId,
            'status' => $status->name,
        ]);

        return $refundId;
    }

    private function loadCapture(string $captureId, Context $context): ?OrderTransactionCaptureEntity
    {
        $criteria = new Criteria([$captureId]);
        $criteria->addAssociation('refunds.stateMachineState');

        $capture = $this->captureRepository->search($criteria, $context)->getEntities()->first();

        return $capture instanceof OrderTransactionCaptureEntity ? $capture : null;
    }

    private function findRefundByExternalReference(
        OrderTransactionCaptureEntity $capture,
        string $gatewayRefundId,
    ): ?OrderTransactionCaptureRefundEntity {
        foreach ($capture->getRefunds() ?? [] as $refund) {
            if ($refund->getExternalReference() === $gatewayRefundId) {
                return $refund;
            }
        }

        return null;
    }

    /**
     * Drive an already-existing refund (typically created PENDING by an in-shop refund)
     * to its final state when the gateway's async webhook arrives. Idempotent: a no-op if
     * the refund is already in the target state, and illegal transitions are swallowed.
     */
    private function finalizeExistingRefund(
        OrderTransactionCaptureRefundEntity $refund,
        string $orderTransactionId,
        OrderTransactionCaptureEntity $capture,
        RefundStatus $status,
        Context $context,
    ): void {
        $currentState = $refund->getStateMachineState()?->getTechnicalName();
        if ($currentState === $this->mapStatusToState($status)) {
            return; // already finalized
        }

        try {
            match ($status) {
                RefundStatus::COMPLETED => $this->refundStateHandler->complete($refund->getId(), $context),
                RefundStatus::FAILED => $this->refundStateHandler->fail($refund->getId(), $context),
                RefundStatus::IN_PROGRESS => $this->refundStateHandler->process($refund->getId(), $context),
            };
        } catch (IllegalTransitionException $e) {
            $this->logger->info('Skipping illegal refund-state transition (webhook finalize)', [
                'refundId' => $refund->getId(),
                'from' => $currentState,
                'to' => $status->name,
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        $this->logger->info('Finalized existing refund from gateway webhook', [
            'orderTransactionId' => $orderTransactionId,
            'refundId' => $refund->getId(),
            'status' => $status->name,
        ]);

        $this->refundStateReflector->reflect($orderTransactionId, $capture->getId(), $context);
    }

    private function mapStatusToState(RefundStatus $status): string
    {
        return match ($status) {
            RefundStatus::COMPLETED => OrderTransactionCaptureRefundStates::STATE_COMPLETED,
            RefundStatus::IN_PROGRESS => OrderTransactionCaptureRefundStates::STATE_IN_PROGRESS,
            RefundStatus::FAILED => OrderTransactionCaptureRefundStates::STATE_FAILED,
        };
    }

    private function resolveStateId(string $stateMachine, string $technicalName, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));
        $criteria->addFilter(new EqualsFilter('stateMachine.technicalName', $stateMachine));
        $criteria->setLimit(1);

        return $this->stateMachineStateRepository->searchIds($criteria, $context)->firstId();
    }
}
