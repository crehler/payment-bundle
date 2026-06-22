<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Adapter;

use Crehler\PaymentBundle\Domain\Contract\PaymentSubMethodPort;
use Crehler\PaymentBundle\Infrastructure\Port\PaymentSubMethodProvider;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class PaymentSubMethodAdapter implements PaymentSubMethodPort
{
    public function __construct(
        #[AutowireIterator(PaymentSubMethodProvider::class)]
        private iterable $subMethodProviders,
    ) {
    }

    public function getPaymentSubMethods(PaymentMethodEntity $paymentMethodEntity, int $paymentValue, SalesChannelContext $context): array
    {
        $paymentSubMethods = [];

        foreach ($this->subMethodProviders as $provider) {
            if (!$provider->supportsPaymentMethod(paymentMethodEntity: $paymentMethodEntity)) {
                continue;
            }

            $subMethods = $provider->getPaymentSubMethods(
                paymentMethodEntity: $paymentMethodEntity,
                paymentValue: $paymentValue,
                context: $context
            );

            foreach ($subMethods as $subMethod) {
                $paymentSubMethods[] = $subMethod;
            }
        }

        return $paymentSubMethods;
    }

    public function supportsPaymentMethod(PaymentMethodEntity $paymentMethodEntity): bool
    {
        foreach ($this->subMethodProviders as $provider) {
            if ($provider->supportsPaymentMethod(paymentMethodEntity: $paymentMethodEntity)) {
                return true;
            }
        }

        return false;
    }
}
