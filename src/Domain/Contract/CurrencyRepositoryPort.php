<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Contract;

use Crehler\PaymentBundle\Domain\ValueObjects\SupportedCurrency;

interface CurrencyRepositoryPort
{
    /**
     * @return array<SupportedCurrency> Array of supported currencies
     */
    public function findByIsoCodes(array $isoCodes): array;
}
