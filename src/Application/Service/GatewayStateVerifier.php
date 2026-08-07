<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service;

use Crehler\PaymentBundle\Application\Port\Driven\PaymentGatewayStatusProviderInterface;
use Crehler\PaymentBundle\Domain\ValueObjects\PaymentStatus;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\{OrderTransactionEntity, OrderTransactionStates};
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Attribute\{Autowire, AutowireIterator};
use Throwable;

use function in_array;

/**
 * Asks the gateway what it actually thinks about a payment, so a notification is
 * never the sole basis for a state change that destroys or resurrects money.
 *
 * A signed notification proves authenticity, not freshness: a delayed, replayed or
 * out-of-order webhook is authentic and stale at the same time. WT-910 hit exactly
 * that — a signed EXPIRED moved a paid order to cancelled while the gateway still
 * held the payment as confirmed.
 *
 * Verdicts are deliberately asymmetric, because a false "no" is as expensive as a
 * false "yes":
 *   - contradiction  → the gateway reports a terminal state opposite to the claim;
 *                      the claim must not be applied.
 *   - inconclusive   → no provider, missing gateway id, API error, or a pending
 *                      status; the caller keeps trusting the signed notification.
 * A gateway status API commonly lags its own webhook, so "pending" right after a
 * CONFIRMED notification is normal and must never block booking the payment.
 */
final readonly class GatewayStateVerifier
{
    /**
     * @param iterable<PaymentGatewayStatusProviderInterface> $statusProviders
     */
    public function __construct(
        #[AutowireIterator('crehler.payment.gateway_status_provider')]
        private iterable $statusProviders,
        #[Autowire(service: 'order_transaction.repository')]
        private EntityRepository $orderTransactionRepository,
        private EnhancedLogger $logger,
    ) {
    }

    /**
     * Does the gateway report a terminal failure? The single question both callers
     * need, read in opposite directions: it contradicts a claimed payment, and it
     * confirms a claimed cancellation. Anything inconclusive answers false, so the
     * caller decides what "don't know" means for its direction.
     */
    public function reportsFailure(string $orderTransactionId, Context $context): bool
    {
        return $this->status($orderTransactionId, $context)?->hasFailed() === true;
    }

    /**
     * Canonical gateway status, or null when it cannot be determined.
     */
    public function status(string $orderTransactionId, Context $context): ?PaymentStatus
    {
        $transaction = $this->loadTransaction($orderTransactionId, $context);

        if ($transaction === null) {
            return null;
        }

        foreach ($this->statusProviders as $provider) {
            try {
                if (!$provider->supports($transaction)) {
                    continue;
                }

                return $provider->getPaymentStatus($transaction);
            } catch (Throwable $e) {
                // A gateway outage must not decide state changes: report inconclusive
                // and let the caller fall back to trusting the notification.
                $this->logger->error('Gateway status verification failed', [
                    'orderTransactionId' => $orderTransactionId,
                    'provider' => $provider::class,
                    'exception' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return null;
    }

    /**
     * Is the transaction in a state where payment is already recognized (or moved
     * beyond it)? Such states must not be undone on a notification alone.
     */
    public function isSettled(?string $transactionState): bool
    {
        return in_array($transactionState, [
            OrderTransactionStates::STATE_PAID,
            OrderTransactionStates::STATE_REFUNDED,
            OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
            OrderTransactionStates::STATE_CHARGEBACK,
        ], true);
    }

    /**
     * Providers resolve the gateway id from custom fields and the credentials from
     * the order's sales channel, so both associations must be loaded here.
     */
    private function loadTransaction(string $orderTransactionId, Context $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('paymentMethod');
        $criteria->addAssociation('order');

        $transaction = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();

        return $transaction instanceof OrderTransactionEntity ? $transaction : null;
    }
}
