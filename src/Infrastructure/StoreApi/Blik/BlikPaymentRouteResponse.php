<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\Blik;

use Crehler\PaymentBundle\Infrastructure\Struct\BlikPaymentStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

final class BlikPaymentRouteResponse extends StoreApiResponse
{
    public function __construct(BlikPaymentStruct $object)
    {
        parent::__construct($object);
    }

    public function getBlikPayment(): BlikPaymentStruct
    {
        return $this->object;
    }

    public function getApiAlias(): string
    {
        return 'cr_payment_blik_response';
    }
}
