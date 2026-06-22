<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Entity\Order;

final readonly class BillingAddress
{
    /**
     * @param array<array<string, mixed>> $customFields
     */
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $street,
        public string $city,
        public string $zipCode,
        public string $countryCode,
        public string $countryName,
        public ?string $phone = null,
        public array $customFields = [],
    ) {
    }
}
