<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Shared;

use function round;

final readonly class AmountFormat
{
    public function floatToInt(float $value): int
    {
        return (int) round($value * 100, 2);
    }

    public function intToFloat(int $value): float
    {
        return round($value / 100, 2);
    }

    public function round(int $value): float
    {
        return $this->intToFloat(self::floatToInt($value));
    }
}
