<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Subscriber;

use Crehler\PaymentBundle\Application\Service\CartChanger;
use Shopware\Core\Checkout\Cart\Event\CartVerifyPersistEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class CartSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly CartChanger $cartChanger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [CartVerifyPersistEvent::class => ['onCartChange', 999]];
    }

    public function onCartChange(CartVerifyPersistEvent $event): void
    {
        $this->cartChanger->change($event->getCart());
    }
}
