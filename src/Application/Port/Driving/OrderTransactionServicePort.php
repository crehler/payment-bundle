<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Port\Driving;

use Crehler\PaymentBundle\Domain\Entity\OrderTransaction\OrderTransaction;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface OrderTransactionServicePort
{
    public function getOrderTransaction(string $orderTransactionId, ?Context $context = null): OrderTransaction;
}
