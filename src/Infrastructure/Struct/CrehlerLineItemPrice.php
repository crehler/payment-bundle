<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Struct;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;

class CrehlerLineItemPrice extends CalculatedPrice
{
    public const API_ALIAS = 'cr_payment_line_item_price';

    public function getApiAlias(): string
    {
        return self::API_ALIAS;
    }

    public static function getExtensionName(): string
    {
        return 'crehler_line_item_price';
    }
}
