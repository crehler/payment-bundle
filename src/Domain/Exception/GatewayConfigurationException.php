<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Domain\Exception;

use function sprintf;

/**
 * Thrown when a payment gateway is missing required configuration
 * (e.g. API credentials) for the given sales channel.
 */
final class GatewayConfigurationException extends DomainException
{
    public static function missingValue(string $configKey): self
    {
        return new self(sprintf('Missing required gateway configuration value "%s"', $configKey));
    }
}
