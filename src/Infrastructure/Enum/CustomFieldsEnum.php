<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Enum;

enum CustomFieldsEnum: string
{
    case CUSTOM_FIELD_SET = 'crehler_payment_set';
    case CUSTOM_FIELD_CUSTOMER_PAYMENT = 'crehler_payments';
    case CUSTOM_FIELD_PAYMENT_SUB_METHOD = 'crehler_payment_submethod';
}
