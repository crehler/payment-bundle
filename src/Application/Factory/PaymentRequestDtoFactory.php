<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Factory;

use Brick\PhoneNumber\{PhoneNumber, PhoneNumberException};
use Crehler\PaymentBundle\Application\DTO\PaymentRequest\{BuyerDTO, DeliveryAddressDTO, OrderItemDTO, PaymentRequestDTO};
use Crehler\PaymentBundle\Domain\Entity\Customer;
use Crehler\PaymentBundle\Domain\Entity\Order\{BillingAddress, Order, ShippingAddress};
use Crehler\PaymentBundle\Domain\Entity\OrderTransaction\OrderTransaction;
use Crehler\PaymentBundle\Domain\ValueObjects\{LineItem, StreetAddress};
use Crehler\PaymentBundle\Shared\EnhancedLogger;

/**
 * Factory for creating payment request DTOs from domain entities.
 *
 * Provides common conversion logic used by all payment gateways.
 */
final readonly class PaymentRequestDtoFactory
{
    public function __construct(
        private EnhancedLogger $logger,
    ) {
    }

    public function createPaymentRequest(
        Order $order,
        OrderTransaction $orderTransaction,
        string $returnUrl,
        string $notifyUrl,
        ?string $customerIp = null,
        ?string $paymentSubMethodId = null,
        ?string $authorizationCode = null,
        ?string $cardToken = null,
        ?string $locale = null,
    ): PaymentRequestDTO {
        return new PaymentRequestDTO(
            order: $order,
            orderTransaction: $orderTransaction,
            returnUrl: $returnUrl,
            notifyUrl: $notifyUrl,
            customerIp: $customerIp,
            paymentSubMethodId: $paymentSubMethodId,
            authorizationCode: $authorizationCode,
            cardToken: $cardToken,
            locale: $locale,
        );
    }

    public function createBuyer(
        Customer $customer,
        BillingAddress $billingAddress,
        ?ShippingAddress $shippingAddress = null,
    ): BuyerDTO {
        $phone = $this->parsePhoneNumber(
            countryCode: $billingAddress->countryCode,
            phoneNumber: $billingAddress->phone ?? $customer->phone,
        );

        $deliveryAddress = null;
        if ($shippingAddress !== null) {
            $deliveryAddress = $this->createDeliveryAddress($shippingAddress);
        }

        return new BuyerDTO(
            email: $customer->email,
            firstName: $customer->firstName,
            lastName: $customer->lastName,
            phone: $phone['number'],
            phonePrefix: $phone['prefix'],
            locale: null,
            customerId: $customer->id,
            deliveryAddress: $deliveryAddress,
        );
    }

    public function createDeliveryAddress(ShippingAddress $shippingAddress): DeliveryAddressDTO
    {
        return new DeliveryAddressDTO(
            street: $shippingAddress->street,
            city: $shippingAddress->city,
            // ShippingAddress calls it zipCode; reading ->postalCode here threw as soon as
            // anyone actually passed a shipping address. Nobody did until now, because
            // Order carried no shipping address to pass.
            //
            // Normalised here rather than at each provider: upper-casing is what every
            // carrier and gateway compares against, so the shared DTO should already
            // carry the comparable form.
            postalCode: StreetAddress::normalizeZipCode($shippingAddress->zipCode),
            countryCode: $shippingAddress->countryCode,
            recipientName: $shippingAddress->firstName . ' ' . $shippingAddress->lastName,
            recipientEmail: null,
            recipientPhone: $shippingAddress->phone,
        );
    }

    /**
     * @param array<LineItem> $lineItems
     *
     * @return array<OrderItemDTO>
     */
    public function createOrderItems(array $lineItems): array
    {
        $result = [];

        foreach ($lineItems as $lineItem) {
            $result[] = new OrderItemDTO(
                name: $lineItem->label,
                quantity: $lineItem->quantity,
                unitPrice: $lineItem->unitPrice->amount,
                category: $lineItem->type,
            );
        }

        return $result;
    }

    /**
     * Reconcile a list of order items so their summed total equals the order
     * total. Rounding of per-item prices can make sum(items) drift by a few
     * minor units from the order total, which several gateways reject. When the
     * sums diverge, an explicit adjustment item is appended (positive or
     * negative) so the gateway sees a consistent total.
     *
     * @param array<OrderItemDTO> $items
     *
     * @return array<OrderItemDTO>
     */
    public function reconcileItemsTotal(array $items, int $expectedTotal, string $adjustmentName = 'Adjustment'): array
    {
        $sum = 0;
        foreach ($items as $item) {
            $sum += $item->getTotalPrice();
        }

        $diff = $expectedTotal - $sum;
        if ($diff !== 0) {
            $items[] = new OrderItemDTO(
                name: $adjustmentName,
                quantity: 1,
                unitPrice: $diff,
                category: 'adjustment',
            );
        }

        return $items;
    }

    /**
     * Parse phone number and extract prefix and national number.
     *
     * @return array{prefix: ?string, number: ?string}
     */
    public function parsePhoneNumber(string $countryCode, ?string $phoneNumber): array
    {
        try {
            if (empty($phoneNumber)) {
                $formattedPhone = PhoneNumber::getExampleNumber($countryCode);
            } else {
                $formattedPhone = PhoneNumber::parse($phoneNumber, $countryCode);
            }

            return [
                'prefix' => '+' . $formattedPhone->getCountryCode(),
                'number' => (string) $formattedPhone->getNationalNumber(),
            ];
        } catch (PhoneNumberException $e) {
            $this->logger->error("Problem with parsing phone number: {$e->getMessage()}", ['exception' => $e]);

            try {
                $formattedPhone = PhoneNumber::getExampleNumber($countryCode);

                return [
                    'prefix' => '+' . $formattedPhone->getCountryCode(),
                    'number' => (string) $formattedPhone->getNationalNumber(),
                ];
            } catch (PhoneNumberException) {
                return [
                    'prefix' => null,
                    'number' => $phoneNumber,
                ];
            }
        }
    }
}
