<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Repository;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface PaymentMethodRepositoryInterface
{
    public function findById(string $paymentMethodId, SalesChannelContext $context): ?PaymentMethodEntity;
}
