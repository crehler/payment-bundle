<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\Blik\Abstract;

use Crehler\PaymentBundle\Infrastructure\StoreApi\Blik\BlikPaymentRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractBlikPaymentRoute
{
    abstract public function pay(Request $request, SalesChannelContext $context): BlikPaymentRouteResponse;

    abstract public function getDecorated(): self;
}
