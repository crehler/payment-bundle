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

final class BlikPaymentStruct extends Struct
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $error = null,
        public readonly ?string $orderId = null,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'cr_payment_blik';
    }
}
