<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Port;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface SavedCardTokenProvider
{
    public function getCustomerCardTokens(PaymentMethodEntity $paymentMethod, SalesChannelContext $salesChannelContext): array;

    public function supportsPaymentMethod(PaymentMethodEntity $paymentMethod): bool;
}
