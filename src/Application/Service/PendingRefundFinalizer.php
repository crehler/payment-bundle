<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service;

use Crehler\PaymentBundle\Application\Port\Driven\RefundStatusProviderPort;
use Crehler\PaymentBundle\Domain\ValueObjects\RefundStatus;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\{OrderTransactionCaptureRefundEntity, OrderTransactionCaptureRefundStates};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{EqualsFilter, NotFilter};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\DependencyInjection\Attribute\{Autowire, AutowireIterator};
use Throwable;

use function round;
use function sprintf;

/**
 * Closes the loop for gateways that accept a refund asynchronously and never announce
 * the outcome.
 *
 * Such a refund is recorded "in progress" and nothing moves it afterwards, so the shop
 * shows a refund permanently mid-flight while the money has long left the account
 * (WT-910). Once per scheduled run every in-progress refund that carries a gateway
 * reference is re-checked at its gateway and the answer applied.
 *
 * The state change itself is delegated to RefundSynchronizer, which already knows how to
 * finalize an existing refund found by its gateway reference — including the idempotent
 * no-op when the state already matches, the illegal-transition guard, and reflecting the
 * result onto the order transaction. Nothing about state handling is reimplemented here.
 */
final readonly class PendingRefundFinalizer
{
    /**
     * Safety valve: one run must not turn into an unbounded gateway hammering.
     *
     * The batch is ordered oldest-first, so the backlog drains across runs as older
     * refunds reach a final state and leave the selection. Should more than this many
     * refunds ever stay stuck in progress, the newer ones keep waiting — a known and
     * accepted limit of the batching.
     *
     * @var int
     */
    private const BATCH_LIMIT = 200;

    /**
     * @param iterable<RefundStatusProviderPort> $statusProviders
     */
    public function __construct(
        #[AutowireIterator(RefundStatusProviderPort::class)]
        private iterable $statusProviders,
        #[Autowire(service: 'order_transaction_capture_refund.repository')]
        private EntityRepository $refundRepository,
        private RefundSynchronizer $refundSynchronizer,
        private EnhancedLogger $logger,
    ) {
    }

    /**
     * @return array{checked: int, finalized: int, errors: string[]}
     */
    public function finalizePending(Context $context): array
    {
        $refunds = $this->loadPendingRefunds($context);
        $checked = 0;
        $finalized = 0;
        $errors = [];

        foreach ($refunds as $refund) {
            $gatewayRefundId = (string) $refund->getExternalReference();
            $orderTransaction = $refund->getTransactionCapture()?->getTransaction();
            $handlerIdentifier = $orderTransaction?->getPaymentMethod()?->getHandlerIdentifier();

            if ($orderTransaction === null || $handlerIdentifier === null) {
                // Orphaned refund (capture/transaction/payment method not resolvable) —
                // there is no provider to ask, so skip quietly rather than error out.
                continue;
            }

            $provider = $this->resolveProvider($handlerIdentifier);
            if ($provider === null) {
                continue; // provider does not offer refund status polling
            }

            ++$checked;

            try {
                $status = $provider->getRefundStatus(
                    gatewayRefundId: $gatewayRefundId,
                    salesChannelId: $orderTransaction->getOrder()?->getSalesChannelId(),
                    context: $context,
                );
            } catch (Throwable $e) {
                // The port forbids throwing, but a provider bug must not take the whole
                // batch down with it.
                $errors[] = sprintf('%s: %s', $gatewayRefundId, $e->getMessage());

                continue;
            }

            if ($status === null || $status === RefundStatus::IN_PROGRESS) {
                continue; // unknown or still processing — ask again next run
            }

            $this->refundSynchronizer->syncExternalRefund(
                orderTransactionId: $orderTransaction->getId(),
                // The refund's own amount, never the "full capture" sentinel: should the
                // reference ever fail to match, this must not invent a full refund.
                amountMinor: $this->amountInMinorUnits($refund),
                gatewayRefundId: $gatewayRefundId,
                status: $status,
                context: $context,
            );

            ++$finalized;

            $this->logger->info('Finalized pending refund from gateway status', [
                'refundId' => $refund->getId(),
                'gatewayRefundId' => $gatewayRefundId,
                'status' => $status->name,
            ]);
        }

        return ['checked' => $checked, 'finalized' => $finalized, 'errors' => $errors];
    }

    /**
     * In-progress refunds that carry a gateway reference — without one there is nothing
     * to ask the gateway about.
     *
     * @return OrderTransactionCaptureRefundEntity[]
     */
    private function loadPendingRefunds(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter(
            'stateMachineState.technicalName',
            OrderTransactionCaptureRefundStates::STATE_IN_PROGRESS,
        ));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_OR, [
            new EqualsFilter('externalReference', null),
            new EqualsFilter('externalReference', ''),
        ]));
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('transactionCapture.transaction.paymentMethod');
        $criteria->addAssociation('transactionCapture.transaction.order');
        // Oldest first: a deterministic order means the batch is not an arbitrary slice of
        // the backlog, and the refunds waiting the longest are always the ones asked about.
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->setLimit(self::BATCH_LIMIT);

        /** @var OrderTransactionCaptureRefundEntity[] $refunds */
        $refunds = $this->refundRepository->search($criteria, $context)->getEntities()->getElements();

        return $refunds;
    }

    private function resolveProvider(string $handlerIdentifier): ?RefundStatusProviderPort
    {
        foreach ($this->statusProviders as $provider) {
            if ($provider->supports($handlerIdentifier)) {
                return $provider;
            }
        }

        return null;
    }

    private function amountInMinorUnits(OrderTransactionCaptureRefundEntity $refund): int
    {
        return (int) round($refund->getAmount()->getTotalPrice() * 100);
    }
}
