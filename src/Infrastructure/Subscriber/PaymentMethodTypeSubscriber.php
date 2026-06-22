<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Subscriber;

use Crehler\PaymentBundle\Infrastructure\Enum\PaymentHandlersEnum;
use Crehler\PaymentBundle\Infrastructure\Extension\PaymentMethodExtension;
use Crehler\PaymentBundle\Infrastructure\Struct\PaymentMethodTypeStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function str_ends_with;
use function str_starts_with;
use function strtolower;

/**
 * Populates the crPaymentType runtime field on PaymentMethodEntity.
 *
 * This subscriber enriches payment method entities with boolean flags
 * indicating the payment type (BLIK, card, bank, etc.), making it easier
 * for frontend applications to identify payment methods without relying on names.
 */
final class PaymentMethodTypeSubscriber implements EventSubscriberInterface
{
    private const CREHLER_NAMESPACE = 'Crehler\\';

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

            $typeStruct = $this->createTypeStruct($paymentMethod);

            // Add to extensions array - this is how RuntimeField data is accessed in API
            $paymentMethod->addExtension(PaymentMethodExtension::EXTENSION_NAME, $typeStruct);
        }
    }

    private function createTypeStruct(PaymentMethodEntity $paymentMethod): PaymentMethodTypeStruct
    {
        $handlerIdentifier = $paymentMethod->getHandlerIdentifier();
        $handlerType = $this->resolveHandlerType($handlerIdentifier);
        $isCrehlerPayment = $this->isCrehlerPaymentMethod($handlerIdentifier);

        return new PaymentMethodTypeStruct(
            isBlik: $handlerType === PaymentHandlersEnum::BLIK_HANDLER,
            isCard: $handlerType === PaymentHandlersEnum::CARD_HANDLER,
            isBank: $handlerType === PaymentHandlersEnum::BANK_HANDLER,
            isEwallet: $handlerType === PaymentHandlersEnum::EWALLET_HANDLER,
            isDeferred: $handlerType === PaymentHandlersEnum::DEFERED_HANDLER,
            hasSubmethods: $this->hasSubmethods($handlerType),
            isCrehlerPayment: $isCrehlerPayment,
        );
    }

    private function resolveHandlerType(?string $handlerIdentifier): ?PaymentHandlersEnum
    {
        if ($handlerIdentifier === null) {
            return null;
        }

        $lowerHandler = strtolower($handlerIdentifier);

        foreach (PaymentHandlersEnum::cases() as $handlerEnum) {
            if (str_ends_with($lowerHandler, $handlerEnum->value)) {
                return $handlerEnum;
            }
        }

        return null;
    }

    private function isCrehlerPaymentMethod(?string $handlerIdentifier): bool
    {
        if ($handlerIdentifier === null) {
            return false;
        }

        return str_starts_with($handlerIdentifier, self::CREHLER_NAMESPACE);
    }

    private function hasSubmethods(?PaymentHandlersEnum $handlerType): bool
    {
        if ($handlerType === null) {
            return false;
        }

        return match ($handlerType) {
            PaymentHandlersEnum::BANK_HANDLER,
            PaymentHandlersEnum::EWALLET_HANDLER,
            PaymentHandlersEnum::DEFERED_HANDLER => true,
            default => false,
        };
    }
}
