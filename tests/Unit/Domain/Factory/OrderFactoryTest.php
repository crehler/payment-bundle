<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Tests\Unit\Domain\Factory;

use Crehler\PaymentBundle\Domain\Factory\{CustomerFactory, OrderFactory};
use Crehler\PaymentBundle\Shared\AmountFormat;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\{OrderDeliveryCollection, OrderDeliveryEntity};
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;

use function md5;
use function substr;

/**
 * The shipping address is the address the goods go to, and it is NOT the billing address.
 * PayNow's PayPo refuses to underwrite without both, so a silent substitution here would
 * hand the gateway a confident answer nobody checked — hence the third case.
 */
final class OrderFactoryTest extends TestCase
{
    public function testTheShippingAddressComesFromTheDelivery(): void
    {
        $order = $this->factory()->createOrder($this->orderEntity(
            $this->address('Dostawcza 7/2', 'Gdańsk', '80-001'),
        ));

        self::assertNotNull($order->shippingAddress);
        self::assertSame('Dostawcza 7/2', $order->shippingAddress->street);
        self::assertSame('Gdańsk', $order->shippingAddress->city);
        self::assertSame('80-001', $order->shippingAddress->zipCode);
        self::assertSame('PL', $order->shippingAddress->countryCode);
    }

    /**
     * A download-only cart creates no delivery. Null is the honest answer; a provider that
     * needs the address can then say so instead of shipping to the billing address.
     */
    public function testAnOrderWithoutADeliveryHasNoShippingAddress(): void
    {
        $order = $this->factory()->createOrder($this->orderEntity(null));

        self::assertNull($order->shippingAddress);
    }

    public function testTheShippingAddressIsNotSilentlyTheBillingAddress(): void
    {
        $order = $this->factory()->createOrder($this->orderEntity(
            $this->address('Dostawcza 7/2', 'Gdańsk', '80-001'),
        ));

        self::assertSame('Rozliczeniowa 1', $order->billingAddress->street);
        self::assertNotSame($order->billingAddress->street, $order->shippingAddress?->street);
    }

    /**
     * Gateways want a BCP47 tag and Shopware already stores one, so the order's own
     * language is the answer rather than the shop default or the request locale.
     */
    public function testTheLocaleComesFromTheOrdersOwnLanguage(): void
    {
        $order = $this->factory()->createOrder($this->orderEntity(null));

        self::assertSame('pl-PL', $order->locale);
    }

    /**
     * Null, not a guessed default. Every gateway has its own fallback, and inventing one
     * here would silently pick the language a customer reads their payment page in.
     */
    public function testAMissingLanguageAssociationLeavesTheLocaleNull(): void
    {
        $order = $this->factory()->createOrder($this->orderEntity(null, withLanguage: false));

        self::assertNull($order->locale);
    }

    /**
     * Shopware types OrderAddressEntity::getZipcode() as ?string, and it is genuinely null
     * for countries that have no postal code at all. A non-nullable zipCode turned that
     * into a TypeError here — while assembling the order, before any gateway was called,
     * so every payment on such an address died rather than being rejected by the gateway.
     */
    public function testAnAddressWithoutAPostalCodeBuildsInsteadOfThrowing(): void
    {
        $order = $this->factory()->createOrder($this->orderEntity(
            $this->address('Dostawcza 7/2', 'Dublin', null),
            billingZipCode: null,
        ));

        self::assertNull($order->billingAddress->zipCode);
        self::assertNotNull($order->shippingAddress);
        self::assertNull($order->shippingAddress->zipCode);
    }

    private function factory(): OrderFactory
    {
        return new OrderFactory(new AmountFormat(), new CustomerFactory());
    }

    private function orderEntity(
        ?OrderAddressEntity $shippingAddress,
        bool $withLanguage = true,
        ?string $billingZipCode = '00-950',
    ): OrderEntity
    {
        $currency = new CurrencyEntity();
        $currency->setId('019ed0356345700000000000000000c1');
        $currency->setIsoCode('PLN');

        $customer = new CustomerEntity();
        $customer->setId('019ed0356345700000000000000000c2');
        $customer->setCustomerNumber('10001');
        $customer->setEmail('jan.kowalski@example.com');
        $customer->setFirstName('Jan');
        $customer->setLastName('Kowalski');
        $customer->setGuest(false);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId('019ed0356345700000000000000000c3');
        $orderCustomer->setCustomer($customer);

        $order = new OrderEntity();
        $order->setId('019ed0356345700000000000000000c4');
        $order->setOrderNumber('10001');
        $order->setAmountTotal(123.45);
        $order->setAmountNet(100.37);
        $order->setShippingTotal(0.0);
        $order->setCurrency($currency);
        $order->setOrderCustomer($orderCustomer);
        $order->setBillingAddress($this->address('Rozliczeniowa 1', 'Warszawa', $billingZipCode));
        $order->setLineItems(new OrderLineItemCollection());
        $order->setSalesChannelId('019ed035634570b09e40580c5ba4654a');

        if ($withLanguage) {
            $locale = new LocaleEntity();
            $locale->setId('019ed0356345700000000000000000c7');
            $locale->setCode('pl-PL');

            $language = new LanguageEntity();
            $language->setId('019ed0356345700000000000000000c8');
            $language->setLocale($locale);

            $order->setLanguage($language);
        }

        if ($shippingAddress !== null) {
            $delivery = new OrderDeliveryEntity();
            $delivery->setId('019ed0356345700000000000000000c5');
            $delivery->setShippingOrderAddress($shippingAddress);

            $order->setDeliveries(new OrderDeliveryCollection([$delivery]));
        }

        return $order;
    }

    private function address(string $street, string $city, ?string $zipCode): OrderAddressEntity
    {
        $country = new CountryEntity();
        $country->setId('019ed0356345700000000000000000c6');
        $country->setIso('PL');
        $country->setName('Polska');

        $address = new OrderAddressEntity();
        $address->setId('019ed035634570000000000000' . substr(md5($street), 0, 6));
        $address->setFirstName('Jan');
        $address->setLastName('Kowalski');
        $address->setStreet($street);
        $address->setCity($city);
        $address->setZipcode($zipCode);
        $address->setCountry($country);

        return $address;
    }
}
