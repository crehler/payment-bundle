<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Constant;

/**
 * Custom-field keys shared by all payment providers. Gateway-specific keys
 * (e.g. refund tracking) stay in the respective plugin.
 */
final class PaymentCustomFields
{
    /**
     * Gateway-side payment/transaction id stored on the Shopware order transaction.
     *
     * @var string
     */
    public const GATEWAY_PAYMENT_ID = 'crehler_payment_gateway_id';

    /**
     * Origin of an order_transaction_capture_refund entity — see Domain\ValueObjects\RefundOrigin.
     *
     * @var string
     */
    public const REFUND_ORIGIN = 'crehler_payment_refund_origin';
}
