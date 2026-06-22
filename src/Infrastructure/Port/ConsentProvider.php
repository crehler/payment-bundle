<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Port;

use Crehler\PaymentBundle\Infrastructure\Struct\ConsentStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for payment providers to supply consent/clauses information.
 * Providers implement this to add required legal consent for their payment methods.
 */
#[AutoconfigureTag]
interface ConsentProvider
{
    /**
     * Check if this provider supplies consent for the given payment method.
     */
    public function supportsPaymentMethod(PaymentMethodEntity $paymentMethodEntity): bool;

    /**
     * Get consent/clauses for the payment method.
     * Returns null if no consent is required.
     */
    public function getConsent(PaymentMethodEntity $paymentMethodEntity, SalesChannelContext $context): ?ConsentStruct;
}
