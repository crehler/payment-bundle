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

    /**
     * Gateway refused the payment. This — not CANCELLED — is what a provider maps a
     * rejection to, so a rejection reported by notification lands in the same terminal
     * state as one answered synchronously by the authorization call. Splitting a single
     * business case across two states by timing alone made the order list, filters and
     * reporting disagree with themselves (WT-910).
     */
    case FAILED;

    /**
     * Payment abandoned rather than refused — reserve it for genuine cancellations.
     */
    case CANCELLED;
    case REFUNDED;
    case NONE;
}
