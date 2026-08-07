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
use Crehler\PaymentBundle\Infrastructure\Resolver\PaymentMethodTypeResolver;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Populates the crPaymentType runtime field on PaymentMethodEntity.
 *
 * This subscriber enriches payment method entities with boolean flags
 * indicating the payment type (BLIK, card, bank, etc.), making it easier
 * for frontend applications to identify payment methods without relying on names.
 *
 * The classification itself lives in PaymentMethodTypeResolver — shared with
 * CheckoutConfirmPageLoadedSubscriber and the BLIK Store API route.
 */
final readonly class PaymentMethodTypeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PaymentMethodTypeResolver $paymentMethodTypeResolver,
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

            // Add to extensions array - this is how RuntimeField data is accessed in API
            $paymentMethod->addExtension(
                PaymentMethodExtension::EXTENSION_NAME,
                $this->paymentMethodTypeResolver->struct($paymentMethod),
            );
        }
    }
}
