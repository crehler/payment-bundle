<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\Blik;

use Crehler\PaymentBundle\Struct\BlikPaymentStruct;

interface BlikPaymenHandlerInterface
{
    public function handle(BlikPaymentStruct $blikPaymentStruct): BlikPaymentRouteResponse;
}
