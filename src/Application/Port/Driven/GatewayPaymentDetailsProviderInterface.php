<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Port\Driven;

use Crehler\PaymentBundle\Application\DTO\GatewayDetails\GatewayPaymentDetails;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Implemented by each payment provider plugin to expose read-only gateway transaction
 * details for the admin order "Szczegóły" tab. Implementations are auto-tagged and
 * collected by GatewayPaymentDetailsController.
 */
#[AutoconfigureTag]
interface GatewayPaymentDetailsProviderInterface
{
    /**
     * Whether this provider owns the given order transaction (matched by payment
     * handler / technical name).
     */
    public function supports(OrderTransactionEntity $orderTransaction): bool;

    /**
     * Fetch the gateway transaction details for the order transaction, or null when
     * unavailable (no gateway id yet, API error, …). The caller may enrich missing
     * amount/currency from the Shopware order transaction.
     */
    public function getDetails(OrderTransactionEntity $orderTransaction): ?GatewayPaymentDetails;
}
