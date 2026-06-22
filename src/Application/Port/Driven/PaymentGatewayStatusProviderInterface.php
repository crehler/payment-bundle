<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Port\Driven;

use Crehler\PaymentBundle\Domain\ValueObjects\PaymentStatus;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

interface PaymentGatewayStatusProviderInterface
{
    /**
     * Check if this provider supports the given payment method.
     */
    public function supports(OrderTransactionEntity $orderTransaction): bool;

    /**
     * Check the payment status from the gateway API and map it to a canonical PaymentStatus.
     *
     * Returns null when the status cannot be determined (e.g. missing gateway payment ID,
     * API error, or a gateway-specific status that should defer to the Shopware state
     * machine such as chargeback/refund). The caller falls back to the OT state machine.
     */
    public function getPaymentStatus(OrderTransactionEntity $orderTransaction): ?PaymentStatus;
}
