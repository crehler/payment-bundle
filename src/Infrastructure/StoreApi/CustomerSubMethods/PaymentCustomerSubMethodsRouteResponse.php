<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSubMethods;

use Crehler\PaymentBundle\Infrastructure\Struct\PaymentSubMethod\PaymentCustomerSubMethodStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class PaymentCustomerSubMethodsRouteResponse extends StoreApiResponse
{
    public const API_ALIAS = 'cr_payment_customer_sub_method_response';

    public function __construct(PaymentCustomerSubMethodStruct $object)
    {
        parent::__construct($object);
    }

    public function getApiAlias(): string
    {
        return self::API_ALIAS;
    }

    public function get(): PaymentCustomerSubMethodStruct
    {
        return $this->object;
    }
}
