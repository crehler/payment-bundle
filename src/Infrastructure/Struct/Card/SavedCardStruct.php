<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Struct\Card;

use Shopware\Core\Framework\Struct\Struct;

class SavedCardStruct extends Struct
{
    public function __construct(
        public string $token,
        public string $brandImgUrl,
        public string $status,
        public int $expirationYear,
        public int $expirationMonth,
        public string $cardNumberMasked,
        public string $cardBrand,
        public ?bool $preferred = false,
        public ?string $cardScheme = null,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'cr_payment_saved_card';
    }
}
