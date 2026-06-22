<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Factory;

use Crehler\PaymentBundle\Domain\Entity\Order\{BillingAddress, Order};
use Crehler\PaymentBundle\Domain\ValueObjects\{LineItem, Money};
use Crehler\PaymentBundle\Shared\AmountFormat;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as ShopwareLineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\{OrderLineItemCollection, OrderLineItemEntity};
use Shopware\Core\Checkout\Order\OrderEntity;

use function is_float;

final readonly class OrderFactory
{
    public function __construct(
        private AmountFormat $amountFormat,
        private CustomerFactory $customerFactory,
    ) {
    }

    public function createOrder(OrderEntity $orderEntity): Order
    {
        $currencyCode = $orderEntity->getCurrency()?->getIsoCode();

        return new Order(
            id: $orderEntity->getId(),
            orderNumber: $orderEntity->getOrderNumber(),
            totalAmount: $this->createMoney($orderEntity->getAmountTotal(), $currencyCode),
            netAmount: $this->createMoney($orderEntity->getAmountNet(), $currencyCode),
            shippingAmount: $this->createMoney($orderEntity->getShippingTotal(), $currencyCode),
            currencyCode: $currencyCode,
            customer: $this->customerFactory->create($orderEntity->getOrderCustomer()->getCustomer()),
            billingAddress: $this->createBillingAddress($orderEntity->getBillingAddress()),
            lineItems: $this->createLineItems($orderEntity->getLineItems(), $currencyCode),
            customFields: $orderEntity->getCustomFields() ?? [],
            salesChannelId: $orderEntity->getSalesChannelId(),
        );
    }

    /**
     * Creates a Money value object.
     */
    private function createMoney(int|float $amount, string $currencyCode): Money
    {
        if (is_float($amount)) {
            $amount = $this->amountFormat->floatToInt($amount);
        }

        return new Money(amount: $amount, currency: $currencyCode);
    }

    private function createBillingAddress(OrderAddressEntity $addressEntity): BillingAddress
    {
        return new BillingAddress(
            id: $addressEntity->getId(),
            firstName: $addressEntity->getFirstName(),
            lastName: $addressEntity->getLastName(),
            street: $addressEntity->getStreet(),
            city: $addressEntity->getCity(),
            zipCode: $addressEntity->getZipcode(),
            countryCode: $addressEntity->getCountry()->getIso(),
            countryName: $addressEntity->getCountry()->getName(),
            phone: $addressEntity->getPhoneNumber(),
            customFields: $addressEntity->getCustomFields() ?? [],
        );
    }

    /**
     * @return array<LineItem>
     */
    private function createLineItems(OrderLineItemCollection $lineItemCollection, string $currencyCode): array
    {
        $lineItems = [];

        foreach ($lineItemCollection as $lineItemEntity) {
            if ($lineItemEntity->getType() !== ShopwareLineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $lineItems[] = $this->createLineItem($lineItemEntity, $currencyCode);
        }

        return $lineItems;
    }

    /**
     * Creates a LineItem value object from a Shopware OrderLineItemEntity.
     */
    private function createLineItem(OrderLineItemEntity $lineItemEntity, string $currencyCode): LineItem
    {
        $crehlerPrice = $lineItemEntity->getCustomFields()['crehler_line_item_price'] ?? null;
        $nativePrice = $lineItemEntity->getPrice();

        $unitPriceFloat = $crehlerPrice['unitPrice'] ?? $lineItemEntity->getUnitPrice() ?? $nativePrice?->getUnitPrice() ?? 0.0;
        $totalPriceFloat = $crehlerPrice['totalPrice'] ?? $lineItemEntity->getTotalPrice() ?? $nativePrice?->getTotalPrice() ?? 0.0;
        $quantity = $crehlerPrice['quantity'] ?? $lineItemEntity->getQuantity();
        $taxRate = $crehlerPrice['taxRules'][0]['taxRate']
            ?? $nativePrice?->getTaxRules()->first()?->getTaxRate()
            ?? 0.0;

        $unitPrice = $this->createMoney($this->amountFormat->floatToInt((float) $unitPriceFloat), $currencyCode);
        $totalPrice = $this->createMoney($this->amountFormat->floatToInt((float) $totalPriceFloat), $currencyCode);

        return new LineItem(
            id: $lineItemEntity->getId(),
            productId: $lineItemEntity->getProductId() ?? $lineItemEntity->getId(),
            productNumber: $lineItemEntity->getProduct()?->getProductNumber() ?? '',
            label: $lineItemEntity->getLabel(),
            quantity: $quantity,
            unitPrice: $unitPrice,
            totalPrice: $totalPrice,
            taxRate: $taxRate,
            type: $lineItemEntity->getType(),
            customFields: $lineItemEntity->getCustomFields() ?? [],
        );
    }
}
