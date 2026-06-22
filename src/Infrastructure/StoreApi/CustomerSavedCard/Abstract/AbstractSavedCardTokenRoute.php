<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSavedCard\Abstract;

use Crehler\PaymentBundle\Infrastructure\StoreApi\CustomerSavedCard\SavedCardTokenResponse;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractSavedCardTokenRoute
{
    abstract public function getCustomerCardTokens(PaymentMethodEntity $paymentMethod, SalesChannelContext $context): SavedCardTokenResponse;

    abstract public function getDecorated(): self;
}
