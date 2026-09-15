<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Tests\Unit\Infrastructure\Adapter;

use Crehler\PaymentBundle\Domain\Enum\PaymentType;
use Crehler\PaymentBundle\Domain\ValueObjects\PaymentSubMethod;
use Crehler\PaymentBundle\Infrastructure\Adapter\PaymentSubMethodAdapter;
use Crehler\PaymentBundle\Infrastructure\Port\PaymentSubMethodProvider;
use Crehler\PaymentBundle\Infrastructure\Resolver\PaymentMethodContractResolver;
use Crehler\PaymentBundle\Tests\Unit\Fixture\{BankFixtureHandler, PaywallFixtureHandler};
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The adapter matches providers on the declared type and nothing else — not on
 * usesGatewayChannels, which is a rendering hint the storefront reads and this class never
 * sees.
 *
 * That is why PAYWALL had to become its own case. A gateway's own selection page takes no
 * channel from the shop, but every provider in the family declares PAY_BY_LINK, so a
 * paywall method borrowing that type would be handed a bank list it ignores — invisible in
 * the storefront, which renders no widget for it, and plainly wrong over the Store API,
 * which serves the list per payment method to anyone who asks.
 */
final class PaymentSubMethodAdapterTest extends TestCase
{
    private const PAYMENT_METHOD_ID = '01a04f1ed4d6734989ca9b0918d3fc86';

    public function testAPaywallMethodIsServedNoChannels(): void
    {
        $provider = $this->bankProvider('mtransfer', 'ipko');

        $subMethods = $this->adapter($provider)->getPaymentSubMethods(
            $this->paymentMethod(PaywallFixtureHandler::class),
            49595,
            $this->createStub(SalesChannelContext::class),
        );

        self::assertSame([], $subMethods);
    }

    public function testAPaywallMethodIsNotClaimedByAnyProvider(): void
    {
        self::assertFalse(
            $this->adapter($this->bankProvider('mtransfer'))
                ->supportsPaymentMethod($this->paymentMethod(PaywallFixtureHandler::class)),
        );
    }

    /**
     * The other half of the same rule: the pay-by-link case must keep working, or this
     * would be a way of breaking every bank list in the family rather than fixing one.
     */
    public function testAPayByLinkMethodStillGetsTheBankList(): void
    {
        $provider = $this->bankProvider('mtransfer', 'ipko');
        $paymentMethod = $this->paymentMethod(BankFixtureHandler::class);
        $adapter = $this->adapter($provider);

        self::assertTrue($adapter->supportsPaymentMethod($paymentMethod));
        self::assertCount(
            2,
            $adapter->getPaymentSubMethods($paymentMethod, 49595, $this->createStub(SalesChannelContext::class)),
        );
    }

    private function adapter(PaymentSubMethodProvider $provider): PaymentSubMethodAdapter
    {
        return new PaymentSubMethodAdapter([$provider], new PaymentMethodContractResolver());
    }

    /**
     * A provider serving pay-by-link, the way all four plugins declare it.
     */
    private function bankProvider(string ...$providerIds): PaymentSubMethodProvider
    {
        $subMethods = [];
        foreach ($providerIds as $providerId) {
            $subMethods[] = new PaymentSubMethod(
                providerId: $providerId,
                name: $providerId,
                shopwareId: self::PAYMENT_METHOD_ID,
                mediaUrl: 'https://example.invalid/' . $providerId . '.png',
            );
        }

        $provider = $this->createStub(PaymentSubMethodProvider::class);
        $provider->method('supportedPaymentTypes')->willReturn([PaymentType::PAY_BY_LINK]);
        $provider->method('getPaymentSubMethods')->willReturn($subMethods);

        return $provider;
    }

    /**
     * @param class-string $handlerIdentifier
     */
    private function paymentMethod(string $handlerIdentifier): PaymentMethodEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(self::PAYMENT_METHOD_ID);
        $paymentMethod->setHandlerIdentifier($handlerIdentifier);

        return $paymentMethod;
    }
}
