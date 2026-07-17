<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\ValueObjects;

/**
 * Who initiated an order_transaction_capture_refund entity — stored on
 * customFields.crehler_payment_refund_origin (see PaymentCustomFields::REFUND_ORIGIN).
 */
enum RefundOrigin: string
{
    case SHOP = 'shop';
    case GATEWAY_SYNC = 'gateway_sync';
}
