<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Port\Driving;

use Crehler\PaymentBundle\Domain\Entity\Order\Order;
use Shopware\Core\Framework\Context;

interface OrderServicePort
{
    public function getOrder(string $orderTransactionId, ?Context $context = null): Order;
}
