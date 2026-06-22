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
 * Generic buyer/customer DTO for payment requests.
 */
final readonly class BuyerDTO
{
    public function __construct(
        public string $email,
        public string $firstName,
        public string $lastName,
        public ?string $phone = null,
        public ?string $phonePrefix = null,
        public ?string $locale = null,
        public ?string $customerId = null,
        public ?DeliveryAddressDTO $deliveryAddress = null,
    ) {
    }
}
