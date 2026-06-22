<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Entity;

final readonly class Customer
{
    /**
     * @param array<array<string, mixed>> $customFields
     */
    public function __construct(
        public string $id,
        public string $customerNumber,
        public string $email,
        public string $firstName,
        public string $lastName,
        public ?string $phone = null,
        public bool $isGuest = false,
        public array $customFields = [],
    ) {
    }
}
