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
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Fan-out: queries every registered RefundReconciliationProviderPort on each hourly
 * run. A provider crashing must not stop the others — errors are isolated and logged
 * per provider, never rethrown.
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
        private readonly EnhancedLogger $logger,
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();

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
