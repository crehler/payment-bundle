<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\DTO\PaymentRequest;

/**
 * Generic delivery address DTO for payment requests.
 */
final readonly class DeliveryAddressDTO
{
    public function __construct(
        public string $street,
        public string $city,
        public string $postalCode,
        public string $countryCode,
        public ?string $recipientName = null,
        public ?string $recipientEmail = null,
        public ?string $recipientPhone = null,
    ) {
    }
}
