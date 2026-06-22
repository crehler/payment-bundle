<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\CheckPaymentStatus;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractCheckPaymentStatusRoute
{
    abstract public function getDecorated(): self;

    abstract public function check(
        CheckPaymentStatusRequest $request,
        SalesChannelContext $context,
    ): CheckPaymentStatusResponse;
}
