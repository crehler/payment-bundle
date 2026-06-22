<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\DTO\BlikPayment;

final readonly class BlikPaymentResponseDTO
{
    public function __construct(
        public bool $success,
        public ?string $redirectUrl = null,
        public ?string $errorMessage = null,
        public ?string $orderId = null,
    ) {
    }
}
