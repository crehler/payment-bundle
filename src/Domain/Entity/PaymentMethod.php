<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Entity;

use Crehler\PaymentBundle\Domain\ValueObjects\PaymentSubMethod;

use function array_filter;
use function array_merge;
use function in_array;

final readonly class PaymentMethod
{
    /**
     * @var string
     */
    public const ENABLED = 'ENABLED';

    /**
     * @param array<PaymentSubMethod> $paymentSubMethods
     */
    public function __construct(
        public array $paymentSubMethods,
    ) {
    }

    public function hasSubMethods(): bool
    {
        return !empty($this->paymentSubMethods);
    }

    public function getEnabledPaymentMethods(): array
    {
        return array_filter(
            $this->paymentSubMethods,
            fn (PaymentSubMethod $paymentSubMethod) => $paymentSubMethod->status === self::ENABLED
        );
    }

    public function addPaymentSubMethod(PaymentSubMethod $paymentSubMethod): self
    {
        if (in_array($paymentSubMethod, $this->paymentSubMethods, true)) {
            return $this;
        }

        $newPaymentSubMethods = array_merge(
            $this->paymentSubMethods,
            [$paymentSubMethod]
        );

        return new self($newPaymentSubMethods);
    }
}
