<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Enum;

enum PaymentMethods: string
{
    case PAYMENT_CUSTOM_FIELD = 'crehler_payments';
    case PAYMENT_METHOD = 'payment_method_';
    case BANK = 'bank';
}
