<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Carries the configured payment-confirmation waiting window to the checkout finish
 * page, so the shared check-payment component does not have to know which provider
 * plugin owns the config domain.
 */
final class CheckPaymentWaitingStruct extends Struct
{
    /**
     * @var string
     */
    public const API_ALIAS = 'cr_payment_waiting';

    public function __construct(
        public readonly int $waitingTimeMs,
    ) {
    }

    public function getApiAlias(): string
    {
        return self::API_ALIAS;
    }
}
