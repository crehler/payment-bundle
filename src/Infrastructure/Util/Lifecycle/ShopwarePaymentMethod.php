<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Util\Lifecycle;

/**
 * Declaration of one payment method, picked up at install time by PaymentMethodClassLocator.
 *
 * A sub-methods-enabled flag used to sit here. It was never persisted — PaymentMethodDataMapper
 * builds the DAL payload without it, exactly as it does with $position — and the installer
 * that read it could never run: the locator it went through filters on
 * is_a($class, ShopwarePaymentMethod::class), which the sub-method creator did not satisfy.
 * Whether a method draws its channels from the gateway is now declared by its handler as
 * usesGatewayChannels(), where it is enforced by the container instead of evaporating.
 */
abstract class ShopwarePaymentMethod
{
    public function __construct(
        public readonly string $handlerIdentifier,
        public readonly int $position,
        public readonly string $technicalName,
        public readonly array $translations = [],
        public readonly bool $afterOrderEnabled = false,
        public readonly ?string $iconName = null,
    ) {
    }
}
