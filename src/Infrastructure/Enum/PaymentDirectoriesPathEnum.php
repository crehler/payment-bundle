<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Enum;

enum PaymentDirectoriesPathEnum: string
{
    case PAYMENT_METHODS = '/Infrastructure/Util/Install/';
    case SUBPAYMENT_METHODS = '/Infrastructure/Util/Install/SubMethods/';
    case PAYMENT_ICONS = '/Resources/icons/';

    public function namespace(): string
    {
        return match ($this) {
            self::PAYMENT_METHODS => '\\Infrastructure\\Util\\Install\\',
            self::SUBPAYMENT_METHODS => '\\Infrastructure\\Util\\Install\\SubMethods\\',
            self::PAYMENT_ICONS => '',
        };
    }
}
