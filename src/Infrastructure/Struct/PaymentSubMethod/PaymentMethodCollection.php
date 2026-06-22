<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Struct\PaymentSubMethod;

use Shopware\Core\Framework\Struct\Collection;

class PaymentMethodCollection extends Collection
{
    public function add($element): void
    {
        if (!$element instanceof PaymentSubMethodStruct) {
            return;
        }

        parent::add($element);
    }

    public function getApiAlias(): string
    {
        return 'cr_payment_sub_method_collection';
    }
}
