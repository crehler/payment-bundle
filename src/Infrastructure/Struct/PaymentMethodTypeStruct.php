<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Struct containing payment method type information.
 *
 * Used as value for the crPaymentType runtime field on PaymentMethodEntity.
 */
final class PaymentMethodTypeStruct extends Struct
{
    public const API_ALIAS = 'cr_payment_method_type';

    public function __construct(
        public readonly bool $isBlik = false,
        public readonly bool $isCard = false,
        public readonly bool $isBank = false,
        public readonly bool $isEwallet = false,
        public readonly bool $isDeferred = false,
        public readonly bool $hasSubmethods = false,
        public readonly bool $isCrehlerPayment = false,
    ) {
    }

    public function getApiAlias(): string
    {
        return self::API_ALIAS;
    }
}
