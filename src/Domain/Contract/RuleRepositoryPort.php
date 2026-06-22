<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Contract;

use Crehler\PaymentBundle\Domain\Entity\PaymentRule;

interface RuleRepositoryPort
{
    public function upsert(PaymentRule $rule): void;

    public function findById(string $id): ?PaymentRule;
}
