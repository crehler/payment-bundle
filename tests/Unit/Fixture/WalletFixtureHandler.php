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
 * A wallet handler named OFF the old convention on purpose.
 *
 * The classification this replaced matched the class-name suffix against 'ewallethandler',
 * so a class called WalletFixtureHandler resolved to nothing and its channels vanished.
 * Declaring the family makes the name irrelevant, which is the point of the contract.
 *
 * Never instantiated — the resolver reads the statics off the class name.
 */
final class WalletFixtureHandler extends AbstractPaymentMethodHandler
{
    public static function paymentType(): PaymentType
    {
        return PaymentType::WALLET;
    }

    public static function usesGatewayChannels(): bool
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
