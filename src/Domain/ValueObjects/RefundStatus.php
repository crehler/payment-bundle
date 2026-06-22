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
 * Outcome a provider reports for a refund request. The bundle translates it into
 * the matching Shopware refund state-machine action (see AbstractPaymentMethodHandler):
 * COMPLETED -> complete(), IN_PROGRESS -> process(), FAILED -> throw (processor fails it).
 */
enum RefundStatus
{
    case COMPLETED;
    case IN_PROGRESS;
    case FAILED;
}
