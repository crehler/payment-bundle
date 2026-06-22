<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Adapter;

use Crehler\PaymentBundle\Infrastructure\Port\SavedCardTokenProvider;
use Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSavedCard\SavedCardTokenProviderInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class SavedCardTokenAdapter implements SavedCardTokenProvider
{
    public function __construct(
        #[AutowireIterator(SavedCardTokenProviderInterface::class)]
        private iterable $savedCardTokenProviders,
    ) {
    }

    public function getCustomerCardTokens(PaymentMethodEntity $paymentMethod, SalesChannelContext $salesChannelContext): array
    {
        $savedCardTokensArr = [];
        foreach ($this->savedCardTokenProviders as $provider) {
            if ($provider->operated(paymentMethod: $paymentMethod)) {
                return $provider->getCustomerCardTokens(
                    paymentMethod: $paymentMethod,
                    salesChannelContext: $salesChannelContext
                );
            }
        }

        return $savedCardTokensArr;
    }

    public function supportsPaymentMethod(PaymentMethodEntity $paymentMethod): bool
    {
        foreach ($this->savedCardTokenProviders as $provider) {
            if ($provider->operated(paymentMethod: $paymentMethod)) {
                return true;
            }
        }

        return false;
    }
}
