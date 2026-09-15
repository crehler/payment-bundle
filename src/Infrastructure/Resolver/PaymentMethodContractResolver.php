<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Resolver;

use Crehler\PaymentBundle\Infrastructure\Handler\AbstractPaymentMethodHandler;
use Crehler\PaymentBundle\Infrastructure\Struct\PaymentMethodContractStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

use function class_exists;
use function is_a;

/**
 * Single source of truth for "what did this payment method's handler declare".
 *
 * Reads the contract statics off the class named in payment_method.handler_identifier.
 * That column is already the canonical link between a stored method and the code serving
 * it, so nothing needs persisting, nothing goes stale on plugin update, and no naming
 * convention has to be obeyed.
 *
 * This replaces classification by class-name suffix. The old resolver matched
 * str_ends_with(strtolower($handlerIdentifier), 'bankhandler'|'ewallethandler'|…) against
 * a hardcoded enum and returned null for anything else — so a handler named WalletHandler
 * instead of EWalletHandler, or an entirely new family like instalments, classified as
 * nothing and its sub-methods silently vanished from checkout.
 *
 * Pure: no I/O, no container lookups, no database. Safe to call per payment method on
 * every entity load.
 */
final readonly class PaymentMethodContractResolver
{
    /**
     * Returns null when the method is not served by a Crehler payment handler.
     *
     * Three ways that happens, all legitimate: the method has no handler at all, the row
     * outlived the plugin that installed it (handler_identifier points at a class that no
     * longer exists — the bundle has already been bitten by instantiating one of those
     * without a class_exists guard), or it belongs to a third-party payment plugin whose
     * handler simply is not ours.
     */
    public function resolve(PaymentMethodEntity $paymentMethod): ?PaymentMethodContractStruct
    {
        // handler_identifier is a non-nullable typed property, so getHandlerIdentifier()
        // throws on an entity that was never hydrated with it — which is what a partial
        // DAL read (Criteria::addFields) produces. isset() routes through Entity::__isset
        // and answers false for an uninitialized typed property, in constant time.
        if (!isset($paymentMethod->handlerIdentifier)) {
            return null;
        }

        $handlerIdentifier = $paymentMethod->getHandlerIdentifier();

        if (!class_exists($handlerIdentifier)
            || !is_a($handlerIdentifier, AbstractPaymentMethodHandler::class, true)
        ) {
            return null;
        }

        return new PaymentMethodContractStruct(
            type: $handlerIdentifier::paymentType(),
            usesGatewayChannels: $handlerIdentifier::usesGatewayChannels(),
            requiresGatewayChannel: $handlerIdentifier::requiresGatewayChannel(),
        );
    }
}
