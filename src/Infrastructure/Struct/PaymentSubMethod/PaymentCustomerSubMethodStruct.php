<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Struct\PaymentSubMethod;

use Shopware\Core\Framework\Struct\Struct;

final class PaymentCustomerSubMethodStruct extends Struct
{
    public function __construct(
        public readonly string $paymentMethodId,
        public readonly ?string $subPaymentMethodId = null,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'cr_payment_customer_sub_method';
    }
}
