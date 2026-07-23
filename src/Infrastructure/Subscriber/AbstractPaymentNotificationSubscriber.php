<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Subscriber;

use Crehler\PaymentBundle\Application\Service\TransactionStateApplier;
use Crehler\PaymentBundle\Domain\Event\PaymentNotificationReceivedEvent;
use Crehler\PaymentBundle\Domain\ValueObjects\TransactionStateTransition;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Base for provider webhook subscribers.
 *
 * The bundle owns the orchestration (claim the event, verify, resolve the
 * transaction, transition state, set the HTTP response). A provider implements
 * only the gateway-specific pieces:
 *   - supports():               is this notification ours?
 *   - verify():                 signature/checksum check
 *   - resolveOrderTransaction(): find the Shopware order transaction
 *   - mapStatus():              gateway status -> canonical transition
 *
 * Override beforeApply() to run side effects before the state change
 * (e.g. storing a refunded amount on custom fields).
 */
abstract class AbstractPaymentNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected readonly TransactionStateApplier $transactionStateApplier,
        protected readonly EnhancedLogger $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentNotificationReceivedEvent::EVENT_NAME => 'onPaymentNotification',
        ];
    }

    final public function onPaymentNotification(PaymentNotificationReceivedEvent $event): void
    {
        if ($event->isHandled() || !$this->supports($event)) {
            return;
        }

        if (!$this->verify($event)) {
            $this->logger->warning('Payment notification failed verification', [
                'provider' => static::class,
            ]);
            $event->setHandled(Response::HTTP_BAD_REQUEST, 'Invalid signature');

            return;
        }

        try {
            $orderTransaction = $this->resolveOrderTransaction($event, $event->context);
            if ($orderTransaction === null) {
                $event->setHandled(Response::HTTP_NOT_FOUND, 'Order transaction not found');

                return;
            }

            $transition = $this->mapStatus($event, $orderTransaction);

            $this->beforeApply($event, $orderTransaction, $transition, $event->context);

            $this->transactionStateApplier->apply(
                transition: $transition,
                orderTransactionId: $orderTransaction->getId(),
                context: $event->context,
            );

            $event->setHandled(Response::HTTP_OK, $this->successResponseBody());
        } catch (Throwable $exception) {
            $this->logger->error('Payment notification handling failed', [
                'provider' => static::class,
                'exception' => $exception,
            ]);
            $event->setHandled(Response::HTTP_INTERNAL_SERVER_ERROR, 'Webhook handling failed');
        }
    }

    /**
     * Does this notification belong to this provider? Inspect headers/payload.
     */
    abstract protected function supports(PaymentNotificationReceivedEvent $event): bool;

    /**
     * Verify the notification authenticity (signature, checksum, SDK call).
     */
    abstract protected function verify(PaymentNotificationReceivedEvent $event): bool;

    /**
     * Resolve the Shopware order transaction this notification refers to.
     */
    abstract protected function resolveOrderTransaction(
        PaymentNotificationReceivedEvent $event,
        Context $context,
    ): ?OrderTransactionEntity;

    /**
     * Map the gateway status to a canonical transition.
     */
    abstract protected function mapStatus(
        PaymentNotificationReceivedEvent $event,
        OrderTransactionEntity $orderTransaction,
    ): TransactionStateTransition;

    /**
     * Hook for side effects before the state transition is applied.
     * Default: no-op.
     */
    protected function beforeApply(
        PaymentNotificationReceivedEvent $event,
        OrderTransactionEntity $orderTransaction,
        TransactionStateTransition $transition,
        Context $context,
    ): void {
    }

    /**
     * Response body returned to the gateway once the notification was handled
     * successfully. Default: 'OK'. Gateways with a specific confirmation
     * protocol override this (e.g. Tpay classic notifications require 'TRUE').
     */
    protected function successResponseBody(): string
    {
        return 'OK';
    }
}
