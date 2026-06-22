<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\DTO\GatewayDetails;

/**
 * Normalised badge level for a gateway status, independent of the provider's own
 * status vocabulary. Drives the colour of the status badge in the admin UI.
 */
enum GatewayStatusLevel: string
{
    case PAID = 'paid';
    case PENDING = 'pending';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case UNKNOWN = 'unknown';
}
