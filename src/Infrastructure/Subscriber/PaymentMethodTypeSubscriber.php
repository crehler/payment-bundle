<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Subscriber;

use Crehler\PaymentBundle\Infrastructure\Extension\PaymentMethodExtension;
use Crehler\PaymentBundle\Infrastructure\Resolver\PaymentMethodContractResolver;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Populates the crPaymentContract runtime field on PaymentMethodEntity.
 *
 * Attaches what the method's handler declares — its PaymentType and whether its channels
 * come from the gateway — so the storefront and the Store API read one answer instead of
 * re-deriving it. Methods not served by a Crehler handler get no extension at all rather
 * than a struct full of falses, so "not ours" and "ours, and none of these things" stay
 * distinguishable.
 *
 * Resolution is pure PHP against the handler class; no I/O on entity load.
 */
final readonly class PaymentMethodTypeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PaymentMethodContractResolver $contractResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'payment_method.loaded' => 'onPaymentMethodsLoaded',
        ];
    }

    public function onPaymentMethodsLoaded(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $paymentMethod) {
            if (!$paymentMethod instanceof PaymentMethodEntity) {
                continue;
            }

            $contract = $this->contractResolver->resolve($paymentMethod);

            if ($contract === null) {
                continue;
            }

            // addExtension is how a Runtime field surfaces in the API response.
            $paymentMethod->addExtension(PaymentMethodExtension::EXTENSION_NAME, $contract);
        }
    }
}
