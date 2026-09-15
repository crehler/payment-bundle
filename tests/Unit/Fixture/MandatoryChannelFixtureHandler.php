<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Tests\Unit\Fixture;

use Crehler\PaymentBundle\Domain\Entity\OrderTransaction\OrderTransaction;
use Crehler\PaymentBundle\Domain\Enum\PaymentType;
use Crehler\PaymentBundle\Infrastructure\Handler\{AbstractPaymentMethodHandler, PaymentResult};
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

/**
 * A handler for a gateway that will not pick a channel for the customer, the way ING does
 * not: its adapter throws when no paymentMethodCode was selected.
 *
 * Exists to pin that requiresGatewayChannel() is a separate answer from
 * usesGatewayChannels() — both true here, while the wallet fixture has the first false and
 * the second true, which is what Tpay and PayU rely on.
 *
 * Never instantiated — the resolver reads the statics off the class name.
 */
final class MandatoryChannelFixtureHandler extends AbstractPaymentMethodHandler
{
    public static function paymentType(): PaymentType
    {
        return PaymentType::INSTALMENTS;
    }

    public static function usesGatewayChannels(): bool
    {
        return true;
    }

    public static function requiresGatewayChannel(): bool
    {
        return true;
    }

    protected function getPaymentProviderName(): string
    {
        return 'fixture';
    }

    protected function processPayment(
        Request $request,
        PaymentTransactionStruct $transaction,
        OrderTransaction $orderTransaction,
        ?string $paymentSubMethodId,
        Context $context,
    ): PaymentResult {
        return PaymentResult::failure('fixture');
    }
}
