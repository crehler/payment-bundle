<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\DTO\CheckPayment;

use Crehler\PaymentBundle\Domain\ValueObjects\PaymentStatus;

final readonly class CheckPaymentStatusResponseDTO
{
    public function __construct(
        public PaymentStatus $paymentStatus,
    ) {
    }
}
