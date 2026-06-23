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

final class CheckPaymentStatusStruct extends Struct
{
    public const API_ALIAS = 'cr_payment_check_status';

    public function __construct(
        public readonly bool $status,
        public readonly bool $waiting,
        public readonly bool $failed,
        public readonly bool $mismatch = false,
    ) {
    }

    public function getApiAlias(): string
    {
        return self::API_ALIAS;
    }
}
