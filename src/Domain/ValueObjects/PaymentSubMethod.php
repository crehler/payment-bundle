<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\ValueObjects;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PaymentSubMethod
{
    /**
     * @param string $providerId Payment provider's method code (e.g., "ai", "dpkl", "blik", "123" for bank IDs).
     *                           This value is sent to the payment gateway API.
     * @param string $name       display name of the payment sub-method
     * @param string $shopwareId shopware payment method UUID - the parent payment method this sub-method belongs to
     * @param string $mediaUrl   URL to the payment method icon/logo
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $providerId,
        #[Assert\NotBlank]
        public string $name,
        #[Assert\NotBlank]
        public string $shopwareId,
        #[Assert\Url]
        public string $mediaUrl,
        public ?int $minAmount = null,
        public ?int $maxAmount = null,
    ) {
    }

    /**
     * Check if this submethod belongs to the specified payment method.
     */
    public function belongsToPaymentMethod(string $paymentMethodId): bool
    {
        return $this->shopwareId === $paymentMethodId;
    }
}
