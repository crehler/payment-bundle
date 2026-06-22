<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\DTO\PaymentRequest;

use Crehler\PaymentBundle\Domain\Entity\Order\Order;
use Crehler\PaymentBundle\Domain\Entity\OrderTransaction\OrderTransaction;

/**
 * Generic payment request DTO used by all payment handlers.
 *
 * Contains all data needed to initiate a payment with any gateway.
 * Gateway-specific adapters/factories transform this into gateway-specific formats.
 */
final readonly class PaymentRequestDTO
{
    public function __construct(
        public Order $order,
        public OrderTransaction $orderTransaction,
        public string $returnUrl,
        public string $notifyUrl,
        public ?string $customerIp = null,
        public ?string $paymentSubMethodId = null,
        public ?string $authorizationCode = null,
        public ?string $cardToken = null,
        public ?string $fingerPrintDevice = null,
        public ?string $locale = null,
    ) {
    }

    public function withPaymentSubMethodId(?string $paymentSubMethodId): self
    {
        return new self(
            order: $this->order,
            orderTransaction: $this->orderTransaction,
            returnUrl: $this->returnUrl,
            notifyUrl: $this->notifyUrl,
            customerIp: $this->customerIp,
            paymentSubMethodId: $paymentSubMethodId,
            authorizationCode: $this->authorizationCode,
            cardToken: $this->cardToken,
            fingerPrintDevice: $this->fingerPrintDevice,
            locale: $this->locale,
        );
    }

    public function withAuthorizationCode(?string $authorizationCode): self
    {
        return new self(
            order: $this->order,
            orderTransaction: $this->orderTransaction,
            returnUrl: $this->returnUrl,
            notifyUrl: $this->notifyUrl,
            customerIp: $this->customerIp,
            paymentSubMethodId: $this->paymentSubMethodId,
            authorizationCode: $authorizationCode,
            cardToken: $this->cardToken,
            fingerPrintDevice: $this->fingerPrintDevice,
            locale: $this->locale,
        );
    }

    public function withCardToken(?string $cardToken): self
    {
        return new self(
            order: $this->order,
            orderTransaction: $this->orderTransaction,
            returnUrl: $this->returnUrl,
            notifyUrl: $this->notifyUrl,
            customerIp: $this->customerIp,
            paymentSubMethodId: $this->paymentSubMethodId,
            authorizationCode: $this->authorizationCode,
            cardToken: $cardToken,
            fingerPrintDevice: $this->fingerPrintDevice,
            locale: $this->locale,
        );
    }
}
