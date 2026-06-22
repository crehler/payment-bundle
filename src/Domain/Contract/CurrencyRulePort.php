<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Contract;

interface CurrencyRulePort
{
    public function createOrUpdateCurrencyRule(object $gatewayIdentifier): ?string;
}
