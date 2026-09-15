<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Struct;

use Crehler\PaymentBundle\Domain\Enum\PaymentType;
use Shopware\Core\Framework\Struct\Struct;

/**
 * What a payment method's handler declares about itself, attached to the entity as the
 * crPaymentContract runtime field and readable in the storefront and the Store API.
 *
 * Every field comes from a static method on the handler, so nothing here is inferred
 * from a class name and nothing here requires I/O. The first two are abstract, so a
 * provider that forgets one cannot build the container.
 *
 * $usesGatewayChannels means "this method's channels are fetched from the gateway",
 * NOT "this method has channels". The count is a runtime fact — it changes with the
 * cart amount, the currency, the merchant's contract and channel outages — so it is
 * never declared. Two wallet handlers from different providers both declare true; one
 * may return three channels and the other one, and the template decides what to render
 * from the count it actually received.
 *
 * $requiresGatewayChannel is the separate question of whether the gateway accepts the
 * payment with no channel picked in the shop. Tpay and PayU do (they show their own bank
 * list), ING does not — so this cannot be derived from $usesGatewayChannels, and the
 * storefront needs it to decide whether to block the order button.
 */
final class PaymentMethodContractStruct extends Struct
{
    public const API_ALIAS = 'cr_payment_contract';

    public function __construct(
        public readonly PaymentType $type,
        public readonly bool $usesGatewayChannels,
        public readonly bool $requiresGatewayChannel = false,
    ) {
    }

    public function getApiAlias(): string
    {
        return self::API_ALIAS;
    }
}
