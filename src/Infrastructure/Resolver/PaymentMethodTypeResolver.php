<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Resolver;

use Crehler\PaymentBundle\Infrastructure\Enum\PaymentHandlersEnum;
use Crehler\PaymentBundle\Infrastructure\Struct\PaymentMethodTypeStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

use function str_ends_with;
use function str_starts_with;
use function strtolower;

/**
 * Single source of truth for "what kind of Crehler payment method is this".
 *
 * The type is derived from the handler class name: every provider handler ends with
 * BlikHandler/CardHandler/BankHandler/… so one suffix match classifies them all
 * without the bundle knowing any concrete provider.
 *
 * Both page subscribers used to carry their own copy of this classification, which
 * meant two places to keep in sync for every new handler type. Callers that need the
 * plain answer ("is this BLIK?") use isBlik(); callers that decorate the payment
 * method for templates/API use struct().
 */
final readonly class PaymentMethodTypeResolver
{
    public function struct(PaymentMethodEntity $paymentMethod): PaymentMethodTypeStruct
    {
        $handlerIdentifier = $paymentMethod->getHandlerIdentifier();
        $handlerType = $this->handlerType($handlerIdentifier);

        return new PaymentMethodTypeStruct(
            isBlik: $handlerType === PaymentHandlersEnum::BLIK_HANDLER,
            isCard: $handlerType === PaymentHandlersEnum::CARD_HANDLER,
            isBank: $handlerType === PaymentHandlersEnum::BANK_HANDLER,
            isEwallet: $handlerType === PaymentHandlersEnum::EWALLET_HANDLER,
            isDeferred: $handlerType === PaymentHandlersEnum::DEFERED_HANDLER,
            hasSubmethods: $this->hasSubmethods($handlerType),
            isCrehlerPayment: $this->isCrehlerPayment($handlerIdentifier),
        );
    }

    /**
     * Is this a BLIK method provided by a Crehler payment plugin? The BLIK Store API route
     * must not be usable to drive a card or bank payment, nor a third-party handler that
     * merely ends with "BlikHandler" — handlerType() rules out both.
     */
    public function isBlik(PaymentMethodEntity $paymentMethod): bool
    {
        return $this->handlerType($paymentMethod->getHandlerIdentifier()) === PaymentHandlersEnum::BLIK_HANDLER;
    }

    public function isCrehlerPayment(?string $handlerIdentifier): bool
    {
        if ($handlerIdentifier === null) {
            return false;
        }

        return str_starts_with($handlerIdentifier, 'Crehler\\');
    }

    /**
     * The suffix alone does not identify the provider, so the namespace gate stays here —
     * before any flag is built. A third-party Vendor\Foo\CardHandler ends with the same
     * suffix as ours, and the result feeds both the API response and the checkout page,
     * where templates read isCard on its own; without this gate a foreign handler would
     * be rendered as a Crehler card method.
     */
    public function handlerType(?string $handlerIdentifier): ?PaymentHandlersEnum
    {
        if (!$this->isCrehlerPayment($handlerIdentifier)) {
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
