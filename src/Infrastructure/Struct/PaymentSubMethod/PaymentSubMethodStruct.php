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

class PaymentSubMethodStruct extends Struct
{
    public function __construct(
        public readonly string $name,
        public readonly string $providerId,
        public readonly string $shopwareId,
        public readonly string $mediaUrl,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'cr_payment_sub_method';
    }
}
