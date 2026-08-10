<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Subscriber;

use Crehler\PaymentBundle\Infrastructure\Configuration\PaymentBundleConfigService;
use Crehler\PaymentBundle\Infrastructure\Resolver\OrderPaymentMethodResolver;
use Crehler\PaymentBundle\Infrastructure\Struct\CheckPaymentWaitingStruct;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Publishes the configured payment-confirmation waiting window to the finish page.
 *
 * The check-payment component lives in this bundle and is included by every provider
 * plugin, so it cannot read `<Plugin>.config.crPaymentWaitingTime` itself — it does not
 * know which plugin owns the order. Resolving it here keeps provider plugins free of
 * per-plugin Twig wiring.
 */
#[Autoconfigure(tags: [['name' => 'kernel.event_subscriber']])]
final readonly class CheckoutFinishPageLoadedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PaymentBundleConfigService $bundleConfigService,
        private OrderPaymentMethodResolver $orderPaymentMethodResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutFinishPageLoadedEvent::class => 'onCheckoutFinishPageLoaded',
        ];
    }

    public function onCheckoutFinishPageLoaded(CheckoutFinishPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $context = $event->getSalesChannelContext();

        // The order's own payment method is authoritative; the session context is only a
        // fallback for the (unlikely) case of an order loaded without its transactions.
        $paymentMethod = $this->orderPaymentMethodResolver->resolve($page->getOrder())
            ?? $context->getPaymentMethod();

        $page->addExtension(
            CheckPaymentWaitingStruct::API_ALIAS,
            new CheckPaymentWaitingStruct(
                $this->bundleConfigService->getWaitingTimeMs($paymentMethod, $context->getSalesChannelId()),
            ),
        );
    }
}
