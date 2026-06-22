<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\CheckPaymentStatus;

use Crehler\PaymentBundle\Infrastructure\Struct\CheckPaymentStatusStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class CheckPaymentStatusResponse extends StoreApiResponse
{
    public function __construct(CheckPaymentStatusStruct $object)
    {
        parent::__construct($object);
    }

    public function getStatus(): CheckPaymentStatusStruct
    {
        return $this->object;
    }

    public function getApiAlias(): string
    {
        return 'cr_payment_check_status_response';
    }
}
