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
use Crehler\PaymentBundle\Infrastructure\Struct\CrehlerLineItemPrice;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function array_filter;
use function array_key_exists;
use function array_shift;
use function count;

final readonly class CartConvertedSubscriber implements EventSubscriberInterface
{
    public function __construct(private CartChanger $cartChanger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [CartConvertedEvent::class => 'onCartConvert'];
    }

    public function onCartConvert(CartConvertedEvent $event): void
    {
        $cart = $event->getCart();
        $cart = $this->cartChanger->change($cart);
        $convertedCart = $event->getConvertedCart();

        foreach ($convertedCart['lineItems'] as &$lineItem) {
            $identifier = $lineItem['identifier'];
            $flatCart = $cart->getLineItems()->getFlat();
            $extension = array_filter($flatCart, fn ($item) => $item->getId() === $identifier);
            if (count($extension) === 0) {
                continue;
            }
            $extension = array_shift($extension)->getExtension(CrehlerLineItemPrice::getExtensionName());
            if ($extension === null) {
                continue;
            }
            if (!array_key_exists('customFields', $lineItem)) {
                $lineItem['customFields'] = [];
            }
            $lineItem['customFields'][CrehlerLineItemPrice::getExtensionName()] = $extension->jsonSerialize();
        }

        $event->setConvertedCart($convertedCart);
    }
}
