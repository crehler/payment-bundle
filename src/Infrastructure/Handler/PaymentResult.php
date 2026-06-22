<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Handler;

/**
 * Represents the result of a payment processing operation.
 */
final readonly class PaymentResult
{
    public function __construct(
        public string $redirectUrl,
        public ?string $gatewayOrderId = null,
        public bool $success = true,
        public ?string $errorMessage = null,
    ) {
    }

    public static function success(string $redirectUrl, ?string $gatewayOrderId = null): self
    {
        return new self(
            redirectUrl: $redirectUrl,
            gatewayOrderId: $gatewayOrderId,
            success: true
        );
    }

    public static function failure(string $errorMessage): self
    {
        return new self(
            redirectUrl: '',
            gatewayOrderId: null,
            success: false,
            errorMessage: $errorMessage
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }
}
