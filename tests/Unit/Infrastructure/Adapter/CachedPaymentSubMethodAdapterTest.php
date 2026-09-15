<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Tests\Unit\Infrastructure\Adapter;

use Crehler\PaymentBundle\Domain\Contract\PaymentSubMethodPort;
use Crehler\PaymentBundle\Domain\ValueObjects\PaymentSubMethod;
use Crehler\PaymentBundle\Infrastructure\Adapter\CachedPaymentSubMethodAdapter;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\{AbstractLogger, LoggerInterface, NullLogger};
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * The point of this decorator is that one checkout render stops asking a gateway the same
 * question four times, so the tests count calls rather than inspect return values.
 */
final class CachedPaymentSubMethodAdapterTest extends TestCase
{
    private const SALES_CHANNEL_ID = '019ed035634570b09e40580c5ba4654a';
    private const PAYMENT_METHOD_ID = '01a04f1ed4d6734989ca9b09187dc060';

    public function testSecondCallInTheSameRequestDoesNotReachTheGateway(): void
    {
        $inner = $this->countingPort();
        $adapter = $this->adapter($inner, new ArrayAdapter());

        $adapter->getPaymentSubMethods($this->paymentMethod(), 49595, $this->context());
        $adapter->getPaymentSubMethods($this->paymentMethod(), 49595, $this->context());

        self::assertSame(1, $inner->calls, 'the memoised second read still hit the gateway');
    }

    public function testTheAmountIsPartOfTheKey(): void
    {
        $inner = $this->countingPort();
        $adapter = $this->adapter($inner, new ArrayAdapter());

        // 200 PLN is below ING's instalment threshold and 495.95 is above it, so these two
        // carts must not share an answer.
        $adapter->getPaymentSubMethods($this->paymentMethod(), 20000, $this->context());
        $adapter->getPaymentSubMethods($this->paymentMethod(), 49595, $this->context());

        self::assertSame(2, $inner->calls);
    }

    public function testTheSalesChannelIsPartOfTheKey(): void
    {
        $inner = $this->countingPort();
        $adapter = $this->adapter($inner, new ArrayAdapter());

        $adapter->getPaymentSubMethods($this->paymentMethod(), 49595, $this->context());
        $adapter->getPaymentSubMethods($this->paymentMethod(), 49595, $this->context('other-sales-channel'));

        self::assertSame(2, $inner->calls, 'one shop was served another shop\'s channel list');
    }

    public function testAnEmptyListIsCachedRatherThanRetriedEveryTime(): void
    {
        $inner = $this->countingPort(returns: []);
        $adapter = $this->adapter($inner, new ArrayAdapter());

        $adapter->getPaymentSubMethods($this->paymentMethod(), 49595, $this->context());
        $adapter->getPaymentSubMethods($this->paymentMethod(), 49595, $this->context());

        self::assertSame(1, $inner->calls, 'a gateway that is down would be hammered on every render');
    }

    /**
     * A broken cache backend must degrade to a direct call, never take the checkout with
     * it — the whole reason this decorator exists is availability.
     */
    public function testABrokenCachePoolStillServesTheChannels(): void
    {
        $inner = $this->countingPort();
        $adapter = $this->adapter($inner, $this->throwingPool());

        $result = $adapter->getPaymentSubMethods($this->paymentMethod(), 49595, $this->context());

        self::assertCount(1, $result);
        self::assertSame(1, $inner->calls);
    }

    /**
     * A pool is allowed to refuse a write by returning false instead of throwing, and that
     * path used to be discarded. The customer is unaffected — they still get their channels
     * — but the decorator has quietly stopped decorating, and without a log line the only
     * visible symptom is gateway load nobody can account for.
     */
    public function testAPoolThatRefusesTheWriteSaysSoInTheLog(): void
    {
        $inner = $this->countingPort();
        $logger = $this->recordingLogger();

        $result = $this->adapter($inner, $this->refusingSavePool(), $logger)
            ->getPaymentSubMethods($this->paymentMethod(), 49595, $this->context());

        self::assertCount(1, $result, 'a refused cache write must not cost the customer the channel list');
        self::assertSame(1, $inner->calls);
        self::assertCount(1, $logger->errors, 'the refused write went unreported');
        self::assertStringContainsString('refused to store', $logger->errors[0]);
    }

    public function testSupportsPaymentMethodIsNotCachedAndDelegatesStraightThrough(): void
    {
        $inner = $this->countingPort();
        $adapter = $this->adapter($inner, new ArrayAdapter());

        self::assertTrue($adapter->supportsPaymentMethod($this->paymentMethod()));
        self::assertTrue($adapter->supportsPaymentMethod($this->paymentMethod()));
        self::assertSame(2, $inner->supportsCalls);
    }

    private function adapter(
        PaymentSubMethodPort $inner,
        CacheItemPoolInterface $cache,
        ?LoggerInterface $logger = null,
    ): CachedPaymentSubMethodAdapter {
        return new CachedPaymentSubMethodAdapter($inner, $cache, new EnhancedLogger($logger ?? new NullLogger()));
    }

    /**
     * @param array<PaymentSubMethod>|null $returns
     */
    private function countingPort(?array $returns = null): PaymentSubMethodPort
    {
        return new class($returns ?? [new PaymentSubMethod(
            providerId: 'gpay',
            name: 'Google Pay',
            shopwareId: self::PAYMENT_METHOD_ID,
            mediaUrl: 'https://data.imoje.pl/img/pay/gpay.png',
        )]) implements PaymentSubMethodPort {
            public int $calls = 0;

            public int $supportsCalls = 0;

            /** @param array<PaymentSubMethod> $returns */
            public function __construct(private readonly array $returns)
            {
            }

            public function getPaymentSubMethods(
                PaymentMethodEntity $paymentMethodEntity,
                int $paymentValue,
                SalesChannelContext $context,
            ): array {
                ++$this->calls;

                return $this->returns;
            }

            public function supportsPaymentMethod(PaymentMethodEntity $paymentMethodEntity): bool
            {
                ++$this->supportsCalls;

                return true;
            }
        };
    }

    private function throwingPool(): CacheItemPoolInterface
    {
        return new class implements CacheItemPoolInterface {
            public function getItem(string $key): never
            {
                throw new class ('pool down') extends \RuntimeException implements \Psr\Cache\CacheException {};
            }

            public function getItems(array $keys = []): iterable
            {
                return [];
            }

            public function hasItem(string $key): bool
            {
                return false;
            }

            public function clear(): bool
            {
                return true;
            }

            public function deleteItem(string $key): bool
            {
                return true;
            }

            public function deleteItems(array $keys): bool
            {
                return true;
            }

            public function save(\Psr\Cache\CacheItemInterface $item): bool
            {
                return false;
            }

            public function saveDeferred(\Psr\Cache\CacheItemInterface $item): bool
            {
                return false;
            }

            public function commit(): bool
            {
                return true;
            }
        };
    }

    /**
     * Reads work, writes are declined — a full Redis, or an item past the adapter's size
     * limit. Distinct from throwingPool(), where getItem() itself blows up.
     */
    private function refusingSavePool(): CacheItemPoolInterface
    {
        return new class(new ArrayAdapter()) implements CacheItemPoolInterface {
            public function __construct(private readonly CacheItemPoolInterface $inner)
            {
            }

            public function getItem(string $key): \Psr\Cache\CacheItemInterface
            {
                return $this->inner->getItem($key);
            }

            public function getItems(array $keys = []): iterable
            {
                return $this->inner->getItems($keys);
            }

            public function hasItem(string $key): bool
            {
                return $this->inner->hasItem($key);
            }

            public function clear(): bool
            {
                return $this->inner->clear();
            }

            public function deleteItem(string $key): bool
            {
                return $this->inner->deleteItem($key);
            }

            public function deleteItems(array $keys): bool
            {
                return $this->inner->deleteItems($keys);
            }

            public function save(\Psr\Cache\CacheItemInterface $item): bool
            {
                return false;
            }

            public function saveDeferred(\Psr\Cache\CacheItemInterface $item): bool
            {
                return false;
            }

            public function commit(): bool
            {
                return false;
            }
        };
    }

    private function recordingLogger(): LoggerInterface
    {
        return new class extends AbstractLogger {
            /** @var array<string> */
            public array $errors = [];

            public function log(mixed $level, \Stringable|string $message, array $context = []): void
            {
                if ((string) $level === 'error') {
                    $this->errors[] = (string) $message;
                }
            }
        };
    }

    private function paymentMethod(): PaymentMethodEntity
    {
        $entity = new PaymentMethodEntity();
        $entity->setId(self::PAYMENT_METHOD_ID);

        return $entity;
    }

    private function context(string $salesChannelId = self::SALES_CHANNEL_ID): SalesChannelContext
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('PLN');

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn($salesChannelId);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getCurrency')->willReturn($currency);
        $context->method('getLanguageInfo')->willReturn(new LanguageInfo('Polski', 'pl-PL'));

        return $context;
    }
}
