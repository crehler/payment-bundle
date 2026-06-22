<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Enum;

enum InstallClasses: string
{
    case PATH = 'Infrastructure\\Util\\Install\\';
    case SUPPORT_CURRENCY_CLASS = 'SupportCurrency';
}
