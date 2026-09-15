<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Tests\Unit\Infrastructure\Resolver;

use Crehler\PaymentBundle\Domain\Enum\PaymentType;
use Crehler\PaymentBundle\Infrastructure\Resolver\PaymentMethodContractResolver;
use Crehler\PaymentBundle\Tests\Unit\Fixture\{BlikFixtureHandler, ForeignFixtureHandler, MandatoryChannelFixtureHandler, WalletFixtureHandler};
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

/**
 * The resolver is a pure function of the entity: it reads the contract statics off the
 * class named in handler_identifier. No database, no container, no HTTP — every case here
 * builds the entity in memory, which is the whole point of moving the answer out of a name
 * guess and out of an install-time flag.
 */
final class PaymentMethodContractResolverTest extends TestCase
{
    private PaymentMethodContractResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PaymentMethodContractResolver();
    }

    public function testReadsTheContractDeclaredByTheHandler(): void
    {
        $contract = $this->resolver->resolve($this->paymentMethod(WalletFixtureHandler::class));

        self::assertNotNull($contract);
        self::assertSame(PaymentType::WALLET, $contract->type);
        self::assertTrue($contract->usesGatewayChannels);
    }

    /**
     * The class name says "Wallet", the old classification wanted "EWalletHandler". A name
     * that does not match the abandoned convention must still resolve — that mismatch is
     * exactly what silently emptied the wallet and instalment channel lists in checkout.
     */
    public function testResolvesAHandlerNamedOutsideTheOldConvention(): void
    {
        self::assertSame(
            PaymentType::WALLET,
            $this->resolver->resolve($this->paymentMethod(WalletFixtureHandler::class))?->type,
        );
    }

    /**
     * "Channels come from the gateway" and "the gateway needs one picked here" are two
     * different facts, and conflating them would break three plugins: Tpay accepts
     * channelId 0 and PayU an empty value, both meaning "show your own bank list".
     */
    public function testRequiringAChannelIsSeparateFromHavingChannels(): void
    {
        $tolerant = $this->resolver->resolve($this->paymentMethod(WalletFixtureHandler::class));
        $strict = $this->resolver->resolve($this->paymentMethod(MandatoryChannelFixtureHandler::class));

        self::assertNotNull($tolerant);
        self::assertNotNull($strict);

        self::assertTrue($tolerant->usesGatewayChannels);
        self::assertFalse($tolerant->requiresGatewayChannel, 'the default must stay tolerant, or Tpay and PayU lose their gateway-side chooser');

        self::assertTrue($strict->usesGatewayChannels);
        self::assertTrue($strict->requiresGatewayChannel);
    }

    public function testCarriesUsesGatewayChannelsFalseThrough(): void
    {
        $contract = $this->resolver->resolve($this->paymentMethod(BlikFixtureHandler::class));

        self::assertNotNull($contract);
        self::assertSame(PaymentType::BLIK, $contract->type);
        self::assertFalse($contract->usesGatewayChannels);
    }

    /**
     * A payment_method row outlives the plugin that installed it, so handler_identifier
     * routinely points at a class that is no longer autoloadable. That must degrade to
     * null, not blow up: the bundle has already been bitten by instantiating such a class
     * without a class_exists guard, mid-install.
     */
    public function testReturnsNullWhenTheHandlerClassNoLongerExists(): void
    {
        $entity = $this->paymentMethod('Crehler\\Uninstalled\\Handler\\GoneHandler');

        self::assertNull($this->resolver->resolve($entity));
    }

    public function testReturnsNullForAThirdPartyHandler(): void
    {
        self::assertNull($this->resolver->resolve($this->paymentMethod(ForeignFixtureHandler::class)));
    }

    public function testReturnsNullWhenThereIsNoHandlerAtAll(): void
    {
        self::assertNull($this->resolver->resolve(new PaymentMethodEntity()));
    }

    private function paymentMethod(string $handlerIdentifier): PaymentMethodEntity
    {
        $entity = new PaymentMethodEntity();
        $entity->setHandlerIdentifier($handlerIdentifier);

        return $entity;
    }
}
