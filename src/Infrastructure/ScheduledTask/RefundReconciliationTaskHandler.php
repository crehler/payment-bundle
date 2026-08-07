<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\ScheduledTask;

use Crehler\PaymentBundle\Application\Port\Driven\RefundReconciliationProviderPort;
use Crehler\PaymentBundle\Application\Service\PendingRefundFinalizer;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Hourly job that makes the shop's refunds agree with the gateways, from both ends:
 *
 * 1. Fan-out over every registered RefundReconciliationProviderPort — finds refunds
 *    created directly in a gateway panel that the shop never heard about.
 * 2. PendingRefundFinalizer — re-checks the shop's own in-progress refunds at gateways
 *    that accept refunds asynchronously without announcing the outcome, which otherwise
 *    leaves them mid-flight forever (WT-910).
 *
 * Both halves are isolated: a provider crashing must not stop the others, and a failing
 * reconciliation pass must not skip the finalizer. Errors are logged, never rethrown.
 */
#[AsMessageHandler(handles: RefundReconciliationTask::class)]
final class RefundReconciliationTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param iterable<RefundReconciliationProviderPort> $providers
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $exceptionLogger,
        #[AutowireIterator(RefundReconciliationProviderPort::class)]
        private readonly iterable $providers,
        private readonly PendingRefundFinalizer $pendingRefundFinalizer,
        private readonly EnhancedLogger $logger,
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();

        $this->reconcileExternalRefunds($context);
        $this->finalizePendingRefunds($context);
    }

    private function finalizePendingRefunds(Context $context): void
    {
        try {
            $report = $this->pendingRefundFinalizer->finalizePending($context);

            $this->logger->info('Pending refund finalization run finished', [
                'refundsChecked' => $report['checked'],
                'refundsFinalized' => $report['finalized'],
                'errors' => $report['errors'],
            ]);
        } catch (Throwable $e) {
            $this->exceptionLogger->error('Pending refund finalization crashed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function reconcileExternalRefunds(Context $context): void
    {
        foreach ($this->providers as $provider) {
            try {
                $report = $provider->reconcile($context);

                $this->logger->info('Refund reconciliation run finished', [
                    'providerId' => $report->providerId,
                    'accountsChecked' => $report->accountsChecked,
                    'refundsSeen' => $report->refundsSeen,
                    'refundsSynced' => $report->refundsSynced,
                    'unmatchedRefunds' => $report->unmatchedRefunds,
                    'errors' => $report->errors,
                ]);
            } catch (Throwable $e) {
                $this->exceptionLogger->error('Refund reconciliation provider crashed', [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
