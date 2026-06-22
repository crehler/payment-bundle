<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Contract;

use Crehler\PaymentBundle\Domain\ValueObjects\PaymentSubMethod;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface PaymentSubMethodPort
{
    /**
     * Find all payment submethods for a given payment method.
     *
     * @return array<PaymentSubMethod>
     */
    public function getPaymentSubMethods(PaymentMethodEntity $paymentMethodEntity, int $paymentValue, SalesChannelContext $context): array;

    /**
     * Check if repository can provide submethods for a payment method.
     */
    public function supportsPaymentMethod(PaymentMethodEntity $paymentMethodEntity): bool;
}
