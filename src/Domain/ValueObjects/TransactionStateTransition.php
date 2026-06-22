<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\ValueObjects;

/**
 * Canonical outcome a provider maps a gateway notification to. The bundle
 * translates it into the matching Shopware order-transaction state transition
 * (see TransactionStateApplier).
 */
enum TransactionStateTransition
{
    case PAID;
    case CANCELLED;
    case REFUNDED;
    case NONE;
}
