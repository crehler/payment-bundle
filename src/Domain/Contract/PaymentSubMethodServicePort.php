<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Contract;

use Crehler\PaymentBundle\Domain\Entity\PaymentMethod;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface PaymentSubMethodServicePort
{
    /**
     * Returns PaymentMethod with submethods for that payment method.
     */
    public function getPayment(string $paymentId, int $paymentValue, SalesChannelContext $context): PaymentMethod;
}
