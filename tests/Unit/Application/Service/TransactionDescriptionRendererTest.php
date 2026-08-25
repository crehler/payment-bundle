<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Tests\Unit\Application\Service;

use Crehler\PaymentBundle\Application\Service\TransactionDescriptionRenderer;
use Crehler\PaymentBundle\Domain\Entity\Customer;
use Crehler\PaymentBundle\Domain\Entity\Order\{BillingAddress, Order};
use Crehler\PaymentBundle\Domain\Event\TransactionDescriptionTokensEvent;
use Crehler\PaymentBundle\Domain\ValueObjects\Money;
use Crehler\PaymentBundle\Infrastructure\Configuration\BundleConfigField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function mb_check_encoding;
use function str_repeat;
use function strlen;

final class TransactionDescriptionRendererTest extends TestCase
{
    private const DOMAIN = 'CrehlerTpay.config';
    private const CONFIG_KEY = self::DOMAIN . '.' . BundleConfigField::TRANSACTION_DESCRIPTION->value;
    private const ORDER_ID = 'e7cd0a2b4f1e4c8ba9d6f30512c7bb41';
    private const SUB_ORDER_ID = '9b1c4e7d2a6f4b8390cd15e2f7a04c63';

    public function testRendersSingleOrderIdToken(): void
    {
        self::assertSame(self::ORDER_ID, $this->render('{{ orderId }}'));
    }

    /**
     * Without a listener the group token collapses to the single paid order, so a template using
     * it stays valid on shops that do not split deliveries at all.
     */
    public function testOrderIdsFallsBackToTheSingleOrderId(): void
    {
        self::assertSame(self::ORDER_ID, $this->render('{{ orderIds }}'));
    }

    public function testListenerCanOverwriteTheOrderIdsToken(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            TransactionDescriptionTokensEvent::EVENT_NAME,
            static function (TransactionDescriptionTokensEvent $event): void {
                $event->setToken('{{ orderIds }}', self::ORDER_ID . ',' . self::SUB_ORDER_ID);
            }
        );

        $rendered = $this->render('{{ orderNumber }} {{ orderIds }}', dispatcher: $dispatcher);

        self::assertSame('74449 ' . self::ORDER_ID . ',' . self::SUB_ORDER_ID, $rendered);
        self::assertSame(71, strlen($rendered), 'recommended template must stay well inside 128 bytes');
    }

    public function testEventExposesWhetherTheTemplateUsesAToken(): void
    {
        $seen = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            TransactionDescriptionTokensEvent::EVENT_NAME,
            static function (TransactionDescriptionTokensEvent $event) use (&$seen): void {
                $seen['orderIds'] = $event->usesToken('{{ orderIds }}');
                $seen['orderId'] = $event->usesToken('{{ orderId }}');
            }
        );

        $this->render('{{ orderNumber }}', dispatcher: $dispatcher);

        self::assertSame(['orderIds' => false, 'orderId' => false], $seen);

        $this->render('{{ orderNumber }} {{ orderIds }}', dispatcher: $dispatcher);

        self::assertSame(['orderIds' => true, 'orderId' => false], $seen);
    }

    public function testEmptyConfigFallsBackToOrderNumber(): void
    {
        self::assertSame('74449', $this->render(''));
    }

    /**
     * The new tokens are strictly opt-in. Neither the field's declared default nor the renderer's
     * fallback may put an order id in front of a gateway on a shop that did not ask for it.
     */
    public function testDefaultTemplateExposesNoOrderId(): void
    {
        self::assertSame('{{ orderNumber }}', BundleConfigField::TRANSACTION_DESCRIPTION->defaultValue());

        foreach (['', BundleConfigField::TRANSACTION_DESCRIPTION->defaultValue()] as $template) {
            $rendered = $this->render((string) $template);

            self::assertSame('74449', $rendered);
            self::assertStringNotContainsString(self::ORDER_ID, $rendered);
        }
    }

    public function testDefaultElementConfigStillDeclaresOrderNumberOnly(): void
    {
        $element = BundleConfigField::TRANSACTION_DESCRIPTION->getElement('CrehlerTpay.config');

        self::assertSame('{{ orderNumber }}', $element['config']['defaultValue']);
        self::assertSame('{{ orderNumber }}', $element['config']['placeholder']['pl-PL']);
    }

    public function testUnknownTokenIsLeftAsLiteral(): void
    {
        self::assertSame('{{ orderid }}', $this->render('{{ orderid }}'));
    }

    public function testNoLimitMeansNoTruncation(): void
    {
        $template = str_repeat('x', 200);

        self::assertSame($template, $this->render($template, maxLengthBytes: null));
    }

    public function testValueInsideTheLimitIsNotLoggedNorCut(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $renderer = $this->renderer('{{ orderNumber }} {{ orderId }}', logger: $logger);
        $rendered = $renderer->render(self::DOMAIN, $this->order(), null, 128);

        self::assertSame('74449 ' . self::ORDER_ID, $rendered);
    }

    public function testAsciiOverflowIsCutToTheByteLimitAndLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $renderer = $this->renderer(str_repeat('x', 200), logger: $logger);
        $rendered = $renderer->render(self::DOMAIN, $this->order(), null, 128);

        self::assertSame(128, strlen($rendered));
    }

    /**
     * Gateways validate with strlen(), so the limit counts bytes — and Polish characters cost two
     * of them. Cutting on a byte boundary must still leave valid UTF-8.
     */
    public function testUtf8OverflowIsCutOnACharacterBoundary(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $renderer = $this->renderer('{{ customerName }}', logger: $logger);
        $customer = new Customer(
            id: 'c0ffee00000000000000000000000001',
            customerNumber: '10000',
            email: 'zolc@example.com',
            firstName: str_repeat('Żółć', 30),
            lastName: 'Ćwikła',
        );
        $rendered = $renderer->render(self::DOMAIN, $this->order($customer), null, 128);

        self::assertLessThanOrEqual(128, strlen($rendered));
        self::assertTrue(mb_check_encoding($rendered, 'UTF-8'));
    }

    #[DataProvider('separatorProvider')]
    public function testStraySeparatorsAreTrimmed(string $template, string $expected): void
    {
        self::assertSame($expected, $this->render($template));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function separatorProvider(): array
    {
        return [
            'empty sales channel leaves a dangling dash' => ['{{ orderNumber }}  -  {{ salesChannelName }}', '74449'],
            'trailing comma from an empty tail' => ['{{ orderNumber }},', '74449'],
            'en dash separator' => ['– {{ orderNumber }} –', '74449'],
            'middle dot separator' => ['· {{ orderNumber }} ·', '74449'],
        ];
    }

    /**
     * The separator set contains multibyte characters, so a byte-wise trim() would eat the last
     * byte of an adjacent character and hand the gateway malformed UTF-8.
     */
    public function testTrimmingSeparatorsKeepsAdjacentMultibyteCharactersIntact(): void
    {
        $rendered = $this->render('Opis ✓');

        self::assertSame('Opis ✓', $rendered);
        self::assertTrue(mb_check_encoding($rendered, 'UTF-8'));
    }

    public function testTruncationDoesNotLeaveADanglingSeparator(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        // Cut lands right after the comma separating the two ids.
        $renderer = $this->renderer('{{ orderIds }},' . str_repeat('y', 40), logger: $logger);
        $rendered = $renderer->render(self::DOMAIN, $this->order(), null, 33);

        self::assertSame(self::ORDER_ID, $rendered);
    }

    private function render(
        string $template,
        ?EventDispatcherInterface $dispatcher = null,
        ?int $maxLengthBytes = null,
    ): string {
        return $this->renderer($template, $dispatcher)
            ->render(self::DOMAIN, $this->order(), null, $maxLengthBytes);
    }

    private function renderer(
        string $template,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ): TransactionDescriptionRenderer {
        $systemConfigService = $this->createStub(SystemConfigService::class);
        $systemConfigService->method('getString')
            ->willReturnCallback(static function (string $key) use ($template): string {
                self::assertSame(self::CONFIG_KEY, $key, 'renderer must read the shared bundle config field');

                return $template;
            });

        return new TransactionDescriptionRenderer(
            $systemConfigService,
            $this->createStub(EntityRepository::class),
            $dispatcher ?? new EventDispatcher(),
            $logger ?? new NullLogger(),
        );
    }

    private function order(?Customer $customer = null): Order
    {
        return new Order(
            id: self::ORDER_ID,
            orderNumber: '74449',
            totalAmount: new Money(12300, 'PLN'),
            netAmount: new Money(10000, 'PLN'),
            shippingAmount: new Money(0, 'PLN'),
            currencyCode: 'PLN',
            customer: $customer ?? new Customer(
                id: 'c0ffee00000000000000000000000001',
                customerNumber: '10000',
                email: 'jan@example.com',
                firstName: 'Jan',
                lastName: 'Kowalski',
            ),
            billingAddress: new BillingAddress(
                id: 'b1111100000000000000000000000001',
                firstName: 'Jan',
                lastName: 'Kowalski',
                street: 'Testowa 1',
                city: 'Gdańsk',
                zipCode: '80-000',
                countryCode: 'PL',
                countryName: 'Polska',
            ),
            lineItems: [],
        );
    }
}
