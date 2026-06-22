<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\DTO;

final readonly class PaymentSubMethodDTO
{
    public function __construct(
        public string $name,
        public string $providerId,
        public string $shopwareId,
        public string $mediaUrl,
    ) {
    }
}
