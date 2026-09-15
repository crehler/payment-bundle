<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Tests\Unit\Infrastructure\Subscriber;

use Crehler\PaymentBundle\Application\Port\Driven\CustomerPaymentSubMethodRepositoryPort;
use Crehler\PaymentBundle\Application\Service\CustomerPaymentSubMethod\CustomerPaymentSubMethodService;
use Crehler\PaymentBundle\Application\Service\SubMethodSelectionValidator;
use Crehler\PaymentBundle\Domain\Contract\{CustomerRepositoryPort, PaymentSubMethodPort};
use Crehler\PaymentBundle\Domain\ValueObjects\PaymentSubMethod;
use Crehler\PaymentBundle\Infrastructure\Subscriber\SalesChannelContextSwitchSubscriber;
use Crehler\PaymentBundle\Shared\{AmountFormat, EnhancedLogger};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;

use function strtoupper;

/**
 * The one place that accepts a sub-method, and the third time it has drawn blood: WT-910
 * (unvalidated values persisted to the account), the slow-gateway 400, and now a value
 * left over from the method the customer just switched away from.
 *
 * That last one is what these cases pin down. The channel widget lives inside Shopware's
 * changePaymentForm, so switching the payment method serialises whatever channel is still
 * checked under the previous one — a wallet channel arriving with a bank transfer. It used
 * to throw, which meant picking a channel and then changing your mind was a hard 400.
 */
final class SalesChannelContextSwitchSubscriberTest extends TestCase
{
    private const WALLET_ID = '01a04f1ed4d6734989ca9b09187dc060';
    private const BANK_ID = '01a04f1ed4d6734989ca9b0918d3fc86';

    public function testAChannelLeftOverFromAnotherMethodIsIgnoredRatherThanRejected(): void
    {
        // gpay was chosen under the wallet; the customer is now switching to bank transfer.
        $event = $this->switchEvent(self::BANK_ID, [
            'paymentSubMethod' => 'gpay',
            'paymentSubMethodFor' => self::WALLET_ID,
        ]);

        // No exception, and the bank's channel list is never even consulted — there is
        // nothing to validate against, because the value is not about this method.
        $this->subscriber($this->neverAskedPort())->onSalesChannelContextSwitch($event);

        $this->addToAssertionCount(1);
    }

    /**
     * The pairing is what carries meaning, so a matching pairing still has to be checked.
     * This is the WT-910 case: a value aimed at the method actually being set.
     */
    public function testAnUnofferedChannelForTheMethodBeingSetIsStillRejected(): void
    {
        $event = $this->switchEvent(self::BANK_ID, [
            'paymentSubMethod' => 'gpay',
            'paymentSubMethodFor' => self::BANK_ID,
        ]);

        $this->expectException(RoutingException::class);

        $this->subscriber($this->portOffering('mtransfer', 'ipko'))->onSalesChannelContextSwitch($event);
    }

    /**
     * A client that does not send the pairing — an older storefront, or a Store API caller
     * setting the sub-method directly — keeps the strict behaviour. Treating a missing
     * pairing as "must be stale" would reopen exactly the hole WT-910 closed.
     */
    public function testAMissingPairingKeepsTheStrictCheck(): void
    {
        $event = $this->switchEvent(self::BANK_ID, ['paymentSubMethod' => 'gpay']);

        $this->expectException(RoutingException::class);

        $this->subscriber($this->portOffering('mtransfer'))->onSalesChannelContextSwitch($event);
    }

    public function testAnOfferedChannelForItsOwnMethodIsAccepted(): void
    {
        $event = $this->switchEvent(self::BANK_ID, [
            'paymentSubMethod' => 'mtransfer',
            'paymentSubMethodFor' => self::BANK_ID,
        ]);

        $this->subscriber($this->portOffering('mtransfer', 'ipko'))->onSalesChannelContextSwitch($event);

        $this->addToAssertionCount(1);
    }

    /**
     * Hex ids reach the storefront upper-cased in places, and a case-sensitive comparison
     * would read a method's own channel as belonging to someone else and silently drop a
     * choice the customer just made.
     */
    public function testThePairingComparisonIgnoresIdCase(): void
    {
        $event = $this->switchEvent(self::BANK_ID, [
            'paymentSubMethod' => 'mtransfer',
            'paymentSubMethodFor' => strtoupper(self::BANK_ID),
        ]);

        $this->subscriber($this->portOffering('mtransfer'))->onSalesChannelContextSwitch($event);

        $this->addToAssertionCount(1);
    }

    public function testARequestWithoutASubMethodIsNoneOfOurBusiness(): void
    {
        $event = $this->switchEvent(self::BANK_ID, ['paymentMethodId' => self::BANK_ID]);

        $this->subscriber($this->neverAskedPort())->onSalesChannelContextSwitch($event);

        $this->addToAssertionCount(1);
    }

    /**
     * paymentSubMethod[]=x would become the string "Array" under a cast and be stored as a
     * channel code.
     */
    public function testANonScalarValueIsRejected(): void
    {
        $event = $this->switchEvent(self::BANK_ID, ['paymentSubMethod' => ['gpay']]);

        $this->expectException(RoutingException::class);

        $this->subscriber($this->neverAskedPort())->onSalesChannelContextSwitch($event);
    }

    private function subscriber(PaymentSubMethodPort $port): SalesChannelContextSwitchSubscriber
    {
        $cart = new Cart('test-token');

        $cartService = $this->createStub(CartService::class);
        $cartService->method('getCart')->willReturn($cart);

        return new SalesChannelContextSwitchSubscriber(
            new CustomerPaymentSubMethodService($this->createStub(CustomerPaymentSubMethodRepositoryPort::class)),
            $this->createStub(CustomerRepositoryPort::class),
            new SubMethodSelectionValidator(
                $port,
                $cartService,
                new AmountFormat(),
                new EnhancedLogger(new NullLogger()),
            ),
            new RequestStack(),
            new EnhancedLogger(new NullLogger()),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function switchEvent(string $paymentMethodId, array $data): SalesChannelContextSwitchEvent
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId($paymentMethodId);

        // No customer: the account write is a separate concern, and leaving it out keeps
        // these cases about the accept/reject decision alone.
        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getPaymentMethod')->willReturn($paymentMethod);
        $context->method('getCustomer')->willReturn(null);
        $context->method('getToken')->willReturn('test-token');

        return new SalesChannelContextSwitchEvent($context, new RequestDataBag($data));
    }

    private function portOffering(string ...$providerIds): PaymentSubMethodPort
    {
        $subMethods = [];
        foreach ($providerIds as $providerId) {
            $subMethods[] = new PaymentSubMethod(
                providerId: $providerId,
                name: $providerId,
                shopwareId: self::BANK_ID,
                mediaUrl: 'https://data.imoje.pl/img/pay/' . $providerId . '.png',
            );
        }

        $port = $this->createStub(PaymentSubMethodPort::class);
        $port->method('getPaymentSubMethods')->willReturn($subMethods);

        return $port;
    }

    /**
     * Asking the gateway at all would mean the stale value was being validated instead of
     * dropped, so the port throws if it is touched.
     */
    private function neverAskedPort(): PaymentSubMethodPort
    {
        $port = $this->createMock(PaymentSubMethodPort::class);
        $port->expects(self::never())->method('getPaymentSubMethods');

        return $port;
    }
}
