<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\ValueObjects;

final readonly class SavedCard
{
    public function __construct(
        public string $token,
        public string $brandImgUrl,
        public string $status,
        public int $expirationYear,
        public int $expirationMonth,
        public string $cardNumberMasked,
        public string $cardBrand,
        public bool $preferred = false,
        public ?string $cardScheme = null,
    ) {
    }
}
