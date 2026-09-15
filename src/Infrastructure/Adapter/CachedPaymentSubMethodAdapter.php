<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Adapter;

use Crehler\PaymentBundle\Domain\Contract\PaymentSubMethodPort;
use Crehler\PaymentBundle\Shared\EnhancedLogger;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\{AsDecorator, Autowire, AutowireDecorated};
use Throwable;

use function array_map;
use function implode;
use function is_array;
use function serialize;
use function sha1;
use function unserialize;

/**
 * Keeps the gateway from being asked the same question twice.
 *
 * Sits on the composite rather than inside any one provider, so all four payment plugins
 * benefit and none of them has to grow its own cache.
 *
 * Two layers, because they solve different problems:
 *
 * 1. Per-request memoisation. One checkout render asked IngPay for channels FOUR times
 *    with an identical request body — the loop runs per payment method, and the four
 *    families each triggered their own POST to get-payment-methods. Differentiation
 *    happened client-side, after the call. This collapses them to one.
 * 2. A short shared TTL, so the next visitor in the same sales channel with the same cart
 *    total does not pay for the round trip either.
 *
 * The key covers the sales channel, because a gateway's channels and their limits are
 * per shop: at ING they hang off serviceId, which is configured per sales channel. It also
 * covers the payment method, the amount, the currency and the locale — every input the
 * providers actually send. Getting this wrong would serve one shop's bank list to another.
 *
 * A cache read or write that fails must never take the checkout down with it, so both are
 * wrapped: on error the call goes straight through to the gateway.
 */
#[AsDecorator(decorates: PaymentSubMethodAdapter::class)]
final class CachedPaymentSubMethodAdapter implements PaymentSubMethodPort
{
    /**
     * Short on purpose. A channel list changes rarely, but when a gateway takes a channel
     * offline — ING currently reports Apple Pay as AVAILABILITY_LIMIT — the shop should
     * follow within minutes, not at the end of a shift.
     */
    private const TTL_SECONDS = 300;

    /** @var array<string, array<mixed>> */
    private array $memo = [];

    public function __construct(
        #[AutowireDecorated]
        private readonly PaymentSubMethodPort $inner,
        #[Autowire(service: 'cache.object')]
        private readonly CacheItemPoolInterface $cache,
        private readonly EnhancedLogger $logger,
    ) {
    }

    public function getPaymentSubMethods(
        PaymentMethodEntity $paymentMethodEntity,
        int $paymentValue,
        SalesChannelContext $context,
    ): array {
        $key = $this->cacheKey($paymentMethodEntity, $paymentValue, $context);

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        try {
            $item = $this->cache->getItem($key);
        } catch (Throwable $e) {
            $this->logger->error('Payment sub-method cache unavailable; asking the gateway directly', [
                'exception' => $e->getMessage(),
            ]);

            return $this->memo[$key] = $this->inner->getPaymentSubMethods($paymentMethodEntity, $paymentValue, $context);
        }

        if ($item->isHit()) {
            // Stored serialised: PaymentSubMethod is a value object, and the pool may be
            // backed by Redis, where only a string round-trips predictably.
            $cached = unserialize((string) $item->get(), ['allowed_classes' => true]);

            if (is_array($cached)) {
                return $this->memo[$key] = $cached;
            }
        }

        $subMethods = $this->inner->getPaymentSubMethods($paymentMethodEntity, $paymentValue, $context);

        // An empty list is cached too: a gateway that is down or a cart below every
        // channel's minimum both produce one, and re-asking on every render is exactly the
        // load this decorator exists to remove. TTL keeps it from sticking.
        try {
            // PSR-6 lets a pool decline a write by returning false rather than throwing —
            // a full Redis, a value past the adapter's item size limit, a backend in
            // read-only failover. Dropping that result leaves the decorator silently not
            // decorating: the memo still covers this request, but every later one goes
            // back to the gateway with nothing in the log to explain the load.
            if (!$this->cache->save($item->set(serialize($subMethods))->expiresAfter(self::TTL_SECONDS))) {
                $this->logger->error('Cache pool refused to store payment sub-methods; the gateway will be asked again next request', [
                    'key' => $key,
                ]);
            }
        } catch (Throwable $e) {
            $this->logger->error('Could not store payment sub-methods in the cache', [
                'exception' => $e->getMessage(),
            ]);
        }

        return $this->memo[$key] = $subMethods;
    }

    public function supportsPaymentMethod(PaymentMethodEntity $paymentMethodEntity): bool
    {
        // Type comparison against declared lists — no gateway call, nothing to cache.
        return $this->inner->supportsPaymentMethod($paymentMethodEntity);
    }

    private function cacheKey(
        PaymentMethodEntity $paymentMethodEntity,
        int $paymentValue,
        SalesChannelContext $context,
    ): string {
        $parts = [
            $context->getSalesChannelId(),
            $paymentMethodEntity->getId(),
            (string) $paymentValue,
            $context->getCurrency()->getIsoCode(),
            $context->getLanguageInfo()->localeCode,
        ];

        // Hashed because a PSR-6 key may not contain the reserved characters that a locale
        // or a currency code can carry, and the raw concatenation would exceed the length
        // some adapters accept.
        return 'cr_payment_sub_methods_' . sha1(implode('|', array_map(strval(...), $parts)));
    }
}
