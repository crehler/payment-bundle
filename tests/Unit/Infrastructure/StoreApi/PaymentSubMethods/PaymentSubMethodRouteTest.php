<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Tests\Unit\Infrastructure\StoreApi\PaymentSubMethods;

use Crehler\PaymentBundle\Domain\Contract\PaymentSubMethodServicePort;
use Crehler\PaymentBundle\Domain\Entity\PaymentMethod;
use Crehler\PaymentBundle\Domain\ValueObjects\PaymentSubMethod;
use Crehler\PaymentBundle\Infrastructure\StoreApi\PaymentSubMethods\PaymentSubMethodRoute;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * The amount is the whole reason this route takes a query parameter: channels are filtered
 * against it, so whatever reaches the provider decides what the customer is offered.
 *
 * It used to default to a hardcoded 100 PLN when the parameter was missing, which reported
 * ING's instalments as non-existent for every cart. Requiring it closed that. These cases
 * cover what the requirement still has to reject once the parameter is present.
 */
final class PaymentSubMethodRouteTest extends TestCase
{
    private const PAYMENT_METHOD_ID = '01a04f1ed4d6734989ca9b0918d3fc86';

    /**
     * A cart total is never below zero, and a negative one is not inert: the provider
     * filters with $paymentValue < $raw->minAmount, so -1 drops every channel that has a
     * lower bound and hands back a list that looks like a gateway offering nothing.
     */
    public function testANegativeAmountIsRejectedBeforeItReachesTheProvider(): void
    {
        $route = new PaymentSubMethodRoute($this->neverAskedPort());

        $this->expectException(RoutingException::class);

        $route->get(self::PAYMENT_METHOD_ID, new Request(['paymentValue' => '-1']), $this->context());
    }

    public function testTheSameRejectionAppliesToTheCurrentMethodRoute(): void
    {
        $route = new PaymentSubMethodRoute($this->neverAskedPort());

        $this->expectException(RoutingException::class);

        $route->getCurrent(new Request(['paymentValue' => '-1']), $this->context());
    }

    public function testAMissingAmountIsStillRejectedRatherThanGuessed(): void
    {
        $route = new PaymentSubMethodRoute($this->neverAskedPort());

        $this->expectException(RoutingException::class);

        $route->get(self::PAYMENT_METHOD_ID, new Request(), $this->context());
    }

    /**
     * Zero is left alone deliberately. A fully discounted cart is a real state, and the
     * gateway is the right place to decide whether it has a channel for it.
     */
    public function testZeroIsAllowedThrough(): void
    {
        $port = $this->portReturningOneChannel();
        $route = new PaymentSubMethodRoute($port);

        $route->get(self::PAYMENT_METHOD_ID, new Request(['paymentValue' => '0']), $this->context());

        self::assertSame([0], $port->seen);
    }

    public function testAValidAmountReachesTheProviderUnchanged(): void
    {
        $port = $this->portReturningOneChannel();
        $route = new PaymentSubMethodRoute($port);

        $response = $route->get(self::PAYMENT_METHOD_ID, new Request(['paymentValue' => '49595']), $this->context());

        self::assertSame([49595], $port->seen);
        self::assertCount(1, $response->getPaymentSubMethods());
    }

    private function neverAskedPort(): PaymentSubMethodServicePort
    {
        $port = $this->createMock(PaymentSubMethodServicePort::class);
        $port->expects(self::never())->method('getPayment');

        return $port;
    }

    private function portReturningOneChannel(): PaymentSubMethodServicePort
    {
        return new class implements PaymentSubMethodServicePort {
            /** @var array<int> */
            public array $seen = [];

            public function getPayment(string $paymentId, int $paymentValue, SalesChannelContext $context): PaymentMethod
            {
                $this->seen[] = $paymentValue;

                return new PaymentMethod([new PaymentSubMethod(
                    providerId: 'mtransfer',
                    name: 'mBank',
                    shopwareId: '01a04f1ed4d6734989ca9b0918d3fc86',
                    mediaUrl: 'https://data.imoje.pl/img/pay/mtransfer.png',
                )]);
            }
        };
    }

    private function context(): SalesChannelContext
    {
        return $this->createStub(SalesChannelContext::class);
    }
}
