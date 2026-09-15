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
 * A family whose channels never come from the gateway, so usesGatewayChannels() is false.
 *
 * Never instantiated — the resolver reads the statics off the class name.
 */
final class BlikFixtureHandler extends AbstractPaymentMethodHandler
{
    public static function paymentType(): PaymentType
    {
        return PaymentType::BLIK;
    }

    public static function usesGatewayChannels(): bool
    {
        return false;
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
