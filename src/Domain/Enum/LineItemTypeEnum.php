<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Enum;

enum LineItemTypeEnum: string
{
    case CREDIT = 'credit';
    case PRODUCT = 'product';
    case CUSTOM = 'custom';
    case PROMOTION = 'promotion';
    case DISCOUNT = 'discount';
    case CONTAINER = 'container';
}
