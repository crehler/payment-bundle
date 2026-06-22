<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Subscriber;

use Crehler\PaymentBundle\Application\Service\CaptureManager;
use Crehler\PaymentBundle\Infrastructure\Handler\AbstractPaymentMethodHandler;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\{OrderTransactionEntity, OrderTransactionStates};
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

use function is_a;

/**
 * Creates the native OrderTransactionCapture when a transaction of one of our
 * providers becomes "paid". The capture is the anchor the native refund flow
 * needs (refund.capture_id is a required FK). Only acts on payment methods whose
 * handler extends AbstractPaymentMethodHandler; idempotent via CaptureManager.
 */
final class CreateCaptureOnPaidSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'order_transaction.repository')]
        private readonly EntityRepository $orderTransactionRepository,
        private readonly CaptureManager $captureManager,
        private readonly EnhancedLogger $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Listen to the generic per-transition event, NOT state_enter.order_transaction.state.paid:
        // under that name Shopware ALSO re-dispatches OrderStateMachineStateChangeEvent (order-level,
        // no getTransition()) via OrderStateChangeEventListener, which would reach this listener and
        // fatal on the type hint. state_machine.order_transaction.state_changed delivers only the base
        // StateMachineStateChangeEvent — the same event core's own listener consumes.
        return [
            'state_machine.order_transaction.state_changed' => 'onTransactionStateChanged',
        ];
    }

    public function onTransactionStateChanged(StateMachineStateChangeEvent $event): void
    {
        if ($event->getNextState()->getTechnicalName() !== OrderTransactionStates::STATE_PAID) {
            return;
        }

        $context = $event->getContext();
        $orderTransactionId = $event->getTransition()->getEntityId();

        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('paymentMethod');
        $criteria->addAssociation('captures');

        $transaction = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();
        if (!$transaction instanceof OrderTransactionEntity) {
            return;
        }

        $handlerIdentifier = $transaction->getPaymentMethod()?->getHandlerIdentifier();
        if ($handlerIdentifier === null || !is_a($handlerIdentifier, AbstractPaymentMethodHandler::class, true)) {
            return;
        }

        // Never let a capture failure abort the paid transition itself — log and move on.
        try {
            $captureId = $this->captureManager->ensureCaptureForTransaction($transaction, $context);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to ensure payment capture on paid transaction', [
                'orderTransactionId' => $orderTransactionId,
                'exception' => $exception,
            ]);

            return;
        }

        if ($captureId === null) {
            $this->logger->warning('Could not ensure payment capture on paid transaction', [
                'orderTransactionId' => $orderTransactionId,
            ]);

            return;
        }

        $this->logger->info('Ensured payment capture on paid transaction', [
            'orderTransactionId' => $orderTransactionId,
            'captureId' => $captureId,
        ]);
    }
}
