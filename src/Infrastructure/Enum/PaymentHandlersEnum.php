<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Enum;

enum PaymentHandlersEnum: string
{
    case BANK_HANDLER = 'bankhandler';
    case BLIK_HANDLER = 'blikhandler';
    case CARD_HANDLER = 'cardhandler';
    case EWALLET_HANDLER = 'ewallethandler';
    case DEFERED_HANDLER = 'deferredhandler';
}
