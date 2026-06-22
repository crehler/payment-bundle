<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Port\Driven;

use Crehler\PaymentBundle\Application\DTO\Refund\RefundReason;

/**
 * Optional driven port a provider implements when its gateway expects a refund to carry
 * a predefined reason (e.g. PayNow's RMA / REFUND_BEFORE_14 / …) rather than free text.
 *
 * When a provider implements this, the admin refund modal shows a reason selector built
 * from getRefundReasons(); the operator's chosen code travels back through RefundCommand
 * to the gateway. The free-text note field is unaffected — it is stored on the Shopware
 * refund entity regardless. Providers whose gateway has no predefined reasons simply don't
 * implement this port (no selector is shown).
 */
interface RefundReasonProviderInterface
{
    /**
     * Whether this port serves reasons for the given payment handler identifier
     * (the fully-qualified handler class Shopware stores on payment_method).
     */
    public function supports(string $handlerIdentifier): bool;

    /**
     * The predefined reasons offered for selection, in display order.
     *
     * @return RefundReason[]
     */
    public function getRefundReasons(): array;

    /**
     * Whether choosing a reason is mandatory before a refund can be submitted.
     */
    public function isReasonRequired(): bool;
}
