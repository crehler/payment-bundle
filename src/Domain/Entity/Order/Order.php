<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Entity\Order;

use Crehler\PaymentBundle\Domain\Entity\Customer;
use Crehler\PaymentBundle\Domain\ValueObjects\{LineItem, Money};

final readonly class Order
{
    /**
     * @param array<LineItem>             $lineItems
     * @param array<array<string, mixed>> $customFields
     */
    public function __construct(
        public string $id,
        public string $orderNumber,
        public Money $totalAmount,
        public Money $netAmount,
        public Money $shippingAmount,
        public string $currencyCode,
        public Customer $customer,
        public BillingAddress $billingAddress,
        public array $lineItems,
        public array $customFields = [],
        public ?string $salesChannelId = null,
        /**
         * Null for an order with no delivery — a download-only cart, or a shop whose
         * checkout never asked for one. Gateways that need a shipping address (PayNow's
         * PayPo will not underwrite without it) have to treat that as a real case rather
         * than assuming the billing address doubles as one.
         */
        public ?ShippingAddress $shippingAddress = null,
        /**
         * The order's own language as a BCP47 tag — "pl-PL", "en-GB". Shopware stores it
         * in exactly that shape, which is the shape gateways ask for.
         *
         * Null when the association was not loaded or the order predates one. Providers
         * should omit the field rather than substitute a default: every gateway already
         * has its own, and guessing one here would silently pick the language a customer
         * reads their payment page in.
         */
        public ?string $locale = null,
    ) {
    }

    public function isTheSame(string $orderId): bool
    {
        return $this->id === $orderId;
    }

    public function validateLineItemTotal(): bool
    {
        $calculatedTotal = $this->calculateLineItemsTotal();

        return $this->totalAmount->equals($calculatedTotal);
    }

    private function calculateLineItemsTotal(): Money
    {
        $total = new Money(0, $this->currencyCode);

        foreach ($this->lineItems as $lineItem) {
            $itemTotal = $lineItem->totalPrice;
            $total = $total->add($itemTotal);
        }

        return $total->add($this->shippingAmount);
    }
}
