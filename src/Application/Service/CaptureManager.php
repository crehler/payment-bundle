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
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Creates the native OrderTransactionCapture that the Shopware refund flow requires.
 *
 * Polish pay-by-link gateways settle in one step (paid == captured in full), so we
 * create a single, full-amount capture in state "completed" with the gateway payment
 * id as external reference. Idempotent: one capture per transaction.
 */
final class CaptureManager
{
    public function __construct(
        #[Autowire(service: 'order_transaction.repository')]
        private readonly EntityRepository $orderTransactionRepository,
        #[Autowire(service: 'order_transaction_capture.repository')]
        private readonly EntityRepository $captureRepository,
        #[Autowire(service: 'state_machine_state.repository')]
        private readonly EntityRepository $stateMachineStateRepository,
    ) {
    }

    /**
     * Ensure a capture exists for the transaction id, loading it first. Returns the
     * capture id, or null if the transaction can't be found / state can't be resolved.
     */
    public function ensureCapture(string $orderTransactionId, Context $context): ?string
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('captures');

        $transaction = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();
        if (!$transaction instanceof OrderTransactionEntity) {
            return null;
        }

        return $this->ensureCaptureForTransaction($transaction, $context);
    }

    /**
     * Ensure a capture exists for an already-loaded transaction entity. The entity
     * must be loaded with the "captures" association (used for idempotency).
     */
    public function ensureCaptureForTransaction(OrderTransactionEntity $transaction, Context $context): ?string
    {
        $captures = $transaction->getCaptures();
        $existingId = $captures?->first()?->getId();

        // A null collection means the "captures" association wasn't loaded (an empty
        // collection would be non-null). Fall back to a direct lookup so a caller that
        // forgot addAssociation('captures') can't create a duplicate capture.
        if ($existingId === null && $captures === null) {
            $existingId = $this->findExistingCaptureId($transaction->getId(), $context);
        }

        if ($existingId !== null) {
            return $existingId;
        }

        $stateId = $this->resolveStateId(
            OrderTransactionCaptureStates::STATE_MACHINE,
            OrderTransactionCaptureStates::STATE_COMPLETED,
            $context,
        );
        if ($stateId === null) {
            return null;
        }

        $captureId = Uuid::randomHex();
        $gatewayPaymentId = ($transaction->getCustomFields() ?? [])[PaymentCustomFields::GATEWAY_PAYMENT_ID] ?? null;

        $this->captureRepository->create([[
            'id' => $captureId,
            'orderTransactionId' => $transaction->getId(),
            'stateId' => $stateId,
            'externalReference' => $gatewayPaymentId,
            'amount' => $transaction->getAmount(),
        ]], $context);

        return $captureId;
    }

    private function findExistingCaptureId(string $orderTransactionId, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderTransactionId', $orderTransactionId));
        $criteria->setLimit(1);

        return $this->captureRepository->searchIds($criteria, $context)->firstId();
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
