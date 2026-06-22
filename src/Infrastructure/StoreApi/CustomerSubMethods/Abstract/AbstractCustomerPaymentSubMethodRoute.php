<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSubMethods\Abstract;

use Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSubMethods\PaymentCustomerSubMethodsRouteResponse;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractCustomerPaymentSubMethodRoute
{
    abstract public function get(PaymentMethodEntity $paymentMethodEntity, SalesChannelContext $context): PaymentCustomerSubMethodsRouteResponse;

    abstract public function getDecorated(): self;
}
