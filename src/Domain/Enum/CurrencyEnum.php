<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Enum;

enum CurrencyEnum: string
{
    case PLN = 'PLN';
    case EUR = 'EUR';
    case USD = 'USD';
    case GBP = 'GBP';
    case CZK = 'CZK';

    public static function getAllValues(): array
    {
        return [
            self::PLN->value,
            self::EUR->value,
            self::USD->value,
            self::GBP->value,
            self::CZK->value,
        ];
    }
}
