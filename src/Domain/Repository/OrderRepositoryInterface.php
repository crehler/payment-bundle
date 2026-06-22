<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Repository;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface OrderRepositoryInterface
{
    public function getOrderWithTransactions(string $orderId, ?Context $context = null): ?OrderEntity;

    /**
     * Merge the supplied customFields into the order. Shopware DAL performs
     * the JSON merge natively, so existing keys remain unless overwritten.
     *
     * @param array<string, mixed> $customFields
     */
    public function updateCustomFields(string $orderId, array $customFields, ?Context $context = null): void;
}
