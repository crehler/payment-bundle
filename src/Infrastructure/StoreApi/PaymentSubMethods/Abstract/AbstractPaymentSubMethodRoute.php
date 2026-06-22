<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\PaymentSubMethods\Abstract;

use Crehler\PaymentBundle\Infrastructure\StoreApi\PaymentSubMethods\PaymentSubMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractPaymentSubMethodRoute
{
    /**
     * HTTP endpoint - gets paymentValue from Request query parameter.
     */
    abstract public function get(string $paymentId, Request $request, SalesChannelContext $context): PaymentSubMethodRouteResponse;

    /**
     * HTTP endpoint - gets paymentValue from Request query parameter.
     */
    abstract public function getCurrent(Request $request, SalesChannelContext $context): PaymentSubMethodRouteResponse;

    /**
     * Internal method for subscriber/service use - accepts paymentValue directly.
     */
    abstract public function getForPayment(string $paymentId, int $paymentValue, SalesChannelContext $context): PaymentSubMethodRouteResponse;

    abstract public function getDecorated(): self;
}
