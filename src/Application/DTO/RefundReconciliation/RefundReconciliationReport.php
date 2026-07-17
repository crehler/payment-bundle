<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\DTO\RefundReconciliation;

/**
 * Outcome of one RefundReconciliationProviderPort::reconcile() run, logged by
 * RefundReconciliationTaskHandler after every provider it queries.
 */
final readonly class RefundReconciliationReport
{
    /**
     * @param string[] $errors
     */
    public function __construct(
        public string $providerId,
        public int $accountsChecked,
        public int $refundsSeen,
        public int $refundsSynced,
        public int $unmatchedRefunds,
        public array $errors = [],
    ) {
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
